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

use ModTwoAuth\Localization;
use ModTwoAuth\Model\TwoAuthUserSetting;

/** @var \MODX\Revolution\modX $modx */
/** @var \MODX\Revolution\modUser $user */

$userId = (int) $user->get('id');
if ($userId <= 0) {
    return;
}

$setting = $modx->getObject(TwoAuthUserSetting::class, ['user_id' => $userId]);

// Custom 2FA fields are not fields of modUser, so they are not present in
// $data ($object->toArray()). Read them from the submitted manager form too.
$data = $scriptProperties['data'] ?? [];
if (!is_array($data)) {
    $data = [];
}

$rawEnabled = array_key_exists('modtwoauth_is_enabled', $data)
    ? $data['modtwoauth_is_enabled']
    : ($_POST['modtwoauth_is_enabled'] ?? null);

$rawProvider = array_key_exists('modtwoauth_provider', $data)
    ? $data['modtwoauth_provider']
    : ($_POST['modtwoauth_provider'] ?? null);

// If a custom field is absent from the request (for example because ExtJS
// disabled it), preserve the current value instead of silently resetting it.
// combo-boolean in MODX Manager may submit localized labels (e.g. "Да" / "Нет"),
// not only 1/0 or true/false. Normalize all supported representations explicitly.
if ($rawEnabled === null) {
    $isEnabled = (int) ($setting ? $setting->get('is_enabled') : 0);
} elseif (is_bool($rawEnabled)) {
    $isEnabled = $rawEnabled ? 1 : 0;
} elseif (is_numeric($rawEnabled)) {
    $isEnabled = ((int) $rawEnabled === 1) ? 1 : 0;
} else {
    $enabledValue = mb_strtolower(trim((string) $rawEnabled), 'UTF-8');
    $isEnabled = in_array($enabledValue, [
        '1', 'true', 'yes', 'on', 'да', 'включено', 'enabled'
    ], true) ? 1 : 0;
}

$provider = $rawProvider !== null
    ? (string) $rawProvider
    : (string) ($setting ? $setting->get('provider') : 'totp');

if (!in_array($provider, ['totp', 'email', 'telegram', 'webauthn'], true)) {
    $provider = $setting ? (string) $setting->get('provider') : 'totp';
}
if (!in_array($provider, ['totp', 'email', 'telegram', 'webauthn'], true)) {
    $provider = 'totp';
}

// Turning 2FA OFF must be a normal save operation. Keep the verified method
// and its credentials so that it can be enabled again later.
if ($setting && !$isEnabled) {
    $setting->set('is_enabled', 0);
    $setting->set('updated_at', date('Y-m-d H:i:s'));
    if (!$setting->save()) {
        $modx->event->output(Localization::get($modx, 'modtwoauth_disable_failed'));
        $modx->event->stopPropagation();
    }
    return;
}

if ($isEnabled && (!$setting || !$setting->get('is_verified'))) {
    $modx->event->output(Localization::get($modx, 'modtwoauth_setup_required'));
    $modx->event->stopPropagation();
    return;
}

if ($setting && $setting->get('provider') !== $provider) {
    // Changing the method while 2FA is enabled is not allowed. Disable it,
    // save, then choose and verify the new method.
    if ($setting->get('is_enabled')) {
        $modx->event->output(Localization::get($modx, 'modtwoauth_disable_before_provider_change'));
        $modx->event->stopPropagation();
        return;
    }

    // 2FA is disabled: changing provider starts a fresh verification.
    $setting->fromArray([
        'provider' => $provider,
        'secret' => null,
        'contact_value' => null,
        'is_verified' => 0,
        'is_enabled' => 0,
        'last_totp_counter' => null,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    if (!$setting->save()) {
        $modx->event->output(Localization::get($modx, 'modtwoauth_settings_save_error'));
        $modx->event->stopPropagation();
    }
    return;
}

if (!$setting) {
    if (!$isEnabled) {
        return;
    }

    $modx->event->output(Localization::get($modx, 'modtwoauth_setup_required'));
    $modx->event->stopPropagation();
    return;
}

$setting->fromArray([
    'provider' => $provider,
    'is_enabled' => $isEnabled,
    'updated_at' => date('Y-m-d H:i:s'),
]);

if (!$setting->save()) {
    $modx->log(
        \MODX\Revolution\modX::LOG_LEVEL_ERROR,
        '[modTwoAuth] Failed to save user setting.'
    );
}
