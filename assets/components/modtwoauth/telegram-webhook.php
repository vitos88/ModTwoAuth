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
 * Public Telegram Bot API webhook for modTwoAuth.
 * No MODX manager authentication is required here.
 */

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';
require_once MODX_CORE_PATH . 'components/modtwoauth/src/Localization.php';

$modx = new \MODX\Revolution\modX();
$modx->initialize('web');
require_once MODX_CORE_PATH . 'components/modtwoauth/src/ErrorHandler.php';
\ModTwoAuth\ErrorHandler::register($modx, 'Telegram webhook');
$T = static function (string $key) use ($modx): string {
    return \ModTwoAuth\Localization::get($modx, $key);
};

$bootstrap = MODX_CORE_PATH . 'components/modtwoauth/bootstrap.php';
if (is_file($bootstrap)) {
    // MODX 3 сам подключает bootstrap неймспейса в _initNamespaces();
    // переменная $namespace нужна на случай, если этого не произошло.
    $namespace = ['path' => MODX_CORE_PATH . 'components/modtwoauth/'];
    require_once $bootstrap;
}

use ModTwoAuth\Model\TwoAuthTelegramRequest;
use ModTwoAuth\Model\TwoAuthUserSetting;
use ModTwoAuth\Providers\TelegramProvider;

$provider = new TelegramProvider($modx);

// Telegram signs outgoing webhook requests with this header when secret_token
// is configured. We derive the secret from the bot token without storing it.
$expectedSecret = $provider->getWebhookSecret();
$receivedSecret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($expectedSecret === '' || !hash_equals($expectedSecret, $receivedSecret)) {
    http_response_code(403);
    exit('Forbidden');
}

$raw = file_get_contents('php://input');
$update = json_decode($raw ?: '', true);
if (!is_array($update)) {
    http_response_code(400);
    exit('Bad request');
}

$now = time();

/*
 * Периодическая очистка отработавших запросов (старше суток), чтобы таблица
 * twoauth_telegram_requests не росла бесконечно. Срабатывает примерно на
 * каждом пятидесятом вебхуке — без отдельного cron-а.
 */
try {
    if (random_int(1, 50) === 1) {
        $modx->removeCollection(TwoAuthTelegramRequest::class, [
            'expires_at:<' => date('Y-m-d H:i:s', $now - 86400),
        ]);
    }
} catch (\Throwable $cleanupError) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Telegram requests cleanup failed: ' . $cleanupError->getMessage());
}

$findRequest = static function ($modx, string $token, string $kind) {
    if ($token === '') {
        return null;
    }
    return $modx->getObject(TwoAuthTelegramRequest::class, [
        'token_hash' => hash('sha256', $token),
        'kind' => $kind,
    ]);
};

$message = $update['message'] ?? null;
if (is_array($message)) {
    $chat = $message['chat'] ?? [];
    $from = $message['from'] ?? [];
    $text = trim((string) ($message['text'] ?? ''));
    $chatId = (string) ($chat['id'] ?? '');
    $telegramUserId = (string) ($from['id'] ?? '');

    if (($chat['type'] ?? '') === 'private' && $chatId !== '' && $telegramUserId !== '') {
        if (preg_match('/^\/start(?:@[^\s]+)?\s+([A-Za-z0-9_-]{1,64})$/u', $text, $matches)) {
            $token = $matches[1];
            $request = $findRequest($modx, $token, 'link');

            if ($request) {
                $expires = strtotime((string) $request->get('expires_at'));
                $status = (string) $request->get('status');

                if ($expires <= $now || in_array($status, ['expired', 'confirmed'], true)) {
                    $provider->sendMessage($chatId, $T('modtwoauth_telegram_link_expired'));
                } else {
                    $request->fromArray([
                        'telegram_user_id' => $telegramUserId,
                        'chat_id' => $chatId,
                        'status' => 'started',
                    ]);
                    if (!$request->save()) {
                        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Could not save the started Telegram link request.');
                        http_response_code(500);
                        exit;
                    }
                    if (!$provider->sendLinkConfirmation($chatId, $token)) {
                        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Could not send the Telegram link confirmation.');
                        http_response_code(500);
                        exit;
                    }
                }
            } else {
                $provider->sendMessage($chatId, $T('modtwoauth_telegram_link_not_found'));
            }
        } elseif (preg_match('/^\/start(?:@[^\s]+)?$/u', $text)) {
            $provider->sendMessage($chatId, $T('modtwoauth_telegram_start_help'));
        }
    }
}

