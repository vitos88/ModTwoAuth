<?php

// Verification page labels and messages
$_lang['modtwoauth_verify_title'] = 'Zwei-Faktor-Authentifizierung';
$_lang['modtwoauth_verify_heading'] = '🔐 Zwei-Faktor-Authentifizierung';
$_lang['modtwoauth_verify_hint_default'] = 'Geben Sie den Bestätigungscode ein.';
$_lang['modtwoauth_verify_hint_totp'] = 'Öffnen Sie Google Authenticator und geben Sie den aktuellen 6-stelligen Code ein.';
$_lang['modtwoauth_verify_hint_email'] = 'Wir haben einen 6-stelligen Code gesendet an';
$_lang['modtwoauth_verify_hint_telegram'] = 'Bestätigen Sie die Anmeldung in Telegram oder geben Sie den 6-stelligen Code aus der Bot-Nachricht ein.';
$_lang['modtwoauth_verify_hint_webauthn'] = 'Verwenden Sie einen Passkey, Touch ID, Face ID oder einen FIDO2-Schlüssel, um die Anmeldung zu bestätigen.';
$_lang['modtwoauth_verify_back'] = '← Zurück zur normalen Anmeldung';
$_lang['modtwoauth_verify_code_placeholder'] = '6-stelliger Code';
$_lang['modtwoauth_verify_submit'] = 'Code bestätigen';
$_lang['modtwoauth_verify_passkey'] = '🔐 Mit Passkey / FIDO2 anmelden';
$_lang['modtwoauth_verify_wait_device'] = '⏳ Wir warten auf die Bestätigung auf Ihrem Gerät...';
$_lang['modtwoauth_verify_wait_telegram'] = '⏳ Wir warten auf die Bestätigung in Telegram...';
$_lang['modtwoauth_verify_invalid_code'] = 'Falscher Bestätigungscode.';
$_lang['modtwoauth_verify_locked'] = 'Zu viele fehlgeschlagene Versuche. Der Zugriff ist gesperrt.';
$_lang['modtwoauth_verify_locked_retry'] = 'Zu viele fehlgeschlagene Versuche. Versuchen Sie es in [[+time]] erneut.';
$_lang['modtwoauth_verify_attempts_left'] = 'Falscher Bestätigungscode. Verbleibende Versuche: [[+left]].';
$_lang['modtwoauth_verify_back_to_login'] = 'Zurück zur Anmeldung';
