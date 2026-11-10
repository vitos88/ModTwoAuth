<?php
/*
 * This file is part of ModTwoAuth.
 *
 * Copyright (C) 2026 vitos88
 *
 * ModTwoAuth is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ModTwoAuth is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ModTwoAuth; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

namespace ModTwoAuth;

use ModTwoAuth\Model\TwoAuthSession;
use MODX\Revolution\modX;

/** Shared tracking helpers for Manager and website sessions. */
class SessionTracker
{
    public static function token(string $context = 'web', ?string $sessionId = null): string
    {
        $sessionId = $sessionId ?? session_id();
        // Keep the existing Manager token format for backwards compatibility.
        return $context === 'mgr'
            ? hash('sha256', $sessionId)
            : hash('sha256', 'web:' . $sessionId);
    }

    public static function track(modX $modx, int $userId, string $context = 'web'): bool
    {
        if ($userId <= 0) return false;
        $token = self::token($context);
        $record = $modx->getObject(TwoAuthSession::class, [
            'user_id' => $userId,
            'session_token' => $token,
        ]);
        $now = date('Y-m-d H:i:s');
        if (!$record) {
            $record = $modx->newObject(TwoAuthSession::class);
            $record->fromArray([
                'user_id' => $userId,
                'session_token' => $token,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 4000),
                'created_at' => $now,
            ]);
        }
        // Запоминаем реальный PHP-session id: по нему gc() проверяет,
        // существует ли ещё сама сессия в хранилище MODX.
        $record->set('php_session_id', session_id() ?: null);
        $record->set('last_active', $now);
        $saved = (bool) $record->save();
        if ($saved && $context !== 'mgr') {
            $_SESSION['modtwoauth_web_session_tracked'] = true;
            $_SESSION['modtwoauth_web_session_user_id'] = $userId;
            $_SESSION['modtwoauth_web_session_context'] = $context;
        } elseif ($saved) {
            $_SESSION['modtwoauth_session_tracked'] = true;
        }

