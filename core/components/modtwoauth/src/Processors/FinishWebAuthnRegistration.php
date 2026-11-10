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

use ModTwoAuth\Model\TwoAuthUserSetting;
use ModTwoAuth\Providers\WebAuthnProvider;

class FinishWebAuthnRegistration extends BaseProcessor
{
    public function process()
    {
        $userId = (int) $this->getProperty('user_id');
        $raw = (string) $this->getProperty('credential', '');
        $credential = json_decode($raw, true);
        if (!is_array($credential)) return $this->failure($this->text('modtwoauth_webauthn_invalid_data'));

        [$ok, $result] = (new WebAuthnProvider($this->modx))->verifyRegistration($credential, $userId);
        if (!$ok) return $this->failure((string) $result);

        $setting = $this->getOrCreateSetting($userId);
        $setting->set('provider', 'webauthn');
        $setting->set('contact_value', null);
        $setting->set('secret', null);
        $setting->set('is_verified', 1);
        $setting->set('is_enabled', 0);
        $setting->set('last_totp_counter', null);
        $setting->set('updated_at', date('Y-m-d H:i:s'));
        if (!$setting->save()) {
            $result->remove();
            return $this->failure($this->text('modtwoauth_webauthn_settings_save_failed'));
        }

        return $this->success($this->text('modtwoauth_webauthn_registered'));
    }
}
