<?php

$_lang['area_modtwoauth_frontend'] = 'Фронтенд сайта';
$_lang['area_modtwoauth_main'] = 'Основные';
$_lang['area_modtwoauth_security'] = 'Безопасность';
$_lang['area_modtwoauth_telegram'] = 'Telegram';
$_lang['area_modtwoauth_email'] = 'Email';
$_lang['area_modtwoauth_webauthn'] = 'WebAuthn / FIDO2';
$_lang['area_modtwoauth_verify'] = 'Страница подтверждения 2FA';

$_lang['setting_modtwoauth_enable_2fa'] = 'Включить 2FA';
$_lang['setting_modtwoauth_enable_2fa_desc'] = 'Включает двухфакторную аутентификацию для пользователей, у которых она настроена и включена.';
$_lang['setting_modtwoauth_code_lifetime'] = 'Срок действия кода';
$_lang['setting_modtwoauth_code_lifetime_desc'] = 'Сколько секунд действует код подтверждения. Минимум 30 секунд.';
$_lang['setting_modtwoauth_max_attempts'] = 'Максимум попыток 2FA';
$_lang['setting_modtwoauth_max_attempts_desc'] = 'Количество неверных кодов до временной блокировки. Блокировка срабатывает на указанной по счёту попытке. Минимум 1, по умолчанию 3.';
$_lang['setting_modtwoauth_lock_minutes'] = 'Время блокировки';
$_lang['setting_modtwoauth_lock_minutes_desc'] = 'Длительность блокировки в минутах после достижения лимита неверных кодов. Минимум 1, по умолчанию 15.';
$_lang['setting_modtwoauth_session_max_age'] = 'Срок сессии 2FA';
$_lang['setting_modtwoauth_session_max_age_desc'] = 'Максимальный возраст подтвержденной 2FA-сессии в секундах. 0 — без ограничения. По истечении сессия завершается и потребуется повторный вход с 2FA.';
$_lang['setting_modtwoauth_session_gc_interval'] = 'Интервал очистки сессий';
$_lang['setting_modtwoauth_session_gc_interval_desc'] = 'Как часто (в секундах) фоновая очистка удаляет записи о сессиях, чьи PHP-сессии уже не существуют (после «Завершить все сеансы», сборки мусора сессий PHP, закрытых браузеров). 0 — отключить фоновую очистку; списки сессий в любом случае проверяют записи при открытии.';
$_lang['setting_modtwoauth_resend_cooldown'] = 'Пауза между отправками кода';
$_lang['setting_modtwoauth_resend_cooldown_desc'] = 'Минимальный интервал в секундах между повторными отправками email-кода. Защищает от почтового спама.';
$_lang['setting_modtwoauth_telegram_bot_token'] = 'Telegram Bot Token';
$_lang['setting_modtwoauth_telegram_bot_token_desc'] = 'Токен бота Telegram, полученный через BotFather.';
$_lang['setting_modtwoauth_email_from_name'] = 'Имя отправителя Email';
$_lang['setting_modtwoauth_email_from_name_desc'] = 'Имя, отображаемое в письмах с кодами 2FA. Если пусто, используется site_name.';
$_lang['setting_modtwoauth_email_from_address'] = 'Адрес отправителя Email';
$_lang['setting_modtwoauth_email_from_address_desc'] = 'Email отправителя кодов 2FA. Если пусто, используется emailsender.';

$_lang['setting_modtwoauth_webauthn_rp_id'] = 'WebAuthn RP ID';
$_lang['setting_modtwoauth_webauthn_rp_id_desc'] = 'Необязательный RP ID. Обычно оставьте пустым — будет использован домен из site_url.';

$_lang['setting_modtwoauth_frontend_enable_2fa'] = 'Включить 2FA на сайте';
$_lang['setting_modtwoauth_frontend_enable_2fa_desc'] = 'Разрешает пользователям сайта самостоятельно настраивать 2FA и требует второй фактор у пользователей, которые его включили.';

$_lang['setting_modtwoauth_verify_chunk_id'] = 'ID чанка страницы 2FA на фронтенде';
$_lang['setting_modtwoauth_verify_chunk_id_desc'] = 'ID пользовательского чанка для страницы подтверждения на фронтенде. Если 0 или чанк не найден, используется modtwoauthVerifyTpl. В Manager всегда используется штатный modtwoauthVerifyTpl.';
$_lang['setting_modtwoauth_verify_url'] = 'URL страницы подтверждения 2FA Manager';
$_lang['setting_modtwoauth_verify_url_desc'] = 'Локальный URL страницы подтверждения 2FA, по умолчанию /2fa. Указанный адрес используется при входе в Manager; веб-сервер должен направлять его через MODX. Для значения /2fa при отключённых ЧПУ используется assets/components/modtwoauth/verify.php.';
$_lang['setting_modtwoauth_web_verify_url'] = 'URL страницы подтверждения 2FA Web';
$_lang['setting_modtwoauth_web_verify_url_desc'] = 'Локальный URL страницы подтверждения 2FA, по умолчанию /2fa-web. Указанный адрес используется при входе в Web; веб-сервер должен направлять его через MODX. Для значения /2fa-web при отключённых ЧПУ используется assets/components/modtwoauth/verify.php.';

$_lang['setting_modtwoauth_key_path'] = 'Путь к ключу шифрования TOTP';
$_lang['setting_modtwoauth_key_path_desc'] = 'Абсолютный путь к файлу ключа вне публичной директории сайта. Оставьте пустым для core/config/modtwoauth.key. При изменении пути после установки перенесите существующий ключ в новый путь; не создавайте новый ключ для уже зашифрованных секретов.';
$_lang['setting_modtwoauth_csp_report_only'] = 'CSP: включить режим отчётности';
$_lang['setting_modtwoauth_csp_policy'] = 'CSP: политика (режим отчётности)';
$_lang['setting_modtwoauth_csp_report_only_desc'] = 'Добавляет Content-Security-Policy-Report-Only, не блокируя ресурсы.';
$_lang['setting_modtwoauth_csp_policy_desc'] = 'Проверяйте сайт и Manager перед использованием политики в блокирующем режиме.';