        // Вход — лучший момент убрать «призраков» этого пользователя: записи,
        // чьи PHP-сессии больше не существуют (после «Завершить все сеансы»
        // в менеджере, сборщика мусора MODX и т.п.).
        if ($saved) {
            try {
                self::gc($modx, $userId, false);
            } catch (\Throwable $e) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[modTwoAuth] Session GC after login failed: ' . $e->getMessage());
            }
        }
        return $saved;
    }

    public static function touch(modX $modx, int $userId, string $context = 'web'): bool
    {
        $record = $modx->getObject(TwoAuthSession::class, [
            'user_id' => $userId,
            'session_token' => self::token($context),
        ]);
        if (!$record) return false;

        // Принудительный перевыпуск 2FA: ограничение по возрасту применяется только
        // к сессиям, завершившим проверку второго фактора (флаг ставится
        // только в конце 2FA-потока), чтобы не менять поведение обычных входов.
        $maxAge = (int) $modx->getOption('modtwoauth_session_max_age', null, 86400);
        $verifiedFlag = $context === 'mgr' ? 'modtwoauth_verified_user_id' : 'modtwoauth_web_verified_user_id';
        $hasVerified2fa = (int) ($_SESSION[$verifiedFlag] ?? 0) === $userId;
        if ($maxAge > 0 && $hasVerified2fa) {
            $createdAt = strtotime((string) ($record->get('created_at') ?? ''));
            if ($createdAt !== false && $createdAt > 0 && (time() - $createdAt) > $maxAge) {
                $record->remove();
                return false;
            }
        }

        // Обновляем last_active не чаще раза в минуту: не пишем в БД на каждый запрос.
        $lastActive = strtotime((string) ($record->get('last_active') ?? ''));
        if ($lastActive !== false && $lastActive > 0 && (time() - $lastActive) < 60) {
            return true;
        }

        // Заодно дозаполняем php_session_id у записей, созданных до его появления.
        $record->set('php_session_id', session_id() ?: null);
        $record->set('last_active', date('Y-m-d H:i:s'));
        return (bool) $record->save();
    }

    public static function remove(modX $modx, int $userId, string $context = 'web'): void
    {
        if ($userId <= 0) return;
        $record = $modx->getObject(TwoAuthSession::class, [
            'user_id' => $userId,
            'session_token' => self::token($context),
        ]);
        if ($record) $record->remove();
    }

    /**
     * Удаляет записи о сессиях, чьи PHP-сессии больше не существуют.
     *
     * «Завершить все сеансы» в менеджере MODX делает TRUNCATE TABLE modx_session
     * и не вызывает ни одного события плагина (ни OnWebLogout, ни OnManagerLogout),
     * поэтому пакет узнаёт о смерти сессий только сверкой с реальным хранилищем.
     * То же касается сессий, удалённых сборщиком мусора MODX, и сессий,
     * умерших без штатного выхода (закрытие браузера и т.п.).
     *
     * @param int|null $userId Ограничить очистку одним пользователем (null — все).
     * @param bool     $scanAllRecords Записи, созданные до появления колонки
     *                                 php_session_id, можно проверить только
     *                                 хешированием всех живых session id.
     *                                 Включайте там, где одна лишняя выборка
     *                                 оправдана (экраны списка сессий, фоновая
     *                                 очистка); вход пользователя обходится без неё.
     * @return int Число удалённых записей.
     */
    public static function gc(modX $modx, ?int $userId = null, bool $scanAllRecords = false): int
    {
        $sessionClass = self::modxSessionClass();
        // Сверка возможна только со встроенным modSessionHandler: при кастомном
        // обработчике таблица {prefix}session не отражает реальность, и опора
        // на неё могла бы удалить записи живых сессий.
        if ($sessionClass === null || !self::usesDatabaseSessions($modx)) {
            return 0;
        }

        $table = $modx->getTableName(TwoAuthSession::class);
        $sessionTable = $modx->getTableName($sessionClass);
        if ($table === '' || $sessionTable === '') {
            return 0;
        }

        // Сессия текущего запроса могла ещё не записаться в БД (modSessionHandler
        // пишет строку при завершении запроса), а сразу после «Завершить все
        // сеансы» — наоборот, уже удалена, хотя пользователь входит прямо сейчас.
        // Запись текущей сессии не трогаем никогда.
        $currentSid = (string) session_id();
        $protectedTokens = [];
        if ($currentSid !== '') {
            $protectedTokens[self::token('mgr', $currentSid)] = true;
            $protectedTokens[self::token('web', $currentSid)] = true;
        }

        $rows = [];
        try {
            if ($userId !== null && $userId > 0) {
                $stmt = $modx->prepare("SELECT `id`, `session_token`, `php_session_id` FROM {$table} WHERE `user_id` = ?");
                if (!$stmt || !$stmt->execute([$userId])) return 0;
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $stmt = $modx->query("SELECT `id`, `session_token`, `php_session_id` FROM {$table}");
                if ($stmt === false) return 0;
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[modTwoAuth] Session GC failed: ' . $e->getMessage());
            return 0;
        }
        if (!$rows) return 0;

        $knownSids = [];
        $needsFullScan = false;
        foreach ($rows as $row) {
            $sid = (string) ($row['php_session_id'] ?? '');
            if ($sid !== '') {
                $knownSids[$sid] = true;
            } else {
                $needsFullScan = true;
            }
        }

        $aliveSids = [];
        $aliveTokens = null;
        try {
            if ($knownSids) {
                foreach (array_chunk(array_keys($knownSids), 500) as $chunk) {
                    $marks = implode(',', array_fill(0, count($chunk), '?'));
                    $stmt = $modx->prepare("SELECT `id` FROM {$sessionTable} WHERE `id` IN ({$marks})");
                    if (!$stmt || !$stmt->execute(array_map('strval', $chunk))) continue;
                    foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $sid) {
                        $aliveSids[(string) $sid] = true;
                    }
                }
            }
            if ($needsFullScan && $scanAllRecords) {
                $aliveTokens = [];
                $stmt = $modx->query("SELECT `id` FROM {$sessionTable}");
                if ($stmt !== false) {
                    foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $sid) {
                        $sid = (string) $sid;
                        $aliveSids[$sid] = true;
                        $aliveTokens[self::token('mgr', $sid)] = true;
                        $aliveTokens[self::token('web', $sid)] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[modTwoAuth] Session GC failed: ' . $e->getMessage());
            return 0;
        }

        $deadIds = [];
        foreach ($rows as $row) {
            $token = (string) $row['session_token'];
            if (isset($protectedTokens[$token])) continue;
            $sid = (string) ($row['php_session_id'] ?? '');
            if ($sid !== '') {
                if (isset($aliveSids[$sid])) continue;
            } elseif (is_array($aliveTokens)) {
                // Запись без php_session_id проверяем по хешу живой сессии.
                if (isset($aliveTokens[$token])) continue;
            } else {
                // Старую запись без полного сканирования проверить нельзя — оставляем.
                continue;
            }
            $deadIds[] = (int) $row['id'];
        }
        if (!$deadIds) return 0;

        $removed = 0;
        try {
            foreach (array_chunk($deadIds, 500) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $modx->prepare("DELETE FROM {$table} WHERE `id` IN ({$marks})");
                if ($stmt && $stmt->execute(array_map('intval', $chunk))) {
                    $removed += (int) $stmt->rowCount();
                }
            }
        } catch (\Throwable $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[modTwoAuth] Session GC failed while removing stale records: ' . $e->getMessage());
            return $removed;
        }
        if ($removed > 0) {
            $modx->log(modX::LOG_LEVEL_INFO, '[modTwoAuth] Session GC removed ' . $removed . ' stale session record(s).');
        }
        return $removed;
    }

    /**
     * Класс modSession с учётом версии MODX (3.x — неймспейс, 2.x — глобальный).
     */
    private static function modxSessionClass(): ?string
    {
        foreach (['MODX\\Revolution\\modSession', 'modSession'] as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
        return null;
    }

    /**
     * Хранит ли MODX сессии в таблице {prefix}session (встроенный modSessionHandler).
     */
    private static function usesDatabaseSessions(modX $modx): bool
    {
        $handler = null;
        if (isset($modx->services) && method_exists($modx->services, 'get')) {
            try {
                $handler = $modx->services->get('session_handler');
            } catch (\Throwable $e) {
                $handler = null;
            }
        }
        if ($handler !== null) {
            foreach (['MODX\\Revolution\\modSessionHandler', 'modSessionHandler'] as $class) {
                if (class_exists($class) && $handler instanceof $class) {
                    return true;
                }
            }
            return false;
        }
        // MODX 2 fallback: решаем по системной настройке session_handler_class.
        $option = strtolower((string) $modx->getOption('session_handler_class', null, 'modSessionHandler'));
        return $option !== '' && str_contains($option, 'modsessionhandler');
    }
}
