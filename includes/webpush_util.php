<?php
declare(strict_types=1);

/**
 * Fonctions utilitaires bas niveau pour le Web Push (VAPID), sans dependance
 * externe (openssl natif de PHP uniquement).
 */

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Construit la cle publique VAPID (point EC non compresse 0x04||X||Y) en
 * base64url a partir des details d'une cle openssl.
 */
function webpush_public_key_b64(array $details): string
{
    $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return b64url_encode("\x04" . $x . $y);
}

/**
 * Convertit une signature ECDSA au format DER (SEQUENCE de deux INTEGER) en
 * signature brute R||S de 64 octets, attendue par JWT ES256.
 */
function der_to_raw_signature(string $der): string
{
    $offset = 0;
    if (ord($der[$offset++]) !== 0x30) {
        throw new RuntimeException('Signature DER invalide (SEQUENCE attendue).');
    }
    // Longueur de la sequence (ignoree ici).
    $seqLen = ord($der[$offset++]);
    if ($seqLen & 0x80) {
        $offset += ($seqLen & 0x7f);
    }

    $lireEntier = function () use ($der, &$offset): string {
        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Signature DER invalide (INTEGER attendu).');
        }
        $len = ord($der[$offset++]);
        $val = substr($der, $offset, $len);
        $offset += $len;
        // Retire un eventuel octet de tete 0x00 et repad a 32 octets.
        $val = ltrim($val, "\0");
        return str_pad($val, 32, "\0", STR_PAD_LEFT);
    };

    $r = $lireEntier();
    $s = $lireEntier();
    return $r . $s;
}

/**
 * Signe un JWT ES256 avec une cle privee EC au format PEM.
 */
function webpush_sign_jwt(array $claims, string $privateKeyPem): string
{
    $header = ['typ' => 'JWT', 'alg' => 'ES256'];
    $segments = [
        b64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)),
        b64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES)),
    ];
    $signingInput = implode('.', $segments);

    $pkey = openssl_pkey_get_private($privateKeyPem);
    if ($pkey === false) {
        throw new RuntimeException('Cle privee VAPID invalide.');
    }

    $der = '';
    if (!openssl_sign($signingInput, $der, $pkey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Echec de la signature JWT.');
    }

    $raw = der_to_raw_signature($der);
    $segments[] = b64url_encode($raw);

    return implode('.', $segments);
}
