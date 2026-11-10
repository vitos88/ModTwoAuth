<?php
/*
 * This file is part of ModTwoAuth.
 *
 * Copyright (C) 2026 vitos88
 *
 * ModTwoAuth is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ModTwoAuth is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ModTwoAuth; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

use ModTwoAuth\Localization;
use ModTwoAuth\Model\TwoAuthUserSetting;
use ModTwoAuth\Providers\EmailProvider;
use ModTwoAuth\Providers\TelegramProvider;

/** @var \MODX\Revolution\modX $modx */
/** @var \MODX\Revolution\modUser $user */
/** @var string $password */

if ((string) $modx->event->name !== 'OnManagerAuthentication') {
    return;
}

$globalEnabled = (bool) $modx->getOption('modtwoauth_enable_2fa', null, false);
if (!$globalEnabled || !$user) {
    return;
}

/*
 * IMPORTANT:
 * The second factor is intentionally NOT posted back to MODX's Login
 * processor. That processor may invoke other authentication plugins
 * (including plugins which expect their own POST fields). We first
 * authenticate the normal password here, stop the normal login flow, and
 * continue on our standalone verify.php page.
 *
 * Never skip this plugin based on request parameters: any externally
 * supplied flag here would completely bypass the second factor.
 */
$userId = (int) $user->get('id');
if ($userId <= 0 || !(int) $user->get('active')) {
    return;
}

$setting = $modx->getObject(TwoAuthUserSetting::class, ['user_id' => $userId]);
if (!$setting || !(int) $setting->get('is_enabled')) {
    return;
}

if (!(int) $setting->get('is_verified')) {
    $modx->event->output(Localization::get($modx, 'modtwoauth_manager_setup_incomplete'));
    $modx->event->stopPropagation();
    return;
}

/* OnManagerAuthentication runs before the normal password check. */
$password = (string) ($password ?? '');
if ($password === '' || !$user->passwordMatches($password)) {
    return;
}

require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
$lockUntil = (string) ($setting->get('locked_until') ?? '');
if (\ModTwoAuth\AttemptLimiter::lockedUntil($setting) > 0) {
    $lockMessage = \ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting);
    $_SESSION['modtwoauth_login_error'] = $lockMessage;
    /*
     * MODX treats a non-empty OnManagerAuthentication output as an error and
     * aborts the login, while an empty output falls through to the local
     * password check. Output the message itself: outputting '' here would let
     * the correct password complete a manager login without the second factor.
     */
    $modx->event->output($lockMessage);
    $modx->event->stopPropagation();
    return;
}

if ($lockUntil !== '') {
    $setting->set('locked_until', null);
    $setting->set('login_attempts', 0);
    $setting->save();
}

$provider = (string) $setting->get('provider');
$contact = (string) $setting->get('contact_value');
$lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));

// Сохраняем предыдущий код до сброса: в пределах кулдауна он переиспользуется,
// чтобы не слать новое письмо на каждый повторный ввод правильного пароля.
$prevCodeHash = (string) ($_SESSION['modtwoauth_pending_code_hash'] ?? '');
$prevEmailSentAt = (int) ($_SESSION['modtwoauth_pending_email_sent_at'] ?? 0);

$_SESSION['modtwoauth_pending_user_id'] = $userId;
$_SESSION['modtwoauth_pending_username'] = (string) $user->get('username');
$_SESSION['modtwoauth_pending_provider'] = $provider;
$_SESSION['modtwoauth_pending_timestamp'] = time();
unset($_SESSION['modtwoauth_login_error'], $_SESSION['modtwoauth_pending_code_hash']);

