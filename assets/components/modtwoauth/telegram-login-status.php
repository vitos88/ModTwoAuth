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

/**
 * Public polling endpoint for a pending manager login.
 * It is bound to the current PHP session hash and the pending request.
 */

use ModTwoAuth\Model\TwoAuthSession;
use ModTwoAuth\Model\TwoAuthUserSetting;
use ModTwoAuth\Model\TwoAuthTelegramRequest;

require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';
require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

try {
    $modx = new \MODX\Revolution\modX();
    $modx->initialize('web');
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
    \ModTwoAuth\ErrorHandler::register($modx, 'Telegram login status');
    $T = static function (string $key) use ($modx): string {
        return \ModTwoAuth\Localization::get($modx, $key);
    };

    $bootstrap = MODX_CORE_PATH . 'components/modtwoauth/bootstrap.php';
    if (is_file($bootstrap)) {
        $namespace = ['path' => MODX_CORE_PATH . 'components/modtwoauth/'];
        require_once $bootstrap;
    }

    $sessionHash = hash('sha256', session_id());
    $requestId = (int) ($_SESSION['modtwoauth_tg_login_request_id'] ?? 0);

    if ($requestId <= 0) {
        echo json_encode(['ok' => true, 'status' => 'idle']);
        exit;
    }

    $request = $modx->getObject(TwoAuthTelegramRequest::class, [
        'id' => $requestId,
        'kind' => 'login',
        'session_hash' => $sessionHash,
    ]);

    if (!$request) {
        echo json_encode(['ok' => true, 'status' => 'idle']);
        exit;
    }

    $pendingUserId = (int) ($_SESSION['modtwoauth_pending_user_id'] ?? 0);
    $pendingProvider = (string) ($_SESSION['modtwoauth_pending_provider'] ?? '');
    $pendingCreated = (int) ($_SESSION['modtwoauth_pending_timestamp'] ?? 0);
    $lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));
    if ($pendingUserId !== (int) $request->get('user_id') || $pendingUserId <= 0
        || $pendingProvider !== 'telegram' || $pendingCreated <= 0
        || (time() - $pendingCreated) > $lifetime
    ) {
        if (in_array((string) $request->get('status'), ['pending', 'approved'], true)) {
            $request->set('status', 'expired');
            $request->save();
        }
        echo json_encode(['ok' => true, 'status' => 'expired']);
        exit;
    }

    $expires = strtotime((string) $request->get('expires_at'));
    $status = (string) $request->get('status');

    if (($expires === false || $expires <= time()) && in_array($status, ['pending', 'approved'], true)) {
        $request->set('status', 'expired');
        $request->save();
        $status = 'expired';
    }

    if ($status === 'approved') {
        $userId = (int) $request->get('user_id');
        $user = $modx->getObject('modUser', $userId);

        // Повторная проверка актуальности 2FA: между вводом пароля и подтверждением
        // в Telegram настройка могла быть отключена или сменён провайдер.
        $setting = $modx->getObject(TwoAuthUserSetting::class, ['user_id' => $userId]);
        if (!$user || !(int) $user->get('active')
            || !$setting
            || !(int) $setting->get('is_enabled')
            || !(int) $setting->get('is_verified')
            || (string) $setting->get('provider') !== 'telegram'
        ) {
            $request->set('status', 'used');
            $request->save();
            http_response_code(403);
            echo json_encode(['ok' => false, 'status' => 'error', 'message' => $T('modtwoauth_error_user_or_2fa_unavailable')]);
            exit;
        }
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
        if (\ModTwoAuth\AttemptLimiter::lockedUntil($setting) > 0) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'status' => 'error', 'message' => $T('modtwoauth_error_login_locked')]);
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        $user->addSessionContext('mgr');
        $_SESSION['modtwoauth_verified_user_id'] = $userId;

        $setting->set('login_attempts', 0);
        $setting->set('locked_until', null);
        $setting->save();

        // track() создаёт/обновляет запись сессии (включая php_session_id), ставит
        // флаг modtwoauth_session_tracked и чистит «мёртвые» записи пользователя.
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';
        \ModTwoAuth\SessionTracker::track($modx, $userId, 'mgr');

        $request->set('status', 'used');
        $request->set('confirmed_at', date('Y-m-d H:i:s'));
        $request->save();

        foreach ([
            'modtwoauth_pending_user_id',
            'modtwoauth_pending_provider',
            'modtwoauth_pending_timestamp',
            'modtwoauth_pending_code_hash',
            'modtwoauth_pending_secret',
            'modtwoauth_login_error',
            'modtwoauth_tg_login_request_id',
            'modtwoauth_login_attempts_' . $userId,
            'modtwoauth_webauthn_login_user_id',
            'modtwoauth_webauthn_login_challenge',
            'modtwoauth_webauthn_login_created',
        ] as $key) {
            unset($_SESSION[$key]);
        }

        echo json_encode(['ok' => true, 'status' => 'approved', 'redirect' => MODX_MANAGER_URL]);
        exit;
    }

    echo json_encode(['ok' => true, 'status' => $status]);
} catch (\Throwable $e) {
    if (isset($modx) && $modx instanceof \MODX\Revolution\modX) {
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Telegram login status error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'status' => 'error', 'message' => isset($T) ? $T('modtwoauth_error_telegram_check') : '']);
}
