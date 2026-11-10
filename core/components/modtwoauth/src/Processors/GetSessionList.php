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
use ModTwoAuth\SessionTracker;

/*
 * Права и CSRF-проверки наследуются из BaseProcessor: только mgr-сессия,
 * свой профиль или право edit_user. Список — read-only, GET разрешён.
 */
class GetSessionList extends BaseProcessor
{
    public function process()
    {
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/SessionTracker.php';

        $userId = (int) $this->getProperty('user_id');

        /*
         * Перед выводом сверяем записи с реальным хранилищем сессий MODX:
         * после «Завершить все сеансы» (TRUNCATE modx_session) и работы
         * сборщика мусора записи остаются «призраками». Здесь разрешён полный
         * скан для старых записей без php_session_id — экран списка открывается
         * редко, а пользователь должен видеть только реально живые сессии.
         */
        try {
            SessionTracker::gc($this->modx, $userId, true);
        } catch (\Throwable $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[modTwoAuth] Session list GC failed: ' . $e->getMessage());
        }

        $allowedSorts = ['created_at', 'last_active', 'ip_address'];
        $sort = (string) $this->getProperty('sort', 'last_active');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'last_active';
        }
        $dir = strtoupper((string) $this->getProperty('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $query = $this->modx->newQuery(TwoAuthSession::class);
        // A session with no activity for 30 days is considered expired for this UI.
        $activeSince = date('Y-m-d H:i:s', time() - (30 * 86400));
        $query->where([
            'user_id' => $userId,
            'last_active:>=' => $activeSince,
        ]);
        $query->sortby($sort, $dir);
        $query->limit(100, 0);

        $currentHash = hash('sha256', session_id());
        $rows = [];
        foreach ($this->modx->getCollection(TwoAuthSession::class, $query) as $session) {
            $row = [
                'id' => (int) $session->get('id'),
                'user_id' => (int) $session->get('user_id'),
                'ip_address' => (string) $session->get('ip_address'),
                'user_agent' => (string) $session->get('user_agent'),
                'created_at' => (string) $session->get('created_at'),
                'last_active' => (string) $session->get('last_active'),
                'is_current' => hash_equals((string) $session->get('session_token'), $currentHash),
            ];
            $rows[] = $row;
        }

        return $this->success('', $rows, ['total' => count($rows)]);
    }
}
