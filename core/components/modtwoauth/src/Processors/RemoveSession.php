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

use ModTwoAuth\Model\TwoAuthSession;

/*
 * Права и CSRF-проверки наследуются из BaseProcessor (mgr-сессия, свой
 * профиль или право edit_user). Завершение сессии — операция, меняющая
 * состояние, поэтому через штатный коннектор она обязана приходить POST-ом
 * с того же источника (см. BaseProcessor::isSameOriginPost).
 */
class RemoveSession extends BaseProcessor
{
    public function process()
    {
        $id = (int) $this->getProperty('id');
        $userId = (int) $this->getProperty('user_id');
        if ($id <= 0 || $userId <= 0) {
            return $this->failure($this->text('modtwoauth_session_or_user_missing'));
        }

        $session = $this->modx->getObject(TwoAuthSession::class, [
            'id' => $id,
            'user_id' => $userId,
        ]);
        if (!$session) {
            return $this->failure($this->text('modtwoauth_session_not_found'));
        }

        $currentHash = hash('sha256', session_id());
        if (hash_equals((string) $session->get('session_token'), $currentHash)) {
            return $this->failure($this->text('modtwoauth_session_current_manager'));
        }

        return $session->remove()
            ? $this->success($this->text('modtwoauth_session_revoked_manager'))
            : $this->failure($this->text('modtwoauth_session_revoke_failed'));
    }
}
