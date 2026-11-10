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
 * ModTwoAuth manager connector.
 *
 * This connector deliberately bootstraps MODX directly and invokes only the
 * two WebAuthn processors needed by the manager UI. We do not call
 * connectors/index.php here because that connector owns the normal MODX
 * connector request lifecycle and can terminate the request before a custom
 * processor gets a chance to return JSON.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

try {
    require_once dirname(__FILE__, 4) . '/config.core.php';
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';

    $modx = new \MODX\Revolution\modX();
    if (!$modx->initialize('mgr')) {
        throw new \RuntimeException('Не удалось инициализировать MODX Manager.');
    }
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
    \ModTwoAuth\ErrorHandler::register($modx, 'Manager connector');
    $T = static function (string $key) use ($modx): string {
        return \ModTwoAuth\Localization::get($modx, $key);
    };

    if (!$modx->user || !$modx->user->hasSessionContext('mgr')) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $T('modtwoauth_error_manager_session'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Дополнительная защита от CSRF для POST: если Origin присутствует, он обязан
    // совпадать с источником сайта. Для браузеров без Origin проверяем Referer тем же способом.
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            $origin = (string) parse_url($referer, PHP_URL_HOST);
        }
    }
    if ($origin !== '') {
        $originHost = (string) parse_url($origin, PHP_URL_HOST);
        if ($originHost === '') {
            // Origin содержал только имя хоста (из Referer).
            $originHost = $origin;
        }
        // Допустимые хосты: сайт, менеджер и сам хост запроса. Хост запроса
        // браузер при кросс-сайтовой подделке подменить не может.
        $allowedHosts = [];
        foreach (['site_url', 'manager_url'] as $option) {
            $host = (string) parse_url((string) $modx->getOption($option, null, ''), PHP_URL_HOST);
            if ($host !== '') {
                $allowedHosts[] = strtolower($host);
            }
        }
        $requestHost = (string) parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($requestHost !== '') {
            $allowedHosts[] = strtolower($requestHost);
        }
        if ($originHost !== '' && !in_array(strtolower($originHost), $allowedHosts, true)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => $T('modtwoauth_error_invalid_origin'),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Load the extra namespace/model map after MODX has been initialized.
    $bootstrap = MODX_CORE_PATH . 'components/modtwoauth/bootstrap.php';
    if (is_readable($bootstrap)) {
        $namespace = ['path' => MODX_CORE_PATH . 'components/modtwoauth/'];
        require_once $bootstrap;
    } else {
        throw new \RuntimeException('Не найден bootstrap modTwoAuth.');
    }

    $action = (string) ($_REQUEST['action'] ?? '');
    // Explicit allowlist for all actions used by the Manager UI.
    $allowed = [
        'ModTwoAuth\\Processors\\BeginWebAuthnRegistration',
        'ModTwoAuth\\Processors\\FinishWebAuthnRegistration',
        'ModTwoAuth\\Processors\\GetSessionList',
        'ModTwoAuth\\Processors\\RemoveSession',
        'ModTwoAuth\\Processors\\GenerateTotp',
        'ModTwoAuth\\Processors\\VerifyTotp',
        'ModTwoAuth\\Processors\\SendEmailCode',
        'ModTwoAuth\\Processors\\VerifyEmail',
        'ModTwoAuth\\Processors\\CreateTelegramLink',
        'ModTwoAuth\\Processors\\TelegramLinkStatus',
    ];
    if (!in_array($action, $allowed, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $T('modtwoauth_error_invalid_action'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Read-only actions may be fetched with GET; everything that changes state
    // must be POST. GET-запросы с чужого сайта (img/script) не отправляют
    // Origin-заголовок, поэтому метод — обязательная защита от CSRF.
    $readOnly = [
        'ModTwoAuth\\Processors\\GetSessionList',
        'ModTwoAuth\\Processors\\TelegramLinkStatus',
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($action, $readOnly, true)) {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => $T('modtwoauth_error_post_required'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $properties = $_REQUEST;
    unset($properties['action']);

    $response = $modx->runProcessor(
        $action,
        $properties,
        [
            'processors_path' => MODX_CORE_PATH . 'components/modtwoauth/src/Processors/',
        ]
    );

    if (!$response instanceof \MODX\Revolution\Processors\ProcessorResponse) {
        throw new \RuntimeException('MODX не вернул корректный ответ процессора.');
    }

    $payload = $response->getResponse();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    if (isset($modx) && $modx instanceof \MODX\Revolution\modX) {
        $modx->log(
            \MODX\Revolution\modX::LOG_LEVEL_ERROR,
            '[modTwoAuth] Manager connector error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
        );
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => isset($T) ? $T('modtwoauth_error_server') : '',
    ], JSON_UNESCAPED_UNICODE);
}
