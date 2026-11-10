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

/**
 * Standalone frontend 2FA verification page.
 *
 * The normal MODX login processor is deliberately not used for the second
 * factor. This prevents unrelated authentication plugins from seeing the
 * second-factor POST and keeps the working 3.0.5-style flow.
 */

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: SAMEORIGIN');

$root = dirname(__DIR__, 3);
$configCore = $root . '/config.core.php';
if (!is_file($configCore)) {
    $configCore = dirname(__DIR__, 4) . '/config.core.php';
}
require_once $configCore;

require_once MODX_CORE_PATH . 'vendor/autoload.php';
require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';

$modx = new \MODX\Revolution\modX();
if (!$modx->initialize('web')) {
    http_response_code(500);
    echo \ModTwoAuth\Localization::get($modx, 'modtwoauth_error_web_init');
    exit;
}
require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
\ModTwoAuth\ErrorHandler::register($modx, 'Frontend verification');

$L = static function (string $key, array $placeholders = []) use ($modx): string {
    return \ModTwoAuth\Localization::get($modx, $key, $placeholders);
};

$bootstrap = MODX_CORE_PATH . 'components/modtwoauth/bootstrap.php';
if (is_file($bootstrap)) {
    $namespace = ['path' => MODX_CORE_PATH . 'components/modtwoauth/'];
    require_once $bootstrap;
}

use ModTwoAuth\Model\TwoAuthSession;
use ModTwoAuth\Model\TwoAuthTelegramRequest;
use ModTwoAuth\Model\TwoAuthUserSetting;
use ModTwoAuth\Providers\TotpProvider;

$pendingUserId = (int) ($_SESSION['modtwoauth_web_pending_user_id'] ?? 0);
$provider = (string) ($_SESSION['modtwoauth_web_pending_provider'] ?? '');
$created = (int) ($_SESSION['modtwoauth_web_pending_timestamp'] ?? 0);
$lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));
$storedError = (string) ($_SESSION['modtwoauth_web_login_error'] ?? '');
// A previous error is display-only: it must not prevent a new POST from being checked.
$error = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? '' : $storedError;
unset($_SESSION['modtwoauth_web_login_error']);
if (empty($_SESSION['modtwoauth_verify_csrf'])) { $_SESSION['modtwoauth_verify_csrf'] = bin2hex(random_bytes(32)); }
$csrfToken = (string) $_SESSION['modtwoauth_verify_csrf'];

if ($pendingUserId <= 0 || $provider === '') {
    header('Location: ' . (string) $modx->getOption('site_url', null, '/'));
    exit;
}

$setting = $modx->getObject(TwoAuthUserSetting::class, ['user_id' => $pendingUserId]);
$user = $modx->getObject('modUser', $pendingUserId);

if (!$setting || !$user || !(int) $user->get('active') || !(int) $setting->get('is_enabled') || !(int) $setting->get('is_verified')) {
    foreach ([
        'modtwoauth_web_pending_user_id',
        'modtwoauth_web_pending_username',
        'modtwoauth_web_pending_provider',
        'modtwoauth_web_pending_timestamp',
        'modtwoauth_web_pending_code_hash',
        'modtwoauth_web_login_error',
        'modtwoauth_web_tg_login_request_id',
        'modtwoauth_web_webauthn_login_user_id',
    ] as $key) {
        unset($_SESSION[$key]);
    }
    header('Location: ' . (string) $modx->getOption('site_url', null, '/'));
    exit;
}

