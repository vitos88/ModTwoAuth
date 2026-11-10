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
 * имя пакета в Установщика
 * @var xPDOTransport $transport
 * @var xPDOObject    $object
 * @var array         $options
 */
if ($transport && $transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            /** @var \MODX\Revolution\Transport\modTransportPackage $package */
            $package = $transport->xpdo->getObject(
                \MODX\Revolution\Transport\modTransportPackage::class,
                ['signature' => $transport->signature]
            );
            if ($package) {
                $package->set('package_name', 'ModTwoAuth');
                $package->save();
            }
            break;
    }
}
return true;