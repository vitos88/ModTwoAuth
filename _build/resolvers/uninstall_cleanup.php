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

/** Remove ModTwoAuth Manager menu entries and System Settings on uninstall. */
/** @var xPDO\Transport\xPDOTransport $transport */
/** @var array $options */
if (!$transport->xpdo) {
    return true;
}
if (($options[xPDOTransport::PACKAGE_ACTION] ?? null) !== xPDOTransport::ACTION_UNINSTALL) {
    return true;
}

$modx = $transport->xpdo;
$removedSettings = 0;
$removedMenus = 0;

foreach ($modx->getIterator(\MODX\Revolution\modSystemSetting::class, ['namespace' => 'modtwoauth']) as $setting) {
    if ($setting->remove()) {
        $removedSettings++;
    }
}

foreach ($modx->getIterator(\MODX\Revolution\modMenu::class, ['namespace' => 'modtwoauth']) as $menu) {
    if ($menu->remove()) {
        $removedMenus++;
    }
}

$modx->log(
    \MODX\Revolution\modX::LOG_LEVEL_INFO,
    "[ModTwoAuth] Uninstall cleanup: removed {$removedMenus} menu entries and {$removedSettings} system settings."
);

// Keep the encryption key and 2FA database records: reinstalling the package must
// not make existing TOTP secrets undecryptable. Delete them manually only after
// a deliberate full data wipe and a verified backup.
return true;