if ((string) $setting->get('provider') !== $provider) {
    header('Location: ' . (string) $modx->getOption('site_url', null, '/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['modtwoauth_csrf'] ?? '');
    if ($postedCsrf === '' || !hash_equals($csrfToken, $postedCsrf)) {
        http_response_code(403);
        exit($L('modtwoauth_error_invalid_request'));
    }
}

require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
if (\ModTwoAuth\AttemptLimiter::lockedUntil($setting) > 0) {
    $error = \ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting);
}

if ($created <= 0 || (time() - $created) > $lifetime) {
    // Просроченный pending-запрос полностью очищаем, чтобы «зависшее» состояние
    // не оставалось в сессии до следующей попытки входа.
    foreach ([
        'modtwoauth_web_pending_user_id',
        'modtwoauth_web_pending_username',
        'modtwoauth_web_pending_provider',
        'modtwoauth_web_pending_timestamp',
        'modtwoauth_web_pending_code_hash',
        'modtwoauth_web_pending_email_sent_at',
        'modtwoauth_web_tg_login_request_id',
        'modtwoauth_web_webauthn_login_user_id',
        'modtwoauth_web_webauthn_login_challenge',
        'modtwoauth_web_webauthn_login_created',
    ] as $expiredKey) {
        unset($_SESSION[$expiredKey]);
    }
    $error = $L('modtwoauth_verify_session_expired');
}

function m2a_finish_login(\MODX\Revolution\modX $modx, \MODX\Revolution\modUser $user, $setting): void
{
    $userId = (int) $user->get('id');
    $siteUrl = (string) $modx->getOption('site_url', null, '/');
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/RedirectGuard.php';
    $redirect = \ModTwoAuth\RedirectGuard::safe((string) ($_SESSION['modtwoauth_web_pending_redirect'] ?? ''), $siteUrl);

    $loginContext = (string) ($_SESSION['modtwoauth_web_pending_context'] ?? 'web');
    if ($loginContext === '' || $loginContext === 'mgr' || !preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $loginContext)) $loginContext = 'web';
    if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    $user->addSessionContext($loginContext);
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';
    \ModTwoAuth\SessionTracker::track($modx, $userId, 'web');
    $_SESSION['modtwoauth_web_session_context'] = $loginContext;
    $_SESSION['modtwoauth_web_verified_user_id'] = $userId;

    require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
    \ModTwoAuth\AttemptLimiter::reset($setting);

    foreach ([
        'modtwoauth_web_pending_user_id',
        'modtwoauth_web_pending_username',
        'modtwoauth_web_pending_provider',
        'modtwoauth_web_pending_timestamp',
        'modtwoauth_web_pending_code_hash',
        'modtwoauth_web_pending_email_sent_at',
        'modtwoauth_web_pending_secret',
        'modtwoauth_web_login_error',
        'modtwoauth_web_tg_login_request_id',
        'modtwoauth_web_webauthn_login_user_id',
        'modtwoauth_web_webauthn_login_challenge',
        'modtwoauth_web_webauthn_login_created',
        'modtwoauth_web_pending_context',
        'modtwoauth_web_pending_redirect',
    ] as $key) {
        unset($_SESSION[$key]);
    }

    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $provider !== 'webauthn') {
    $code = trim((string) ($_POST['twoauth_code'] ?? $_POST['code'] ?? ''));
    $valid = false;

    if ($provider === 'totp') {
        $storedSecret = trim((string) $setting->get('secret'));
        try {
            require_once MODX_CORE_PATH . 'components/modtwoauth/src/SecurityCrypto.php';
            $secret = \ModTwoAuth\SecurityCrypto::decrypt($storedSecret);
            $lastStep = (int) ($setting->get('last_totp_counter') ?? 0);
            $valid = $secret !== '' && (new TotpProvider($modx))->verifyCode($secret, $code, $lastStep, $matchedStep);
            if ($valid) {
                // Запоминаем timestep: повторное использование того же кода станет невозможным.
                $setting->set('last_totp_counter', $matchedStep);
            }
        } catch (\Throwable $e) {
            $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] TOTP secret could not be decrypted; secret value omitted.');
            $valid = false;
        }
    } elseif ($provider === 'email') {
        $hash = (string) ($_SESSION['modtwoauth_web_pending_code_hash'] ?? '');
        $valid = $hash !== '' && $code !== '' && password_verify($code, $hash);
    } elseif ($provider === 'telegram') {
        $hash = (string) ($_SESSION['modtwoauth_web_pending_code_hash'] ?? '');
        $valid = $hash !== '' && $code !== '' && password_verify($code, $hash);

        $requestId = (int) ($_SESSION['modtwoauth_web_tg_login_request_id'] ?? 0);
        if ($valid && $requestId > 0) {
            $request = $modx->getObject(TwoAuthTelegramRequest::class, [
                'id' => $requestId,
                'user_id' => $pendingUserId,
                'kind' => 'login',
                'session_hash' => hash('sha256', session_id()),
            ]);
            if ($request && (string) $request->get('status') === 'denied') {
                $valid = false;
            }
        }
    }

    if ($valid) {
        if ($provider === 'telegram') {
            $requestId = (int) ($_SESSION['modtwoauth_web_tg_login_request_id'] ?? 0);
            if ($requestId > 0) {
                $request = $modx->getObject(TwoAuthTelegramRequest::class, [
                    'id' => $requestId,
                    'user_id' => $pendingUserId,
                    'kind' => 'login',
                    'session_hash' => hash('sha256', session_id()),
                ]);
                if ($request) {
                    $request->set('status', 'used');
                    $request->set('confirmed_at', date('Y-m-d H:i:s'));
                    $request->save();
                }
            }
        }
        m2a_finish_login($modx, $user, $setting);
    }

    require_once MODX_CORE_PATH . 'components/modtwoauth/src/AttemptLimiter.php';
    $attempt = \ModTwoAuth\AttemptLimiter::fail($modx, $setting);
    if ($attempt['locked']) {
        $error = \ModTwoAuth\AttemptLimiter::lockedMessage($modx, $setting);
    } else {
        $error = \ModTwoAuth\AttemptLimiter::attemptsLeftMessage(
            $modx,
            $attempt['max'] - $attempt['attempts']
        );
    }
    $_SESSION['modtwoauth_web_login_error'] = $error;
}

