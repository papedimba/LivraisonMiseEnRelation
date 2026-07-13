<?php
declare(strict_types=1);

/**
 * Configuration Mobile Money (Orange/MTN/Moov/Wave/GeniusPay) modifiable par
 * l'administrateur, sans acces au serveur. Voir includes/mobile_money_settings.php
 * pour le detail du stockage (table `parametres`) et du masquage des secrets.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/mobile_money_settings.php';

Auth::requireRole('admin');

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = request_body();
    $operateur = clean_str((string) input($body, 'operateur', ''));

    if (!isset(MOBILE_MONEY_CHAMPS[$operateur])) {
        Response::error('Operateur Mobile Money inconnu.', 422);
    }

    $reinitialiser = (bool) input($body, 'reinitialiser', false);
    $champsBruts = input($body, 'champs', []);
    $champs = is_array($champsBruts) ? $champsBruts : [];

    // Seuls les champs de la whitelist de cet operateur sont pris en compte
    // (aucune cle arbitraire ne peut atteindre la table `parametres`).
    $valeurs = [];
    foreach (MOBILE_MONEY_CHAMPS[$operateur] as $champ) {
        if (array_key_exists($champ, $champs)) {
            $valeurs[$champ] = clean_str((string) $champs[$champ]);
        }
    }

    try {
        mobile_money_enregistrer($db, $operateur, $valeurs, $reinitialiser);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), 422);
    }

    Response::success(
        ['etat' => mobile_money_etat_admin($db)[$operateur]],
        $reinitialiser ? 'Identifiants reinitialises (retour aux valeurs de .env).' : 'Identifiants enregistres.'
    );
}

Response::success(['operateurs' => mobile_money_etat_admin($db)]);
