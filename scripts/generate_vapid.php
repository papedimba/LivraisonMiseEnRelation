<?php
declare(strict_types=1);

/**
 * Genere une paire de cles VAPID pour le Web Push.
 *
 * Utilisation (en ligne de commande) :
 *     php scripts/generate_vapid.php
 *
 * Le script ecrit la cle privee au format PEM dans storage/vapid_private.pem
 * et affiche la cle publique (base64url) a copier dans le fichier .env sous
 * VAPID_PUBLIC_KEY. Renseignez aussi VAPID_SUBJECT (une adresse mailto: ou
 * l'URL de votre site) dans .env.
 */

require_once __DIR__ . '/../includes/webpush_util.php';

$config = [
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
];

$res = openssl_pkey_new($config);
if ($res === false) {
    fwrite(STDERR, "Erreur : impossible de generer la cle (extension openssl EC requise).\n");
    exit(1);
}

openssl_pkey_export($res, $pem);

$details = openssl_pkey_get_details($res);
$publicKeyB64 = webpush_public_key_b64($details);

$cheminPem = __DIR__ . '/../storage/vapid_private.pem';
file_put_contents($cheminPem, $pem);
@chmod($cheminPem, 0600);

echo "Cle privee VAPID ecrite dans : storage/vapid_private.pem\n\n";
echo "Ajoutez ces lignes a votre fichier .env :\n\n";
echo "VAPID_PUBLIC_KEY={$publicKeyB64}\n";
echo "VAPID_PRIVATE_KEY_PATH=storage/vapid_private.pem\n";
echo "VAPID_SUBJECT=mailto:contact@votre-domaine.com\n";
