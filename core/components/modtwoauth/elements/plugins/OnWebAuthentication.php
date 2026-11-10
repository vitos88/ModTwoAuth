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

if ((string) $modx->event->name !== 'OnWebAuthentication') {
    return;
}
if (!(bool) $modx->getOption('modtwoauth_frontend_enable_2fa', null, true) || !$user) {
    return;
}
// Never change the Manager flow here; OnWebAuthentication is for non-manager contexts.
$userId = (int) $user->get('id');
if ($userId <= 0 || !(int) $user->get('active')) return;
$setting = $modx->getObject(TwoAuthUserSetting::class, ['user_id' => $userId]);
if (!$setting || !(int) $setting->get('is_enabled')) return;
if (!(int) $setting->get('is_verified')) {
    $modx->event->output(Localization::get($modx, 'modtwoauth_frontend_setup_incomplete'));
    $modx->event->stopPropagation();
    return;
}
// OnWebAuthentication fires before MODX's built-in password check. Verify the password
// ourselves before creating a pending second-factor flow; never store the password.
$password = (string) ($password ?? '');
if ($password === '' || !$user->passwordMatches($password)) return;

require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
$lockUntil = (string) ($setting->get('locked_until') ?? '');
if (\ModTwoAuth\AttemptLimiter::lockedUntil($setting) > 0) {
    $modx->event->output(\ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting));
    $modx->event->stopPropagation();
    return;
}
if ($lockUntil !== '') {
    $setting->set('locked_until', null);
    $setting->set('login_attempts', 0);
    $setting->save();
}
$provider = (string) $setting->get('provider');
$context = isset($loginContext) && is_string($loginContext) && $loginContext !== '' ? $loginContext : 'web';
if ($context === 'mgr' || !preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $context)) $context = 'web';
$lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));
$_SESSION['modtwoauth_web_pending_user_id'] = $userId;
$_SESSION['modtwoauth_web_pending_username'] = (string) $user->get('username');
$_SESSION['modtwoauth_web_pending_provider'] = $provider;
$_SESSION['modtwoauth_web_pending_context'] = $context;
$_SESSION['modtwoauth_web_pending_timestamp'] = time();

// Preserve Login extra's loginResourceId even though the 2FA flow temporarily
// leaves Login's controller before its normal redirectAfterLogin() can run.
$siteUrl = (string) $modx->getOption('site_url', null, '/');
$targetId = (int) ($_POST['modtwoauth_login_resource_id'] ?? $_REQUEST['loginResourceId'] ?? $_REQUEST['login_resource_id'] ?? 0);
$sourceTexts = [];
if ($modx->resource) {
    $sourceTexts[] = (string) $modx->resource->get('content');
    $templateId = (int) $modx->resource->get('template');
    if ($templateId > 0) {
        $template = $modx->getObject('modTemplate', $templateId);
        if ($template) $sourceTexts[] = (string) $template->get('content');
    }
}
if ($targetId <= 0) {
    foreach ($sourceTexts as $sourceText) {
        if (preg_match('~&loginResourceId\s*=\s*\x60\s*(\d+)\s*\x60~i', $sourceText, $match)) {
            $targetId = (int) $match[1];
            break;
        }
    }
}
if ($targetId <= 0) {
    // Some sites put the Login call in a chunk rather than resource/template
    // content. The scan touches every chunk, so the result (including "not
    // found" = 0) is cached for an hour instead of running on every 2FA login.
    $cacheKey = 'modtwoauth/login_resource_id';
    $cached = $modx->cacheManager->get($cacheKey);
    if ($cached !== null) {
        $targetId = (int) $cached;
    } else {
        foreach ($modx->getIterator('modChunk', ['content:LIKE' => '%loginResourceId%']) as $chunk) {
            if (preg_match('~&loginResourceId\s*=\s*\x60\s*(\d+)\s*\x60~i', (string) $chunk->get('content'), $match)) {
                $targetId = (int) $match[1];
                break;
            }
        }
        $modx->cacheManager->set($cacheKey, $targetId, 3600);
    }
}
$redirect = '';
if ($targetId > 0) {
    $params = [];
    foreach ($sourceTexts as $sourceText) {
        if (preg_match('~&loginResourceParams\s*=\s*\x60([^\x60]*)\x60~i', $sourceText, $match)) {
            $decoded = $modx->fromJSON(trim($match[1]));
            if (is_array($decoded)) $params = $decoded;
            break;
        }
    }
    $redirect = (string) $modx->makeUrl($targetId, '', $params, 'full');
}
if ($redirect === '') {
    $candidate = trim((string) ($_POST['returnUrl'] ?? $_REQUEST['returnUrl'] ?? ''));
    if ($candidate !== '') {
        $siteParts = parse_url($siteUrl);
        if (str_starts_with($candidate, '/') && !str_starts_with($candidate, '//')) {
            $redirect = rtrim($siteUrl, '/') . $candidate;
        } elseif (preg_match('~^https?://~i', $candidate)) {
            $candidateParts = parse_url($candidate);
            if (!empty($candidateParts['host']) && !empty($siteParts['host']) && strcasecmp($candidateParts['host'], $siteParts['host']) === 0) {
                $redirect = $candidate;
            }
        }
    }
}
if ($redirect === '') $redirect = $siteUrl;
$_SESSION['modtwoauth_web_pending_redirect'] = $redirect;

