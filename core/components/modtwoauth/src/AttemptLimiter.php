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

/** Shared per-account lockout state for all second-factor login verifiers. */
final class AttemptLimiter
{
    public static function lockedUntil($setting): int
    {
        $value = (string) ($setting ? ($setting->get('locked_until') ?? '') : '');
        $time = $value !== '' ? strtotime($value) : false;
        return $time !== false && $time > time() ? $time : 0;
    }

    public static function remaining($setting): int
    {
        return max(0, self::lockedUntil($setting) - time());
    }

    public static function formatRemaining(int $seconds): string
    {
        $seconds = max(1, $seconds);
        return sprintf('%02d:%02d', (int) floor($seconds / 60), $seconds % 60);
    }

    public static function translate($modx, string $key, array $placeholders, string $fallback): string
    {
        /**
         * The line was replaced with delegation to Localization::get()
         * $modx->lexicon->load('modtwoauth:default');
         * $value = (string) $modx->lexicon($key, $placeholders);
         * return $value !== '' && $value !== $key ? $value : $fallback;
         */
        require_once __DIR__ . '/Localization.php';
        return Localization::get($modx, $key, $placeholders, $fallback);
    }

    public static function lockedMessage($modx, $setting): string
    {
        $remaining = self::remaining($setting);
        $time = self::formatRemaining($remaining);
        return self::translate(
            $modx,
            'modtwoauth_verify_locked_retry',
            ['time' => $time],
            'Too many incorrect attempts. Try again in ' . $time . '.'
        );
    }

    public static function attemptsLeftMessage($modx, int $left): string
    {
        $left = max(0, $left);
        return self::translate(
            $modx,
            'modtwoauth_verify_attempts_left',
            ['left' => $left],
            'Invalid verification code. Attempts left: ' . $left . '.'
        );
    }

    /** @return array{attempts:int,max:int,locked:bool,minutes:int} */
    public static function fail($modx, $setting): array
    {
        // A user can remain on the verification page until the old lock expires.
        // Start a fresh attempt window instead of instantly locking them again.
        $lockedUntil = (string) ($setting->get('locked_until') ?? '');
        if ($lockedUntil !== '' && self::lockedUntil($setting) === 0) {
            $setting->set('login_attempts', 0);
            $setting->set('locked_until', null);
        }

        $attempts = (int) $setting->get('login_attempts') + 1;
        $max = max(1, (int) $modx->getOption('modtwoauth_max_attempts', null, 3));
        $minutes = max(1, (int) $modx->getOption('modtwoauth_lock_minutes', null, 15));
        $setting->set('login_attempts', $attempts);
        $locked = $attempts >= $max;
        if ($locked) $setting->set('locked_until', date('Y-m-d H:i:s', time() + $minutes * 60));
        $setting->save();
        return ['attempts' => $attempts, 'max' => $max, 'locked' => $locked, 'minutes' => $minutes];
    }

    public static function reset($setting): void
    {
        $setting->set('login_attempts', 0);
        $setting->set('locked_until', null);
        $setting->save();
    }
}
