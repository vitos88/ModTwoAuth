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

/** Remove the obsolete third-party QR API setting on package install/update. */
/** @var xPDO\Transport\xPDOTransport $transport */
/** @var array $options */
if (!$transport->xpdo) {
    return true;
}
$modx = $transport->xpdo;
if (!in_array($options[xPDOTransport::PACKAGE_ACTION] ?? null, [xPDOTransport::ACTION_INSTALL, xPDOTransport::ACTION_UPGRADE], true)) {
    return true;
}
$setting = $modx->getObject(\MODX\Revolution\modSystemSetting::class, ['key' => 'modtwoauth_qr_api_url']);
if ($setting) {
    $setting->remove();
}
return true;
