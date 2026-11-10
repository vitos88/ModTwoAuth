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

class VerifyTotp extends BaseProcessor
{
    public function process()
    {
        $userId = (int) $this->getProperty('user_id');
        $code = trim((string) $this->getProperty('code'));
        if (!preg_match('/^\d{6}$/', $code)) return $this->failure($this->text('modtwoauth_enter_six_digits'));

        $secretKey = 'modtwoauth_totp_secret_' . $userId;
        $createdKey = 'modtwoauth_totp_created_' . $userId;
        $attemptsKey = 'modtwoauth_totp_attempts_' . $userId;
        $secret = (string) ($_SESSION[$secretKey] ?? '');

        if ($secret === '' || $this->isCodeExpired($createdKey)) {
            unset($_SESSION[$secretKey], $_SESSION[$createdKey], $_SESSION[$attemptsKey]);
            return $this->failure($this->text('modtwoauth_totp_qr_expired'));
        }

        $matchedStep = null;
        if (!(new TotpProvider($this->modx))->verifyCode($secret, $code, 0, $matchedStep)) {
            // Не даём перебирать 6-значный код бесконечно: после исчерпания
            // попыток секрет настройки аннулируется и нужен новый QR-код.
            $attempts = (int) ($_SESSION[$attemptsKey] ?? 0) + 1;
            $_SESSION[$attemptsKey] = $attempts;
            $maxAttempts = max(1, (int) $this->modx->getOption('modtwoauth_max_attempts', null, 3));
            if ($attempts >= $maxAttempts) {
                unset($_SESSION[$secretKey], $_SESSION[$createdKey], $_SESSION[$attemptsKey]);
                return $this->failure($this->text('modtwoauth_totp_attempts_exceeded'));
            }
            return $this->failure($this->text('modtwoauth_totp_invalid_retry'));
        }

        $setting = $this->getOrCreateSetting($userId);
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/SecurityCrypto.php';
        try {
            $storedSecret = \ModTwoAuth\SecurityCrypto::encrypt($secret);
        } catch (\Throwable $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Manager TOTP encryption failed: ' . $e->getMessage());
            return $this->failure($this->text('modtwoauth_totp_encryption_failed'));
        }
        $setting->fromArray([
            'provider' => 'totp',
            'secret' => $storedSecret,
            'contact_value' => null,
            'is_enabled' => 0,
            'is_verified' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
            // Новый секрет — обнуляем счётчик, код настройки нельзя использовать повторно.
            'last_totp_counter' => $matchedStep,
        ]);

        if (!$setting->save()) return $this->failure($this->text('modtwoauth_settings_save_error'));
        unset($_SESSION[$secretKey], $_SESSION[$createdKey], $_SESSION[$attemptsKey]);

        return $this->success($this->text('modtwoauth_totp_configured'));
    }
}
