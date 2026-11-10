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

namespace ModTwoAuth\Processors;

use MODX\Revolution\Processors\Processor;
use ModTwoAuth\Localization;
use ModTwoAuth\Model\TwoAuthUserSetting;

abstract class BaseProcessor extends Processor
{
    /** Processors that only read state and may be requested with GET. */
    private const READ_ONLY = [
        GetSessionList::class,
        TelegramLinkStatus::class,
    ];

    public function checkPermissions()
    {
        if (!$this->modx->user || !$this->modx->user->hasSessionContext('mgr')) {
            return false;
        }

        /*
         * Изменяющие состояние процессоры обязаны приходить POST-ом и проходить
         * проверку Origin/Referer не только в connector.php, но и на уровне
         * самого процессора. Это защита в глубину при вызове через другой
         * коннектор. Штатный коннектор MODX дополнительно требует HTTP_MODAUTH;
         * проверки здесь не заменяют и не обходят этот токен.
         */
        if (!in_array(static::class, self::READ_ONLY, true) && !$this->isSameOriginPost()) {
            return false;
        }

        $userId = (int) $this->getProperty('user_id');
        $currentUserId = (int) $this->modx->user->get('id');

        $allowed = $userId > 0
            && ($userId === $currentUserId || $this->modx->hasPermission('edit_user'));
        if (!$allowed) {
            return false;
        }

        // Пока 2FA включена, нельзя молча заменить метод через прямой вызов
        // процессора. Сначала метод отключается штатной формой профиля.
        $enrollmentProcessors = [
            GenerateTotp::class,
            VerifyTotp::class,
            SendEmailCode::class,
            VerifyEmail::class,
            CreateTelegramLink::class,
            BeginWebAuthnRegistration::class,
            FinishWebAuthnRegistration::class,
        ];
        if (in_array(static::class, $enrollmentProcessors, true)) {
            $setting = $this->getSetting($userId);
            if ($setting && (int) $setting->get('is_enabled')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Запрос обязан быть POST, а его источник (Origin, а для старых браузеров
     * Referer) — тем же хостом, на который пришёл запрос. Если ни Origin, ни
     * Referer нет, пропускаем: современные браузеры всегда шлют Origin на
     * кросс-сайтовом POST, а cookie SameSite=Lax закрывают остальное.
     */
    protected function isSameOriginPost(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin !== '' && strcasecmp($origin, 'null') !== 0) {
            $originHost = (string) parse_url($origin, PHP_URL_HOST);
        } else {
            $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
            $originHost = $referer !== '' ? (string) parse_url($referer, PHP_URL_HOST) : '';
        }
        if ($originHost === '') {
            return true;
        }

        $allowedHosts = [];
        // Хост, на который пришёл сам запрос: браузер при CSRF не может
        // подменить Host жертвы, поэтому совпадение = тот же источник.
        $requestHost = (string) parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($requestHost !== '') {
            $allowedHosts[] = strtolower($requestHost);
        }
        foreach (['site_url', 'manager_url'] as $option) {
            $host = (string) parse_url((string) $this->modx->getOption($option, null, ''), PHP_URL_HOST);
            if ($host !== '') {
                $allowedHosts[] = strtolower($host);
            }
        }

        return in_array(strtolower($originHost), $allowedHosts, true);
    }

    protected function getSetting(int $userId): ?TwoAuthUserSetting
    {
        $setting = $this->modx->getObject(
            TwoAuthUserSetting::class,
            ['user_id' => $userId]
        );

        return $setting instanceof TwoAuthUserSetting ? $setting : null;
    }

    protected function getOrCreateSetting(int $userId): TwoAuthUserSetting
    {
        $setting = $this->getSetting($userId);
        if (!$setting) {
            $setting = $this->modx->newObject(TwoAuthUserSetting::class);
            $setting->set('user_id', $userId);
        }

        return $setting;
    }

    protected function isCodeExpired(string $timestampKey): bool
    {
        $created = (int) ($_SESSION[$timestampKey] ?? 0);
        $lifetime = max(
            30,
            (int) $this->modx->getOption('modtwoauth_code_lifetime', null, 300)
        );

        return $created <= 0 || (time() - $created) > $lifetime;
    }

    protected function text(string $key, array $placeholders = []): string
    {
        return Localization::get($this->modx, $key, $placeholders);
    }
}
