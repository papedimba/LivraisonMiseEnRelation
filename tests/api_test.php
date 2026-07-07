<?php
declare(strict_types=1);

/**
 * Tests d'integration : scenario complet contre un serveur en cours d'execution.
 * Necessite TEST_BASE_URL et une base fraiche (voir tests/run.sh).
 */

function tests_api(string $base): void
{
    $suffix = substr((string) time(), -6) . random_int(10, 99);

    // -- Admin -----------------------------------------------------------------
    t_section('Authentification');
    $admin = new TestHttp($base);
    $r = $admin->post('/api/auth/login.php', ['email' => 'admin@livraisonci.local', 'password' => 'ChangeMoi123!']);
    t_eq(200, $r['code'], 'login admin HTTP 200');
    t_eq('admin', $r['body']['data']['role'] ?? null, 'role admin');

    $r = $admin->post('/api/auth/login.php', ['email' => 'admin@livraisonci.local', 'password' => 'mauvais']);
    t_eq(401, $r['code'], 'mauvais mot de passe -> 401');

    // -- Client ----------------------------------------------------------------
    t_section('Inscription et commande client');
    $client = new TestHttp($base);
    $emailClient = "client{$suffix}@ex.com";
    $r = $client->post('/api/auth/register.php', [
        'role' => 'client', 'nom' => 'Test', 'prenom' => 'Client',
        'email' => $emailClient, 'telephone' => "07{$suffix}11", 'password' => 'MotDePasse1',
    ]);
    t_eq(201, $r['code'], 'inscription client HTTP 201');

    $r = $client->post('/api/auth/login.php', ['email' => $emailClient, 'password' => 'MotDePasse1']);
    t_eq(200, $r['code'], 'login client');

    $r = $client->post('/api/client/estimation.php', [
        'type_livraison_id' => 1, 'lat_depart' => 7.69, 'lng_depart' => -5.03,
        'lat_arrivee' => 7.70, 'lng_arrivee' => -5.04, 'express' => false,
    ]);
    t_ok(($r['body']['data']['montant_estime'] ?? 0) > 0, 'estimation montant > 0');

    $r = $client->post('/api/client/orders_create.php', [
        'type_livraison_id' => 1, 'adresse_depart' => 'A', 'lat_depart' => 7.69, 'lng_depart' => -5.03,
        'adresse_arrivee' => 'B', 'lat_arrivee' => 7.70, 'lng_arrivee' => -5.04, 'mode_paiement' => 'especes',
    ]);
    t_eq(201, $r['code'], 'creation commande HTTP 201');
    $reference = $r['body']['data']['reference'] ?? '';
    $commandeId = $r['body']['data']['commande_id'] ?? 0;
    t_ok($reference !== '', 'reference commande generee');

    // Le client lit son code de livraison via le suivi.
    $r = $client->get('/api/client/orders_track.php?reference=' . urlencode($reference));
    $codeLivraison = $r['body']['data']['commande']['code_livraison'] ?? '';
    t_ok(preg_match('/^\d{4}$/', $codeLivraison) === 1, 'code de livraison present dans le suivi');

    // -- Livreur ---------------------------------------------------------------
    t_section('Livreur : validation, acceptation, livraison');
    $livreur = new TestHttp($base);
    $emailLivreur = "livreur{$suffix}@ex.com";
    $r = $livreur->post('/api/auth/register.php', [
        'role' => 'livreur', 'nom' => 'Test', 'prenom' => 'Livreur',
        'email' => $emailLivreur, 'telephone' => "07{$suffix}22", 'password' => 'MotDePasse1', 'type_vehicule' => 'moto',
    ]);
    $livreurId = $r['body']['data']['user_id'] ?? 0;
    t_ok($livreurId > 0, 'inscription livreur');

    $r = $admin->post('/api/admin/livreurs_validate.php', ['user_id' => $livreurId, 'decision' => 'valide']);
    t_eq(200, $r['code'], 'validation livreur par admin');

    $r = $livreur->post('/api/auth/login.php', ['email' => $emailLivreur, 'password' => 'MotDePasse1']);
    t_eq(200, $r['code'], 'login livreur (valide)');

    $livreur->post('/api/livreur/toggle_availability.php', ['disponibilite' => 'en_ligne']);
    $r = $livreur->post('/api/livreur/orders_accept.php', ['commande_id' => $commandeId]);
    t_eq(200, $r['code'], 'acceptation de la commande');

    $livreur->post('/api/livreur/orders_update_status.php', ['commande_id' => $commandeId, 'statut' => 'recuperee']);
    $livreur->post('/api/livreur/orders_update_status.php', ['commande_id' => $commandeId, 'statut' => 'en_cours']);

    // Preuve de livraison : mauvais code puis bon code.
    $r = $livreur->post('/api/livreur/confirm_delivery.php', ['commande_id' => $commandeId, 'code_livraison' => '0000']);
    t_eq(422, $r['code'], 'mauvais code de livraison rejete (422)');

    $r = $livreur->post('/api/livreur/confirm_delivery.php', ['commande_id' => $commandeId, 'code_livraison' => $codeLivraison]);
    t_eq(200, $r['code'], 'bon code de livraison accepte');
    t_eq('livree', $r['body']['data']['statut'] ?? null, 'commande livree');

    // -- Evaluation ------------------------------------------------------------
    t_section('Evaluation');
    $r = $client->post('/api/client/rate.php', ['commande_id' => $commandeId, 'note' => 5, 'commentaire' => 'Parfait']);
    t_eq(200, $r['code'], 'evaluation du livreur');

    // -- Codes promo -----------------------------------------------------------
    t_section('Codes promo');
    $codePromo = 'TEST' . $suffix;
    $r = $admin->post('/api/admin/promos_save.php', [
        'action' => 'creer', 'code' => $codePromo, 'type' => 'pourcentage', 'valeur' => 10, 'montant_min' => 1000,
    ]);
    t_eq(201, $r['code'], 'creation code promo');

    $r = $client->post('/api/client/promo_check.php', ['code' => $codePromo, 'montant' => 2000]);
    t_eq(200, $r['code'], 'verification code promo valide');
    t_eq(200.0, (float) ($r['body']['data']['reduction'] ?? 0), 'reduction 10% de 2000 = 200');

    $r = $client->post('/api/client/promo_check.php', ['code' => $codePromo, 'montant' => 500]);
    t_eq(422, $r['code'], 'code promo sous le montant minimum rejete');

    // -- Dispatch par proximite ------------------------------------------------
    t_section('Dispatch automatique par proximite');
    // Le livreur (deja en ligne apres sa livraison) enregistre sa position.
    $livreur->post('/api/livreur/update_position.php', ['latitude' => 7.6905, 'longitude' => -5.0305]);
    $r = $client->post('/api/client/orders_create.php', [
        'type_livraison_id' => 1, 'adresse_depart' => 'R', 'lat_depart' => 7.69, 'lng_depart' => -5.03,
        'adresse_arrivee' => 'D', 'lat_arrivee' => 7.70, 'lng_arrivee' => -5.04, 'mode_paiement' => 'especes',
    ]);
    $cmdDispatch = $r['body']['data']['commande_id'] ?? 0;

    $r = $livreur->get('/api/livreur/offer_get.php');
    $offreId = $r['body']['data']['offre']['offre_id'] ?? 0;
    t_ok($offreId > 0, 'une offre est proposee au livreur le plus proche');
    t_eq($cmdDispatch, $r['body']['data']['offre']['commande_id'] ?? -1, 'l\'offre concerne la bonne commande');

    $r = $livreur->post('/api/livreur/offer_respond.php', ['offre_id' => $offreId, 'decision' => 'accepter']);
    t_eq(200, $r['code'], 'acceptation de l\'offre de dispatch');
    t_eq($cmdDispatch, $r['body']['data']['commande_id'] ?? -1, 'commande attribuee via le dispatch');

    // -- Parametres admin (regression : placeholder reutilise) -----------------
    t_section('Parametres admin');
    $r = $admin->post('/api/admin/settings.php', ['commission_taux_defaut' => '18']);
    t_eq(200, $r['code'], 'sauvegarde des parametres admin');
    $r = $admin->get('/api/admin/settings.php');
    t_eq('18', $r['body']['data']['parametres']['commission_taux_defaut'] ?? null, 'parametre commission bien enregistre');

    // -- Securite --------------------------------------------------------------
    t_section('Controle d\'acces');
    $anon = new TestHttp($base);
    $r = $anon->get('/api/admin/dashboard.php');
    t_eq(401, $r['code'], 'dashboard admin inaccessible sans session (401)');

    $r = $client->get('/api/admin/dashboard.php');
    t_eq(403, $r['code'], 'dashboard admin interdit a un client (403)');
}