$hint = $L('modtwoauth_verify_hint_default');
if ($provider === 'totp') {
    $hint = $L('modtwoauth_verify_hint_totp');
} elseif ($provider === 'email') {
    $email = (string) $setting->get('contact_value');
    $masked = $email;
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        [$local, $domain] = explode('@', $email, 2);
        $visible = function_exists('mb_substr') ? mb_substr($local, 0, 1) : substr($local, 0, 1);
        $localLength = function_exists('mb_strlen') ? mb_strlen($local) : strlen($local);
        $masked = $visible . str_repeat('*', max(2, min(6, $localLength - 1))) . '@' . $domain;
    }
    $hint = $L('modtwoauth_verify_hint_email') . ' <strong>' . htmlspecialchars($masked, ENT_QUOTES, 'UTF-8') . '</strong>.';
} elseif ($provider === 'telegram') {
    $hint = $L('modtwoauth_verify_hint_telegram');
} elseif ($provider === 'webauthn') {
    $hint = $L('modtwoauth_verify_hint_webauthn');
}

$statusUrl = rtrim((string) $modx->getOption('assets_url', null, MODX_ASSETS_URL), '/')
    . '/components/modtwoauth/web-telegram-login-status.php';
$webauthnUrl = rtrim((string) $modx->getOption('assets_url', null, MODX_ASSETS_URL), '/')
    . '/components/modtwoauth/webauthn-web-login.php';

