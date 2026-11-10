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
 * Dedicated WebAuthn registration endpoint for the Manager.
 *
 * This intentionally does not use the MODX connector/request lifecycle.
 * It bootstraps the Manager context directly and talks to WebAuthnProvider.
 * That keeps Passkey registration independent from connector.php/WAF quirks.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function m2aWebAuthnJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$T = null;
register_shutdown_function(function () use (&$T) {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    // Фатальная ошибка PHP: не отдаём наружу ни текст ошибки, ни пути — только факт сбоя.
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'ok' => false,
        'message' => is_callable($T) ? $T('modtwoauth_webauthn_fatal') : '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

try {
    // IMPORTANT: do not call PHP session_start() here. MODX owns the session
    // handler and initializes the Manager session in $modx->initialize('mgr').

    $configCore = dirname(__FILE__, 4) . '/config.core.php';
    if (!is_readable($configCore)) {
        throw new RuntimeException('Не найден config.core.php.');
    }
    require_once $configCore;
    require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';

    // MODX 3 ships Composer dependencies in core/vendor/.
    $autoload = MODX_CORE_PATH . 'vendor/autoload.php';
    if (is_readable($autoload)) {
        require_once $autoload;
    }
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';

    $authContext = (string) ($_GET['context'] ?? $_POST['context'] ?? 'mgr');
    if (!in_array($authContext, ['mgr', 'web'], true)) $authContext = 'mgr';
    $modx = new \MODX\Revolution\modX();
    if (!$modx->initialize($authContext)) {
        throw new RuntimeException('Не удалось инициализировать контекст MODX.');
    }
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
    \ModTwoAuth\ErrorHandler::register($modx, 'WebAuthn registration');
    $T = static function (string $key) use ($modx): string {
        return \ModTwoAuth\Localization::get($modx, $key);
    };

    if ($authContext === 'mgr') {
        if (!$modx->user || !$modx->user->hasSessionContext('mgr')) {
            m2aWebAuthnJson([
                'ok' => false,
                'message' => $T('modtwoauth_error_manager_session_missing'),
            ], 401);
        }
    } else {
        /*
         * При инициализации web пользователь, вошедший только в shop, не
         * попадает в $modx->user. Выбираем ID из серверных contextTokens MODX
         * и загружаем учётную запись явно; Manager-only сессия не подходит.
         */
        $currentUserId = $modx->user ? (int) $modx->user->get('id') : 0;
        $frontendUserId = 0;
        $contextTokens = $_SESSION['modx.user.contextTokens'] ?? [];
        if (is_array($contextTokens)) {
            foreach ($contextTokens as $ctxKey => $ctxUserId) {
                $candidateId = (int) $ctxUserId;
                if (!is_string($ctxKey) || $ctxKey === 'mgr' || $candidateId <= 0) continue;
                if ($frontendUserId === 0) $frontendUserId = $candidateId;
                if ($currentUserId > 0 && $candidateId === $currentUserId) {
                    $frontendUserId = $currentUserId;
                    break;
                }
            }
        }
        $frontendUser = $frontendUserId > 0 ? $modx->getObject('modUser', $frontendUserId) : null;
        if (!$frontendUser || !(int) $frontendUser->get('active')) {
            m2aWebAuthnJson([
                'ok' => false,
                'message' => $T('modtwoauth_webauthn_login_to_register'),
            ], 401);
        }
        $modx->user = $frontendUser;
    }

    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = (string) parse_url($origin, PHP_URL_HOST);
        $allowedHosts = [];
        foreach (['site_url', 'manager_url'] as $option) {
            $host = (string) parse_url((string) $modx->getOption($option, null, ''), PHP_URL_HOST);
            if ($host !== '') $allowedHosts[] = strtolower($host);
        }
        $requestHost = (string) parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($requestHost !== '') $allowedHosts[] = strtolower($requestHost);
        if ($originHost === '' || !in_array(strtolower($originHost), $allowedHosts, true)) {
            m2aWebAuthnJson(['ok' => false, 'message' => $T('modtwoauth_error_invalid_origin')], 403);
        }
    }

    $bootstrap = MODX_CORE_PATH . 'components/modtwoauth/bootstrap.php';
    if (!is_readable($bootstrap)) {
        throw new RuntimeException('Не найден bootstrap modTwoAuth.');
    }
    $namespace = ['path' => MODX_CORE_PATH . 'components/modtwoauth/'];
    require_once $bootstrap;

    $userId = (int) $modx->user->get('id');
    if ($userId <= 0) {
        m2aWebAuthnJson(['ok' => false, 'message' => $T('modtwoauth_error_current_user')], 401);
    }

    $settingClass = '\\ModTwoAuth\\Model\\TwoAuthUserSetting';
    $setting = $modx->getObject($settingClass, ['user_id' => $userId]);
    if ($setting && (int) $setting->get('is_enabled')) {
        m2aWebAuthnJson([
            'ok' => false,
            'message' => $T('modtwoauth_webauthn_disable_before_register'),
        ], 409);
    }

    $provider = new \ModTwoAuth\Providers\WebAuthnProvider($modx);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $options = $provider->registrationOptions(
            $userId,
            (string) $modx->user->get('username'),
            (string) $modx->user->get('username')
        );
        m2aWebAuthnJson(['ok' => true, 'publicKey' => $options]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        m2aWebAuthnJson(['ok' => false, 'message' => $T('modtwoauth_error_method_not_allowed')], 405);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $credential = $payload['credential'] ?? $payload;
    if (is_string($credential)) {
        $credential = json_decode($credential, true);
    }
    if (!is_array($credential)) {
        m2aWebAuthnJson(['ok' => false, 'message' => $T('modtwoauth_webauthn_invalid_data')], 400);
    }

    [$ok, $result] = $provider->verifyRegistration($credential, $userId);
    if (!$ok) {
        m2aWebAuthnJson(['ok' => false, 'message' => (string) $result], 400);
    }

    if (!$setting) {
        $setting = $modx->newObject($settingClass);
        $setting->set('user_id', $userId);
    }
    $setting->set('provider', 'webauthn');
    $setting->set('contact_value', null);
    $setting->set('secret', null);
    $setting->set('is_verified', 1);
    $setting->set('is_enabled', 0);
    $setting->set('last_totp_counter', null);
    $setting->set('updated_at', date('Y-m-d H:i:s'));
    if (!$setting->save()) {
        $result->remove();
        m2aWebAuthnJson(['ok' => false, 'message' => $T('modtwoauth_webauthn_save_failed')], 500);
    }

    m2aWebAuthnJson([
        'ok' => true,
        'message' => $T('modtwoauth_webauthn_registered'),
    ]);
} catch (\Throwable $e) {
    if (isset($modx) && $modx instanceof \MODX\Revolution\modX) {
        $modx->log(
            \MODX\Revolution\modX::LOG_LEVEL_ERROR,
            '[modTwoAuth] WebAuthn registration endpoint error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
        );
    }
    m2aWebAuthnJson([
        'ok' => false,
        'message' => is_callable($T) ? $T('modtwoauth_webauthn_internal_error') : '',
    ], 500);
}