// Сохраняем предыдущий код до сброса: в пределах кулдауна он переиспользуется.
$prevCodeHash = (string) ($_SESSION['modtwoauth_web_pending_code_hash'] ?? '');
$prevEmailSentAt = (int) ($_SESSION['modtwoauth_web_pending_email_sent_at'] ?? 0);
unset($_SESSION['modtwoauth_web_login_error'], $_SESSION['modtwoauth_web_pending_code_hash']);

if ($provider === 'email') {
    $cooldown = max(15, (int) $modx->getOption('modtwoauth_resend_cooldown', null, 60));
    if ($prevCodeHash !== '' && $prevEmailSentAt > 0 && (time() - $prevEmailSentAt) < $cooldown) {
        // Недавно уже отправляли — не спамим почту, действующий код остаётся тем же.
        $_SESSION['modtwoauth_web_pending_code_hash'] = $prevCodeHash;
        $_SESSION['modtwoauth_web_pending_email_sent_at'] = $prevEmailSentAt;
    } else {
        $code = (new EmailProvider($modx))->generateCode();
        if ((new EmailProvider($modx))->sendCode($userId, (string) $setting->get('contact_value'), $code)) {
            $_SESSION['modtwoauth_web_pending_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
            $_SESSION['modtwoauth_web_pending_email_sent_at'] = time();
        } else {
            $_SESSION['modtwoauth_web_login_error'] = Localization::get($modx, 'modtwoauth_email_login_send_failed_frontend');
        }
    }
} elseif ($provider === 'telegram') {
    $telegram = new TelegramProvider($modx);
    $code = $telegram->generateCode();
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $now = time();
    $request = $modx->newObject(\ModTwoAuth\Model\TwoAuthTelegramRequest::class);
    $request->fromArray([
        'user_id' => $userId, 'kind' => 'login', 'token_hash' => hash('sha256', $token),
        'chat_id' => (string) $setting->get('contact_value'), 'status' => 'pending',
        'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'session_hash' => hash('sha256', session_id()),
        'created_at' => date('Y-m-d H:i:s', $now), 'expires_at' => date('Y-m-d H:i:s', $now + $lifetime),
    ]);
    try {
        $telegram->ensureWebhook();
        $sent = $request->save() && $telegram->sendLoginChallenge((string) $setting->get('contact_value'), $code, $token, (string) $modx->getOption('site_name', null, 'Website'));
    } catch (\Throwable $e) {
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Frontend Telegram challenge error: ' . $e->getMessage());
        $sent = false;
    }
    if ($sent) {
        $_SESSION['modtwoauth_web_pending_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['modtwoauth_web_tg_login_request_id'] = (int) $request->get('id');
    } else {
        $_SESSION['modtwoauth_web_login_error'] = Localization::get($modx, 'modtwoauth_telegram_login_send_failed');
    }
} elseif ($provider === 'webauthn') {
    $_SESSION['modtwoauth_web_webauthn_login_user_id'] = $userId;
}

$modx->event->output('');
$modx->event->stopPropagation();

$friendlyUrls = (bool) $modx->getOption('friendly_urls', null, false);
$verifyUrl = trim((string) $modx->getOption('modtwoauth_web_verify_url', null, '/2fa-web'));
if ($verifyUrl === '') {
    $verifyUrl = '/2fa-web';
}

if (!$friendlyUrls && $verifyUrl === '/2fa-web') {
    $verifyUrl = rtrim((string) $modx->getOption('assets_url', null, MODX_ASSETS_URL), '/')
        . '/components/modtwoauth/web-verify.php';
} elseif (strpos($verifyUrl, '/') === 0) {
    $verifyUrl = rtrim((string) $modx->getOption('site_url', null, MODX_SITE_URL), '/') . $verifyUrl;
}

header('Location: ' . $verifyUrl, true, 302);
exit;