<?php

include_once 'telegram.inc.php';
include_once 'frontend.inc.php';
include_once 'verification_page.inc.php';

$_lang['modtwoauth'] = 'ModTwoAuth';

$_lang['modtwoauth_email_subject'] = 'Código de verificación de inicio de sesión';
$_lang['modtwoauth_email_body'] = '<p>Su código de verificación: <strong>[[+code]]</strong></p><p>El código es válido durante [[+minutes]] min. Si no solicitó un código, simplemente ignore este correo.</p>';
