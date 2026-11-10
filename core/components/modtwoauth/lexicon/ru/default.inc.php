<?php

include_once 'telegram.inc.php';
include_once 'frontend.inc.php';
include_once 'verification_page.inc.php';

$_lang['modtwoauth'] = 'ModTwoAuth';

$_lang['modtwoauth_email_subject'] = 'Код подтверждения входа';
$_lang['modtwoauth_email_body'] = '<p>Ваш код подтверждения: <strong>[[+code]]</strong></p><p>Код действует [[+minutes]] мин. Если вы не запрашивали код, просто проигнорируйте это письмо.</p>';

