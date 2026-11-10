<?php

include_once 'telegram.inc.php';
include_once 'frontend.inc.php';
include_once 'verification_page.inc.php';

$_lang['modtwoauth'] = 'ModTwoAuth';

$_lang['modtwoauth_email_subject'] = 'Code de vérification de connexion';
$_lang['modtwoauth_email_body'] = '<p>Votre code de vérification : <strong>[[+code]]</strong></p><p>Le code est valable [[+minutes]] min. Si vous n\'avez pas demandé de code, ignorez simplement cet email.</p>';
