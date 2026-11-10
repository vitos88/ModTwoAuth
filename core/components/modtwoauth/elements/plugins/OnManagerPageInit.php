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

use ModTwoAuth\Model\TwoAuthUserSetting;
use ModTwoAuth\SessionTracker;

/** @var \MODX\Revolution\modX $modx */

if (!$modx->user || !$modx->user->hasSessionContext('mgr')) {
    return;
}

$userId = (int) $modx->user->get('id');
if ($userId <= 0 || empty($_SESSION['modtwoauth_session_tracked'])) {
    return;
}

require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';

try {
    // touch() возвращает false, если сессия была отозвана в менеджере сессий
    // либо превысила modtwoauth_session_max_age.
    if (SessionTracker::touch($modx, $userId, 'mgr')) {
        return;
    }
} catch (\Throwable $e) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Session guard failed: ' . $e->getMessage());
    return;
}

// The session record was removed from the session manager (or has expired).
// Remove the Manager context and redirect to login so the revoked session
// cannot continue working.
$modx->user->removeSessionContext('mgr');
unset($_SESSION['modtwoauth_session_tracked']);
$siteUrl = (string) $modx->getOption('site_url', null, '/');
$managerUrl = (string) $modx->getOption('manager_url', null, 'manager/');
if (preg_match('~^https?://~i', $managerUrl)) {
    $loginUrl = rtrim($managerUrl, '/') . '/?a=security/login&session_revoked=1';
} else {
    $loginUrl = rtrim($siteUrl, '/') . '/' . trim($managerUrl, '/') . '/?a=security/login&session_revoked=1';
}
header('Location: ' . $loginUrl, true, 302);
exit;
