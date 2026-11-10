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

class TelegramLinkStatus extends BaseProcessor
{
    public function process()
    {
        $userId = (int) $this->getProperty('user_id');
        $requestId = (int) ($_SESSION['modtwoauth_tg_link_id'] ?? 0);

        if ($userId <= 0 || $requestId <= 0) {
            return $this->success($this->text('modtwoauth_telegram_waiting_link'), ['status' => 'idle']);
        }

        $request = $this->modx->getObject(TwoAuthTelegramRequest::class, [
            'id' => $requestId,
            'user_id' => $userId,
            'kind' => 'link',
        ]);

        if (!$request) {
            return $this->success($this->text('modtwoauth_telegram_waiting_link'), ['status' => 'idle']);
        }

        $status = (string) $request->get('status');
        $expiresAt = strtotime((string) $request->get('expires_at'));

        if ($expiresAt > 0 && $expiresAt < time() && !in_array($status, ['confirmed'], true)) {
            $request->set('status', 'expired');
            $request->save();
            $status = 'expired';
        }

        if ($status === 'confirmed') {
            unset($_SESSION['modtwoauth_tg_link_id'], $_SESSION['modtwoauth_tg_link_user_id'], $_SESSION['modtwoauth_tg_link_created']);
            return $this->success($this->text('modtwoauth_telegram_connected'), [
                'status' => 'confirmed',
                'connected' => true,
            ]);
        }

        if ($status === 'expired') {
            return $this->success($this->text('modtwoauth_telegram_qr_expired'), [
                'status' => 'expired',
                'connected' => false,
            ]);
        }

        return $this->success(
            $this->text($status === 'started' ? 'modtwoauth_telegram_confirm_in_bot' : 'modtwoauth_telegram_waiting_confirmation'),
            [
                'status' => $status,
                'connected' => false,
            ]
        );
    }
}
