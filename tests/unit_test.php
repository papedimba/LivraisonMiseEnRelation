<?php
declare(strict_types=1);

/**
 * Tests unitaires des fonctions pures (aucune base de donnees requise).
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/webpush_util.php';
require_once __DIR__ . '/../includes/pricing.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/PaymentGateway.php';

function tests_unitaires(): void
{
    t_section('Distance (Haversine)');
    $d = haversine_distance_km(7.69, -5.03, 7.70, -5.04);
    t_ok($d > 1.4 && $d < 1.7, "distance Bouake ~1.57 km (obtenu {$d})");
    t_eq(0.0, haversine_distance_km(7.69, -5.03, 7.69, -5.03), 'distance nulle pour meme point');

    t_section('Tarification dynamique (surge)');
    t_ok(surge_heure_de_pointe(12, '11-14,18-21'), '12h est une heure de pointe (plage 11-14)');
    t_ok(surge_heure_de_pointe(19, '11-14,18-21'), '19h est une heure de pointe (plage 18-21)');
    t_ok(!surge_heure_de_pointe(14, '11-14,18-21'), '14h exclu (borne de fin)');
    t_ok(!surge_heure_de_pointe(9, '11-14,18-21'), '9h hors pointe');
    t_ok(surge_heure_de_pointe(20, '20'), 'heure unique 20h reconnue');
    t_eq(1200.0, appliquer_surge(800, ['facteur' => 1.5, 'actif' => true, 'raison' => '']), 'surge x1.5 sur 800 = 1200');
    t_eq(800.0, appliquer_surge(800, ['facteur' => 1.0, 'actif' => false, 'raison' => '']), 'surge x1 = inchange');

    t_section('Estimation du cout');
    // tarif_base 500 + tarif_km 150 * 2 km = 800, sans express
    t_eq(800.0, estimer_cout(500, 150, 2, false, 1000), 'cout 2 km sans express = 800');
    // avec express : +1000
    t_eq(1800.0, estimer_cout(500, 150, 2, true, 1000), 'cout 2 km avec express = 1800');

    t_section('Reference et code de livraison');
    t_ok((bool) preg_match('/^CMD-\d{6}-[0-9A-F]{6}$/', generer_reference_commande()), 'format reference commande');
    $code = generer_code_livraison();
    t_ok((bool) preg_match('/^\d{4}$/', $code), "code livraison a 4 chiffres (obtenu {$code})");

    t_section('Validation email / telephone');
    t_ok(is_valid_email('a@b.com'), 'email valide');
    t_ok(!is_valid_email('pas-un-email'), 'email invalide rejete');
    t_ok(is_valid_phone('0700000000'), 'telephone valide');
    t_ok(!is_valid_phone('abc'), 'telephone invalide rejete');

    t_section('Passerelle de paiement : agregateur GeniusPay');
    // Ces assertions restent purement structurelles (estDisponible(), choix du
    // driver) : aucun appel reseau n'est jamais declenche par un test unitaire,
    // seule initierPaiement() en ferait un (teste separement, hors CI, avec de
    // vrais identifiants de recette).
    $configVide = ['base_url' => '', 'api_key' => '', 'api_secret' => '', 'webhook_secret' => ''];
    t_ok(!GeniusPayDriver::estDisponible($configVide), 'GeniusPay indisponible sans identifiants');
    t_ok(PaymentGateway::driver('orange_money') instanceof OrangeMoneyDriver, 'sans GeniusPay configure, orange_money utilise son driver direct');
    t_ok(PaymentGateway::driver('especes') instanceof EspecesDriver, 'le paiement especes reste inchange');

    // Cle publique seule (sans cle secrete) : GeniusPay ne doit PAS se
    // declarer disponible (les deux sont requises pour authentifier une requete).
    $configIncomplete = ['base_url' => 'http://pay.genius.ci/api/v1/merchant', 'api_key' => 'pk_test_x', 'api_secret' => '', 'webhook_secret' => ''];
    t_ok(!GeniusPayDriver::estDisponible($configIncomplete), 'GeniusPay indisponible sans cle secrete (api_secret manquante)');

    // Configuration complete : GeniusPay se declare disponible (deviendrait
    // l'agregateur pour Orange/MTN/Wave - jamais Moov Money, qu'il ne supporte
    // pas, voir PaymentGateway::driver() et le test d'integration dedie dans
    // tests/api_test.php pour le routage reel via la config admin en base).
    $configComplete = ['base_url' => 'http://pay.genius.ci/api/v1/merchant', 'api_key' => 'pk_test_x', 'api_secret' => 'sk_test_x', 'webhook_secret' => 'whsec_test_x'];
    t_ok(GeniusPayDriver::estDisponible($configComplete), 'GeniusPay disponible avec cle publique et cle secrete');

    t_section('Web Push : signature VAPID ES256 (round-trip)');
    $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if ($res === false) {
        t_ok(false, 'generation cle EC (openssl EC indisponible)');
        return;
    }
    openssl_pkey_export($res, $pem);
    $jwt = webpush_sign_jwt(['aud' => 'https://ex.com', 'exp' => time() + 60, 'sub' => 'mailto:x@y.z'], $pem);
    $parts = explode('.', $jwt);
    t_eq(3, count($parts), 'JWT a 3 segments');
    $sig = b64url_decode($parts[2]);
    t_eq(64, strlen($sig), 'signature brute de 64 octets');

    // Verification cryptographique de la signature.
    $r = substr($sig, 0, 32);
    $s = substr($sig, 32, 32);
    $derint = function (string $v): string {
        $v = ltrim($v, "\0");
        if ($v === '' || (ord($v[0]) & 0x80)) {
            $v = "\0" . $v;
        }
        return "\x02" . chr(strlen($v)) . $v;
    };
    $der = $derint($r) . $derint($s);
    $der = "\x30" . chr(strlen($der)) . $der;
    $pub = openssl_pkey_get_public(openssl_pkey_get_details(openssl_pkey_get_private($pem))['key']);
    $ok = openssl_verify($parts[0] . '.' . $parts[1], $der, $pub, OPENSSL_ALGO_SHA256);
    t_eq(1, $ok, 'signature VAPID verifiee avec la cle publique');
}
