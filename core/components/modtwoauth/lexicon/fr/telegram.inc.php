<?php

// Telegram bot messages and setup status.
$_lang['modtwoauth_telegram_allow_login'] = '✅ Autoriser la connexion';
$_lang['modtwoauth_telegram_bot_unavailable'] = 'Le bot Telegram est indisponible. Vérifiez le token et les paramètres du bot.';
$_lang['modtwoauth_telegram_code_message'] = "Votre code de vérification ModTwoAuth : [[+code]]\n\nLe code est valable [[+minutes]] min. Ne le communiquez à personne.";
$_lang['modtwoauth_telegram_confirm_in_bot'] = 'Confirmez la connexion dans le message du bot Telegram.';
$_lang['modtwoauth_telegram_confirm_link'] = '✅ Confirmer la connexion';
$_lang['modtwoauth_telegram_confirmation_save_failed'] = 'Impossible d\'enregistrer la confirmation. Réessayez.';
$_lang['modtwoauth_telegram_connected'] = 'Telegram connecté avec succès.';
$_lang['modtwoauth_telegram_deny_login'] = '❌ Refuser';
$_lang['modtwoauth_telegram_link_create_failed'] = 'Impossible de créer la demande de connexion Telegram.';
$_lang['modtwoauth_telegram_link_expired'] = '⚠️ Cette demande de connexion a déjà expiré. Retournez dans MODX et créez un nouveau QR code.';
$_lang['modtwoauth_telegram_link_invalid'] = 'La demande est invalide, annulée ou expirée.';
$_lang['modtwoauth_telegram_link_message'] = 'Confirmez la connexion de Telegram à ModTwoAuth en cliquant sur le bouton ci-dessous.';
$_lang['modtwoauth_telegram_link_not_found'] = '⚠️ Demande de connexion introuvable ou déjà expirée. Créez un nouveau QR code dans MODX.';
$_lang['modtwoauth_telegram_link_save_failed'] = 'Impossible d\'enregistrer la connexion.';
$_lang['modtwoauth_telegram_linked'] = 'Telegram est connecté à MODX.';
$_lang['modtwoauth_telegram_linked_message'] = "✅ Telegram connecté à MODX avec succès.\n\nRetournez au panneau MODX — la page se rafraîchira automatiquement.";
$_lang['modtwoauth_telegram_login_allowed'] = 'Connexion autorisée.';
$_lang['modtwoauth_telegram_login_allowed_message'] = '✅ Connexion à MODX autorisée.';
$_lang['modtwoauth_telegram_login_denied'] = 'Connexion refusée.';
$_lang['modtwoauth_telegram_login_denied_message'] = '❌ Connexion à MODX refusée.';
$_lang['modtwoauth_telegram_login_invalid'] = 'La demande est invalide ou expirée.';
$_lang['modtwoauth_telegram_login_message'] = "Demande de connexion à [[+site]].\n\nCode de vérification : [[+code]]\nValable : [[+minutes]] min.\n\nSi c'était vous, autorisez la connexion. Sinon, refusez la demande.";
$_lang['modtwoauth_telegram_login_not_found'] = 'Demande de connexion introuvable.';
$_lang['modtwoauth_telegram_login_send_failed'] = 'Impossible d\'envoyer la demande de vérification dans Telegram.';
$_lang['modtwoauth_telegram_qr_expired'] = 'Le QR code a expiré. Créez-en un nouveau.';
$_lang['modtwoauth_telegram_qr_ready'] = 'Le QR code a été créé. Ouvrez Telegram et confirmez la connexion.';
$_lang['modtwoauth_telegram_request_create_failed'] = 'Impossible de créer la demande Telegram.';
$_lang['modtwoauth_telegram_request_not_found'] = 'Demande introuvable.';
$_lang['modtwoauth_telegram_request_processed'] = 'Cette demande a déjà été traitée.';
$_lang['modtwoauth_telegram_start_help'] = 'Pour connecter Telegram, ouvrez le lien à usage unique ou le QR code depuis les paramètres ModTwoAuth du site. La simple commande /start sans lien ne lie pas le compte.';
$_lang['modtwoauth_telegram_waiting_confirmation'] = 'En attente de confirmation dans Telegram...';
$_lang['modtwoauth_telegram_waiting_link'] = 'En attente de la connexion Telegram...';
$_lang['modtwoauth_telegram_webhook_failed'] = 'Impossible de configurer le webhook Telegram. Une adresse HTTPS publique du site est nécessaire ; vérifiez site_url, assets_url, le certificat SSL et la disponibilité de telegram-webhook.php. Détails dans le journal d\'erreurs MODX.';
