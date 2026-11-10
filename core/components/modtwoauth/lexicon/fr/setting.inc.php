<?php

$_lang['area_modtwoauth_frontend'] = 'Frontend du site';
$_lang['area_modtwoauth_main'] = 'Général';
$_lang['area_modtwoauth_security'] = 'Sécurité';
$_lang['area_modtwoauth_telegram'] = 'Telegram';
$_lang['area_modtwoauth_email'] = 'Email';
$_lang['area_modtwoauth_webauthn'] = 'WebAuthn / FIDO2';
$_lang['area_modtwoauth_verify'] = 'Page de vérification 2FA';

$_lang['setting_modtwoauth_enable_2fa'] = 'Activer la 2FA';
$_lang['setting_modtwoauth_enable_2fa_desc'] = 'Active l\'authentification à deux facteurs pour les utilisateurs qui l\'ont configurée et activée.';
$_lang['setting_modtwoauth_code_lifetime'] = 'Durée de validité du code';
$_lang['setting_modtwoauth_code_lifetime_desc'] = 'Durée de validité en secondes du code de vérification. Minimum 30 secondes.';
$_lang['setting_modtwoauth_max_attempts'] = 'Nombre maximum de tentatives 2FA';
$_lang['setting_modtwoauth_max_attempts_desc'] = 'Nombre de codes incorrects avant le verrouillage temporaire. Le verrouillage se déclenche à la tentative indiquée. Minimum 1, par défaut 3.';
$_lang['setting_modtwoauth_lock_minutes'] = 'Durée de verrouillage';
$_lang['setting_modtwoauth_lock_minutes_desc'] = 'Durée du verrouillage en minutes après atteinte de la limite de codes incorrects. Minimum 1, par défaut 15.';
$_lang['setting_modtwoauth_session_max_age'] = 'Durée de la session 2FA';
$_lang['setting_modtwoauth_session_max_age_desc'] = 'Âge maximum d\'une session 2FA vérifiée en secondes. 0 — sans limite. À l\'expiration, la session se termine et une reconnexion avec 2FA sera nécessaire.';
$_lang['setting_modtwoauth_session_gc_interval'] = 'Intervalle de nettoyage des sessions';
$_lang['setting_modtwoauth_session_gc_interval_desc'] = 'Fréquence (en secondes) à laquelle le nettoyage en arrière-plan supprime les enregistrements de sessions dont les sessions PHP n\'existent plus (après « Terminer toutes les sessions », le GC des sessions PHP ou des navigateurs fermés). 0 désactive le nettoyage en arrière-plan ; les listes de sessions vérifient toujours les enregistrements à l\'ouverture.';
$_lang['setting_modtwoauth_resend_cooldown'] = 'Délai entre les envois de code';
$_lang['setting_modtwoauth_resend_cooldown_desc'] = 'Intervalle minimum en secondes entre les envois répétés du code par email. Protège contre le spam de courrier.';
$_lang['setting_modtwoauth_telegram_bot_token'] = 'Telegram Bot Token';
$_lang['setting_modtwoauth_telegram_bot_token_desc'] = 'Token du bot Telegram obtenu via BotFather.';
$_lang['setting_modtwoauth_email_from_name'] = 'Nom de l\'expéditeur des emails';
$_lang['setting_modtwoauth_email_from_name_desc'] = 'Nom affiché dans les emails avec codes 2FA. Si vide, site_name est utilisé.';
$_lang['setting_modtwoauth_email_from_address'] = 'Adresse de l\'expéditeur des emails';
$_lang['setting_modtwoauth_email_from_address_desc'] = 'Email de l\'expéditeur des codes 2FA. Si vide, emailsender est utilisé.';

$_lang['setting_modtwoauth_webauthn_rp_id'] = 'WebAuthn RP ID';
$_lang['setting_modtwoauth_webauthn_rp_id_desc'] = 'RP ID facultatif. En général, laissez vide — le domaine de site_url sera utilisé.';

$_lang['setting_modtwoauth_frontend_enable_2fa'] = 'Activer la 2FA sur le site';
$_lang['setting_modtwoauth_frontend_enable_2fa_desc'] = 'Permet aux utilisateurs du site de configurer eux-mêmes la 2FA et exige le second facteur pour les utilisateurs qui l\'ont activée.';

$_lang['setting_modtwoauth_verify_chunk_id'] = 'ID du chunk de la page 2FA du frontend';
$_lang['setting_modtwoauth_verify_chunk_id_desc'] = 'ID d\'un chunk personnalisé pour la page de vérification du frontend. Si 0 ou si le chunk est introuvable, modtwoauthVerifyTpl est utilisé. Le Manager utilise toujours le modtwoauthVerifyTpl natif.';
$_lang['setting_modtwoauth_verify_url'] = 'URL de la page de vérification 2FA du Manager';
$_lang['setting_modtwoauth_verify_url_desc'] = 'URL locale de la page de vérification 2FA, /2fa par défaut. L\'adresse indiquée est utilisée à la connexion au Manager ; le serveur web doit la router via MODX. Pour la valeur /2fa avec les URL simplifiées désactivées, assets/components/modtwoauth/verify.php est utilisé.';
$_lang['setting_modtwoauth_web_verify_url'] = 'URL de la page de vérification 2FA Web';
$_lang['setting_modtwoauth_web_verify_url_desc'] = 'URL locale de la page de vérification 2FA, /2fa-web par défaut. L\'adresse indiquée est utilisée à la connexion au contexte Web ; le serveur web doit la router via MODX. Pour la valeur /2fa-web avec les URL simplifiées désactivées, assets/components/modtwoauth/verify.php est utilisé.';

$_lang['setting_modtwoauth_key_path'] = 'Chemin de la clé de chiffrement TOTP';
$_lang['setting_modtwoauth_key_path_desc'] = 'Chemin absolu vers le fichier de clé en dehors du répertoire public du site. Laissez vide pour core/config/modtwoauth.key. Si vous modifiez le chemin après l\'installation, déplacez la clé existante vers le nouveau chemin ; ne créez pas de nouvelle clé pour des secrets déjà chiffrés.';
$_lang['setting_modtwoauth_csp_report_only'] = 'CSP : activer le mode rapport';
$_lang['setting_modtwoauth_csp_policy'] = 'CSP : politique (mode rapport)';
$_lang['setting_modtwoauth_csp_report_only_desc'] = 'Ajoute Content-Security-Policy-Report-Only sans bloquer les ressources.';
$_lang['setting_modtwoauth_csp_policy_desc'] = 'Vérifiez le site et le Manager avant d\'utiliser la politique en mode bloquant.';
