<?php

// Verification page labels and messages
$_lang['modtwoauth_verify_title'] = 'Autenticación de dos factores';
$_lang['modtwoauth_verify_heading'] = '🔐 Autenticación de dos factores';
$_lang['modtwoauth_verify_hint_default'] = 'Introduzca el código de verificación.';
$_lang['modtwoauth_verify_hint_totp'] = 'Abra Google Authenticator e introduzca el código de 6 dígitos actual.';
$_lang['modtwoauth_verify_hint_email'] = 'Hemos enviado un código de 6 dígitos a';
$_lang['modtwoauth_verify_hint_telegram'] = 'Confirme el inicio de sesión en Telegram o introduzca el código de 6 dígitos del mensaje del bot.';
$_lang['modtwoauth_verify_hint_webauthn'] = 'Use un Passkey, Touch ID, Face ID o una llave FIDO2 para confirmar el inicio de sesión.';
$_lang['modtwoauth_verify_back'] = '← Volver al inicio de sesión habitual';
$_lang['modtwoauth_verify_code_placeholder'] = 'Código de 6 dígitos';
$_lang['modtwoauth_verify_submit'] = 'Confirmar código';
$_lang['modtwoauth_verify_passkey'] = '🔐 Iniciar sesión con Passkey / FIDO2';
$_lang['modtwoauth_verify_wait_device'] = '⏳ Esperando la confirmación en su dispositivo...';
$_lang['modtwoauth_verify_wait_telegram'] = '⏳ Esperando la confirmación en Telegram...';
$_lang['modtwoauth_verify_invalid_code'] = 'Código de verificación incorrecto.';
$_lang['modtwoauth_verify_locked'] = 'Demasiados intentos incorrectos. Acceso bloqueado.';
$_lang['modtwoauth_verify_locked_retry'] = 'Demasiados intentos incorrectos. Inténtelo de nuevo en [[+time]].';
$_lang['modtwoauth_verify_attempts_left'] = 'Código de verificación incorrecto. Intentos restantes: [[+left]].';
$_lang['modtwoauth_verify_back_to_login'] = 'Volver al inicio de sesión';
