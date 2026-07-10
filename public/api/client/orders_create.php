<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/PaymentGateway.php';
require_once __DIR__ . '/../../../includes/pricing.php';

$clientId = Auth::requireRole('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, [
    'type_livraison_id', 'adresse_depart', 'lat_depart', 'lng_depart',
    'adresse_arrivee', 'lat_arrivee', 'lng_arrivee', 'mode_paiement',
]);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$modesValides = ['especes', 'orange_money', 'mtn_money', 'moov_money', 'wave'];
$modePaiement = clean_str($body['mode_paiement']);
if (!in_array($modePaiement, $modesValides, true)) {
    Response::error('Mode de paiement invalide.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM types_livraison WHERE id = :id AND actif = 1');
$stmt->execute(['id' => (int) $body['type_livraison_id']]);
$type = $stmt->fetch();
if (!$type) {
    Response::error('Type de livraison invalide.');
}

$commercantId = isset($body['commercant_id']) && $body['commercant_id'] !== '' ? (int) $body['commercant_id'] : null;
if ($commercantId !== null) {
    $stmt = $db->prepare("SELECT user_id FROM commercant_details WHERE user_id = :id AND statut_validation = 'valide'");
    $stmt->execute(['id' => $commercantId]);
    if (!$stmt->fetch()) {
        Response::error('Commercant invalide ou non valide.');
    }
}

$latDepart = (float) $body['lat_depart'];
$lngDepart = (float) $body['lng_depart'];
$latArrivee = (float) $body['lat_arrivee'];
$lngArrivee = (float) $body['lng_arrivee'];
$express = (bool) input($body, 'express', false);
$instructions = clean_str(input($body, 'instructions', ''));

$distanceKm = haversine_distance_km($latDepart, $lngDepart, $latArrivee, $lngArrivee);
$montantLivraison = estimer_cout((float) $type['tarif_base'], (float) $type['tarif_km'], $distanceKm, $express, (float) $type['supplement_express']);

// Moyen de transport choisi (facultatif) : multiplicateur tarifaire.
$transport = moyen_transport_actif($db, isset($body['moyen_transport_id']) ? (int) $body['moyen_transport_id'] : null);
$moyenTransportId = $transport['id'] ?? null;
if ($transport) {
    $montantLivraison = round($montantLivraison * (float) $transport['multiplicateur']);
}

// Tarification dynamique : meme regle qu'a l'estimation.
$surge = surge_multiplicateur($db);
$montantLivraison = appliquer_surge($montantLivraison, $surge);

$produits = input($body, 'produits', []);
$montantProduits = 0.0;
$lignesValidees = [];

if (is_array($produits) && count($produits) > 0) {
    if ($commercantId === null) {
        Response::error('Un commercant_id est requis lorsque des produits sont commandes.');
    }
    foreach ($produits as $ligne) {
        if (!isset($ligne['produit_id'], $ligne['quantite'])) {
            continue;
        }
        $stmt = $db->prepare('SELECT * FROM produits WHERE id = :id AND commercant_id = :commercant_id AND disponible = 1');
        $stmt->execute(['id' => (int) $ligne['produit_id'], 'commercant_id' => $commercantId]);
        $produit = $stmt->fetch();
        if (!$produit) {
            continue;
        }
        $quantite = max(1, (int) $ligne['quantite']);
        $montantProduits += (float) $produit['prix'] * $quantite;
        $lignesValidees[] = [
            'produit_id' => $produit['id'],
            'quantite' => $quantite,
            'prix_unitaire' => $produit['prix'],
        ];
    }
}

$montantBrut = $montantLivraison + $montantProduits;

// Code promo (voir includes/promo.php). Sans code valide : reduction nulle.
require_once __DIR__ . '/../../../includes/promo.php';
$codePromoSaisi = clean_str(input($body, 'code_promo', ''));
try {
    [$codePromoApplique, $reduction, $promoId] = appliquer_code_promo($db, $codePromoSaisi, $montantBrut);
} catch (PromoException $e) {
    Response::error($e->getMessage(), 422);
}

$montantTotal = max(0, $montantBrut - $reduction);

$stmt = $db->prepare("SELECT valeur FROM parametres WHERE cle = 'commission_taux_defaut'");
$stmt->execute();
$commissionTaux = (float) ($stmt->fetch()['valeur'] ?? 15);
$commissionMontant = round($montantTotal * $commissionTaux / 100, 0);

$reference = generer_reference_commande();

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO commandes (
            reference, client_id, commercant_id, type_livraison_id, moyen_transport_id, statut,
            adresse_depart, lat_depart, lng_depart, adresse_arrivee, lat_arrivee, lng_arrivee,
            distance_km, est_express, instructions, code_livraison,
            code_promo, reduction, montant_estime, commission_taux, commission_montant,
            mode_paiement, statut_paiement
        ) VALUES (
            :reference, :client_id, :commercant_id, :type_livraison_id, :moyen_transport_id, :statut,
            :adresse_depart, :lat_depart, :lng_depart, :adresse_arrivee, :lat_arrivee, :lng_arrivee,
            :distance_km, :est_express, :instructions, :code_livraison,
            :code_promo, :reduction, :montant_estime, :commission_taux, :commission_montant,
            :mode_paiement, :statut_paiement
        )'
    );
    $stmt->execute([
        'reference' => $reference,
        'client_id' => $clientId,
        'commercant_id' => $commercantId,
        'type_livraison_id' => $type['id'],
        'moyen_transport_id' => $moyenTransportId,
        'statut' => 'en_attente',
        'adresse_depart' => clean_str($body['adresse_depart']),
        'lat_depart' => $latDepart,
        'lng_depart' => $lngDepart,
        'adresse_arrivee' => clean_str($body['adresse_arrivee']),
        'lat_arrivee' => $latArrivee,
        'lng_arrivee' => $lngArrivee,
        'distance_km' => $distanceKm,
        'est_express' => $express ? 1 : 0,
        'instructions' => $instructions,
        'code_livraison' => generer_code_livraison(),
        'code_promo' => $codePromoApplique,
        'reduction' => $reduction,
        'montant_estime' => $montantTotal,
        'commission_taux' => $commissionTaux,
        'commission_montant' => $commissionMontant,
        'mode_paiement' => $modePaiement,
        'statut_paiement' => 'en_attente',
    ]);
    $commandeId = (int) $db->lastInsertId();

    incrementer_usage_promo($db, $promoId);

    if (!empty($lignesValidees)) {
        $stmtLigne = $db->prepare(
            'INSERT INTO commande_produits (commande_id, produit_id, quantite, prix_unitaire) VALUES (:commande_id, :produit_id, :quantite, :prix_unitaire)'
        );
        foreach ($lignesValidees as $ligne) {
            $stmtLigne->execute([
                'commande_id' => $commandeId,
                'produit_id' => $ligne['produit_id'],
                'quantite' => $ligne['quantite'],
                'prix_unitaire' => $ligne['prix_unitaire'],
            ]);
        }
    }

    $numeroPaiement = clean_str(input($body, 'numero_paiement', ''));
    $driver = PaymentGateway::driver($modePaiement);
    $resultatPaiement = $driver->initierPaiement($numeroPaiement, $montantTotal, $reference);

    $stmtPaiement = $db->prepare(
        'INSERT INTO paiements (commande_id, methode, reference_transaction, montant, statut, payload_json)
         VALUES (:commande_id, :methode, :reference, :montant, :statut, :payload)'
    );
    $stmtPaiement->execute([
        'commande_id' => $commandeId,
        'methode' => $modePaiement,
        'reference' => $resultatPaiement['reference'],
        'montant' => $montantTotal,
        'statut' => $resultatPaiement['statut'],
        'payload' => json_encode($resultatPaiement['payload'], JSON_UNESCAPED_UNICODE),
    ]);

    if ($resultatPaiement['statut'] === 'reussi') {
        $db->prepare("UPDATE commandes SET statut_paiement = 'paye' WHERE id = :id")->execute(['id' => $commandeId]);
    } elseif ($resultatPaiement['statut'] === 'echec') {
        $db->prepare("UPDATE commandes SET statut_paiement = 'echec' WHERE id = :id")->execute(['id' => $commandeId]);
    }

    creer_notification(
        $db,
        $clientId,
        'Commande enregistree',
        "Votre commande {$reference} a ete enregistree et est en attente d'un livreur.",
        'commande',
        "/client/track.php?ref={$reference}"
    );

    if ($commercantId !== null) {
        creer_notification(
            $db,
            $commercantId,
            'Nouvelle commande',
            "Une nouvelle commande {$reference} vient d'etre passee.",
            'commande',
            "/commercant/orders.php?ref={$reference}"
        );
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de la creation de la commande : ' . $e->getMessage(), 422);
}

// Dispatch automatique : proposer la course au livreur en ligne le plus proche.
// Best-effort : n'affecte jamais la reussite de la creation de commande.
try {
    require_once __DIR__ . '/../../../includes/dispatch.php';
    dispatcher_commande($db, $commandeId);
} catch (Throwable $e) {
    error_log('Dispatch error: ' . $e->getMessage());
}

Response::created([
    'commande_id' => $commandeId,
    'reference' => $reference,
    'distance_km' => $distanceKm,
    'montant_total' => $montantTotal,
    'statut_paiement' => $resultatPaiement['statut'],
    // Pour le paiement Mobile Money : URL de paiement web (Wave/Orange) ou
    // message d'instructions (push USSD MTN/Moov). null si paiement immediat.
    'redirect_url' => $resultatPaiement['redirect_url'] ?? null,
    'instructions' => $resultatPaiement['instructions'] ?? null,
], 'Commande creee avec succes.');
