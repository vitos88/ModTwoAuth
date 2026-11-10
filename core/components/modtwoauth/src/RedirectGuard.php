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

/**
 * Защита от открытых редиректов (open redirect) в потоке веб-верификации 2FA.
 *
 * Разрешены только:
 *  - относительные пути внутри сайта (начинаются с одной "/"); "//host" запрещён;
 *  - абсолютные URL, чей хост совпадает с хостом сайта (сравнение без учёта регистра).
 * Всё остальное заменяется на безопасное значение по умолчанию.
 */
final class RedirectGuard
{
    public static function safe(string $candidate, string $fallback): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return $fallback;
        }
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $candidate)) {
            return $fallback;
        }

        // Только путь внутри сайта. Браузеры могут трактовать обратный слэш
        // как разделитель URL, поэтому все его варианты отклонены выше.
        if ($candidate[0] === '/') {
            return isset($candidate[1]) && $candidate[1] === '/' ? $fallback : $candidate;
        }

        // Абсолютный URL должен сохранять не только хост, но и схему и порт сайта.
        // Иначе HTTPS-вход мог завершаться редиректом на HTTP или другой сервис.
        if (preg_match('#^https?://#i', $candidate)) {
            $siteParts = parse_url($fallback);
            $candParts = parse_url($candidate);
            if (!is_array($siteParts) || !is_array($candParts)
                || isset($candParts['user']) || isset($candParts['pass'])
                || empty($siteParts['host']) || empty($candParts['host'])
            ) {
                return $fallback;
            }
            $siteScheme = strtolower((string) ($siteParts['scheme'] ?? 'https'));
            $candScheme = strtolower((string) ($candParts['scheme'] ?? ''));
            $sitePort = (int) ($siteParts['port'] ?? ($siteScheme === 'https' ? 443 : 80));
            $candPort = (int) ($candParts['port'] ?? ($candScheme === 'https' ? 443 : 80));
            if ($candScheme === $siteScheme
                && $candPort === $sitePort
                && strcasecmp((string) $candParts['host'], (string) $siteParts['host']) === 0
            ) {
                return $candidate;
            }
        }

        return $fallback;
    }
}
