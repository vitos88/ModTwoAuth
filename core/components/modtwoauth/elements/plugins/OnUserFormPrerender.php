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
/** @var array $scriptProperties */
/** @var \MODX\Revolution\modUser $user */

$user = $scriptProperties['user'] ?? null;
if (!$user) {
    return;
}

$userId = (int) $user->get('id');
if ($userId <= 0) {
    return;
}

$setting = $modx->getObject(TwoAuthUserSetting::class, ['user_id' => $userId]);

$lexiconKeys = [
    'modtwoauth_verify_heading',
    'modtwoauth_mgr_enable',
    'modtwoauth_mgr_status',
    'modtwoauth_mgr_configured',
    'modtwoauth_mgr_not_configured',
    'modtwoauth_mgr_provider',
    'modtwoauth_mgr_sessions_title',
    'modtwoauth_mgr_sessions_loading',
    'modtwoauth_mgr_sessions_refresh',
    'modtwoauth_mgr_qr_library_failed',
    'modtwoauth_mgr_sessions_empty',
    'modtwoauth_mgr_device_browser',
    'modtwoauth_mgr_created',
    'modtwoauth_mgr_last_active',
    'modtwoauth_mgr_action',
    'modtwoauth_mgr_unknown_device',
    'modtwoauth_mgr_current_session',
    'modtwoauth_mgr_revoke',
    'modtwoauth_mgr_sessions_revoke_hint',
    'modtwoauth_mgr_sessions_load_failed',
    'modtwoauth_mgr_request_error',
    'modtwoauth_mgr_session_revoke_confirm',
    'modtwoauth_mgr_session_revoked',
    'modtwoauth_session_revoke_failed',
    'modtwoauth_mgr_already_configured',
    'modtwoauth_mgr_totp_setup',
    'modtwoauth_mgr_totp_step_generate',
    'modtwoauth_mgr_totp_step_scan',
    'modtwoauth_mgr_totp_step_verify',
    'modtwoauth_mgr_generate_qr',
    'modtwoauth_mgr_enter_app_code',
    'modtwoauth_mgr_confirm',
    'modtwoauth_mgr_email_setup',
    'modtwoauth_mgr_email_instruction',
    'modtwoauth_mgr_send_code',
    'modtwoauth_mgr_enter_email_code',
    'modtwoauth_mgr_webauthn_setup',
    'modtwoauth_mgr_webauthn_instruction',
    'modtwoauth_mgr_register_passkey',
    'modtwoauth_mgr_telegram_setup',
    'modtwoauth_mgr_telegram_instruction',
    'modtwoauth_mgr_create_qr',
    'modtwoauth_mgr_generating',
    'modtwoauth_mgr_secret',
    'modtwoauth_mgr_scan_qr',
    'modtwoauth_mgr_qr_manual',
    'modtwoauth_mgr_error',
    'modtwoauth_enter_six_digits',
    'modtwoauth_email_invalid',
    'modtwoauth_mgr_sending',
    'modtwoauth_mgr_browser_no_webauthn',
    'modtwoauth_mgr_webauthn_preparing',
    'modtwoauth_mgr_http_no_json',
    'modtwoauth_webauthn_invalid_base64',
    'modtwoauth_mgr_webauthn_missing_rp',
    'modtwoauth_mgr_webauthn_invalid_rp',
    'modtwoauth_mgr_webauthn_checking',
    'modtwoauth_mgr_key_registered',
    'modtwoauth_mgr_registration_cancelled',
    'modtwoauth_mgr_safari_rp_error',
    'modtwoauth_mgr_qr_creating',
    'modtwoauth_mgr_telegram_step1',
    'modtwoauth_mgr_telegram_step2',
    'modtwoauth_mgr_qr_valid_5m',
    'modtwoauth_verify_wait_telegram',
    'modtwoauth_mgr_qr_create_failed',
    'modtwoauth_telegram_connected',
    'modtwoauth_mgr_qr_expired',
    'modtwoauth_mgr_waiting_confirmation',
];
$lexicon = [];
foreach ($lexiconKeys as $key) {
    $lexicon[$key] = Localization::get($modx, $key);
}

$data = [
    'provider' => $setting ? (string) $setting->get('provider') : 'totp',
    'contact_value' => $setting ? (string) $setting->get('contact_value') : '',
    'is_enabled' => $setting ? (bool) $setting->get('is_enabled') : false,
    'is_verified' => $setting ? (bool) $setting->get('is_verified') : false,
    'has_secret' => $setting ? !empty($setting->get('secret')) : false,
    'user_id' => $userId,
    'lexicon' => $lexicon,
];

$assetsUrl = $modx->getOption('assets_url', null, MODX_ASSETS_URL);
$modx->controller->addLastJavascript(
    $assetsUrl . 'components/modtwoauth/js/mgr/user.inject.js'
);
$modx->controller->addHtml(
    '<script>var ModTwoAuthUserConfig = ' .
    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
    ';</script>'
);