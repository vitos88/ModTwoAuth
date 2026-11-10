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

use ModTwoAuth\Model\TwoAuthSession;
use ModTwoAuth\SessionTracker;

/** @var \MODX\Revolution\modX $modx */
$event = (string) $modx->event->name;
$modx->addPackage('ModTwoAuth\\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');
require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';

if ($event === 'OnWebLogout') {
    $userId = (int) ($_SESSION['modtwoauth_web_session_user_id'] ?? ($modx->user ? $modx->user->get('id') : 0));
    SessionTracker::remove($modx, $userId, 'web');
    unset($_SESSION['modtwoauth_web_session_tracked'], $_SESSION['modtwoauth_web_session_user_id'], $_SESSION['modtwoauth_web_session_context'], $_SESSION['modtwoauth_web_verified_user_id']);
    return;
}

if (!$modx->user || !$modx->user->isAuthenticated($modx->context->get('key'))) return;
$userId = (int) $modx->user->get('id');
if ($userId <= 0) return;
$context = (string) $modx->context->get('key');
if ($context === '' || $context === 'mgr') $context = 'web';

if ($event === 'OnWebLogin') {
    SessionTracker::track($modx, $userId, 'web');
    $_SESSION['modtwoauth_web_session_context'] = $context;
    // Обычный вход не должен наследовать отметку 2FA от предыдущего аккаунта
    // в той же PHP-сессии. Standalone 2FA-обработчики ставят её после этого события.
    unset($_SESSION['modtwoauth_web_verified_user_id']);
    return;
}

if ($event !== 'OnWebPageInit') return;

if (!empty($_SESSION['modtwoauth_web_session_tracked'])) {
    // Do not recreate a removed record: absence means this session was revoked.
    if (!SessionTracker::touch($modx, $userId, 'web')) {
        $modx->user->removeSessionContext($context);
        unset($_SESSION['modtwoauth_web_session_tracked'], $_SESSION['modtwoauth_web_session_user_id'], $_SESSION['modtwoauth_web_session_context'], $_SESSION['modtwoauth_web_verified_user_id']);
        $siteUrl = (string) $modx->getOption('site_url', null, '/');
        $separator = str_contains($siteUrl, '?') ? '&' : '?';
        header('Location: ' . $siteUrl . $separator . 'session_revoked=1', true, 302);
        exit;
    }

    /*
     * Фоновая очистка «мёртвых» записей — не чаще раза в интервал.
     * TRUNCATE из «Завершить все сеансы» и сборщик мусора MODX не вызывают
     * событий, поэтому записи о таких сессиях выявляются только сверкой
     * с таблицей сессий MODX (SessionTracker::gc).
     */
    $gcInterval = (int) $modx->getOption('modtwoauth_session_gc_interval', null, 900);
    if ($gcInterval > 0 && ($cacheManager = $modx->getCacheManager())) {
        $gcKey = 'modtwoauth/session_gc';
        if ((int) $cacheManager->get($gcKey) < time() - $gcInterval) {
            $cacheManager->set($gcKey, time(), $gcInterval);
            try {
                SessionTracker::gc($modx, null, true);
            } catch (\Throwable $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Background session GC failed: ' . $e->getMessage());
            }
        }
    }
    return;
}

// Existing sessions created before this version are registered on their next visit.
SessionTracker::track($modx, $userId, 'web');
$_SESSION['modtwoauth_web_session_context'] = $context;
