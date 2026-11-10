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

/** @var xPDO\Transport\xPDOTransport $transport */
/** @var array $options */
if (!$transport->xpdo) return true;
$modx = $transport->xpdo;
if (!in_array($options[xPDOTransport::PACKAGE_ACTION] ?? '', [xPDOTransport::ACTION_INSTALL, xPDOTransport::ACTION_UPGRADE], true)) return true;
$configuredKeyPath = getenv('MODTWOAUTH_KEY_PATH');
if (!is_string($configuredKeyPath) || $configuredKeyPath === '') {
    $configuredKeyPath = trim((string) $modx->getOption('modtwoauth_key_path', null, ''));
}
// Resolve relative paths against the site root (MODX_BASE_PATH) instead of the current working directory.
if ($configuredKeyPath !== '' && $configuredKeyPath[0] !== '/' && $configuredKeyPath[0] !== '\\'
    && !preg_match('/^[A-Za-z]:[\/\\\\]/', $configuredKeyPath)) {
    $base = defined('MODX_BASE_PATH') ? rtrim((string) MODX_BASE_PATH, '/\\') : '';
    if ($base === '' && defined('MODX_CORE_PATH')) {
        $base = rtrim(dirname(rtrim((string) MODX_CORE_PATH, '/\\')), '/\\');
    }
    if ($base !== '') {
        $configuredKeyPath = $base . '/' . ltrim($configuredKeyPath, '/\\');
    }
}
$defaultKeyFile = rtrim(MODX_CORE_PATH, '/\\') . '/config/modtwoauth.key';
$keyFile = ($configuredKeyPath !== '') ? $configuredKeyPath : $defaultKeyFile;
$keyDir = dirname($keyFile);
if (!is_dir($keyDir) || !is_writable($keyDir)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Cannot create encryption key at configured path. Set MODTWOAUTH_KEY_PATH to a secure writable path. TOTP encryption migration was not run.');
    return true;
}
if (!is_file($keyFile) && $keyFile !== $defaultKeyFile && is_file($defaultKeyFile) && is_readable($defaultKeyFile)) {
    if (@copy($defaultKeyFile, $keyFile)) @chmod($keyFile, 0600);
}
if (!is_file($keyFile)) {
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[ModTwoAuth] PHP sodium is missing. TOTP secrets were not encrypted; install sodium and rerun package upgrade.');
        return true;
    }
    $key = base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    // Исключительное создание не даёт двум параллельным установкам перезаписать
    // ключ, которым уже могли быть зашифрованы часть секретов.
    $handle = @fopen($keyFile, 'x');
    if ($handle === false) {
        if (!is_file($keyFile)) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Failed creating encryption key; TOTP migration skipped.');
            return true;
        }
    } else {
        $written = fwrite($handle, $key . "\n");
        fclose($handle);
        @chmod($keyFile, 0600);
        if ($written === false) {
            @unlink($keyFile);
            $modx->log(modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Failed writing encryption key; TOTP migration skipped.');
            return true;
        }
    }
}
if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[ModTwoAuth] PHP sodium is missing. Existing TOTP secrets remain unchanged.');
    return true;
}
try {
    require_once MODX_CORE_PATH . 'components/modtwoauth/src/SecurityCrypto.php';
    $modx->addPackage('ModTwoAuth\\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');
    $class = 'ModTwoAuth\\Model\\TwoAuthUserSetting';
    foreach ($modx->getIterator($class, ['provider' => 'totp']) as $setting) {
        $secret = (string) $setting->get('secret');
        if ($secret !== '' && !\ModTwoAuth\SecurityCrypto::isEncrypted($secret)) {
            $setting->set('secret', \ModTwoAuth\SecurityCrypto::encrypt($secret));
            if (!$setting->save()) $modx->log(modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Failed migrating TOTP secret for user ID ' . (int)$setting->get('user_id'));
        }
    }
    $modx->log(modX::LOG_LEVEL_INFO, '[ModTwoAuth] TOTP secret encryption migration completed. Preserve core/config/modtwoauth.key in secure backups.');
} catch (Throwable $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Security migration failed: ' . $e->getMessage());
}
return true;
