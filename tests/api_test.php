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
    $adminId = $r['body']['data']['user_id'] ?? 0;

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

    // Notation a double sens : le livreur note aussi le client.
    $r = $livreur->get('/api/livreur/pending_rating.php');
    t_ok(($r['body']['data']['a_noter']['id'] ?? null) == $commandeId, 'une course a noter est proposee au livreur');
    $r = $livreur->post('/api/livreur/rate_client.php', ['commande_id' => $commandeId, 'note' => 4, 'commentaire' => 'Client ponctuel']);
    t_eq(200, $r['code'], 'le livreur note le client');
    $r = $livreur->post('/api/livreur/rate_client.php', ['commande_id' => $commandeId, 'note' => 4]);
    t_eq(409, $r['code'], 'double notation du meme client rejetee');

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

    // -- Messagerie in-app (client <-> livreur) --------------------------------
    t_section('Messagerie in-app');
    // cmdDispatch vient d'etre acceptee par le livreur : les deux sont participants.
    $r = $client->post('/api/chat/send.php', ['commande_id' => $cmdDispatch, 'message' => 'Bonjour, vous etes loin ?']);
    t_eq(201, $r['code'], 'le client envoie un message');

    $r = $livreur->get('/api/chat/messages.php?commande_id=' . $cmdDispatch);
    t_ok(count($r['body']['data']['messages'] ?? []) >= 1, 'le livreur recoit le message du client');

    $r = $livreur->post('/api/chat/send.php', ['commande_id' => $cmdDispatch, 'message' => 'J\'arrive dans 5 minutes']);
    t_eq(201, $r['code'], 'le livreur repond au client');

    // Un role non participant (admin) n'accede pas a la messagerie.
    $r = $admin->get('/api/chat/messages.php?commande_id=' . $cmdDispatch);
    t_eq(403, $r['code'], 'acces au chat interdit hors participants (403)');

    // -- Adresses favorites ----------------------------------------------------
    t_section('Adresses favorites');
    $r = $client->post('/api/client/addresses_save.php', [
        'libelle' => 'Maison', 'adresse' => 'Belleville', 'latitude' => 7.69, 'longitude' => -5.03,
    ]);
    t_eq(201, $r['code'], 'enregistrement d\'une adresse favorite');
    $favId = $r['body']['data']['id'] ?? 0;

    $r = $client->get('/api/client/addresses_list.php');
    t_ok(count($r['body']['data']['adresses'] ?? []) >= 1, 'liste des adresses favorites');

    $r = $client->post('/api/client/addresses_delete.php', ['id' => $favId]);
    t_eq(200, $r['code'], 'suppression d\'une adresse favorite');

    // -- Suivi de la flotte ----------------------------------------------------
    t_section('Suivi de la flotte');
    // Le livreur vient d'accepter cmdDispatch : il doit apparaitre "en route".
    $r = $admin->get('/api/admin/fleet.php');
    t_eq(200, $r['code'], 'l\'admin consulte la flotte');
    $flotte = $r['body']['data']['livreurs'] ?? [];
    $moi = null;
    foreach ($flotte as $l) {
        if (($l['id'] ?? 0) === $livreurId) { $moi = $l; break; }
    }
    t_ok($moi !== null, 'le livreur actif figure dans la flotte');
    t_eq('en_route', $moi['statut'] ?? null, 'le livreur sur une course est "en route"');

    // Passage en pause : le statut de flotte doit suivre une fois la course finie.
    $r = $livreur->post('/api/livreur/toggle_availability.php', ['disponibilite' => 'pause']);
    t_eq(200, $r['code'], 'le livreur peut se mettre en pause');

    // -- Administration des comptes (creation / edition / droits) --------------
    t_section('Administration des comptes');
    $emailNew = "cree{$suffix}@ex.com";
    $r = $admin->post('/api/admin/users_create.php', [
        'role' => 'client', 'nom' => 'Cree', 'prenom' => 'ParAdmin',
        'email' => $emailNew, 'telephone' => "07{$suffix}33", 'password' => 'MotDePasse1', 'statut' => 'actif',
    ]);
    t_eq(201, $r['code'], 'l\'admin cree un compte utilisateur');
    $newId = $r['body']['data']['user_id'] ?? 0;
    t_ok($newId > 0, 'identifiant du compte cree');

    // Le compte cree par l'admin peut se connecter immediatement.
    $nouveau = new TestHttp($base);
    $r = $nouveau->post('/api/auth/login.php', ['email' => $emailNew, 'password' => 'MotDePasse1']);
    t_eq(200, $r['code'], 'le compte cree par l\'admin peut se connecter');

    // Attribution de droits : le client devient livreur actif.
    $r = $admin->post('/api/admin/users_update.php', [
        'user_id' => $newId, 'role' => 'livreur', 'statut' => 'actif', 'type_vehicule' => 'moto',
    ]);
    t_eq(200, $r['code'], 'l\'admin change le role en livreur');

    $r = $admin->get('/api/admin/user_get.php?user_id=' . $newId);
    t_eq('livreur', $r['body']['data']['utilisateur']['role'] ?? null, 'le nouveau role est applique');
    t_ok(($r['body']['data']['utilisateur']['livreur'] ?? null) !== null, 'la fiche livreur est creee au changement de role');

    // Garde-fou : un admin ne peut pas se retirer ses propres droits.
    $r = $admin->post('/api/admin/users_update.php', ['user_id' => $adminId, 'role' => 'client']);
    t_eq(409, $r['code'], 'un admin ne peut pas se retrograder lui-meme');

    // Email deja utilise -> conflit.
    $r = $admin->post('/api/admin/users_create.php', [
        'role' => 'client', 'nom' => 'X', 'prenom' => 'Y',
        'email' => $emailNew, 'telephone' => "07{$suffix}44", 'password' => 'MotDePasse1',
    ]);
    t_eq(409, $r['code'], 'creation avec un email deja utilise rejetee');

    // -- Analytics admin -------------------------------------------------------
    t_section('Analytics admin');
    $r = $admin->get('/api/admin/analytics.php?jours=30');
    t_eq(200, $r['code'], 'l\'admin consulte les analytics');
    t_eq(30, $r['body']['data']['jours'] ?? null, 'periode de 30 jours');
    t_ok(count($r['body']['data']['serie'] ?? []) === 30, 'serie journaliere complete (30 points)');
    t_ok(($r['body']['data']['resume']['total'] ?? 0) >= 1, 'au moins une commande comptee sur la periode');
    // Periode invalide -> repli sur 30 jours.
    $r = $admin->get('/api/admin/analytics.php?jours=999');
    t_eq(30, $r['body']['data']['jours'] ?? null, 'periode invalide repliee sur 30 jours');

    // -- Parametres admin (regression : placeholder reutilise) -----------------
    t_section('Parametres admin');
    $r = $admin->post('/api/admin/settings.php', ['commission_taux_defaut' => '18']);
    t_eq(200, $r['code'], 'sauvegarde des parametres admin');
    $r = $admin->get('/api/admin/settings.php');
    t_eq('18', $r['body']['data']['parametres']['commission_taux_defaut'] ?? null, 'parametre commission bien enregistre');

    // -- Tarification dynamique (surge) ----------------------------------------
    t_section('Tarification dynamique');
    $paramsEstim = [
        'type_livraison_id' => 1, 'lat_depart' => 7.69, 'lng_depart' => -5.03,
        'lat_arrivee' => 7.70, 'lng_arrivee' => -5.04, 'express' => false,
    ];
    $r = $client->post('/api/client/estimation.php', $paramsEstim);
    $montantNormal = (float) ($r['body']['data']['montant_estime'] ?? 0);
    t_ok(($r['body']['data']['surge_actif'] ?? true) === false, 'surge inactif par defaut');

    $admin->post('/api/admin/settings.php', ['surge_actif' => '1', 'surge_manuel' => '1.5', 'surge_auto' => '0']);
    $r = $client->post('/api/client/estimation.php', $paramsEstim);
    t_ok(($r['body']['data']['surge_actif'] ?? false) === true, 'surge actif apres activation');
    t_eq(round($montantNormal * 1.5), (float) ($r['body']['data']['montant_estime'] ?? 0), 'montant majore x1.5');

    $admin->post('/api/admin/settings.php', ['surge_actif' => '0']); // ne pas polluer la suite

    // -- Securite --------------------------------------------------------------
    t_section('Controle d\'acces');
    $anon = new TestHttp($base);
    $r = $anon->get('/api/admin/dashboard.php');
    t_eq(401, $r['code'], 'dashboard admin inaccessible sans session (401)');

    $r = $client->get('/api/admin/dashboard.php');
    t_eq(403, $r['code'], 'dashboard admin interdit a un client (403)');
}