$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$siteUrl = (string) $modx->getOption('site_url', null, '/');
$content = '';
if ($provider === 'webauthn') {
    $content = '<button type="button" class="m2a-button" id="m2a-webauthn">' . $esc($L('modtwoauth_verify_passkey')) . '</button><div id="m2a-status" style="margin-top:14px;text-align:center;font-size:13px"></div>';
    $content .= '<script>(function(){var btn=document.getElementById("m2a-webauthn"),st=document.getElementById("m2a-status"),url=' . json_encode($webauthnUrl, JSON_UNESCAPED_SLASHES) . ';function b64(s){if(typeof s!=="string"||!/^[A-Za-z0-9_-]*$/.test(s))throw new Error(' . json_encode($L('modtwoauth_webauthn_invalid_base64'), JSON_UNESCAPED_UNICODE) . ');s=s.replace(/-/g,"+").replace(/_/g,"/");while(s.length%4)s+="=";var x=atob(s),a=new Uint8Array(x.length);for(var i=0;i<x.length;i++)a[i]=x.charCodeAt(i);return a.buffer;}function validRpId(id){return typeof id==="string"&&/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i.test(id)&&id.indexOf(":")===-1&&id.indexOf("/")===-1;}function s64(b){var a=new Uint8Array(b),x="";for(var i=0;i<a.length;i++)x+=String.fromCharCode(a[i]);return btoa(x).replace(/\+/g,"-").replace(/\//g,"_").replace(/=+$/g,"");}btn.addEventListener("click",function(){btn.disabled=true;st.textContent=' . json_encode($L('modtwoauth_verify_wait_device'), JSON_UNESCAPED_UNICODE) . ';fetch(url,{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(o){if(!o.ok)throw new Error(o.message||' . json_encode($L('modtwoauth_webauthn_start_failed'), JSON_UNESCAPED_UNICODE) . ');var p=o.publicKey;if(!validRpId(p.rpId))throw new Error(' . json_encode($L('modtwoauth_webauthn_invalid_rpid'), JSON_UNESCAPED_UNICODE) . ');p.challenge=b64(p.challenge);if(p.allowCredentials)p.allowCredentials=p.allowCredentials.map(function(c){c.id=b64(c.id);return c;});return navigator.credentials.get({publicKey:p});}).then(function(c){var a={id:c.id,rawId:s64(c.rawId),type:c.type,response:{clientDataJSON:s64(c.response.clientDataJSON),authenticatorData:s64(c.response.authenticatorData),signature:s64(c.response.signature),userHandle:c.response.userHandle?s64(c.response.userHandle):null}};return fetch(url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(a)});}).then(function(r){return r.json();}).then(function(o){if(!o.ok)throw new Error(o.message||' . json_encode($L('modtwoauth_webauthn_rejected'), JSON_UNESCAPED_UNICODE) . ');window.location.href=o.redirect;}).catch(function(e){btn.disabled=false;st.textContent="❌ "+String(e.message||e);st.style.color="#c62828";});});})();</script>';
} else {
    $content = '<form method="post" action="" autocomplete="off"><input type="hidden" name="modtwoauth_csrf" value="' . $esc($csrfToken) . '"><input class="m2a-input" type="text" name="twoauth_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="' . $esc($L('modtwoauth_verify_code_placeholder')) . '" required autofocus><button class="m2a-button" type="submit">' . $esc($L('modtwoauth_verify_submit')) . '</button></form>';
    if ($provider === 'telegram') {
        $content .= '<div id="m2a-tg-status" style="margin-top:14px;text-align:center;color:#1976d2;font-size:13px">' . $esc($L('modtwoauth_verify_wait_telegram')) . '</div>';
        $content .= '<script>(function(){var url=' . json_encode($statusUrl, JSON_UNESCAPED_SLASHES) . ',el=document.getElementById("m2a-tg-status");function poll(){var x=new XMLHttpRequest();x.open("GET",url+"?t="+Date.now(),true);x.onreadystatechange=function(){if(x.readyState!==4)return;if(x.status===200){try{var r=JSON.parse(x.responseText);if(r.status==="approved"){window.location.href=r.redirect||' . json_encode($siteUrl, JSON_UNESCAPED_SLASHES) . ';return;}if(r.status==="denied"){el.textContent=' . json_encode($L('modtwoauth_verify_telegram_denied'), JSON_UNESCAPED_UNICODE) . ';el.style.color="#c62828";return;}if(r.status==="expired"){el.textContent=' . json_encode($L('modtwoauth_verify_request_expired'), JSON_UNESCAPED_UNICODE) . ';el.style.color="#c62828";return;}}catch(e){}}setTimeout(poll,2000);};x.onerror=function(){setTimeout(poll,3000);};x.send();}poll();})();</script>';
    }
}

// Unlike the Manager, the frontend honors the configured custom chunk. Invalid
// or empty values fall back to the bundled modtwoauthVerifyTpl template.
$customChunkId = (int) $modx->getOption('modtwoauth_verify_chunk_id', null, 0);
$chunk = $customChunkId > 0 ? $modx->getObject('modChunk', $customChunkId) : null;
if (!$chunk) {
    $chunk = $modx->getObject('modChunk', ['name' => 'modtwoauthVerifyTpl']);
}
if ($chunk) {
    $output = $chunk->getContent();
    $output = str_replace(
        ['[[+title]]','[[+heading]]','[[+error_html]]','[[+hint]]','[[+content]]','[[+back_url]]','[[+custom_css]]','[[+back_label]]','[[+back_text]]'],
        [$L('modtwoauth_verify_title'),$L('modtwoauth_verify_heading'),$error !== '' ? '<div class="m2a-error">❌ ' . $esc($error) . '</div>' : '',$hint,$content,$esc($siteUrl),'',$L('modtwoauth_verify_back'),$L('modtwoauth_verify_back')],
        $output
    );
    echo $output;
} else {
    echo '<!doctype html><html lang="' . $esc($modx->getOption('cultureKey', null, 'en')) . '"><meta charset="utf-8"><title>' . $esc($L('modtwoauth_verify_title')) . '</title><body><h1>' . $esc($L('modtwoauth_verify_heading')) . '</h1>' . ($error !== '' ? '<p>' . $esc($error) . '</p>' : '') . '<p>' . $hint . '</p>' . $content . '<p><a href="' . $esc($siteUrl) . '">' . $esc($L('modtwoauth_verify_back_to_login')) . '</a></p></body></html>';
}
