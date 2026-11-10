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

/** Web-context WebAuthn assertion endpoint for Login extra users. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function m2afWJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $configCore = dirname(__FILE__, 4) . '/config.core.php';
    if (!is_readable($configCore)) {
        throw new RuntimeException('Не найден config.core.php.');
    }
    require_once $configCore;
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';
    $modx = new \MODX\Revolution\modX();
    if (!$modx->initialize('web')) {
        throw new RuntimeException('Не удалось инициализировать веб-контекст MODX.');
    }
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
    \ModTwoAuth\ErrorHandler::register($modx, 'Frontend WebAuthn login');
    $T = static function (string $key) use ($modx): string {
        return \ModTwoAuth\Localization::get($modx, $key);
    };
    $modx->addPackage('ModTwoAuth\\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');

    $userId = (int) ($_SESSION['modtwoauth_web_pending_user_id'] ?? 0);
    $pendingCreated = (int) ($_SESSION['modtwoauth_web_pending_timestamp'] ?? 0);
    $lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));
    if ($userId <= 0
        || (string) ($_SESSION['modtwoauth_web_pending_provider'] ?? '') !== 'webauthn'
        || (int) ($_SESSION['modtwoauth_web_webauthn_login_user_id'] ?? 0) !== $userId
        || $pendingCreated <= 0 || (time() - $pendingCreated) > $lifetime
    ) {
        m2afWJson(['ok' => false, 'message' => $T('modtwoauth_webauthn_session_expired')], 400);
    }

    $setting = $modx->getObject('ModTwoAuth\\Model\\TwoAuthUserSetting', ['user_id' => $userId]);
    $user = $modx->getObject('modUser', $userId);
    if (!$setting || !(int) $setting->get('is_enabled') || !(int) $setting->get('is_verified')
        || $setting->get('provider') !== 'webauthn' || !$user || !(int) $user->get('active')
    ) {
        m2afWJson(['ok' => false, 'message' => $T('modtwoauth_webauthn_not_enabled_account')], 403);
    }
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
    if (\ModTwoAuth\AttemptLimiter::lockedUntil($setting) > 0) {
        m2afWJson([
            'ok' => false,
            'locked' => true,
            'message' => \ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting),
        ], 429);
    }

    $provider = new \ModTwoAuth\Providers\WebAuthnProvider($modx);
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET') {
        m2afWJson(['ok' => true, 'publicKey' => $provider->loginOptions($userId)]);
    }
    if ($method !== 'POST') {
        m2afWJson(['ok' => false, 'message' => $T('modtwoauth_error_method_not_allowed')], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    [$ok, $result] = $provider->verifyAssertion($payload, $userId);
    if (!$ok) {
        $attempt = \ModTwoAuth\AttemptLimiter::fail($modx, $setting);
        m2afWJson([
            'ok' => false,
            'message' => $attempt['locked']
                ? \ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting)
                : (string) $result,
            'attempts_left' => max(0, $attempt['max'] - $attempt['attempts']),
            'locked' => $attempt['locked'],
        ], $attempt['locked'] ? 429 : 401);
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

    foreach ([
        'modtwoauth_web_webauthn_login_user_id', 'modtwoauth_web_webauthn_login_challenge',
        'modtwoauth_web_webauthn_login_created', 'modtwoauth_web_pending_user_id',
        'modtwoauth_web_pending_username', 'modtwoauth_web_pending_provider',
        'modtwoauth_web_pending_timestamp', 'modtwoauth_web_pending_context',
        'modtwoauth_web_pending_redirect', 'modtwoauth_web_pending_code_hash',
        'modtwoauth_web_pending_email_sent_at', 'modtwoauth_web_login_error',
        'modtwoauth_web_tg_login_request_id',
    ] as $key) {
        unset($_SESSION[$key]);
    }
    m2afWJson(['ok' => true, 'redirect' => $redirect]);
} catch (\Throwable $e) {
    if (isset($modx) && $modx instanceof \MODX\Revolution\modX) {
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] WebAuthn frontend login error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
    m2afWJson(['ok' => false, 'message' => isset($T) ? $T('modtwoauth_webauthn_frontend_error') : ''], 500);
}
