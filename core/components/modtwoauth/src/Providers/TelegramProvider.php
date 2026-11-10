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

namespace ModTwoAuth\Providers;

use MODX\Revolution\modX;
use ModTwoAuth\Localization;

class TelegramProvider extends BaseProvider
{
    private function request(string $method, array $params = []): ?array
    {
        $token = trim((string) $this->modx->getOption('modtwoauth_telegram_bot_token'));
        if ($token === '' || !function_exists('curl_init')) {
            return null;
        }

        $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modTwoAuth] Telegram request failed: ' . $error
            );
            return null;
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modTwoAuth] Telegram API ' . $method . ' returned invalid JSON.'
            );
            return null;
        }
        if (empty($result['ok'])) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modTwoAuth] Telegram API ' . $method . ' failed: '
                    . (int) ($result['error_code'] ?? 0) . ' '
                    . (string) ($result['description'] ?? 'Unknown error')
            );
        }
        return $result;
    }

    public function getBotUsername(): ?string
    {
        $result = $this->request('getMe');
        $username = $result['result']['username'] ?? null;
        return is_string($username) && $username !== '' ? $username : null;
    }

    public function sendCode(int $userId, string $contact, string $code): bool
    {
        if ($contact === '') {
            return false;
        }

        $lifetime = max(30, (int) $this->modx->getOption('modtwoauth_code_lifetime', null, 300));
        $minutes = max(1, (int) ceil($lifetime / 60));
        $result = $this->request('sendMessage', [
            'chat_id' => $contact,
            'text' => Localization::get($this->modx, 'modtwoauth_telegram_code_message', [
                'code' => $code,
                'minutes' => $minutes,
            ]),
        ]);

        return !empty($result['ok']);
    }

    /** Send a plain text message to a chat (used by the webhook replies). */
    public function sendMessage(string $chatId, string $text): bool
    {
        if ($chatId === '') {
            return false;
        }

        $result = $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        return !empty($result['ok']);
    }

    public function sendLoginChallenge(string $chatId, string $code, string $token, string $siteName = 'MODX'): bool
    {
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => Localization::get($this->modx, 'modtwoauth_telegram_allow_login'), 'callback_data' => 'm2a_login:' . $token],
                    ['text' => Localization::get($this->modx, 'modtwoauth_telegram_deny_login'), 'callback_data' => 'm2a_deny:' . $token],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $lifetime = max(30, (int) $this->modx->getOption('modtwoauth_code_lifetime', null, 300));
        $minutes = max(1, (int) ceil($lifetime / 60));
        $text = Localization::get($this->modx, 'modtwoauth_telegram_login_message', [
            'site' => $siteName,
            'code' => $code,
            'minutes' => $minutes,
        ]);

        $result = $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard,
        ]);

        return !empty($result['ok']);
    }

    public function sendLinkConfirmation(string $chatId, string $token): bool
    {
        $keyboard = json_encode([
            'inline_keyboard' => [
                [[
                    'text' => Localization::get($this->modx, 'modtwoauth_telegram_confirm_link'),
                    'callback_data' => 'm2a_link:' . $token,
                ]],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => Localization::get($this->modx, 'modtwoauth_telegram_link_message'),
            'reply_markup' => $keyboard,
        ]);

        return !empty($result['ok']);
    }

    public function answerCallback(string $callbackId, string $text, bool $alert = false): bool
    {
        $result = $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $alert ? 'true' : 'false',
        ]);
        return !empty($result['ok']);
    }

    public function editMessage(string $chatId, int $messageId, string $text): bool
    {
        $result = $this->request('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ]);
        return !empty($result['ok']);
    }

    public function ensureWebhook(): bool
    {
        $siteUrl = trim((string) $this->modx->getOption('site_url'));
        if ($siteUrl === '' || stripos($siteUrl, 'https://') !== 0) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modTwoAuth] Telegram webhook requires a public HTTPS site_url.'
            );
            return false;
        }

        $assetsUrl = trim((string) $this->modx->getOption('assets_url', null, MODX_ASSETS_URL));
        if (preg_match('#^https?://#i', $assetsUrl)) {
            $webhookUrl = rtrim($assetsUrl, '/') . '/components/modtwoauth/telegram-webhook.php';
        } else {
            $webhookUrl = rtrim($siteUrl, '/') . '/' . ltrim($assetsUrl, '/')
                . 'components/modtwoauth/telegram-webhook.php';
        }
        if (stripos($webhookUrl, 'https://') !== 0) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modTwoAuth] Telegram webhook URL must use HTTPS: ' . $webhookUrl
            );
            return false;
        }

        $botToken = trim((string) $this->modx->getOption('modtwoauth_telegram_bot_token'));
        $secret = substr(hash('sha256', 'modTwoAuth:webhook:' . $botToken), 0, 64);

        $result = $this->request('setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);

        return !empty($result['ok']);
    }

    public function getWebhookSecret(): string
    {
        $botToken = trim((string) $this->modx->getOption('modtwoauth_telegram_bot_token'));
        // Без настроенного токена бота секрета нет: вебхук должен отвергать любые запросы,
        // иначе «секрет» превратился бы в известную всем константу.
        if ($botToken === '') {
            return '';
        }
        return substr(hash('sha256', 'modTwoAuth:webhook:' . $botToken), 0, 64);
    }

    public function validateChatId(string $chatId): bool
    {
        if ($chatId === '') {
            return false;
        }

        $result = $this->request('getChat', ['chat_id' => $chatId]);
        return !empty($result['ok']);
    }
}
