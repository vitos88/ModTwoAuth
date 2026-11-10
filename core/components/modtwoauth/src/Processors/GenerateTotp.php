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

namespace ModTwoAuth\Processors;
use ModTwoAuth\Providers\TotpProvider;

class GenerateTotp extends BaseProcessor
{
    public function process()
    {
        $userId = (int) $this->getProperty('user_id');
        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) return $this->failure($this->text('modtwoauth_user_not_found'));

        $provider = new TotpProvider($this->modx);
        $secret = $provider->generateSecret();
        $qrData = $provider->getQrUrl(
            $secret,
            (string) $user->get('username'),
            (string) $this->modx->getOption('site_name', null, 'MODX')
        );

        $_SESSION['modtwoauth_totp_secret_' . $userId] = $secret;
        $_SESSION['modtwoauth_totp_created_' . $userId] = time();
        // Новый QR — новые попытки ввода кода.
        unset($_SESSION['modtwoauth_totp_attempts_' . $userId]);

        return $this->success($this->text('modtwoauth_totp_qr_generated'), [
            'qr_data' => $qrData['qr_data'],
            'secret' => $secret,
            'otpauth' => $qrData['otpauth'],
        ]);
    }
}
