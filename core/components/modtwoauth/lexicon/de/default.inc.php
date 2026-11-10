<?php

include_once 'telegram.inc.php';
include_once 'frontend.inc.php';
include_once 'verification_page.inc.php';

$_lang['modtwoauth'] = 'ModTwoAuth';

$_lang['modtwoauth_email_subject'] = 'Bestätigungscode für die Anmeldung';
$_lang['modtwoauth_email_body'] = '<p>Ihr Bestätigungscode: <strong>[[+code]]</strong></p><p>Der Code ist [[+minutes]] Min. gültig. Wenn Sie keinen Code angefordert haben, ignorieren Sie diese E-Mail einfach.</p>';
