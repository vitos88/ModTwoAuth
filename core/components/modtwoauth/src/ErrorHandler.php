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

namespace ModTwoAuth;

use MODX\Revolution\modX;

/** Logs PHP runtime errors from standalone component endpoints to the MODX error log. */
final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(modX $modx, string $channel): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $previous = null;
        $previous = set_error_handler(
            static function (int $level, string $message, string $file = '', int $line = 0) use ($modx, $channel, &$previous): bool {
                if ((error_reporting() & $level) !== 0) {
                    $modx->log(
                        modX::LOG_LEVEL_ERROR,
                        '[ModTwoAuth] ' . $channel . ' PHP error: ' . $message . ' @ ' . $file . ':' . $line
                    );
                }

                if (is_callable($previous)) {
                    return (bool) $previous($level, $message, $file, $line);
                }
                return false;
            }
        );

        register_shutdown_function(static function () use ($modx, $channel): void {
            $error = error_get_last();
            if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            $modx->log(
                modX::LOG_LEVEL_ERROR,
                '[ModTwoAuth] ' . $channel . ' fatal PHP error: ' . $error['message']
                    . ' @ ' . $error['file'] . ':' . $error['line']
            );
        });
    }
}
