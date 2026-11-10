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
 * Standalone WebAuthn/FIDO2 manager-login endpoint.
 *
 * This endpoint deliberately uses the same MODX Manager session that was
 * created by OnManagerAuthentication -> verify.php. It must not create a
 * separate PHP session and must not initialize the web context, otherwise
 * the pending WebAuthn challenge can be lost.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function m2aJson(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $configCore = dirname(__FILE__, 4) . '/config.core.php';
    if (!is_file($configCore)) {
        $configCore = dirname(__FILE__, 5) . '/config.core.php';
    }
    if (!is_file($configCore)) {
        throw new RuntimeException('Не найден config.core.php.');
    }

    require_once $configCore;
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';

    $modx = new \MODX\Revolution\modX();
    if (!$modx->initialize('mgr')) {
        throw new RuntimeException('Не удалось инициализировать MODX Manager.');
    }
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
    \ModTwoAuth\ErrorHandler::register($modx, 'Manager WebAuthn login');
    $T = static function (string $key) use ($modx): string {
        return \ModTwoAuth\Localization::get($modx, $key);
    };

    $bootstrap = MODX_CORE_PATH . 'components/modtwoauth/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Не найден bootstrap modTwoAuth.');
    }
    // MODX 3 сам подключает bootstrap неймспейса в _initNamespaces();
    // переменная $namespace нужна на случай, если этого не произошло.
    $namespace = ['path' => MODX_CORE_PATH . 'components/modtwoauth/'];
    require_once $bootstrap;

    $userId = (int) ($_SESSION['modtwoauth_pending_user_id'] ?? 0);
    $pendingCreated = (int) ($_SESSION['modtwoauth_pending_timestamp'] ?? 0);
    $lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));
    if ($userId <= 0
        || (string) ($_SESSION['modtwoauth_pending_provider'] ?? '') !== 'webauthn'
        || (int) ($_SESSION['modtwoauth_webauthn_login_user_id'] ?? 0) !== $userId
        || $pendingCreated <= 0 || (time() - $pendingCreated) > $lifetime
    ) {
        m2aJson([
            'ok' => false,
            'message' => $T('modtwoauth_webauthn_session_expired'),
        ], 400);
    }

    $setting = $modx->getObject(\ModTwoAuth\Model\TwoAuthUserSetting::class, ['user_id' => $userId]);
    $user = $modx->getObject('modUser', $userId);
    if (!$setting
        || !(int) $setting->get('is_enabled')
        || !(int) $setting->get('is_verified')
        || (string) $setting->get('provider') !== 'webauthn'
        || !$user || !(int) $user->get('active')
    ) {
        m2aJson([
            'ok' => false,
            'message' => $T('modtwoauth_webauthn_not_enabled_user'),
        ], 403);
    }

    require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
    if (\ModTwoAuth\AttemptLimiter::lockedUntil($setting) > 0) {
        m2aJson([
            'ok' => false,
            'locked' => true,
            'message' => \ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting),
        ], 429);
    }

    $provider = new \ModTwoAuth\Providers\WebAuthnProvider($modx);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $options = $provider->loginOptions($userId);
        m2aJson(['ok' => true, 'publicKey' => $options]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        m2aJson(['ok' => false, 'message' => $T('modtwoauth_error_method_not_allowed')], 405);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    [$ok, $result] = $provider->verifyAssertion($payload, $userId);
    if (!$ok) {
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
        $attempt = \ModTwoAuth\AttemptLimiter::fail($modx, $setting);
        $attempts = $attempt['attempts']; $max = $attempt['max'];

        if ($attempt['locked']) {
            m2aJson([
                'ok' => false,
                'locked' => true,
                'message' => \ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting),
            ], 429);
        }

        $setting->save();
        m2aJson([
            'ok' => false,
            'message' => (string) $result,
            'attempts_left' => max(0, $max - $attempts),
        ], 401);
    }

    \ModTwoAuth\AttemptLimiter::reset($setting);

    // Complete the Manager login for the actual pending user, not $modx->user
    // (which may still be the anonymous Manager user at this point).
    if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    $user->addSessionContext('mgr');
    $_SESSION['modtwoauth_verified_user_id'] = $userId;

    // track() создаёт/обновляет запись сессии (включая php_session_id), ставит
    // флаг modtwoauth_session_tracked и чистит «мёртвые» записи пользователя.
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';
    \ModTwoAuth\SessionTracker::track($modx, $userId, 'mgr');

    foreach ([
        'modtwoauth_pending_user_id', 'modtwoauth_pending_username',
        'modtwoauth_pending_provider', 'modtwoauth_pending_timestamp',
        'modtwoauth_pending_code_hash', 'modtwoauth_pending_email_sent_at',
        'modtwoauth_pending_secret', 'modtwoauth_login_error',
        'modtwoauth_tg_login_request_id', 'modtwoauth_webauthn_login_user_id',
        'modtwoauth_webauthn_login_challenge', 'modtwoauth_webauthn_login_created',
    ] as $key) {
        unset($_SESSION[$key]);
    }

    m2aJson(['ok' => true, 'redirect' => MODX_MANAGER_URL]);
} catch (Throwable $e) {
    if (isset($modx) && $modx instanceof \MODX\Revolution\modX) {
        $modx->log(
            \MODX\Revolution\modX::LOG_LEVEL_ERROR,
            '[modTwoAuth] WebAuthn login endpoint error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
        );
    }

    // Подробности — только в журнал MODX; клиенту отдаём обобщённое сообщение,
    // чтобы не раскрывать пути, SQL-ошибки и внутренние данные.
    m2aJson([
        'ok' => false,
        'message' => isset($T) ? $T('modtwoauth_webauthn_internal_error') : '',
    ], 500);
}
