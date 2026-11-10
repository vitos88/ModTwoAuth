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

namespace ModTwoAuth\Providers;

use MODX\Revolution\modX;
use ModTwoAuth\Localization;
use ModTwoAuth\Model\TwoAuthWebAuthnCredential;

/**
 * Small dependency-free WebAuthn/FIDO2 verifier for manager 2FA.
 * Registration uses attestation="none" and supports ES256, RS256 and Ed25519 credentials.
 */
class WebAuthnProvider extends BaseProvider
{
    /**
     * WebAuthn does not deliver one-time codes. BaseProvider is shared by
     * the code-based providers, so satisfy its contract with a no-op false
     * implementation. WebAuthn registration/login use their dedicated
     * challenge/assertion methods below.
     */
    public function sendCode(int $userId, string $contact, string $code): bool
    {
        return false;
    }

    private function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function b64urlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode($data, true);
        return $decoded === false ? '' : $decoded;
    }

    private function contextUrl(): string
    {
        $context = $this->modx->context ? (string) $this->modx->context->get('key') : 'web';
        $option = $context === 'mgr' ? 'manager_url' : 'site_url';
        return (string) $this->modx->getOption($option, null, '');
    }

    private function validHost(string $host): string
    {
        $host = strtolower(trim($host, '.'));
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') $host = strtolower($ascii);
        }
        return preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host) ? $host : '';
    }

    public function rpId(): string
    {
        $configured = trim((string) $this->modx->getOption('modtwoauth_webauthn_rp_id', null, ''));
        if ($configured !== '') {
            // Accept both a bare RP ID (example.com) and an accidentally supplied URL.
            $candidate = $configured;
            if (preg_match('#^https?://#i', $candidate)) {
                $candidate = (string) parse_url($candidate, PHP_URL_HOST);
            }
            $candidate = preg_replace('/:\d+$/', '', (string) $candidate);
            $valid = $this->validHost((string) $candidate);
            if ($valid !== '') return $valid;
        }
        $host = (string) parse_url($this->contextUrl(), PHP_URL_HOST);
        if ($host === '') {
            $host = (string) parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        }
        return $this->validHost($host);
    }

    public function origin(): string
    {
        $url = $this->contextUrl();
        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            $host = (string) parse_url('//' . $httpHost, PHP_URL_HOST);
            $port = parse_url('//' . $httpHost, PHP_URL_PORT);
        }
        $host = $this->validHost($host);
        if ($host === '') $host = 'localhost';
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }
        $origin = $scheme . '://' . $host;
        if ($port && !(($scheme === 'https' && (int) $port === 443) || ($scheme === 'http' && (int) $port === 80))) {
            $origin .= ':' . (int) $port;
        }
        return $origin;
    }

    public function registrationOptions(int $userId, string $username, string $displayName): array
    {
        $rpId = $this->rpId();
        if ($rpId === '') {
            throw new \RuntimeException('WebAuthn RP ID is not configured correctly.');
        }
        $challenge = random_bytes(32);
        $_SESSION['modtwoauth_webauthn_reg_challenge'] = $this->b64urlEncode($challenge);
        $_SESSION['modtwoauth_webauthn_reg_user_id'] = $userId;
        $_SESSION['modtwoauth_webauthn_reg_created'] = time();

        $exclude = [];
        $credentials = $this->modx->getCollection(TwoAuthWebAuthnCredential::class, ['user_id' => $userId]);
        foreach ($credentials as $credential) {
            $exclude[] = ['type' => 'public-key', 'id' => (string) $credential->get('credential_id')];
        }

        return [
            'challenge' => $this->b64urlEncode($challenge),
            'rp' => ['name' => (string) $this->modx->getOption('site_name', null, 'MODX'), 'id' => $rpId],
            'user' => [
                'id' => $this->b64urlEncode(hash('sha256', 'modtwoauth:user:' . $userId, true)),
                'name' => $username,
                'displayName' => $displayName ?: $username,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
                ['type' => 'public-key', 'alg' => -8],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $exclude,
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'preferred',
            ],
        ];
    }

    public function loginOptions(int $userId): array
    {
        $challenge = random_bytes(32);
        $_SESSION['modtwoauth_webauthn_login_challenge'] = $this->b64urlEncode($challenge);
        $_SESSION['modtwoauth_webauthn_login_user_id'] = $userId;
        $_SESSION['modtwoauth_webauthn_login_created'] = time();

        $allow = [];
        $credentials = $this->modx->getCollection(TwoAuthWebAuthnCredential::class, ['user_id' => $userId]);
        foreach ($credentials as $credential) {
            $allow[] = ['type' => 'public-key', 'id' => (string) $credential->get('credential_id')];
        }
        return [
            'challenge' => $this->b64urlEncode($challenge),
            'rpId' => $this->rpId(),
            'timeout' => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allow,
        ];
    }

    private function text(string $key): string
    {
        return Localization::get($this->modx, $key);
    }

    private function error(string $key): array
    {
        return [false, $this->text($key)];
    }

    public function verifyRegistration(array $credential, int $userId): array
    {
        $challenge = (string) ($_SESSION['modtwoauth_webauthn_reg_challenge'] ?? '');
        $created = (int) ($_SESSION['modtwoauth_webauthn_reg_created'] ?? 0);
        if ($challenge === '' || (time() - $created) > 600 || (int) ($_SESSION['modtwoauth_webauthn_reg_user_id'] ?? 0) !== $userId) {
            return $this->error('modtwoauth_webauthn_registration_expired');
        }

        $clientData = $this->b64urlDecode((string) ($credential['response']['clientDataJSON'] ?? ''));
        $attestation = $this->b64urlDecode((string) ($credential['response']['attestationObject'] ?? ''));
        $credentialId = (string) ($credential['id'] ?? '');
        if ($clientData === '' || $attestation === '' || $credentialId === ''
            || ($credential['type'] ?? 'public-key') !== 'public-key'
        ) {
            return $this->error('modtwoauth_webauthn_invalid_data');
        }

        $client = json_decode($clientData, true);
        if (!is_array($client) || ($client['type'] ?? '') !== 'webauthn.create') return $this->error('modtwoauth_webauthn_invalid_type');
        if (!hash_equals($challenge, (string) ($client['challenge'] ?? ''))) return $this->error('modtwoauth_webauthn_invalid_challenge');
        if (!hash_equals($this->origin(), (string) ($client['origin'] ?? ''))) return $this->error('modtwoauth_webauthn_invalid_origin');

        try { $obj = $this->cborDecode($attestation); } catch (\Throwable $e) { $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] WebAuthn parsing error: ' . $e->getMessage()); return $this->error('modtwoauth_webauthn_attestation_parse_failed'); }
        if (!isset($obj['authData']) || !is_string($obj['authData'])) return $this->error('modtwoauth_webauthn_authenticator_missing');
        $authData = $obj['authData'];
        if (strlen($authData) < 37) return $this->error('modtwoauth_webauthn_authenticator_invalid');
        $rpHash = substr($authData, 0, 32);
        if (!hash_equals(hash('sha256', $this->rpId(), true), $rpHash)) return $this->error('modtwoauth_webauthn_rp_mismatch');
        $flags = ord($authData[32]);
        if (($flags & 0x01) === 0 || ($flags & 0x40) === 0) return $this->error('modtwoauth_webauthn_registration_not_confirmed');
        $signCount = unpack('N', substr($authData, 33, 4))[1];
        if (strlen($authData) < 55) return $this->error('modtwoauth_webauthn_credential_id_missing');
        $idLen = unpack('n', substr($authData, 53, 2))[1];
        $offset = 55;
        if ($idLen < 1 || strlen($authData) < $offset + $idLen) return $this->error('modtwoauth_webauthn_credential_id_invalid');
        $rawCredentialId = substr($authData, $offset, $idLen);
        $credentialB64 = $this->b64urlEncode($rawCredentialId);
        if (strlen($credentialB64) > 512 || !hash_equals($credentialB64, $credentialId)) {
            return $this->error('modtwoauth_webauthn_credential_id_mismatch');
        }
        if (isset($credential['rawId']) && !hash_equals($credentialB64, (string) $credential['rawId'])) {
            return $this->error('modtwoauth_webauthn_raw_id_mismatch');
        }

        $offset += $idLen;
        $keyBytes = substr($authData, $offset);
        if ($keyBytes === '') return $this->error('modtwoauth_webauthn_public_key_missing');
        try { $cose = $this->cborDecode($keyBytes); } catch (\Throwable $e) { $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] WebAuthn parsing error: ' . $e->getMessage()); return $this->error('modtwoauth_webauthn_cose_invalid'); }
        if (!is_array($cose)) return $this->error('modtwoauth_webauthn_cose_invalid');
        $alg = (int) ($cose[3] ?? 0);
        $kty = (int) ($cose[1] ?? 0);
        $validKey = ($alg === -7 && $kty === 2 && ($cose[-1] ?? 0) === 1
                && is_string($cose[-2] ?? null) && strlen($cose[-2]) === 32
                && is_string($cose[-3] ?? null) && strlen($cose[-3]) === 32)
            || ($alg === -257 && $kty === 3 && !empty($cose[-1]) && is_string($cose[-1])
                && !empty($cose[-2]) && is_string($cose[-2]))
            || ($alg === -8 && $kty === 1 && ($cose[-1] ?? 0) === 6
                && is_string($cose[-2] ?? null) && strlen($cose[-2]) === 32);
        if (!$validKey) return $this->error('modtwoauth_webauthn_cose_unsupported');

        $existing = $this->modx->getObject(TwoAuthWebAuthnCredential::class, ['credential_id' => $credentialB64]);
        if ($existing) return $this->error('modtwoauth_webauthn_already_registered');

        $cred = $this->modx->newObject(TwoAuthWebAuthnCredential::class);
        $cred->fromArray([
            'user_id' => $userId,
            'credential_id' => $credentialB64,
            'public_key' => $this->b64urlEncode($keyBytes),
            'sign_count' => $signCount,
            'name' => substr(trim((string) ($credential['name'] ?? '')), 0, 120) ?: 'Passkey / Security Key',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$cred->save()) return $this->error('modtwoauth_webauthn_save_failed');

        unset($_SESSION['modtwoauth_webauthn_reg_challenge'], $_SESSION['modtwoauth_webauthn_reg_user_id'], $_SESSION['modtwoauth_webauthn_reg_created']);
        return [true, $cred];
    }

    public function verifyAssertion(array $assertion, int $userId): array
    {
        $challenge = (string) ($_SESSION['modtwoauth_webauthn_login_challenge'] ?? '');
        $created = (int) ($_SESSION['modtwoauth_webauthn_login_created'] ?? 0);
        if ($challenge === '' || (time() - $created) > 600 || (int) ($_SESSION['modtwoauth_webauthn_login_user_id'] ?? 0) !== $userId) {
            return $this->error('modtwoauth_webauthn_login_expired_refresh');
        }

        $id = (string) ($assertion['id'] ?? '');
        $clientData = $this->b64urlDecode((string) ($assertion['response']['clientDataJSON'] ?? ''));
        $authData = $this->b64urlDecode((string) ($assertion['response']['authenticatorData'] ?? ''));
        $signature = $this->b64urlDecode((string) ($assertion['response']['signature'] ?? ''));
        if ($id === '' || $clientData === '' || $authData === '' || $signature === ''
            || ($assertion['type'] ?? 'public-key') !== 'public-key'
            || (isset($assertion['rawId']) && !hash_equals($id, (string) $assertion['rawId']))
        ) {
            return $this->error('modtwoauth_webauthn_invalid_data');
        }

        $credential = $this->modx->getObject(TwoAuthWebAuthnCredential::class, ['credential_id' => $id, 'user_id' => $userId]);
        if (!$credential) return $this->error('modtwoauth_webauthn_not_registered_for_user');

        $client = json_decode($clientData, true);
        if (!is_array($client) || ($client['type'] ?? '') !== 'webauthn.get') return $this->error('modtwoauth_webauthn_invalid_type');
        if (!hash_equals($challenge, (string) ($client['challenge'] ?? ''))) return $this->error('modtwoauth_webauthn_invalid_challenge');
        if (!hash_equals($this->origin(), (string) ($client['origin'] ?? ''))) return $this->error('modtwoauth_webauthn_invalid_origin');
        if (strlen($authData) < 37) return $this->error('modtwoauth_webauthn_authenticator_invalid');
        if (!hash_equals(hash('sha256', $this->rpId(), true), substr($authData, 0, 32))) return $this->error('modtwoauth_webauthn_rp_mismatch');
        $flags = ord($authData[32]);
        if (($flags & 0x01) === 0) return $this->error('modtwoauth_webauthn_user_not_present');
        $newCount = unpack('N', substr($authData, 33, 4))[1];
        $oldCount = (int) $credential->get('sign_count');
        if ($newCount !== 0 && $oldCount !== 0 && $newCount <= $oldCount) return $this->error('modtwoauth_webauthn_counter_invalid');

        $clientHash = hash('sha256', $clientData, true);
        $signedData = $authData . $clientHash;
        try { $cose = $this->cborDecode($this->b64urlDecode((string) $credential->get('public_key'))); } catch (\Throwable $e) { $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] WebAuthn parsing error: ' . $e->getMessage()); return $this->error('modtwoauth_webauthn_public_key_read_failed'); }
        if (!is_array($cose) || !$this->verifySignature($cose, $signedData, $signature)) return $this->error('modtwoauth_webauthn_signature_invalid');

        $credential->set('sign_count', $newCount);
        $credential->set('last_used_at', date('Y-m-d H:i:s'));
        if (!$credential->save()) {
            return $this->error('modtwoauth_webauthn_counter_save_failed');
        }
        unset($_SESSION['modtwoauth_webauthn_login_challenge'], $_SESSION['modtwoauth_webauthn_login_user_id'], $_SESSION['modtwoauth_webauthn_login_created']);
        return [true, $credential];
    }

    private function verifySignature(array $cose, string $data, string $signature): bool
    {
        $alg = (int) ($cose[3] ?? 0);
        if ($alg === -7) {
            $x = $cose[-2] ?? null; $y = $cose[-3] ?? null;
            if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) return false;
            $pem = $this->ecPublicKeyPem($x, $y);

            // WebAuthn ES256 assertion signatures are ASN.1 DER encoded
            // ECDSA-Sig-Value (SEQUENCE(INTEGER r, INTEGER s)). Safari
            // returns the standards-compliant DER form. Older versions of
            // this provider incorrectly treated every ES256 signature as a
            // 64-byte raw R||S value and converted it again, corrupting the
            // signature and causing a false "invalid signature" result.
            // Accept DER as-is and keep raw R||S support for compatibility.
            $opensslSignature = $signature;
            if (strlen($signature) === 64 && (ord($signature[0]) !== 0x30)) {
                $opensslSignature = $this->ecdsaRawToDer($signature);
            }
            if ($opensslSignature === '') return false;
            return openssl_verify($data, $opensslSignature, $pem, OPENSSL_ALGO_SHA256) === 1;
        }
        if ($alg === -257) {
            $n = $cose[-1] ?? null; $e = $cose[-2] ?? null;
            if (!is_string($n) || !is_string($e)) return false;
            return openssl_verify($data, $signature, $this->rsaPublicKeyPem($n, $e), OPENSSL_ALGO_SHA256) === 1;
        }
        if ($alg === -8 && function_exists('sodium_crypto_sign_verify_detached')) {
            $x = $cose[-2] ?? null;
            return is_string($x) && strlen($x) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES && sodium_crypto_sign_verify_detached($signature, $data, $x);
        }
        return false;
    }

    private function ecPublicKeyPem(string $x, string $y): string
    {
        $point = "\x04" . $x . $y;
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function rsaPublicKeyPem(string $n, string $e): string
    {
        $der = $this->asn1Integer($n) . $this->asn1Integer($e);
        $der = "\x30" . $this->asn1Len(strlen($der)) . $der;
        $alg = hex2bin('300d06092a864886f70d0101010500');
        $bit = "\x03" . $this->asn1Len(strlen($der) + 1) . "\x00" . $der;
        $spki = $alg . $bit;
        $spki = "\x30" . $this->asn1Len(strlen($spki)) . $spki;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80)) $bytes = "\x00" . $bytes;
        return "\x02" . $this->asn1Len(strlen($bytes)) . $bytes;
    }

    private function asn1Len(int $len): string
    {
        if ($len < 128) return chr($len);
        $out = ''; while ($len > 0) { $out = chr($len & 255) . $out; $len >>= 8; }
        return chr(0x80 | strlen($out)) . $out;
    }

    private function ecdsaRawToDer(string $sig): string
    {
        $len = intdiv(strlen($sig), 2); if ($len < 32) return '';
        $r = substr($sig, 0, $len); $s = substr($sig, $len);
        $r = ltrim($r, "\x00"); $s = ltrim($s, "\x00");
        if ($r === '' ) $r = "\x00"; if ($s === '') $s = "\x00";
        if (ord($r[0]) & 0x80) $r = "\x00" . $r; if (ord($s[0]) & 0x80) $s = "\x00" . $s;
        $body = "\x02" . $this->asn1Len(strlen($r)) . $r . "\x02" . $this->asn1Len(strlen($s)) . $s;
        return "\x30" . $this->asn1Len(strlen($body)) . $body;
    }

    private function cborDecode(string $data): mixed
    {
        $offset = 0;
        return $this->cborItem($data, $offset, 0);
    }

    private function cborItem(string $data, int &$o, int $depth): mixed
    {
        // Лимит вложенности защищает от исчерпания стека (DoS) на специально
        // составленных attestationObject.
        if ($depth > 32) throw new \RuntimeException('CBOR nesting too deep');
        if ($o >= strlen($data)) throw new \RuntimeException('CBOR EOF');
        $b = ord($data[$o++]); $major = $b >> 5; $ai = $b & 31;
        $len = $this->cborLength($data, $o, $ai);
        if ($major === 0) return $len;
        if ($major === 1) return -1 - $len;
        if ($major === 2) { if ($o + $len > strlen($data)) throw new \RuntimeException('CBOR bytes'); $v = substr($data, $o, $len); $o += $len; return $v; }
        if ($major === 3) { if ($o + $len > strlen($data)) throw new \RuntimeException('CBOR text'); $v = substr($data, $o, $len); $o += $len; return $v; }
        if ($major === 4) { $a=[]; for($i=0;$i<$len;$i++) $a[]=$this->cborItem($data,$o,$depth+1); return $a; }
        if ($major === 5) { $m=[]; for($i=0;$i<$len;$i++){ $k=$this->cborItem($data,$o,$depth+1); $v=$this->cborItem($data,$o,$depth+1); $m[$k]=$v; } return $m; }
        if ($major === 7) { if($ai===20)return false;if($ai===21)return true;if($ai===22)return null; }
        throw new \RuntimeException('Unsupported CBOR type');
    }

    private function cborLength(string $data, int &$o, int $ai): int
    {
        if ($ai < 24) return $ai;
        $sizes = [24 => 1, 25 => 2, 26 => 4, 27 => 8];
        if (!isset($sizes[$ai])) throw new \RuntimeException('Indefinite CBOR unsupported');
        $size = $sizes[$ai];
        if ($o + $size > strlen($data)) throw new \RuntimeException('CBOR truncated length');
        $bytes = substr($data, $o, $size);
        $o += $size;
        if ($size === 1) return ord($bytes);
        if ($size === 2) return unpack('n', $bytes)[1];
        if ($size === 4) return unpack('N', $bytes)[1];
        $parts = unpack('N2', $bytes);
        $value = ($parts[1] * 4294967296) + $parts[2];
        if ($value > PHP_INT_MAX) throw new \RuntimeException('CBOR value too large');
        return (int) $value;
    }
}
