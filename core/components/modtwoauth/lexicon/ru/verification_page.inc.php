<?php

// Verification page labels and messages
$_lang['modtwoauth_verify_title'] = 'Двухфакторная аутентификация';
$_lang['modtwoauth_verify_heading'] = '🔐 Двухфакторная аутентификация';
$_lang['modtwoauth_verify_hint_default'] = 'Введите код подтверждения.';
$_lang['modtwoauth_verify_hint_totp'] = 'Откройте Google Authenticator и введите текущий 6-значный код.';
$_lang['modtwoauth_verify_hint_email'] = 'Мы отправили 6-значный код на';
$_lang['modtwoauth_verify_hint_telegram'] = 'Подтвердите вход в Telegram или введите 6-значный код из сообщения бота.';
$_lang['modtwoauth_verify_hint_webauthn'] = 'Используйте Passkey, Touch ID, Face ID или FIDO2-ключ для подтверждения входа.';
$_lang['modtwoauth_verify_back'] = '← Вернуться к обычному входу';
$_lang['modtwoauth_verify_code_placeholder'] = 'Код из 6 цифр';
$_lang['modtwoauth_verify_submit'] = 'Подтвердить код';
$_lang['modtwoauth_verify_passkey'] = '🔐 Войти с помощью Passkey / FIDO2';
$_lang['modtwoauth_verify_wait_device'] = '⏳ Ожидаем подтверждение на устройстве...';
$_lang['modtwoauth_verify_wait_telegram'] = '⏳ Ожидаем подтверждение в Telegram...';
$_lang['modtwoauth_verify_invalid_code'] = 'Неверный код подтверждения.';
$_lang['modtwoauth_verify_locked'] = 'Слишком много неверных попыток. Доступ заблокирован.';
$_lang['modtwoauth_verify_locked_retry'] = 'Слишком много неверных попыток. Попробуйте снова через [[+time]].';
$_lang['modtwoauth_verify_attempts_left'] = 'Неверный код подтверждения. Осталось попыток: [[+left]].';
$_lang['modtwoauth_verify_back_to_login'] = 'Вернуться к входу';