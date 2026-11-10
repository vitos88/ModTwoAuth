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

return [
    'verify_chunk_id' => [
        'xtype' => 'numberfield',
        'value' => 0,
        'area' => 'modtwoauth_verify',
    ],
    'verify_url' => [
        'xtype' => 'textfield',
        'value' => '/2fa',
        'area' => 'modtwoauth_verify',
    ],
    'web_verify_url' => [
        'xtype' => 'textfield',
        'value' => '/2fa-web',
        'area' => 'modtwoauth_verify',
    ],
    'frontend_enable_2fa' => [
        'xtype' => 'combo-boolean',
        'value' => true,
        'area' => 'modtwoauth_frontend',
    ],
    'enable_2fa' => [
        'xtype' => 'combo-boolean',
        'value' => false,
        'area' => 'modtwoauth_main',
    ],
    'code_lifetime' => [
        'xtype' => 'numberfield',
        'value' => 300,
        'area' => 'modtwoauth_main',
    ],
    'max_attempts' => [
        'xtype' => 'numberfield',
        'value' => 3,
        'area' => 'modtwoauth_security',
    ],
    'lock_minutes' => [
        'xtype' => 'numberfield',
        'value' => 15,
        'area' => 'modtwoauth_security',
    ],
    'resend_cooldown' => [
        'xtype' => 'numberfield',
        'value' => 60,
        'area' => 'modtwoauth_security',
    ],
    'session_max_age' => [
        'xtype' => 'numberfield',
        'value' => 86400,
        'area' => 'modtwoauth_security',
    ],
    'session_gc_interval' => [
        'xtype' => 'numberfield',
        'value' => 900,
        'area' => 'modtwoauth_security',
    ],
    'telegram_bot_token' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'modtwoauth_telegram',
    ],
    'email_from_name' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'modtwoauth_email',
    ],
    'email_from_address' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'modtwoauth_email',
    ],
    'key_path' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'modtwoauth_security',
    ],
    'csp_report_only' => [
        'xtype' => 'combo-boolean',
        'value' => true,
        'area' => 'modtwoauth_security',
    ],
    'csp_policy' => [
        'xtype' => 'textarea',
        'value' => "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; upgrade-insecure-requests",
        'area' => 'modtwoauth_security',
    ],
    'webauthn_rp_id' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'modtwoauth_webauthn',
    ],
];
