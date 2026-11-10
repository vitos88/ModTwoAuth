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
 * Route the configured pretty 2FA URLs to the standalone verification handlers.
 * The web server must route these requests through MODX index.php.
 */
if ($modx->event->name !== 'OnHandleRequest') { return; }

require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';

// Resolve the requested path relative to the site base path.
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '');
$basePath = (string) (parse_url((string) $modx->getOption('site_url'), PHP_URL_PATH) ?? '');
$basePath = rtrim($basePath, '/');
if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = '/' . trim(rawurldecode($requestPath), '/');

// setting key => [ default path, handler inside assets/components/modtwoauth/ ]
$routes = [
    'modtwoauth_verify_url'     => ['/2fa',     'verify.php'],     // Manager 2FA page
    'modtwoauth_web_verify_url' => ['/2fa-web', 'web-verify.php'], // Frontend 2FA page
];

foreach ($routes as $settingKey => $route) {
    list($default, $file) = $route;
    $configured = trim((string) $modx->getOption($settingKey, null, $default));
    if ($configured === '') { $configured = $default; }
    $parts = parse_url($configured);
    if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) { continue; }
    $expectedPath = '/' . trim((string) ($parts['path'] ?? ''), '/');
    if ($expectedPath === '/') { continue; }
    if (strcasecmp($requestPath, $expectedPath) !== 0) { continue; }

    $handler = MODX_ASSETS_PATH . 'components/modtwoauth/' . $file;
    if (!is_file($handler)) {
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] 2FA route handler not found: ' . $handler);
        http_response_code(500);
        echo \ModTwoAuth\Localization::get($modx, 'modtwoauth_verify_unavailable');
        exit;
    }
    require $handler;
    exit;
}
