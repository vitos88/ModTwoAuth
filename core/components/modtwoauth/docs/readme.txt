ModTwoAuth
==============

## 🇬🇧 English
----------------

###Frontend snippets

```[[!ModTwoAuthFrontend]]```

⋅⋅⋅2FA personal account: selecting and configuring methods, enabling/disabling (with password confirmation).

⋅⋅⋅The **tpl** parameter sets the ID or name of the template chunk. An invalid value falls back to the default modtwoauthFrontendSettingsTpl. The **methods** parameter sets a comma-separated list of methods: totp,email,telegram,webauthn. empty means all methods.

```[[!ModTwoAuthSessions]]```

⋅⋅⋅A list of the user's active sessions with a revoke button.

⋅⋅⋅The **tpl** parameter sets the ID or name of the template chunk (fallback — modtwoauthSessionsTpl). The **showCurrent** parameter with value 0 hides the current session from the list.

Both snippets are available only to authorized users in any web context.

----------------
## 🇷🇺 Русский

###Сниппеты фронтенда

```[[!ModTwoAuthFrontend]]```

⋅⋅⋅Личный кабинет 2FA: выбор и настройка методов, включение и отключение (с подтверждением паролем).

⋅⋅⋅Параметр **tpl** задаёт ID или имя чанка-шаблона. Неверное значение откатывается к штатному modtwoauthFrontendSettingsTpl. Параметр **methods** задаёт список методов через запятую: totp,email,telegram,webauthn. Значение пусто означает все методы.

```[[!ModTwoAuthSessions]]```

⋅⋅⋅Список активных сессий пользователя с кнопкой отзыва.

⋅⋅⋅Параметр **tpl** задаёт ID или имя чанка-шаблона (по умолчанию — modtwoauthSessionsTpl). Параметр **showCurrent** со значением 0 скрывает текущую сессию из списка.

Оба сниппета доступны только авторизованным в любом веб-контексте пользователям.