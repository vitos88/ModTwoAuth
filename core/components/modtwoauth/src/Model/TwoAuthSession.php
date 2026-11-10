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

namespace ModTwoAuth\Model;

use xPDO\xPDO;

/**
 * Class TwoAuthSession
 *
 * @property int $user_id
 * @property string $session_token
 * @property string|null $php_session_id
 * @property string $ip_address
 * @property string $user_agent
 * @property string $last_active
 * @property string $created_at
 *
 * @package ModTwoAuth\Model
 */
class TwoAuthSession extends \xPDO\Om\xPDOSimpleObject
{
}