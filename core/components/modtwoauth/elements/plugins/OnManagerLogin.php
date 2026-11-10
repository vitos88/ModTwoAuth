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

use ModTwoAuth\SessionTracker;

/** @var \MODX\Revolution\modX $modx */
/** @var \MODX\Revolution\modUser $user */

$eventName = (string) $modx->event->name;
$userId = $user ? (int) $user->get('id') : 0;

$modx->addPackage('ModTwoAuth\\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');
require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';

if ($eventName === 'OnManagerLogout') {
    if ($userId > 0) {
        SessionTracker::remove($modx, $userId, 'mgr');
    }
    unset(
        $_SESSION['modtwoauth_verified_user_id'],
        $_SESSION['modtwoauth_pending_user_id'],
        $_SESSION['modtwoauth_pending_provider'],
        $_SESSION['modtwoauth_pending_timestamp'],
        $_SESSION['modtwoauth_pending_code_hash'],
        $_SESSION['modtwoauth_pending_email_sent_at'],
        $_SESSION['modtwoauth_pending_secret'],
        $_SESSION['modtwoauth_login_error'],
        $_SESSION['modtwoauth_tg_login_request_id']
    );
    return;
}

if ($eventName !== 'OnManagerLogin' || $userId <= 0) {
    return;
}

// track() находит/создаёт запись по хешу текущей сессии, пишет php_session_id,
// ставит флаг modtwoauth_session_tracked и попутно чистит «мёртвые» записи пользователя.
SessionTracker::track($modx, $userId, 'mgr');

foreach ([
    'modtwoauth_verified_user_id',
    'modtwoauth_pending_user_id',
    'modtwoauth_pending_provider',
    'modtwoauth_pending_timestamp',
    'modtwoauth_pending_code_hash',
    'modtwoauth_pending_email_sent_at',
    'modtwoauth_pending_secret',
    'modtwoauth_login_error',
    'modtwoauth_tg_login_request_id',
] as $key) {
    unset($_SESSION[$key]);
}
