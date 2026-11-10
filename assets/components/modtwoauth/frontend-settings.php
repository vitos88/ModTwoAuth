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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function m2afJson(array $data, int $status = 200): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
try {
    $configCore = dirname(__FILE__, 4) . '/config.core.php';
    if (!is_readable($configCore)) throw new RuntimeException('config.core.php not found.');
    require_once $configCore;
    require_once MODX_CORE_PATH . 'vendor/autoload.php';
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';
    $modx = new \MODX\Revolution\modX();
    $T = static fn(string $key, array $placeholders = []): string => \ModTwoAuth\Localization::get($modx, $key, $placeholders);
    if (!$modx->initialize('web')) throw new RuntimeException('Could not initialize the MODX web context.');
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
    \ModTwoAuth\ErrorHandler::register($modx, 'Frontend settings');
    $modx->addPackage('ModTwoAuth\\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');
    if (!(bool) $modx->getOption('modtwoauth_frontend_enable_2fa', null, true)) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_frontend_api_disabled')], 403);
    /*
     * Endpoint инициализирует контекст web, поэтому при входе только в shop
     * $modx->user может остаться анонимным. Сначала выбираем пользователя из
     * серверных MODX contextTokens, отдавая приоритет уже выбранному MODX
     * пользователю, а затем явно загружаем и проверяем его учётную запись.
     */
    $currentUserId = $modx->user ? (int) $modx->user->get('id') : 0;
    $userId = 0;
    $contextTokens = $_SESSION['modx.user.contextTokens'] ?? [];
    if (is_array($contextTokens)) {
        foreach ($contextTokens as $ctxKey => $ctxUserId) {
            $candidateId = (int) $ctxUserId;
            if (!is_string($ctxKey) || $ctxKey === 'mgr' || $candidateId <= 0) continue;
            if ($userId === 0) $userId = $candidateId;
            if ($currentUserId > 0 && $candidateId === $currentUserId) {
                $userId = $currentUserId;
                break;
            }
        }
    }
    $user = $userId > 0 ? $modx->getObject('modUser', $userId) : null;
    if (!$user || !(int) $user->get('active')) {
        m2afJson(['ok'=>false,'message'=>$T('modtwoauth_frontend_login_again')], 401);
    }
    $modx->user = $user;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') m2afJson(['ok'=>false,'message'=>$T('modtwoauth_method_not_allowed')], 405);
    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) $payload = $_POST;
    $csrf = (string) ($payload['csrf'] ?? '');
    $expected = (string) ($_SESSION['modtwoauth_frontend_csrf'] ?? '');
    if ($csrf === '' || $expected === '' || !hash_equals($expected, $csrf)) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_csrf_refresh')], 403);
    $setting = $modx->getObject('ModTwoAuth\\Model\\TwoAuthUserSetting', ['user_id'=>$userId]);
    if (!$setting) { $setting = $modx->newObject('ModTwoAuth\\Model\\TwoAuthUserSetting'); $setting->set('user_id', $userId); }
    $action = (string) ($payload['action'] ?? '');
    $enrollmentActions = ['begin_totp', 'confirm_totp', 'send_email_code', 'confirm_email', 'telegram_start'];
    if ((int) $setting->get('is_enabled') && in_array($action, $enrollmentActions, true)) {
        m2afJson(['ok'=>false,'message'=>$T('modtwoauth_frontend_disable_before_setup')], 409);
    }
    $now = date('Y-m-d H:i:s');
    switch ($action) {
        case 'sessions_list':
            /*
             * Сначала сверяем записи с реальным хранилищем сессий MODX:
             * после «Завершить все сеансы» в менеджере (TRUNCATE modx_session)
             * и сборщика мусора старые записи остаются «призраками» и выглядят
             * активными. Полный скан для старых записей без php_session_id
             * оправдан: список открывается редко.
             */
            try {
                \ModTwoAuth\SessionTracker::gc($modx, $userId, true);
            } catch (\Throwable $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Frontend sessions list GC failed: ' . $e->getMessage());
            }
            $activeSince = date('Y-m-d H:i:s', time() - (30 * 86400));
            $query = $modx->newQuery('ModTwoAuth\\Model\\TwoAuthSession');
            $query->where(['user_id' => $userId, 'last_active:>=' => $activeSince]);
            $query->sortby('last_active', 'DESC');
            $currentToken = hash('sha256', 'web:' . session_id());
            $sessions = [];
            foreach ($modx->getCollection('ModTwoAuth\\Model\\TwoAuthSession', $query) as $session) {
                $sessions[] = [
                    'id' => (int) $session->get('id'),
                    'ip_address' => (string) ($session->get('ip_address') ?? ''),
                    'user_agent' => (string) ($session->get('user_agent') ?? ''),
                    'created_at' => (string) ($session->get('created_at') ?? ''),
                    'last_active' => (string) ($session->get('last_active') ?? ''),
                    'is_current' => hash_equals((string) $session->get('session_token'), $currentToken),
                ];
            }
            m2afJson(['ok' => true, 'sessions' => $sessions]);
        case 'session_revoke':
            $sessionId = (int) ($payload['id'] ?? 0);
            if ($sessionId <= 0) m2afJson(['ok' => false, 'message' => $T('modtwoauth_session_missing')], 400);
            $session = $modx->getObject('ModTwoAuth\\Model\\TwoAuthSession', ['id' => $sessionId, 'user_id' => $userId]);
            if (!$session) m2afJson(['ok' => false, 'message' => $T('modtwoauth_session_not_found')], 404);
            $currentToken = hash('sha256', 'web:' . session_id());
            if (hash_equals((string) $session->get('session_token'), $currentToken)) {
                m2afJson(['ok' => false, 'message' => $T('modtwoauth_session_current_frontend')], 400);
            }
            if (!$session->remove()) m2afJson(['ok' => false, 'message' => $T('modtwoauth_session_revoke_failed')], 500);
            m2afJson(['ok' => true, 'message' => $T('modtwoauth_session_revoked_frontend')]);
        case 'begin_totp':
            $provider = new \ModTwoAuth\Providers\TotpProvider($modx);
            $secret = $provider->generateSecret();
            $_SESSION['modtwoauth_frontend_totp_secret'] = $secret;
            $_SESSION['modtwoauth_frontend_totp_created'] = time();
            // Новый QR — новый счётчик попыток ввода кода.
            unset($_SESSION['modtwoauth_frontend_totp_attempts']);
            $info = $provider->getQrUrl($secret, (string) $user->get('username'), (string) $modx->getOption('site_name', null, 'MODX'));
            m2afJson(['ok'=>true,'secret'=>$secret,'qr_data'=>$info['qr_data']]);
        case 'confirm_totp':
            $secret = (string) ($_SESSION['modtwoauth_frontend_totp_secret'] ?? '');
            if ($secret === '' || time() - (int) ($_SESSION['modtwoauth_frontend_totp_created'] ?? 0) > 600) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_totp_setup_expired')], 400);
            $code = trim((string) ($payload['code'] ?? ''));
            $matchedStep = null;
            if (!(new \ModTwoAuth\Providers\TotpProvider($modx))->verifyCode($secret, $code, 0, $matchedStep)) {
                // Не даём перебирать 6-значный код: после исчерпания попыток
                // секрет аннулируется и потребуется новый QR-код.
                $attemptsKey = 'modtwoauth_frontend_totp_attempts';
                $attempts = (int) ($_SESSION[$attemptsKey] ?? 0) + 1;
                $_SESSION[$attemptsKey] = $attempts;
                $maxAttempts = max(1, (int) $modx->getOption('modtwoauth_max_attempts', null, 3));
                if ($attempts >= $maxAttempts) {
                    unset($_SESSION['modtwoauth_frontend_totp_secret'], $_SESSION['modtwoauth_frontend_totp_created'], $_SESSION[$attemptsKey]);
                    m2afJson(['ok'=>false,'message'=>$T('modtwoauth_totp_setup_attempts_exceeded')], 429);
                }
                m2afJson(['ok'=>false,'message'=>$T('modtwoauth_totp_invalid_time')], 400);
            }
            require_once MODX_CORE_PATH . 'components/modtwoauth/src/SecurityCrypto.php';
            try {
                $storedSecret = \ModTwoAuth\SecurityCrypto::encrypt($secret);
            } catch (\Throwable $e) {
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Frontend TOTP encryption failed: ' . $e->getMessage());
                m2afJson(['ok'=>false,'message'=>$T('modtwoauth_totp_encryption_failed')], 500);
            }
            $setting->fromArray(['provider'=>'totp','secret'=>$storedSecret,'contact_value'=>null,'is_verified'=>1,'is_enabled'=>0,'updated_at'=>$now,'last_totp_counter'=>$matchedStep]);
            if (!$setting->save()) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_totp_save_failed')], 500);
            unset($_SESSION['modtwoauth_frontend_totp_secret'], $_SESSION['modtwoauth_frontend_totp_created'], $_SESSION['modtwoauth_frontend_totp_attempts']);
            m2afJson(['ok'=>true,'message'=>$T('modtwoauth_totp_configured_short')]);
        case 'send_email_code':
            $email = trim((string) $user->get('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_frontend_profile_email_invalid')], 400);
            // Ограничение частоты отправки: не даём забить почту повторными запросами.
            $sentAt = (int)($_SESSION['modtwoauth_frontend_email_created'] ?? 0);
            $cooldown = max(15, (int)$modx->getOption('modtwoauth_resend_cooldown', null, 60));
            if ($sentAt > 0 && (time() - $sentAt) < $cooldown) {
                m2afJson(['ok'=>false,'message'=>$T('modtwoauth_email_resend_wait', ['seconds'=>max(1, $cooldown - (time() - $sentAt))])], 429);
            }
            $code = (new \ModTwoAuth\Providers\EmailProvider($modx))->generateCode();
            if (!(new \ModTwoAuth\Providers\EmailProvider($modx))->sendCode($userId, $email, $code)) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_email_send_failed_frontend')], 500);
            $_SESSION['modtwoauth_frontend_email_hash'] = password_hash($code, PASSWORD_DEFAULT);
            $_SESSION['modtwoauth_frontend_email_created'] = time();
            $_SESSION['modtwoauth_frontend_email_value'] = $email;
            unset($_SESSION['modtwoauth_frontend_email_attempts']);
            $maskedEmail = preg_replace('/(^.).*(@.*$)/', '$1***$2', $email);
            m2afJson(['ok'=>true,'message'=>$T('modtwoauth_email_sent_masked', ['email'=>$maskedEmail])]);
        case 'confirm_email':
            $hash = (string) ($_SESSION['modtwoauth_frontend_email_hash'] ?? '');
            $createdAt = (int) ($_SESSION['modtwoauth_frontend_email_created'] ?? 0);
            $lifetime = max(30, (int) $modx->getOption('modtwoauth_code_lifetime', null, 300));
            if ($hash === '' || $createdAt <= 0 || time() - $createdAt > $lifetime) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_code_expired_request_new')], 400);
            $code = trim((string) ($payload['code'] ?? ''));
            $attemptsKey = 'modtwoauth_frontend_email_attempts';
            $attempts = (int) ($_SESSION[$attemptsKey] ?? 0) + 1;
            if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $hash)) {
                $_SESSION[$attemptsKey] = $attempts;
                $maxAttempts = max(1, (int) $modx->getOption('modtwoauth_max_attempts', null, 3));
                if ($attempts >= $maxAttempts) {
                    unset($_SESSION['modtwoauth_frontend_email_hash'], $_SESSION['modtwoauth_frontend_email_created'], $_SESSION['modtwoauth_frontend_email_value'], $_SESSION[$attemptsKey]);
                    m2afJson(['ok'=>false,'message'=>$T('modtwoauth_code_attempts_exceeded')], 429);
                }
                m2afJson(['ok'=>false,'message'=>$T('modtwoauth_email_code_invalid')], 400);
            }
            $email = (string) ($_SESSION['modtwoauth_frontend_email_value'] ?? $user->get('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_email_stored_invalid')], 400);
            $setting->fromArray(['provider'=>'email','secret'=>null,'contact_value'=>$email,'is_verified'=>1,'is_enabled'=>0,'updated_at'=>$now]);
            if (!$setting->save()) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_email_save_failed')], 500);
            unset($_SESSION['modtwoauth_frontend_email_hash'], $_SESSION['modtwoauth_frontend_email_created'], $_SESSION['modtwoauth_frontend_email_value'], $_SESSION[$attemptsKey]);
            m2afJson(['ok'=>true,'message'=>$T('modtwoauth_email_confirmed_short')]);
        case 'telegram_start':
            $old = $modx->getIterator('ModTwoAuth\\Model\\TwoAuthTelegramRequest', ['user_id'=>$userId,'kind'=>'link','status:IN'=>['pending','started']]);
            foreach ($old as $item) { $item->set('status','expired'); $item->save(); }
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $ts = time();
            $request = $modx->newObject('ModTwoAuth\\Model\\TwoAuthTelegramRequest');
            $request->fromArray(['user_id'=>$userId,'kind'=>'link','token_hash'=>hash('sha256',$token),'status'=>'pending','created_at'=>date('Y-m-d H:i:s',$ts),'expires_at'=>date('Y-m-d H:i:s',$ts+300)]);
            if (!$request->save()) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_telegram_request_create_failed')], 500);
            $telegram = new \ModTwoAuth\Providers\TelegramProvider($modx);
            $bot = $telegram->getBotUsername();
            if (!$bot) { $request->remove(); m2afJson(['ok'=>false,'message'=>$T('modtwoauth_telegram_bot_unavailable')], 500); }
            try {
                if (!$telegram->ensureWebhook()) {
                    $request->remove();
                    m2afJson(['ok'=>false,'message'=>$T('modtwoauth_telegram_webhook_failed')], 500);
                }
            } catch (\Throwable $e) {
                $request->remove();
                $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Frontend Telegram link: '.$e->getMessage());
                m2afJson(['ok'=>false,'message'=>$T('modtwoauth_telegram_webhook_failed')], 500);
            }
            $_SESSION['modtwoauth_frontend_tg_link_id'] = (int) $request->get('id');
            $deep = 'https://t.me/'.$bot.'?start='.$token;
            // QR is encoded locally by the bundled browser script; keep the one-time token off third-party services.
            m2afJson(['ok'=>true,'deep_link'=>$deep,'qr_data'=>$deep]);
        case 'telegram_status':
            $requestId = (int) ($_SESSION['modtwoauth_frontend_tg_link_id'] ?? 0);
            $request = $requestId ? $modx->getObject('ModTwoAuth\\Model\\TwoAuthTelegramRequest', ['id'=>$requestId,'user_id'=>$userId,'kind'=>'link']) : null;
            if (!$request) m2afJson(['ok'=>true,'status'=>'idle']);
            $status = (string) $request->get('status');
            if (strtotime((string)$request->get('expires_at')) < time() && $status !== 'confirmed') { $request->set('status','expired'); $request->save(); $status='expired'; }
            if ($status === 'confirmed') unset($_SESSION['modtwoauth_frontend_tg_link_id']);
            m2afJson(['ok'=>true,'status'=>$status]);
        case 'enable':
            if (!(int) $setting->get('is_verified') || !in_array((string)$setting->get('provider'), ['totp','email','telegram','webauthn'], true)) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_frontend_setup_verify_first')], 400);
            if ((string)$setting->get('provider') === 'webauthn') {
                $count = $modx->getCount('ModTwoAuth\\Model\\TwoAuthWebAuthnCredential', ['user_id'=>$userId]);
                if ($count < 1) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_frontend_register_passkey_first')], 400);
            }
            $setting->set('is_enabled',1); $setting->set('updated_at',$now);
            if (!$setting->save()) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_enable_failed')], 500);
            m2afJson(['ok'=>true,'message'=>$T('modtwoauth_frontend_enabled')]);
        case 'disable':
            /*
             * Отключение 2FA — единственное действие с проверкой пароля, и без
             * лимита оно превращается в канал его перебора. Счётчик хранится в
             * серверной сессии: сбросить его, имея только cookie, нельзя.
             */
            $pwAttemptsKey = 'modtwoauth_frontend_pw_attempts';
            $pwLockedKey = 'modtwoauth_frontend_pw_locked_until';
            if ((int) ($_SESSION[$pwLockedKey] ?? 0) > time()) {
                m2afJson(['ok'=>false,'message'=>$T('modtwoauth_password_attempts_later')], 429);
            }
            $password = (string) ($payload['password'] ?? '');
            if ($password === '' || !$user->passwordMatches($password)) {
                $pwAttempts = (int) ($_SESSION[$pwAttemptsKey] ?? 0) + 1;
                $_SESSION[$pwAttemptsKey] = $pwAttempts;
                $maxAttempts = max(1, (int) $modx->getOption('modtwoauth_max_attempts', null, 3));
                if ($pwAttempts >= $maxAttempts) {
                    $lockMinutes = max(1, (int) $modx->getOption('modtwoauth_lock_minutes', null, 15));
                    $_SESSION[$pwLockedKey] = time() + $lockMinutes * 60;
                    $_SESSION[$pwAttemptsKey] = 0;
                    m2afJson(['ok'=>false,'message'=>$T('modtwoauth_password_attempts_minutes', ['minutes'=>$lockMinutes])], 429);
                }
                m2afJson(['ok'=>false,'message'=>$T('modtwoauth_password_invalid')], 403);
            }
            unset($_SESSION[$pwAttemptsKey], $_SESSION[$pwLockedKey]);
            $setting->set('is_enabled',0); $setting->set('updated_at',$now);
            if (!$setting->save()) m2afJson(['ok'=>false,'message'=>$T('modtwoauth_disable_failed')], 500);
            m2afJson(['ok'=>true,'message'=>$T('modtwoauth_frontend_disabled_success')]);
        default: m2afJson(['ok'=>false,'message'=>$T('modtwoauth_unknown_action')], 400);
    }
} catch (\Throwable $e) {
    if (isset($modx) && $modx instanceof \MODX\Revolution\modX) $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Frontend settings error: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
    m2afJson(['ok'=>false,'message'=>isset($T) ? $T('modtwoauth_frontend_server_error') : ''], 500);
}
