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

class VerifyEmail extends BaseProcessor
{
    public function process()
    {
        $userId = (int) $this->getProperty('user_id');
        $code = trim((string) $this->getProperty('code'));
        if (!preg_match('/^\d{6}$/', $code)) return $this->failure($this->text('modtwoauth_enter_six_digits'));

        $hashKey = 'modtwoauth_email_code_' . $userId;
        $contactKey = 'modtwoauth_email_contact_' . $userId;
        $createdKey = 'modtwoauth_email_created_' . $userId;
        $hash = (string) ($_SESSION[$hashKey] ?? '');
        $email = (string) ($_SESSION[$contactKey] ?? '');

        $attemptsKey = 'modtwoauth_email_attempts_' . $userId;
        if ($hash === '' || $email === '' || $this->isCodeExpired($createdKey)) {
            unset($_SESSION[$hashKey], $_SESSION[$contactKey], $_SESSION[$createdKey], $_SESSION[$attemptsKey]);
            return $this->failure($this->text('modtwoauth_code_expired_request_new'));
        }
        if (!password_verify($code, $hash)) {
            $attempts = (int) ($_SESSION[$attemptsKey] ?? 0) + 1;
            $_SESSION[$attemptsKey] = $attempts;
            $maxAttempts = max(1, (int) $this->modx->getOption('modtwoauth_max_attempts', null, 3));
            if ($attempts >= $maxAttempts) {
                unset($_SESSION[$hashKey], $_SESSION[$contactKey], $_SESSION[$createdKey], $_SESSION[$attemptsKey]);
                return $this->failure($this->text('modtwoauth_code_attempts_exceeded'));
            }
            return $this->failure($this->text('modtwoauth_code_invalid'));
        }

        $setting = $this->getOrCreateSetting($userId);
        $setting->fromArray([
            'provider' => 'email',
            'secret' => null,
            'contact_value' => $email,
            'is_enabled' => 0,
            'is_verified' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$setting->save()) return $this->failure($this->text('modtwoauth_settings_save_error'));

        unset($_SESSION[$hashKey], $_SESSION[$contactKey], $_SESSION[$createdKey], $_SESSION[$attemptsKey]);
        return $this->success($this->text('modtwoauth_email_configured'));
    }
}
