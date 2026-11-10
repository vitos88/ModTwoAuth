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

namespace ModTwoAuth\Providers;

use MODX\Revolution\Mail\modMail;
use MODX\Revolution\modX;
use ModTwoAuth\Localization;

class EmailProvider extends BaseProvider
{
    public function sendCode(int $userId, string $contact, string $code): bool
    {
        if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // getOption() applies its default only when a setting is absent, not when
        // the packaged setting exists with an empty value. Handle both cases.
        $fromName = trim((string) $this->modx->getOption('modtwoauth_email_from_name', null, ''));
        if ($fromName === '') {
            $fromName = trim((string) $this->modx->getOption('site_name', null, 'MODX'));
        }
        $fromAddress = trim((string) $this->modx->getOption('modtwoauth_email_from_address', null, ''));
        if ($fromAddress === '') {
            $fromAddress = trim((string) $this->modx->getOption('emailsender', null, ''));
        }

        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modTwoAuth] Invalid sender email address.'
            );
            return false;
        }

        $this->modx->getService('mail', 'mail.modPHPMailer');
        if (!$this->modx->mail) {
            return false;
        }

        $this->modx->mail->set(modMail::MAIL_FROM, $fromAddress);
        $this->modx->mail->set(modMail::MAIL_FROM_NAME, $fromName);
        $this->modx->mail->set(modMail::MAIL_SENDER, $fromAddress);
        $lifetime = max(30, (int) $this->modx->getOption('modtwoauth_code_lifetime', null, 300));
        $minutes = max(1, (int) ceil($lifetime / 60));
        $this->modx->mail->set(
            modMail::MAIL_SUBJECT,
            Localization::get($this->modx, 'modtwoauth_email_subject')
        );
        $this->modx->mail->set(
            modMail::MAIL_BODY,
            Localization::get($this->modx, 'modtwoauth_email_body', [
                'code' => $code,
                'minutes' => $minutes,
            ])
        );
        $this->modx->mail->address('to', $contact);
        $this->modx->mail->setHTML(true);

        $sent = (bool) $this->modx->mail->send();
        if (!$sent) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modTwoAuth] Email send failed.'
            );
        }

        $this->modx->mail->reset();
        return $sent;
    }
}
