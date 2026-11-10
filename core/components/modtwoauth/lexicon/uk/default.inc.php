<?php

include_once 'telegram.inc.php';
include_once 'frontend.inc.php';
include_once 'verification_page.inc.php';

$_lang['modtwoauth'] = 'ModTwoAuth';

$_lang['modtwoauth_email_subject'] = 'Код підтвердження входу';
$_lang['modtwoauth_email_body'] = '<p>Ваш код підтвердження: <strong>[[+code]]</strong></p><p>Код діє [[+minutes]] хв. Якщо ви не запитували код, просто проігноруйте цей лист.</p>';
