<?php

$_lang['area_modtwoauth_frontend'] = 'Фронтенд сайту';
$_lang['area_modtwoauth_main'] = 'Основні';
$_lang['area_modtwoauth_security'] = 'Безпека';
$_lang['area_modtwoauth_telegram'] = 'Telegram';
$_lang['area_modtwoauth_email'] = 'Email';
$_lang['area_modtwoauth_webauthn'] = 'WebAuthn / FIDO2';
$_lang['area_modtwoauth_verify'] = 'Сторінка підтвердження 2FA';

$_lang['setting_modtwoauth_enable_2fa'] = 'Увімкнути 2FA';
$_lang['setting_modtwoauth_enable_2fa_desc'] = 'Увімкнує двофакторну автентифікацію для користувачів, у яких вона налаштована і включена.';
$_lang['setting_modtwoauth_code_lifetime'] = 'Строк дії коду';
$_lang['setting_modtwoauth_code_lifetime_desc'] = 'Скільки секунд діє код підтвердження. Мінімум 30 секунд.';
$_lang['setting_modtwoauth_max_attempts'] = 'Максимум спроб 2FA';
$_lang['setting_modtwoauth_max_attempts_desc'] = 'Кількість невірних кодів до тимчасового блокування. Блокування спрацьовує на вказаній за рахунком спробі. Мінімум 1, за замовчуванням 3.';
$_lang['setting_modtwoauth_lock_minutes'] = 'Час блокування';
$_lang['setting_modtwoauth_lock_minutes_desc'] = 'Тривалість блокування в хвилинах після досягнення ліміту невірних кодів. Мінімум 1, за замовчуванням 15.';
$_lang['setting_modtwoauth_session_max_age'] = 'Строк сесії 2FA';
$_lang['setting_modtwoauth_session_max_age_desc'] = 'Максимальний вік підтвердженої сесії 2FA в секундах. 0 — без обмеження. Після завершення строку сесію буде припинено і знадобиться повторний вхід із 2FA.';
$_lang['setting_modtwoauth_session_gc_interval'] = 'Інтервал очищення сесій';
$_lang['setting_modtwoauth_session_gc_interval_desc'] = 'Як часто (у секундах) фонове очищення видаляє записи про сесії, чиї PHP-сесії більше не існують (після «Завершити всі сеанси», збирання сміття сесій PHP, закритих браузерів). 0 — вимкнути фонове очищення; списки сесій у будь-якому разі перевіряють записи під час відкриття.';
$_lang['setting_modtwoauth_resend_cooldown'] = 'Пауза між надсиланнями коду';
$_lang['setting_modtwoauth_resend_cooldown_desc'] = 'Мінімальний інтервал у секундах між повторними надсиланнями email-коду. Захищає від поштового спаму.';
$_lang['setting_modtwoauth_telegram_bot_token'] = 'Telegram Bot Token';
$_lang['setting_modtwoauth_telegram_bot_token_desc'] = 'Токен бота Telegram, отриманий через BotFather.';
$_lang['setting_modtwoauth_email_from_name'] = 'Ім\'я відправника Email';
$_lang['setting_modtwoauth_email_from_name_desc'] = 'Ім\'я, що відображається в листах із кодами 2FA. Якщо порожньо, використовується site_name.';
$_lang['setting_modtwoauth_email_from_address'] = 'Адреса відправника Email';
$_lang['setting_modtwoauth_email_from_address_desc'] = 'Email відправника кодів 2FA. Якщо порожньо, використовується emailsender.';

$_lang['setting_modtwoauth_webauthn_rp_id'] = 'WebAuthn RP ID';
$_lang['setting_modtwoauth_webauthn_rp_id_desc'] = 'Необов\'язковий RP ID. Зазвичай залиште порожнім — буде використано домен із site_url.';

$_lang['setting_modtwoauth_frontend_enable_2fa'] = 'Увімкнути 2FA на сайті';
$_lang['setting_modtwoauth_frontend_enable_2fa_desc'] = 'Дозволяє користувачам сайту самостійно налаштовувати 2FA і вимагає другий фактор у користувачів, які його увімкнули.';

$_lang['setting_modtwoauth_verify_chunk_id'] = 'ID чанка сторінки 2FA на фронтенді';
$_lang['setting_modtwoauth_verify_chunk_id_desc'] = 'ID користувацького чанка для сторінки підтвердження на фронтенді. Якщо 0 або чанк не знайдено, використовується modtwoauthVerifyTpl. У Manager завжди використовується штатний modtwoauthVerifyTpl.';
$_lang['setting_modtwoauth_verify_url'] = 'URL сторінки підтвердження 2FA Manager';
$_lang['setting_modtwoauth_verify_url_desc'] = 'Локальний URL сторінки підтвердження 2FA, за замовчуванням /2fa. Вказана адреса використовується під час входу в Manager; веб-сервер має спрямовувати її через MODX. Для значення /2fa при вимкнених ЧПУ використовується assets/components/modtwoauth/verify.php.';
$_lang['setting_modtwoauth_web_verify_url'] = 'URL сторінки підтвердження 2FA Web';
$_lang['setting_modtwoauth_web_verify_url_desc'] = 'Локальний URL сторінки підтвердження 2FA, за замовчуванням /2fa-web. Вказана адреса використовується під час входу в Web; веб-сервер має спрямовувати її через MODX. Для значення /2fa-web при вимкнених ЧПУ використовується assets/components/modtwoauth/verify.php.';

$_lang['setting_modtwoauth_key_path'] = 'Шлях до ключа шифрування TOTP';
$_lang['setting_modtwoauth_key_path_desc'] = 'Абсолютний шлях до файлу ключа поза публічною директорією сайту. Залиште порожнім для core/config/modtwoauth.key. При зміні шляху після встановлення перенесіть наявний ключ у новий шлях; не створюйте новий ключ для вже зашифрованих секретів.';
$_lang['setting_modtwoauth_csp_report_only'] = 'CSP: увімкнути режим звітності';
$_lang['setting_modtwoauth_csp_policy'] = 'CSP: політика (режим звітності)';
$_lang['setting_modtwoauth_csp_report_only_desc'] = 'Додає Content-Security-Policy-Report-Only, не блокуючи ресурси.';
$_lang['setting_modtwoauth_csp_policy_desc'] = 'Перевіряйте сайт і Manager перед використанням політики в блокувальному режимі.';
