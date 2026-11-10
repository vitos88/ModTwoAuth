<?php

// Verification page labels and messages
$_lang['modtwoauth_verify_title'] = 'Authentification à deux facteurs';
$_lang['modtwoauth_verify_heading'] = '🔐 Authentification à deux facteurs';
$_lang['modtwoauth_verify_hint_default'] = 'Saisissez le code de vérification.';
$_lang['modtwoauth_verify_hint_totp'] = 'Ouvrez Google Authenticator et saisissez le code à 6 chiffres actuel.';
$_lang['modtwoauth_verify_hint_email'] = 'Nous avons envoyé un code à 6 chiffres à';
$_lang['modtwoauth_verify_hint_telegram'] = 'Confirmez la connexion dans Telegram ou saisissez le code à 6 chiffres du message du bot.';
$_lang['modtwoauth_verify_hint_webauthn'] = 'Utilisez un Passkey, Touch ID, Face ID ou une clé FIDO2 pour confirmer la connexion.';
$_lang['modtwoauth_verify_back'] = '← Retour à la connexion classique';
$_lang['modtwoauth_verify_code_placeholder'] = 'Code à 6 chiffres';
$_lang['modtwoauth_verify_submit'] = 'Confirmer le code';
$_lang['modtwoauth_verify_passkey'] = '🔐 Se connecter avec Passkey / FIDO2';
$_lang['modtwoauth_verify_wait_device'] = '⏳ En attente de confirmation sur votre appareil...';
$_lang['modtwoauth_verify_wait_telegram'] = '⏳ En attente de confirmation dans Telegram...';
$_lang['modtwoauth_verify_invalid_code'] = 'Code de vérification incorrect.';
$_lang['modtwoauth_verify_locked'] = 'Trop de tentatives incorrectes. Accès verrouillé.';
$_lang['modtwoauth_verify_locked_retry'] = 'Trop de tentatives incorrectes. Réessayez dans [[+time]].';
$_lang['modtwoauth_verify_attempts_left'] = 'Code de vérification incorrect. Tentatives restantes : [[+left]].';
$_lang['modtwoauth_verify_back_to_login'] = 'Retour à la connexion';
