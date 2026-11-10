<?php

$_lang['area_modtwoauth_frontend'] = 'Website-Frontend';
$_lang['area_modtwoauth_main'] = 'Allgemein';
$_lang['area_modtwoauth_security'] = 'Sicherheit';
$_lang['area_modtwoauth_telegram'] = 'Telegram';
$_lang['area_modtwoauth_email'] = 'E-Mail';
$_lang['area_modtwoauth_webauthn'] = 'WebAuthn / FIDO2';
$_lang['area_modtwoauth_verify'] = '2FA-Bestätigungsseite';

$_lang['setting_modtwoauth_enable_2fa'] = '2FA aktivieren';
$_lang['setting_modtwoauth_enable_2fa_desc'] = 'Aktiviert die Zwei-Faktor-Authentifizierung für Benutzer, die sie eingerichtet und aktiviert haben.';
$_lang['setting_modtwoauth_code_lifetime'] = 'Gültigkeitsdauer des Codes';
$_lang['setting_modtwoauth_code_lifetime_desc'] = 'Wie viele Sekunden ein Bestätigungscode gültig ist. Minimum 30 Sekunden.';
$_lang['setting_modtwoauth_max_attempts'] = 'Maximale 2FA-Versuche';
$_lang['setting_modtwoauth_max_attempts_desc'] = 'Anzahl falscher Codes bis zur temporären Sperre. Die Sperre greift beim angegebenen Versuch. Minimum 1, Standard 3.';
$_lang['setting_modtwoauth_lock_minutes'] = 'Sperrdauer';
$_lang['setting_modtwoauth_lock_minutes_desc'] = 'Dauer der Sperre in Minuten nach Erreichen des Limits falscher Codes. Minimum 1, Standard 15.';
$_lang['setting_modtwoauth_session_max_age'] = 'Lebensdauer der 2FA-Sitzung';
$_lang['setting_modtwoauth_session_max_age_desc'] = 'Maximales Alter einer bestätigten 2FA-Sitzung in Sekunden. 0 bedeutet keine Begrenzung. Nach Ablauf wird die Sitzung beendet und eine erneute Anmeldung mit 2FA ist erforderlich.';
$_lang['setting_modtwoauth_session_gc_interval'] = 'Intervall der Sitzungsbereinigung';
$_lang['setting_modtwoauth_session_gc_interval_desc'] = 'Wie oft (in Sekunden) die Hintergrundbereinigung Sitzungseinträge entfernt, deren PHP-Sitzungen nicht mehr existieren (nach „Alle Sitzungen beenden“, dem PHP-Session-GC oder geschlossenen Browsern). 0 deaktiviert die Hintergrundbereinigung; Sitzungslisten prüfen Einträge weiterhin beim Öffnen.';
$_lang['setting_modtwoauth_resend_cooldown'] = 'Wartezeit zwischen Code-Sendungen';
$_lang['setting_modtwoauth_resend_cooldown_desc'] = 'Mindestabstand in Sekunden zwischen erneuten Sendungen des E-Mail-Codes. Schützt vor Mail-Spam.';
$_lang['setting_modtwoauth_telegram_bot_token'] = 'Telegram Bot Token';
$_lang['setting_modtwoauth_telegram_bot_token_desc'] = 'Das Token des Telegram-Bots, über BotFather erhalten.';
$_lang['setting_modtwoauth_email_from_name'] = 'E-Mail-Absendername';
$_lang['setting_modtwoauth_email_from_name_desc'] = 'Der Name, der in E-Mails mit 2FA-Codes angezeigt wird. Wenn leer, wird site_name verwendet.';
$_lang['setting_modtwoauth_email_from_address'] = 'E-Mail-Absenderadresse';
$_lang['setting_modtwoauth_email_from_address_desc'] = 'Die Absender-E-Mail-Adresse für 2FA-Codes. Wenn leer, wird emailsender verwendet.';

$_lang['setting_modtwoauth_webauthn_rp_id'] = 'WebAuthn RP ID';
$_lang['setting_modtwoauth_webauthn_rp_id_desc'] = 'Optionale RP ID. Meist leer lassen — es wird die Domain aus site_url verwendet.';

$_lang['setting_modtwoauth_frontend_enable_2fa'] = '2FA auf der Website aktivieren';
$_lang['setting_modtwoauth_frontend_enable_2fa_desc'] = 'Erlaubt Website-Benutzern, 2FA selbst einzurichten, und verlangt den zweiten Faktor von Benutzern, die ihn aktiviert haben.';

$_lang['setting_modtwoauth_verify_chunk_id'] = 'Chunk-ID der 2FA-Seite im Frontend';
$_lang['setting_modtwoauth_verify_chunk_id_desc'] = 'ID eines benutzerdefinierten Chunks für die Bestätigungsseite im Frontend. Bei 0 oder wenn der Chunk nicht gefunden wird, wird modtwoauthVerifyTpl verwendet. Der Manager verwendet immer das integrierte modtwoauthVerifyTpl.';
$_lang['setting_modtwoauth_verify_url'] = 'URL der 2FA-Bestätigungsseite im Manager';
$_lang['setting_modtwoauth_verify_url_desc'] = 'Lokale URL der 2FA-Bestätigungsseite, standardmäßig /2fa. Die angegebene Adresse wird bei der Anmeldung im Manager verwendet; der Webserver muss sie über MODX leiten. Für den Wert /2fa wird bei deaktivierten friendly URLs assets/components/modtwoauth/verify.php verwendet.';
$_lang['setting_modtwoauth_web_verify_url'] = 'URL der 2FA-Bestätigungsseite im Web';
$_lang['setting_modtwoauth_web_verify_url_desc'] = 'Lokale URL der 2FA-Bestätigungsseite, standardmäßig /2fa-web. Die angegebene Adresse wird bei der Anmeldung im Web verwendet; der Webserver muss sie über MODX leiten. Für den Wert /2fa-web wird bei deaktivierten friendly URLs assets/components/modtwoauth/verify.php verwendet.';

$_lang['setting_modtwoauth_key_path'] = 'Pfad zum TOTP-Verschlüsselungsschlüssel';
$_lang['setting_modtwoauth_key_path_desc'] = 'Absoluter Pfad zur Schlüsseldatei außerhalb des öffentlichen Verzeichnisses der Website. Leer lassen für core/config/modtwoauth.key. Wenn Sie den Pfad nach der Installation ändern, verschieben Sie den vorhandenen Schlüssel zum neuen Pfad; erstellen Sie keinen neuen Schlüssel für bereits verschlüsselte Geheimnisse.';
$_lang['setting_modtwoauth_csp_report_only'] = 'CSP: Report-Only-Modus aktivieren';
$_lang['setting_modtwoauth_csp_policy'] = 'CSP: Richtlinie (Report-Only-Modus)';
$_lang['setting_modtwoauth_csp_report_only_desc'] = 'Fügt Content-Security-Policy-Report-Only hinzu, ohne Ressourcen zu blockieren.';
$_lang['setting_modtwoauth_csp_policy_desc'] = 'Prüfen Sie Website und Manager, bevor Sie die Richtlinie im blockierenden Modus verwenden.';
