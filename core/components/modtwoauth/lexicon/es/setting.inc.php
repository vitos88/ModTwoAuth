<?php

$_lang['area_modtwoauth_frontend'] = 'Frontend del sitio';
$_lang['area_modtwoauth_main'] = 'Principal';
$_lang['area_modtwoauth_security'] = 'Seguridad';
$_lang['area_modtwoauth_telegram'] = 'Telegram';
$_lang['area_modtwoauth_email'] = 'Email';
$_lang['area_modtwoauth_webauthn'] = 'WebAuthn / FIDO2';
$_lang['area_modtwoauth_verify'] = 'Página de verificación 2FA';

$_lang['setting_modtwoauth_enable_2fa'] = 'Activar 2FA';
$_lang['setting_modtwoauth_enable_2fa_desc'] = 'Activa la autenticación de dos factores para los usuarios que la tienen configurada y activada.';
$_lang['setting_modtwoauth_code_lifetime'] = 'Duración del código';
$_lang['setting_modtwoauth_code_lifetime_desc'] = 'Cuántos segundos es válido el código de verificación. Mínimo 30 segundos.';
$_lang['setting_modtwoauth_max_attempts'] = 'Máximo de intentos 2FA';
$_lang['setting_modtwoauth_max_attempts_desc'] = 'Número de códigos incorrectos antes del bloqueo temporal. El bloqueo se activa en el intento indicado. Mínimo 1, por defecto 3.';
$_lang['setting_modtwoauth_lock_minutes'] = 'Tiempo de bloqueo';
$_lang['setting_modtwoauth_lock_minutes_desc'] = 'Duración del bloqueo en minutos tras alcanzar el límite de códigos incorrectos. Mínimo 1, por defecto 15.';
$_lang['setting_modtwoauth_session_max_age'] = 'Duración de la sesión 2FA';
$_lang['setting_modtwoauth_session_max_age_desc'] = 'Edad máxima de una sesión 2FA verificada, en segundos. 0 significa sin límite. Al expirar, la sesión se cierra y será necesario iniciar sesión con 2FA de nuevo.';
$_lang['setting_modtwoauth_session_gc_interval'] = 'Intervalo de limpieza de sesiones';
$_lang['setting_modtwoauth_session_gc_interval_desc'] = 'Con qué frecuencia (en segundos) la limpieza en segundo plano elimina los registros de sesiones cuyas sesiones PHP ya no existen (tras «Terminar todas las sesiones», el GC de sesiones de PHP o navegadores cerrados). 0 desactiva la limpieza en segundo plano; las listas de sesiones siguen verificando los registros al abrirse.';
$_lang['setting_modtwoauth_resend_cooldown'] = 'Pausa entre envíos de código';
$_lang['setting_modtwoauth_resend_cooldown_desc'] = 'Intervalo mínimo en segundos entre reenvíos del código por email. Protege contra el spam de correo.';
$_lang['setting_modtwoauth_telegram_bot_token'] = 'Telegram Bot Token';
$_lang['setting_modtwoauth_telegram_bot_token_desc'] = 'Token del bot de Telegram obtenido mediante BotFather.';
$_lang['setting_modtwoauth_email_from_name'] = 'Nombre del remitente del Email';
$_lang['setting_modtwoauth_email_from_name_desc'] = 'Nombre que se muestra en los correos con códigos 2FA. Si está vacío, se usa site_name.';
$_lang['setting_modtwoauth_email_from_address'] = 'Dirección del remitente del Email';
$_lang['setting_modtwoauth_email_from_address_desc'] = 'Email del remitente de los códigos 2FA. Si está vacío, se usa emailsender.';

$_lang['setting_modtwoauth_webauthn_rp_id'] = 'WebAuthn RP ID';
$_lang['setting_modtwoauth_webauthn_rp_id_desc'] = 'RP ID opcional. Normalmente déjelo vacío: se usará el dominio de site_url.';

$_lang['setting_modtwoauth_frontend_enable_2fa'] = 'Activar 2FA en el sitio';
$_lang['setting_modtwoauth_frontend_enable_2fa_desc'] = 'Permite a los usuarios del sitio configurar la 2FA por su cuenta y exige el segundo factor a los usuarios que la hayan activado.';

$_lang['setting_modtwoauth_verify_chunk_id'] = 'ID del chunk de la página 2FA del frontend';
$_lang['setting_modtwoauth_verify_chunk_id_desc'] = 'ID de un chunk personalizado para la página de verificación del frontend. Si es 0 o el chunk no se encuentra, se usa modtwoauthVerifyTpl. El Manager siempre usa el modtwoauthVerifyTpl nativo.';
$_lang['setting_modtwoauth_verify_url'] = 'URL de la página de verificación 2FA del Manager';
$_lang['setting_modtwoauth_verify_url_desc'] = 'URL local de la página de verificación 2FA, /2fa por defecto. La dirección indicada se usa al iniciar sesión en el Manager; el servidor web debe dirigirla a través de MODX. Para el valor /2fa con URLs amigables desactivadas, se usa assets/components/modtwoauth/verify.php.';
$_lang['setting_modtwoauth_web_verify_url'] = 'URL de la página de verificación 2FA Web';
$_lang['setting_modtwoauth_web_verify_url_desc'] = 'URL local de la página de verificación 2FA, /2fa-web por defecto. La dirección indicada se usa al iniciar sesión en el contexto Web; el servidor web debe dirigirla a través de MODX. Para el valor /2fa-web con URLs amigables desactivadas, se usa assets/components/modtwoauth/verify.php.';

$_lang['setting_modtwoauth_key_path'] = 'Ruta de la clave de cifrado TOTP';
$_lang['setting_modtwoauth_key_path_desc'] = 'Ruta absoluta al archivo de clave fuera del directorio público del sitio. Déjelo vacío para core/config/modtwoauth.key. Si cambia la ruta después de la instalación, mueva la clave existente a la nueva ruta; no cree una clave nueva para secretos ya cifrados.';
$_lang['setting_modtwoauth_csp_report_only'] = 'CSP: activar el modo de informes';
$_lang['setting_modtwoauth_csp_policy'] = 'CSP: política (modo de informes)';
$_lang['setting_modtwoauth_csp_report_only_desc'] = 'Añade Content-Security-Policy-Report-Only sin bloquear recursos.';
$_lang['setting_modtwoauth_csp_policy_desc'] = 'Compruebe el sitio y el Manager antes de usar la política en modo de bloqueo.';
