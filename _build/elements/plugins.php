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

return [
    /*'ModTwoAuth' => [
        'file' => 'modtwoauth',
        'description' => '',
        'events' => [
            'OnManagerPageInit' => [],
        ],
    ],*/
    'modTwoAuthUserPrerender' => [
        'file' => 'OnUserFormPrerender',
        'events' => ['OnUserFormPrerender' => []],
        'description' => 'Injects 2FA JS into user profile form',
    ],
    'modTwoAuthUserSave' => [
        'file' => 'OnBeforeUserFormSave',
        'events' => ['OnBeforeUserFormSave' => []],
        'description' => 'Saves 2FA settings when profile is updated',
    ],
    'modTwoAuthLoginFormRender' => [
        'file' => 'OnManagerLoginFormRender',
        'events' => [],
        'description' => 'Legacy compatibility plugin; 2FA UI is rendered by verify.php',
    ],
    'modTwoAuthLogin' => [
        'file' => 'OnManagerLogin',
        'events' => [
            'OnManagerLogin' => [],
            'OnManagerLogout' => [],
        ],
        'description' => 'Maintains 2FA sessions after manager login and cleans up on logout',
    ],
    'modTwoAuthAuthentication' => [
        'file' => 'OnManagerAuthentication',
        'events' => [
            'OnManagerAuthentication' => [],
        ],
        'description' => 'Validates the first and second authentication factors for manager login',
    ],
    'modTwoAuthVerifyRoute' => [
        'file' => 'OnTwoAuthRoute',
        'events' => [
            'OnHandleRequest' => [],
        ],
        'description' => 'Routes the configured pretty 2FA URL to the verification handler',
    ],
    'modTwoAuthFrontendAuthentication' => [
        'file' => 'OnWebAuthentication',
        'events' => [
            'OnWebAuthentication' => [],
        ],
        'description' => 'Requires second factor for frontend Login extra authentication',
    ],
    'modTwoAuthFrontendSessions' => [
        'file' => 'OnWebSession',
        'events' => [
            'OnWebLogin' => [],
            'OnWebLogout' => [],
            'OnWebPageInit' => [],
        ],
        'description' => 'Tracks frontend sessions and enforces revoked website sessions',
    ],
    'modTwoAuthSecurityHeaders' => [
        'file' => 'OnSecurityHeaders',
        'events' => ['OnWebPageInit' => [], 'OnManagerPageInit' => []],
        'description' => 'Adds configurable Content-Security-Policy-Report-Only headers',
    ],
    'modTwoAuthSessionGuard' => [
        'file' => 'OnManagerPageInit',
        'events' => [
            'OnManagerPageInit' => [],
        ],
        'description' => 'Tracks manager activity and enforces revoked modTwoAuth sessions',
    ],
];