$callback = $update['callback_query'] ?? null;
if (is_array($callback)) {
    $callbackId = (string) ($callback['id'] ?? '');
    $data = (string) ($callback['data'] ?? '');
    $from = $callback['from'] ?? [];
    $telegramUserId = (string) ($from['id'] ?? '');
    $callbackChatId = (string) (($callback['message']['chat']['id'] ?? ''));
    $messageId = (int) ($callback['message']['message_id'] ?? 0);

    if (preg_match('/^m2a_link:([A-Za-z0-9_-]{1,64})$/', $data, $matches)) {
        $request = $findRequest($modx, $matches[1], 'link');
        if (!$request) {
            $provider->answerCallback($callbackId, $T('modtwoauth_telegram_request_not_found'), true);
        } else {
            $expires = strtotime((string) $request->get('expires_at'));
            $status = (string) $request->get('status');
            $storedTelegramUserId = (string) $request->get('telegram_user_id');
            $storedChatId = (string) $request->get('chat_id');

            if ($expires <= $now || $status !== 'started'
                || !hash_equals($storedTelegramUserId, $telegramUserId)
                || !hash_equals($storedChatId, $callbackChatId)
            ) {
                $provider->answerCallback($callbackId, $T('modtwoauth_telegram_link_invalid'), true);
            } else {
                $userId = (int) $request->get('user_id');
                $user = $modx->getObject('modUser', $userId);
                $setting = $modx->getObject(TwoAuthUserSetting::class, ['user_id' => $userId]);
                if (!$user || !(int) $user->get('active')) {
                    $provider->answerCallback($callbackId, $T('modtwoauth_error_user_unavailable'), true);
                } elseif ($setting && (int) $setting->get('is_enabled')) {
                    $provider->answerCallback($callbackId, $T('modtwoauth_error_disable_2fa_first'), true);
                } else {
                    if (!$setting) {
                        $setting = $modx->newObject(TwoAuthUserSetting::class);
                        $setting->set('user_id', $userId);
                    }

                    $setting->fromArray([
                        'provider' => 'telegram',
                        'secret' => null,
                        'contact_value' => $callbackChatId,
                        'is_enabled' => 0,
                        'is_verified' => 1,
                        'last_totp_counter' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    if (!$setting->save()) {
                        $provider->answerCallback($callbackId, $T('modtwoauth_telegram_link_save_failed'), true);
                    } else {
                        $request->fromArray([
                            'status' => 'confirmed',
                            'confirmed_at' => date('Y-m-d H:i:s'),
                        ]);
                        $request->save();
                        $provider->answerCallback($callbackId, $T('modtwoauth_telegram_linked'));
                        if ($messageId > 0) {
                            $provider->editMessage($callbackChatId, $messageId, $T('modtwoauth_telegram_linked_message'));
                        }
                    }
                }
            }
        }
    } elseif (preg_match('/^m2a_login:([A-Za-z0-9_-]{1,64})$/', $data, $matches)) {
        $request = $findRequest($modx, $matches[1], 'login');
        if (!$request) {
            $provider->answerCallback($callbackId, $T('modtwoauth_telegram_login_not_found'), true);
        } else {
            $expires = strtotime((string) $request->get('expires_at'));
            $storedTelegramUserId = (string) $request->get('telegram_user_id');
            $storedChatId = (string) $request->get('chat_id');
            if ($expires <= $now || ($callback['message']['chat']['type'] ?? '') !== 'private'
                || ($storedTelegramUserId !== '' && !hash_equals($storedTelegramUserId, $telegramUserId))
                || !hash_equals($storedChatId, $callbackChatId)
            ) {
                $provider->answerCallback($callbackId, $T('modtwoauth_telegram_login_invalid'), true);
            } elseif ((string) $request->get('status') !== 'pending') {
                $provider->answerCallback($callbackId, $T('modtwoauth_telegram_request_processed'), true);
            } else {
                $request->fromArray([
                    'telegram_user_id' => $telegramUserId,
                    'status' => 'approved',
                    'confirmed_at' => date('Y-m-d H:i:s'),
                ]);
                if (!$request->save()) {
                    $provider->answerCallback($callbackId, $T('modtwoauth_telegram_confirmation_save_failed'), true);
                    http_response_code(500);
                    exit;
                }
                $provider->answerCallback($callbackId, $T('modtwoauth_telegram_login_allowed'));
                if ($messageId > 0) {
                    $provider->editMessage($callbackChatId, $messageId, $T('modtwoauth_telegram_login_allowed_message'));
                }
            }
        }
    } elseif (preg_match('/^m2a_deny:([A-Za-z0-9_-]{1,64})$/', $data, $matches)) {
        $request = $findRequest($modx, $matches[1], 'login');
        if (!$request) {
            $provider->answerCallback($callbackId, $T('modtwoauth_telegram_login_not_found'), true);
        } else {
            $expires = strtotime((string) $request->get('expires_at'));
            $storedTelegramUserId = (string) $request->get('telegram_user_id');
            $storedChatId = (string) $request->get('chat_id');
            if ($expires <= $now || ($callback['message']['chat']['type'] ?? '') !== 'private'
                || ($storedTelegramUserId !== '' && !hash_equals($storedTelegramUserId, $telegramUserId))
                || !hash_equals($storedChatId, $callbackChatId)
            ) {
                $provider->answerCallback($callbackId, $T('modtwoauth_telegram_login_invalid'), true);
            } elseif ((string) $request->get('status') !== 'pending') {
                $provider->answerCallback($callbackId, $T('modtwoauth_telegram_request_processed'), true);
            } else {
                $request->set('status', 'denied');
                $request->set('confirmed_at', date('Y-m-d H:i:s'));
                $request->save();
                $provider->answerCallback($callbackId, $T('modtwoauth_telegram_login_denied'));
                if ($messageId > 0) {
                    $provider->editMessage($callbackChatId, $messageId, $T('modtwoauth_telegram_login_denied_message'));
                }
            }
        }
    }
}

http_response_code(200);
echo 'OK';
