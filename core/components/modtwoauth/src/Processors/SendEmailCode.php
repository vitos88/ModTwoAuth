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
use ModTwoAuth\Providers\EmailProvider;

class SendEmailCode extends BaseProcessor
{
    public function process()
    {
        $userId = (int) $this->getProperty('user_id');
        $email = trim((string) $this->getProperty('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->failure($this->text('modtwoauth_email_invalid'));

        // Ограничение частоты отправки кодов (анти-спам почты).
        $createdKey = 'modtwoauth_email_created_' . $userId;
        $cooldown = max(15, (int) $this->modx->getOption('modtwoauth_resend_cooldown', null, 60));
        $sentAt = (int) ($_SESSION[$createdKey] ?? 0);
        if ($sentAt > 0 && (time() - $sentAt) < $cooldown) {
            return $this->failure($this->text('modtwoauth_email_resend_wait', [
                'seconds' => max(1, $cooldown - (time() - $sentAt)),
            ]));
        }

        $provider = new EmailProvider($this->modx);
        $code = $provider->generateCode();
        if (!$provider->sendCode($userId, $email, $code)) {
            return $this->failure($this->text('modtwoauth_email_send_failed_manager'));
        }

        $_SESSION['modtwoauth_email_code_' . $userId] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['modtwoauth_email_contact_' . $userId] = $email;
        $_SESSION['modtwoauth_email_created_' . $userId] = time();
        unset($_SESSION['modtwoauth_email_attempts_' . $userId]);

        return $this->success($this->text('modtwoauth_email_sent_to', ['email' => $email]));
    }
}
