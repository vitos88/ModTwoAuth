<?php

$_lang['area_modtwoauth_frontend'] = 'Site frontend';
$_lang['area_modtwoauth_main'] = 'Main';
$_lang['area_modtwoauth_security'] = 'Security';
$_lang['area_modtwoauth_telegram'] = 'Telegram';
$_lang['area_modtwoauth_email'] = 'Email';
$_lang['area_modtwoauth_webauthn'] = 'WebAuthn / FIDO2';
$_lang['area_modtwoauth_verify'] = '2FA verification page';

$_lang['setting_modtwoauth_enable_2fa'] = 'Enable 2FA';
$_lang['setting_modtwoauth_enable_2fa_desc'] = 'Enables two-factor authentication for users who have it set up and enabled.';
$_lang['setting_modtwoauth_code_lifetime'] = 'Code lifetime';
$_lang['setting_modtwoauth_code_lifetime_desc'] = 'How many seconds a verification code is valid. Minimum 30 seconds.';
$_lang['setting_modtwoauth_max_attempts'] = 'Maximum 2FA attempts';
$_lang['setting_modtwoauth_max_attempts_desc'] = 'The number of invalid codes before a temporary lockout. The lockout triggers on the specified attempt. Minimum 1, default 3.';
$_lang['setting_modtwoauth_lock_minutes'] = 'Lockout duration';
$_lang['setting_modtwoauth_lock_minutes_desc'] = 'Lockout duration in minutes after the invalid code limit is reached. Minimum 1, default 15.';
$_lang['setting_modtwoauth_session_max_age'] = '2FA session lifetime';
$_lang['setting_modtwoauth_session_max_age_desc'] = 'Maximum age of a verified 2FA session in seconds. 0 means no limit. When it expires, the session ends and signing in with 2FA will be required again.';
$_lang['setting_modtwoauth_session_gc_interval'] = 'Інтэрвал ачысткі сеансаў';
$_lang['setting_modtwoauth_session_gc_interval_desc'] = 'Як часта (у секундах) фонавая ачыстка выдаляе запісы сеансаў, чые PHP-сеансы ўжо не існуюць (пасля «Завяршыць усе сеансы», зборкі смецця сеансаў PHP або зачыненых браўзераў). 0 — адключыць фонавую ачыстку; спісы сеансаў усё роўна правяраюць запісы пры адкрыцці.';
$_lang['setting_modtwoauth_resend_cooldown'] = 'Resend cooldown';
$_lang['setting_modtwoauth_resend_cooldown_desc'] = 'Minimum interval in seconds between repeated email code sends. Protects against mail spam.';
$_lang['setting_modtwoauth_telegram_bot_token'] = 'Telegram Bot Token';
$_lang['setting_modtwoauth_telegram_bot_token_desc'] = 'The Telegram bot token obtained from BotFather.';
$_lang['setting_modtwoauth_email_from_name'] = 'Email sender name';
$_lang['setting_modtwoauth_email_from_name_desc'] = 'The name shown in emails with 2FA codes. If empty, site_name is used.';
$_lang['setting_modtwoauth_email_from_address'] = 'Email sender address';
$_lang['setting_modtwoauth_email_from_address_desc'] = 'The sender email for 2FA codes. If empty, emailsender is used.';

$_lang['setting_modtwoauth_webauthn_rp_id'] = 'WebAuthn RP ID';
$_lang['setting_modtwoauth_webauthn_rp_id_desc'] = 'Optional RP ID. Usually leave it empty — the domain from site_url will be used.';

$_lang['setting_modtwoauth_frontend_enable_2fa'] = 'Enable 2FA on the site';
$_lang['setting_modtwoauth_frontend_enable_2fa_desc'] = 'Allows site users to set up 2FA on their own and requires the second factor for users who have enabled it.';

$_lang['setting_modtwoauth_verify_chunk_id'] = 'Frontend 2FA page chunk ID';
$_lang['setting_modtwoauth_verify_chunk_id_desc'] = 'ID of a custom chunk for the frontend verification page. If 0 or the chunk is not found, modtwoauthVerifyTpl is used. The Manager always uses the built-in modtwoauthVerifyTpl.';
$_lang['setting_modtwoauth_verify_url'] = 'Manager 2FA verification page URL';
$_lang['setting_modtwoauth_verify_url_desc'] = 'Local URL of the 2FA verification page, /2fa by default. The specified address is used when signing in to the Manager; the web server must route it through MODX. For the /2fa value with friendly URLs disabled, assets/components/modtwoauth/verify.php is used.';
$_lang['setting_modtwoauth_web_verify_url'] = 'Web 2FA verification page URL';
$_lang['setting_modtwoauth_web_verify_url_desc'] = 'Local URL of the 2FA verification page, /2fa-web by default. The specified address is used when signing in to the Web context; the web server must route it through MODX. For the /2fa-web value with friendly URLs disabled, assets/components/modtwoauth/verify.php is used.';

$_lang['setting_modtwoauth_key_path'] = 'TOTP encryption key path';
$_lang['setting_modtwoauth_key_path_desc'] = 'Absolute path to the key file outside the site public directory. Leave it empty for core/config/modtwoauth.key. If you change the path after installation, move the existing key to the new path; do not create a new key for already encrypted secrets.';
$_lang['setting_modtwoauth_csp_report_only'] = 'CSP: enable report-only mode';
$_lang['setting_modtwoauth_csp_policy'] = 'CSP: policy (report-only mode)';
$_lang['setting_modtwoauth_csp_report_only_desc'] = 'Adds Content-Security-Policy-Report-Only without blocking resources.';
$_lang['setting_modtwoauth_csp_policy_desc'] = 'Check the site and the Manager before using the policy in blocking mode.';
