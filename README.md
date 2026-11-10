# ModTwoAuth

**Two-Factor Authentication (2FA) for MODX 3** - TOTP, Email, Telegram, and WebAuthn / FIDO2 / Passkeys, for the manager panel and for website users.

[![Version](https://img.shields.io/badge/version-1.0.0--pl-de1a68)](https://github.com/vitos88/ModTwoAuth)
[![MODX](https://img.shields.io/badge/MODX-3.x-green)](https://modx.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb3)](https://php.net/)
[![Security](https://img.shields.io/badge/security-audited-brightgreen)]()

[Русский &#127479;&#127482;](README.ru.md) | English

<p align="center">
    <picture>
      <img src="image/logo.png" alt="ModTwoAuth" width="900" height="514" />
    </picture>
</p>

---

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuring Methods](#configuring-methods)
  - [TOTP](#totp-authenticator-app)
  - [Email](#email)
  - [Telegram](#telegram)
  - [WebAuthn / Passkeys](#webauthn--fido2--passkeys)
- [Frontend Snippets](#frontend-snippets)
- [System Settings](#system-settings)
- [Code Emails](#code-emails)
- [Encryption Key](#encryption-key)
- [Sessions and Devices](#sessions-and-devices)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Licenses](#licenses)

---

## Features

- **TOTP** - compatible with Google Authenticator, Authy, Aegis, and any RFC 6238 app; ±1 step (30 s) drift allowed; an accepted code cannot be reused.
- **Email** - one-time 6-digit code sent to a verified address via MODX mail (SMTP), with a resend cooldown.
- **Telegram** - pairing via QR code / deep-link with a confirmation button in the bot; login with a 6-digit code from the bot **or** with a single "Approve Login" (or "Reject") button.
- **WebAuthn / FIDO2 / Passkeys** - Touch ID, Face ID, Windows Hello, hardware keys; ES256, RS256, Ed25519 algorithms; clone detection via the signature counter.
- **Manager and frontend** - independent 2FA enablement for the MODX manager and for website users (Login-compatible).
- **Brute-force protection** - 3 wrong codes block login for 15 minutes (configurable); state is stored in the database and survives restarts; the limit also applies when setting up a new method.
- **Secret encryption** - TOTP secrets are stored only encrypted (libsodium XChaCha20-Poly1305); the key lives outside the component.
- **Session hardening** - session ID regeneration after two-step login; list of active sessions for the last 30 days with per-session revocation; forced 2FA re-verification by session age.
- **QR with no intermediaries** - QR codes are generated locally in the browser by a built-in library (MIT); one-time tokens are never sent to external services.
- **CSRF and same-origin** - tokens on all verification forms, POST + Origin/Referer checks for state-changing operations.
- **Security diagnostics** - right on the component's main page in the manager (key, sodium, HTTPS, cookie flags, CSP, plugin status).

## Requirements

- MODX Revolution **3.x**
- PHP **8.1+** with the **sodium** extension (`ext-sodium` ships with PHP by default since 7.2)
- **HTTPS** for the site; mandatory for the Telegram webhook and WebAuthn (WebAuthn works over HTTP only on `localhost`)
- For the default `/2fa` and `/2fa-web` routes - friendly URLs enabled (otherwise direct addresses are used, see [Troubleshooting](#troubleshooting))

## Installation

### From the transport package

1. Take `modtwoauth-1.0.0-pl.transport.zip` from the [releases](https://github.com/vitos88/ModTwoAuth/releases).
2. In the manager, open **Extras → Package Management**, upload the package, and install it.
3. The installer will create the DB tables, generate the encryption key `core/config/modtwoauth.key` (permissions 0600), and automatically encrypt previously stored plaintext TOTP secrets.
4. Check the component page (**Extras → modtwoauth**) - it also shows the security diagnostics.

### Building from source

```bash
php _build/build.php
```

After the build, the transport package will appear in `core/packages/`. To download the zip from a browser on a dev server, open `http://dev.site/_build/build.php?download=1` (requires an authorized manager with sudo permission).

## Quick Start

### 2FA for managers

1. In **System Settings → ModTwoAuth**, enable `modtwoauth_enable_2fa`.
2. Each manager sets up a method for themselves: **Users → edit own account → "🔐 2FA" tab** - scan the QR, confirm an email code, link Telegram, or register a Passkey, then flip the "Enable 2FA" switch.
3. From the next login, MODX will request the second factor after the password. Managers always use the built-in verification page template (chunk `modtwoauthVerifyTpl`); the URL is changed via the `modtwoauth_verify_url` setting (default `/2fa`).

### 2FA for website users (frontend)

1. Enable `modtwoauth_frontend_enable_2fa` (enabled by default).
2. Place the snippets on a page available to authorized users (e.g., in the personal account):

```html
[[!ModTwoAuthFrontend]]
[[!ModTwoAuthSessions]]
```

3. Users set up methods themselves, enable 2FA (after login), and see the list of active sessions for the last 30 days; any other session can be terminated - it will be rejected on the next request. Disabling 2FA requires the current password.
4. The frontend verification page can be styled with your own chunk - set its ID in `modtwoauth_verify_chunk_id`. An empty or invalid value falls back to the built-in `modtwoauthVerifyTpl`; this setting does not affect the manager.

## Configuring Methods

### TOTP (authenticator app)

1. In the profile (2FA tab), select the TOTP method and click "Generate QR".
2. Scan the QR in an app or enter the secret manually.
3. Enter the current 6-digit code to confirm. After 3 wrong attempts the secret is invalidated - a new QR will be required.

The secret is 160-bit, stored in the DB only in encrypted form; reuse of an accepted code is impossible (the RFC 6238 §5.2 timestep is remembered).

### Email

1. Select the Email method and specify an address (on the frontend, the code goes to the user's profile email).
2. Enter the 6-digit code from the email. The code lives `modtwoauth_code_lifetime` seconds (default 300); resending is allowed no more than once per `modtwoauth_resend_cooldown` seconds (60).

### Telegram

1. Create a bot via [@BotFather](https://t.me/BotFather) and set the token in `modtwoauth_telegram_bot_token`.
2. The site must be publicly accessible (not localhost) over HTTPS: the webhook is registered automatically by the package when a pairing/login request is created. The webhook secret is derived from the bot token and verified on every request.
3. The user scans the QR (or follows the deep-link), presses **Start**, then in the bot presses **Confirm Connection**. The request is one-time, valid for 5 minutes; only token hashes are stored in the DB.
4. At login, the bot sends a 6-digit code and **Approve Login** / **Reject** buttons. Only the chat linked to the account can confirm.

### WebAuthn / FIDO2 / Passkeys

- The RP ID is determined from the manager host (`manager_url`) for the manager and from `site_url` for the frontend; if needed, set `modtwoauth_webauthn_rp_id` (you can specify either "example.com" or a full URL).
- Attestation type is `none` (vendor attestation chain verification is intentionally not performed).
- `residentKey: preferred`, `userVerification: preferred` - passkeys work without a credentials list.
- The authenticator's signature counter is verified (cloned key detection); for authenticators without a counter the check is skipped.
- ES256 signatures (including correct DER and raw R‖S handling) and RS256 via OpenSSL are supported, Ed25519 via sodium.
- Wrong attempts count toward the shared `modtwoauth_max_attempts` / `modtwoauth_lock_minutes` limit.

## Frontend Snippets

### `[[!ModTwoAuthFrontend]]

The 2FA personal account: choosing and configuring methods, enabling/disabling (with password confirmation).

| Parameter | Default | Description |
|---|---|---|
| `&tpl` | `modtwoauthFrontendSettingsTpl` | Chunk-template ID (optional) |
| `&methods` | all | Comma-separated method list: `totp,email,telegram,webauthn` (optional) |

### `[[!ModTwoAuthSessions]]`

List of the user's active sessions (last 30 days) with a terminate button.

| Parameter | Default | Description |
|---|---|---|
| `&tpl` | `modtwoauthSessionsTpl` | Chunk-template ID (optional) |
| `&showCurrent` | `1` | `0` - hide the current session from the list |

Both snippets are available only to users authorized in any web context.

## System Settings

| Setting | Default | Description |
|---|---|---|
| `modtwoauth_enable_2fa` | No | Enable 2FA in the manager |
| `modtwoauth_frontend_enable_2fa` | Yes | Enable 2FA on the frontend |
| `modtwoauth_code_lifetime` | 300 | Lifetime of the email/telegram code and the pending login window, sec. (min. 30) |
| `modtwoauth_max_attempts` | 3 | Wrong codes before lockout |
| `modtwoauth_lock_minutes` | 15 | Lockout duration, min. |
| `modtwoauth_resend_cooldown` | 60 | Cooldown between email code resends, sec. (min. 15) |
| `modtwoauth_session_max_age` | 86400 | Max age of a verified 2FA session, sec.; `0` - no limit. Applies only to sessions that passed the second factor; an expired session is forcibly terminated |
| `modtwoauth_verify_url` | `/2fa` | Verification page URL in the manager. Path from site root (friendly URLs required); with FURLs off and the default value, the direct address `/assets/components/modtwoauth/verify.php` is used |
| `modtwoauth_web_verify_url` | `/2fa-web` | Verification page URL on the frontend (same rules) |
| `modtwoauth_verify_chunk_id` | 0 | ID of your own verification page chunk **on the frontend** (0 - built-in; manager is not affected) |
| `modtwoauth_telegram_bot_token` | - | Telegram Bot API token |
| `modtwoauth_email_from_name` | - | Sender name; empty - `site_name` is used |
| `modtwoauth_email_from_address` | - | Sender address; empty - `emailsender` is used. The address is validated |
| `modtwoauth_key_path` | - | Custom path to the encryption key (a relative path is resolved from `MODX_BASE_PATH`) |
| `modtwoauth_csp_report_only` | Yes | Send CSP in Report-Only mode (does not block) |
| `modtwoauth_csp_policy` | `default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; upgrade-insecure-requests` | The CSP policy value |
| `modtwoauth_webauthn_rp_id` | - | Explicit WebAuthn RP ID (host or URL) |

Key source priority: environment variable `MODTWOAUTH_KEY_PATH` → `modtwoauth_key_path` → `core/config/modtwoauth.key`.

## Code Emails

The email subject and HTML body are configured in the lexicons `modtwoauth_email_subject` and `modtwoauth_email_body` (topic `modtwoauth:default`). Available placeholders: `[[+code]]` - the code, `[[+minutes]]` - validity period in minutes.

## Encryption Key

The key is created by the installer in `core/config/modtwoauth.key` (0600). **Back it up**: if the key is lost, the encrypted TOTP secrets cannot be recovered - users will need to re-enroll.

Moving the key outside the document root on a fresh install (adapt the paths and owner to your server):

```bash
install -d -m 700 /var/www/site/modtwoauth
umask 077
php -r 'echo base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)), PHP_EOL;' \
  > /var/www/site/modtwoauth/modtwoauth.key
chmod 600 /var/www/site/modtwoauth/modtwoauth.key
```

Then set the full path in the `modtwoauth_key_path` setting; the PHP-FPM user must have read access to the file. If TOTP is already configured, **do not create a new key**: first move the existing `core/config/modtwoauth.key` to the new location, and only then change the setting.

## Sessions and Devices

- Session records are stored in the `twoauth_sessions` table (IP, User-Agent, creation and activity time). Activity is updated no more than once per minute.
- The session list in the manager is on the "2FA" tab of the profile; the web list is rendered by the `ModTwoAuthSessions` snippet. Sessions active in the last 30 days are shown; tokens are not displayed in the lists.
- Revoking another session: the record is deleted, and on the next request the plugin (`OnManagerPageInit` / `OnWebPageInit`) will terminate that session. The current session cannot be revoked from the interface.
- `modtwoauth_session_max_age` forcibly terminates a verified 2FA session older than the specified age - the user will have to log in again.

## Security

Mechanisms applied in the component:

- the second factor is passed **only** through the built-in verification pages; it cannot be bypassed by a request with an external flag - there are no such flags;
- when locked out, login is **rejected**, it does not "fall through" to password verification;
- an accepted TOTP code is not accepted again; email/telegram codes are stored only as bcrypt hashes;
- all verification pages are protected by CSRF tokens; the connector and processors require POST and check Origin/Referer; the connector action list is an allowlist;
- with 2FA enabled, the method cannot be "silently" overwritten by a direct processor call - you must first disable it through the profile;
- the session ID is regenerated after a successful second factor (manager, frontend, Telegram, WebAuthn);
- redirects after 2FA are validated against the site's scheme, host, and port (open redirect protection);
- the Telegram webhook rejects requests without a valid secret; only one-time token hashes are stored in the DB; only the linked chat can confirm a login;
- server messages are escaped before being inserted into the DOM; session tokens are not shown in the UI; exception details are written only to the MODX log;
- TOTP secrets are encrypted with libsodium XChaCha20-Poly1305 using a key outside the component tree.

Server checklist after installation:

1. HTTPS on the manager and the site.
2. `session.cookie_secure=1`, `session.cookie_httponly=1`, an appropriate `session.cookie_samesite` in PHP.
3. The encryption key moved outside the document root (or `0600` verified), with a backup.
4. Review CSP Report-Only reports in the browser console before enabling the blocking policy (`modtwoauth_csp_report_only=No`).
5. The diagnostics on the component's main page should show all items green.

## Troubleshooting

**Login does not ask for the second factor.** The user's method is not enabled or not verified (the "Enable 2FA" switch), or `modtwoauth_enable_2fa` (manager) / `modtwoauth_frontend_enable_2fa` (frontend) is disabled. 2FA applies only to users with a configured and enabled method.

**Friendly URLs are disabled - `/2fa` does not open.** Use the direct addresses `/assets/components/modtwoauth/verify.php` (manager) and `/assets/components/modtwoauth/web-verify.php` (frontend). A custom route URL requires FURL; otherwise specify the direct path.

**"3 wrong codes" - the account is locked.** Wait `modtwoauth_lock_minutes` (default 15), or an administrator can temporarily disable 2FA in the user's profile and re-enable it after login.

**Telegram: the bot does not respond / the webhook is not set.** The site must be publicly accessible over HTTPS, the token must be valid. Telegram API errors are written to the MODX error log.

**WebAuthn: "Secure context" / key creation error.** WebAuthn requires HTTPS (exception - `localhost`). The RP ID must match the page host: for non-standard subdomains, set `modtwoauth_webauthn_rp_id`.

**TOTP cannot be set up - encryption error.** The sodium extension is not installed/enabled, or the key file is inaccessible. Check the "sodium" item in the component diagnostics.

**Changing `modtwoauth_key_path` with TOTP already working.** First move the existing `core/config/modtwoauth.key` to the new location, then change the setting. If a non-existent path is specified, the component will try to copy the built-in key automatically, but it is better to do it manually.

**Lost the encryption key.** The encrypted TOTP secrets are unrecoverable: users need to disable and re-configure TOTP (email/telegram/webauthn secrets are unaffected).

## Changelog

### 1.0.0-pl

First public release.

**Authentication methods:** TOTP (RFC 6238, ±1 window), Email codes, Telegram (QR/deep-link pairing, login by code or button in the bot), WebAuthn/FIDO2/Passkeys (ES256/RS256/Ed25519) - for the manager and the frontend.

**Security:**

- Protection against TOTP code reuse (timestep of the last accepted code stored in the DB).
- The Telegram webhook rejects all requests without the secret; the secret is derived from the bot token; only one-time token hashes are stored in the DB; login confirmation is bound to the chat and session.
- Email code sending rate limited (`modtwoauth_resend_cooldown`); shared wrong-attempt limit with lockout, persistent in the DB; separate attempt limits when configuring methods.
- Session ID regeneration after 2FA in all flows; session lists with per-session revocation; `modtwoauth_session_max_age` forcibly terminates expired 2FA sessions.
- TOTP secrets encrypted with libsodium XChaCha20-Poly1305; the key can be moved outside the component (system setting); automatic migration of plaintext secrets on install.
- CSRF tokens on all verification forms; optional CSP Report-Only; security diagnostics in the panel.

## Licenses

- ModTwoAuth is free software licensed under the **GNU General Public License v2.0 or later**
(GPL-2.0-or-later) - see [LICENSE](LICENSE). The full text is also bundled with the
transport package at `core/components/modtwoauth/docs/license.txt`.

- The built-in local QR encoder - qrcode-generator © Kazuhiko Arase, MIT License
(see `assets/components/modtwoauth/js/QR-CODE-LICENSE.txt`); the library makes no network requests.