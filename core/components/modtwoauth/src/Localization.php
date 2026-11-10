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

/**
 * Central lexicon helper of the component.
 *
 * Loads all component topics on demand and returns a translated UI
 * string with a safe English fallback for languages that do not yet
 * ship a native translation of a key.
 */
final class Localization
{
    /**
     * All lexicon topics of the component.
     *
     * The 'setting' topic is not listed: MODX loads it automatically
     */
    private const TOPICS = [
        'modtwoauth:default',
        'modtwoauth:dashboard',
        'modtwoauth:errors',
        'modtwoauth:frontend',
        'modtwoauth:manager',
        'modtwoauth:passkey',
        'modtwoauth:telegram',
        'modtwoauth:totp',
        'modtwoauth:verification_page',
    ];

    /** @var array<string, bool> Topics already loaded during this request, per language ('' = current). */
    private static $loaded = [];

    /**
     * Loads every component topic once per request.
     *
     * @param modX $modx
     * @param string|null $language Pass a language code ('en') or null for the current culture key.
     */
    public static function loadTopics(modX $modx, $language = null): void
    {
        $mapKey = (string) $language;

        if (!empty(self::$loaded[$mapKey])) {
            return;
        }

        foreach (self::TOPICS as $topic) {
            if ($language === null) {
                $modx->lexicon->load($topic);
            } else {
                $modx->lexicon->load($language . ':' . $topic);
            }
        }

        self::$loaded[$mapKey] = true;
    }

    /**
     * Returns a translated UI string with a safe English fallback for languages
     * that do not yet ship a native translation of a newly introduced key.
     */
    public static function get(modX $modx, string $key, array $placeholders = [], string $fallback = ''): string
    {
        self::loadTopics($modx);

        $value = (string) $modx->lexicon($key, $placeholders);

        if ($value === '' || $value === $key) {
            self::loadTopics($modx, 'en');
            $value = (string) $modx->lexicon($key, $placeholders);
        }

        return $value !== '' && $value !== $key ? $value : $fallback;
    }
}
