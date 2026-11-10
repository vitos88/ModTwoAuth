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

/** Telegram approval polling for a pending frontend login. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function m2afTgJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_once dirname(__DIR__, 3) . '/config.core.php';
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';
    $modx = new \MODX\Revolution\modX();
    if (!$modx->initialize('web')) {
        throw new RuntimeException('Не удалось инициализировать веб-контекст MODX.');
    }
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
    \ModTwoAuth\ErrorHandler::register($modx, 'Frontend Telegram status');
    $T = static function (string $key) use ($modx): string {
        return \ModTwoAuth\Localization::get($modx, $key);
    };
    $modx->addPackage('ModTwoAuth\\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');

    $requestId = (int) ($_SESSION['modtwoauth_web_tg_login_request_id'] ?? 0);
    if ($requestId <= 0) {
        m2afTgJson(['ok' => true, 'status' => 'idle']);
    }
    $request = $modx->getObject('ModTwoAuth\\Model\\TwoAuthTelegramRequest', [
        'id' => $requestId,
        'kind' => 'login',
        'session_hash' => hash('sha256', session_id()),
    ]);
    if (!$request) {
        m2afTgJson(['ok' => true, 'status' => 'idle']);
    }

    $pendingUserId = (int) ($_SESSION['modtwoauth_web_pending_user_id'] ?? 0);
    $pendingProvider = (string) ($_SESSION['modtwoauth_web_pending_provider'] ?? '');
    $pendingCreated = (int) ($_SESSION['modtwoauth_web_pending_timestamp'] ?? 0);
    $lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));
    $pendingValid = $pendingUserId > 0
        && $pendingUserId === (int) $request->get('user_id')
        && $pendingProvider === 'telegram'
        && $pendingCreated > 0
        && (time() - $pendingCreated) <= $lifetime;
    if (!$pendingValid) {
        if (in_array((string) $request->get('status'), ['pending', 'approved'], true)) {
            $request->set('status', 'expired');
            $request->save();
        }
        m2afTgJson(['ok' => true, 'status' => 'expired']);
    }

    $status = (string) $request->get('status');
    $expires = strtotime((string) $request->get('expires_at'));
    if (($expires === false || $expires <= time()) && in_array($status, ['pending', 'approved'], true)) {
        $request->set('status', 'expired');
        $request->save();
        $status = 'expired';
    }

    if ($status === 'approved') {
        $userId = (int) $request->get('user_id');
        $user = $modx->getObject('modUser', $userId);
        $setting = $modx->getObject('ModTwoAuth\\Model\\TwoAuthUserSetting', ['user_id' => $userId]);
        if (!$user || !(int) $user->get('active') || !$setting
            || !(int) $setting->get('is_enabled')
            || !(int) $setting->get('is_verified')
            || $setting->get('provider') !== 'telegram'
        ) {
            m2afTgJson(['ok' => false, 'status' => 'error', 'message' => $T('modtwoauth_error_user_or_2fa_unavailable')], 403);
        }

        require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
        if (\ModTwoAuth\AttemptLimiter::lockedUntil($setting) > 0) {
            m2afTgJson(['ok' => false, 'status' => 'error', 'message' => $T('modtwoauth_error_login_locked')], 429);
        }

        $context = (string) ($_SESSION['modtwoauth_web_pending_context'] ?? 'web');
        if ($context === '' || $context === 'mgr' || !preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $context)) {
            $context = 'web';
        }
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/RedirectGuard.php';
        $redirect = \ModTwoAuth\RedirectGuard::safe(
            (string) ($_SESSION['modtwoauth_web_pending_redirect'] ?? ''),
            (string) $modx->getOption('site_url', null, '/')
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $user->addSessionContext($context);
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';
        \ModTwoAuth\SessionTracker::track($modx, $userId, 'web');
        $_SESSION['modtwoauth_web_session_context'] = $context;
        $_SESSION['modtwoauth_web_verified_user_id'] = $userId;
        \ModTwoAuth\AttemptLimiter::reset($setting);
        $request->set('status', 'used');
        $request->set('confirmed_at', date('Y-m-d H:i:s'));
        $request->save();

        foreach ([
            'modtwoauth_web_pending_user_id', 'modtwoauth_web_pending_username',
            'modtwoauth_web_pending_provider', 'modtwoauth_web_pending_timestamp',
            'modtwoauth_web_pending_context', 'modtwoauth_web_pending_redirect',
            'modtwoauth_web_pending_code_hash', 'modtwoauth_web_pending_email_sent_at',
            'modtwoauth_web_login_error', 'modtwoauth_web_tg_login_request_id',
            'modtwoauth_web_webauthn_login_user_id', 'modtwoauth_web_webauthn_login_challenge',
            'modtwoauth_web_webauthn_login_created',
        ] as $key) {
            unset($_SESSION[$key]);
        }
        m2afTgJson(['ok' => true, 'status' => 'approved', 'redirect' => $redirect]);
    }
    if ($status === 'denied') {
        $request->set('status', 'used');
        $request->save();
    }
    m2afTgJson(['ok' => true, 'status' => $status]);
} catch (\Throwable $e) {
    if (isset($modx) && $modx instanceof \MODX\Revolution\modX) {
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Web Telegram status error: ' . $e->getMessage());
    }
    m2afTgJson(['ok' => false, 'status' => 'error', 'message' => isset($T) ? $T('modtwoauth_error_telegram_check') : ''], 500);
}
