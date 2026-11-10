<?php

// Telegram bot messages and setup status.
$_lang['modtwoauth_telegram_allow_login'] = '✅ Allow login';
$_lang['modtwoauth_telegram_bot_unavailable'] = 'The Telegram bot is unavailable. Check the bot token and settings.';
$_lang['modtwoauth_telegram_code_message'] = "Your ModTwoAuth verification code: [[+code]]\n\nThe code is valid for [[+minutes]] min. Do not share it with anyone.";
$_lang['modtwoauth_telegram_confirm_in_bot'] = 'Confirm the connection in the Telegram bot message.';
$_lang['modtwoauth_telegram_confirm_link'] = '✅ Confirm connection';
$_lang['modtwoauth_telegram_confirmation_save_failed'] = 'Failed to save the confirmation. Please try again.';
$_lang['modtwoauth_telegram_connected'] = 'Telegram has been connected successfully.';
$_lang['modtwoauth_telegram_deny_login'] = '❌ Deny';
$_lang['modtwoauth_telegram_link_create_failed'] = 'Failed to create a Telegram connection request.';
$_lang['modtwoauth_telegram_link_expired'] = '⚠️ This connection request has already expired. Return to MODX and create a new QR code.';
$_lang['modtwoauth_telegram_link_invalid'] = 'The request is invalid, cancelled or expired.';
$_lang['modtwoauth_telegram_link_message'] = 'Confirm connecting Telegram to ModTwoAuth by tapping the button below.';
$_lang['modtwoauth_telegram_link_not_found'] = '⚠️ Connection request not found or already expired. Create a new QR code in MODX.';
$_lang['modtwoauth_telegram_link_save_failed'] = 'Failed to save the connection.';
$_lang['modtwoauth_telegram_linked'] = 'Telegram is connected to MODX.';
$_lang['modtwoauth_telegram_linked_message'] = "✅ Telegram has been connected to MODX successfully.\n\nReturn to the MODX panel — the page will refresh automatically.";
$_lang['modtwoauth_telegram_login_allowed'] = 'Login allowed.';
$_lang['modtwoauth_telegram_login_allowed_message'] = '✅ Login to MODX allowed.';
$_lang['modtwoauth_telegram_login_denied'] = 'Login denied.';
$_lang['modtwoauth_telegram_login_denied_message'] = '❌ Login to MODX denied.';
$_lang['modtwoauth_telegram_login_invalid'] = 'The request is invalid or expired.';
$_lang['modtwoauth_telegram_login_message'] = "Login request for [[+site]].\n\nVerification code: [[+code]]\nValid for: [[+minutes]] min.\n\nIf this was you, allow the login. Otherwise deny the request.";
$_lang['modtwoauth_telegram_login_not_found'] = 'Login request not found.';
$_lang['modtwoauth_telegram_login_send_failed'] = 'Failed to send the verification request to Telegram.';
$_lang['modtwoauth_telegram_qr_expired'] = 'The QR code has expired. Create a new one.';
$_lang['modtwoauth_telegram_qr_ready'] = 'The QR code has been created. Open Telegram and confirm the connection.';
$_lang['modtwoauth_telegram_request_create_failed'] = 'Failed to create a Telegram request.';
$_lang['modtwoauth_telegram_request_not_found'] = 'Request not found.';
$_lang['modtwoauth_telegram_request_processed'] = 'This request has already been processed.';
$_lang['modtwoauth_telegram_start_help'] = 'To connect Telegram, open the one-time link or QR code from the ModTwoAuth settings on the site. The plain /start command without a link does not link the account.';
$_lang['modtwoauth_telegram_waiting_confirmation'] = 'Waiting for confirmation in Telegram...';
$_lang['modtwoauth_telegram_waiting_link'] = 'Waiting for the Telegram connection...';
$_lang['modtwoauth_telegram_webhook_failed'] = 'Failed to set up the Telegram webhook. A public HTTPS address of the site is required; check site_url, assets_url, the SSL certificate and the availability of telegram-webhook.php. See the MODX error log for details.';
