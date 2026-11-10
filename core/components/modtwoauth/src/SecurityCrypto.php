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

/** Authenticated encryption for persisted TOTP secrets. Key stays outside the component tree. */
final class SecurityCrypto
{
    private const PREFIX = 'm2aenc:v1:';

    public static function keyPath(?\MODX\Revolution\modX $modx = null): string
    {
        if (defined('MODTWOAUTH_KEY_PATH') && MODTWOAUTH_KEY_PATH !== '') return self::resolvePath((string) MODTWOAUTH_KEY_PATH);
        $env = getenv('MODTWOAUTH_KEY_PATH');
        if (is_string($env) && trim($env) !== '') return self::resolvePath(trim($env));
        $modx = $modx ?? ($GLOBALS['modx'] ?? null);
        if (is_object($modx) && method_exists($modx, 'getOption')) {
            $configured = trim((string) $modx->getOption('modtwoauth_key_path', null, ''));
            if ($configured !== '') {
                $configured = self::resolvePath($configured);
                $default = defined('MODX_CORE_PATH') ? rtrim(MODX_CORE_PATH, '/\\') . '/config/modtwoauth.key' : '';
                if (!is_file($configured) && $default !== '' && is_file($default) && is_readable($default)) {
                    $dir = dirname($configured);
                    if (is_dir($dir) && is_writable($dir) && @copy($default, $configured)) @chmod($configured, 0600);
                }
                return $configured;
            }
        }
        return defined('MODX_CORE_PATH') ? rtrim(MODX_CORE_PATH, '/\\') . '/config/modtwoauth.key' : '';
    }

    /** Resolve a relative path against the site root (MODX_BASE_PATH); absolute paths are returned unchanged. */
    private static function resolvePath(string $path): string
    {
        if ($path === '' || $path[0] === '/' || $path[0] === '\\') return $path;
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) return $path; // Windows absolute (C:\ or C:/)
        $base = '';
        if (defined('MODX_BASE_PATH')) {
            $base = rtrim((string) MODX_BASE_PATH, '/\\');
        } elseif (defined('MODX_CORE_PATH')) {
            $base = rtrim(dirname(rtrim((string) MODX_CORE_PATH, '/\\')), '/\\');
        }
        return ($base !== '') ? $base . '/' . ltrim($path, '/\\') : $path;
    }

    public static function available(): bool
    {
        $path = self::keyPath();
        return $path !== '' && is_file($path) && is_readable($path)
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt');
    }

    public static function isEncrypted(string $value): bool
    {
        return strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    private static function key(): string
    {
        $path = self::keyPath();
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('ModTwoAuth encryption key is unavailable.');
        }
        $key = trim((string) file_get_contents($path));
        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new \RuntimeException('ModTwoAuth encryption key is invalid.');
        }
        return $decoded;
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '' || self::isEncrypted($plain)) return $plain;
        if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new \RuntimeException('PHP sodium extension is required to encrypt TOTP secrets.');
        }
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, 'modtwoauth:totp:v1', $nonce, self::key());
        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '' || !self::isEncrypted($stored)) return $stored; // legacy value, migrated by package resolver
        if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            throw new \RuntimeException('PHP sodium extension is required to decrypt TOTP secrets.');
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($raw === false || strlen($raw) <= $nonceLength) throw new \RuntimeException('Encrypted TOTP secret is malformed.');
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $nonceLength), 'modtwoauth:totp:v1', substr($raw, 0, $nonceLength), self::key());
        if ($plain === false) throw new \RuntimeException('TOTP secret authentication failed.');
        return $plain;
    }
}