if ($provider === 'email') {
    $emailProvider = new EmailProvider($modx);
    $cooldown = max(15, (int) $modx->getOption('modtwoauth_resend_cooldown', null, 60));
    if ($prevCodeHash !== '' && $prevEmailSentAt > 0 && (time() - $prevEmailSentAt) < $cooldown) {
        // Недавно уже отправляли — не спамим почту, действующий код остаётся тем же.
        $_SESSION['modtwoauth_pending_code_hash'] = $prevCodeHash;
        $_SESSION['modtwoauth_pending_email_sent_at'] = $prevEmailSentAt;
    } else {
        $code = $emailProvider->generateCode();
        if ($emailProvider->sendCode($userId, $contact, $code)) {
            $_SESSION['modtwoauth_pending_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
            $_SESSION['modtwoauth_pending_email_sent_at'] = time();
        } else {
            $_SESSION['modtwoauth_login_error'] = Localization::get($modx, 'modtwoauth_email_login_send_failed_manager');
        }
    }
} elseif ($provider === 'telegram') {
    $telegram = new TelegramProvider($modx);
    $code = $telegram->generateCode();
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $now = time();
    $sessionHash = hash('sha256', session_id());

    $request = $modx->newObject(\ModTwoAuth\Model\TwoAuthTelegramRequest::class);
    $request->fromArray([
        'user_id' => $userId,
        'kind' => 'login',
        'token_hash' => hash('sha256', $token),
        'chat_id' => $contact,
        'status' => 'pending',
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'session_hash' => $sessionHash,
        'created_at' => date('Y-m-d H:i:s', $now),
        'expires_at' => date('Y-m-d H:i:s', $now + $lifetime),
    ]);

    try {
        $telegram->ensureWebhook();
        $saved = $request->save();
        $sent = $saved && $telegram->sendLoginChallenge(
            $contact,
            $code,
            $token,
            (string) $modx->getOption('site_name', null, 'MODX')
        );
    } catch (\Throwable $e) {
        $modx->log(
            \MODX\Revolution\modX::LOG_LEVEL_ERROR,
            '[modTwoAuth] Telegram login challenge error: ' . $e->getMessage()
        );
        $sent = false;
    }

    if ($sent) {
        $_SESSION['modtwoauth_pending_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['modtwoauth_tg_login_request_id'] = (int) $request->get('id');
    } else {
        if ($request->get('id')) {
            $request->set('status', 'expired');
            $request->save();
        }
        $_SESSION['modtwoauth_login_error'] = Localization::get($modx, 'modtwoauth_telegram_login_send_failed');
    }
} elseif ($provider === 'webauthn') {
    /*
     * The WebAuthn endpoint uses this value to identify the user after the
     * password has already been checked.
     */
    $_SESSION['modtwoauth_webauthn_login_user_id'] = $userId;
    unset(
        $_SESSION['modtwoauth_webauthn_login_challenge'],
        $_SESSION['modtwoauth_webauthn_login_created']
    );
}

/*
 * Do not let MODX continue into its normal login processor. This is important:
 * the standalone verify.php endpoint will finish the login and add the mgr
 * session only after the second factor succeeds.
 */
$modx->event->output('');
$modx->event->stopPropagation();

// Always honor an explicitly configured verification URL. With the default
// /2fa value and friendly URLs disabled, retain the direct endpoint fallback.
$friendlyUrls = (bool) $modx->getOption('friendly_urls', null, false);
$verifyUrl = trim((string) $modx->getOption('modtwoauth_verify_url', null, '/2fa'));
if ($verifyUrl === '') {
    $verifyUrl = '/2fa';
}
if (!$friendlyUrls && $verifyUrl === '/2fa') {
    $verifyUrl = rtrim((string) $modx->getOption('assets_url', null, MODX_ASSETS_URL), '/')
        . '/components/modtwoauth/verify.php';
} elseif (strpos($verifyUrl, '/') === 0) {
    $verifyUrl = rtrim((string) $modx->getOption('site_url', null, MODX_SITE_URL), '/') . $verifyUrl;
}
// Send the exact configured URL instead of letting another redirect handler
// replace it with the direct assets endpoint.
header('Location: ' . $verifyUrl, true, 302);
exit;
