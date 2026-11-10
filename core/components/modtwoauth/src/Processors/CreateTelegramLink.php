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

namespace ModTwoAuth\Processors;

use ModTwoAuth\Model\TwoAuthTelegramRequest;
use ModTwoAuth\Providers\TelegramProvider;

class CreateTelegramLink extends BaseProcessor
{
    public function process()
    {
        $userId = (int) $this->getProperty('user_id');
        if ($userId <= 0) {
            return $this->failure($this->text('modtwoauth_user_not_found'));
        }

        // Remove previous unfinished links for this user.
        $old = $this->modx->getIterator(TwoAuthTelegramRequest::class, [
            'user_id' => $userId,
            'kind' => 'link',
            'status:IN' => ['pending', 'started'],
        ]);
        foreach ($old as $request) {
            $request->set('status', 'expired');
            $request->save();
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = time();
        $expires = $now + 300;

        $request = $this->modx->newObject(TwoAuthTelegramRequest::class);
        $request->fromArray([
            'user_id' => $userId,
            'kind' => 'link',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $expires),
        ]);

        if (!$request->save()) {
            return $this->failure($this->text('modtwoauth_telegram_link_create_failed'));
        }

        $provider = new TelegramProvider($this->modx);
        $username = $provider->getBotUsername();
        if (!$username) {
            $request->remove();
            return $this->failure($this->text('modtwoauth_telegram_bot_unavailable'));
        }

        // Linking cannot complete without webhook delivery: /start and the
        // confirmation button are both processed by telegram-webhook.php.
        if (!$provider->ensureWebhook()) {
            $request->remove();
            return $this->failure($this->text('modtwoauth_telegram_webhook_failed'));
        }

        $_SESSION['modtwoauth_tg_link_id'] = (int) $request->get('id');
        $_SESSION['modtwoauth_tg_link_user_id'] = $userId;
        $_SESSION['modtwoauth_tg_link_created'] = $now;

        $deepLink = 'https://t.me/' . $username . '?start=' . $token;
        // The browser generates the QR locally; do not send the one-time token to a QR service.
        $qrData = $deepLink;

        return $this->success($this->text('modtwoauth_telegram_qr_ready'), [
            'bot_username' => $username,
            'deep_link' => $deepLink,
            'qr_data' => $qrData,
            'expires_in' => 300,
        ]);
    }
}
