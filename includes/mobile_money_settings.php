<?php
declare(strict_types=1);

/**
 * Configuration Mobile Money modifiable par l'administrateur (sans acces au
 * serveur / au fichier .env). Les identifiants sont stockes dans la table
 * `parametres` (cle 'mm_<operateur>_<champ>') et surchargent les valeurs par
 * defaut issues de .env (config/config.php) : .env reste le socle initial /
 * la config de secours, l'admin peut ensuite ajuster ou completer en direct.
 *
 * Champs "secrets" (cles API, jetons...) : jamais renvoyes en clair a l'admin
 * apres enregistrement, seulement un apercu masque (ex. "••••3f2a"). Un champ
 * laisse vide lors de l'enregistrement conserve sa valeur actuelle (pas
 * d'ecrasement accidentel).
 */

// Champs geres par operateur (whitelist stricte : aucune autre cle ne peut
// etre ecrite dans `parametres` via ce mecanisme).
const MOBILE_MONEY_CHAMPS = [
    'orange_money' => ['base_url', 'client_id', 'client_secret', 'merchant_key'],
    'mtn_money' => ['base_url', 'environment', 'subscription_key', 'api_user', 'api_key'],
    'moov_money' => ['base_url', 'client_id', 'client_secret', 'merchant_id'],
    'wave' => ['base_url', 'api_key', 'webhook_secret'],
    'geniuspay' => ['base_url', 'api_key', 'merchant_id', 'webhook_secret'],
];

// Champs non sensibles (affiches et modifiables en clair). Tout le reste est
// traite comme un secret (masque en lecture).
const MOBILE_MONEY_CHAMPS_PUBLICS = ['base_url', 'environment'];

function mobile_money_champ_secret(string $champ): bool
{
    return !in_array($champ, MOBILE_MONEY_CHAMPS_PUBLICS, true);
}

function mobile_money_cle_parametre(string $operateur, string $champ): string
{
    return "mm_{$operateur}_{$champ}";
}

/**
 * Configuration effective (fusion .env + surcharges admin en base), utilisee
 * par PaymentGateway pour initier les paiements. Les valeurs en base
 * l'emportent sur .env des qu'elles sont non vides.
 */
function mobile_money_config_effective(PDO $db): array
{
    $cles = [];
    foreach (MOBILE_MONEY_CHAMPS as $operateur => $champs) {
        foreach ($champs as $champ) {
            $cles[] = mobile_money_cle_parametre($operateur, $champ);
        }
    }

    $surcharges = [];
    if (!empty($cles)) {
        $placeholders = implode(',', array_fill(0, count($cles), '?'));
        $stmt = $db->prepare("SELECT cle, valeur FROM parametres WHERE cle IN ({$placeholders})");
        $stmt->execute($cles);
        foreach ($stmt->fetchAll() as $row) {
            $surcharges[$row['cle']] = $row['valeur'];
        }
    }

    $config = MOBILE_MONEY_CONFIG;
    foreach (MOBILE_MONEY_CHAMPS as $operateur => $champs) {
        foreach ($champs as $champ) {
            $cle = mobile_money_cle_parametre($operateur, $champ);
            if (isset($surcharges[$cle]) && $surcharges[$cle] !== '') {
                $config[$operateur][$champ] = $surcharges[$cle];
            }
        }
    }

    return $config;
}

/**
 * Etat courant pour l'ecran d'administration : valeurs en clair pour les
 * champs publics, apercu masque pour les champs secrets (jamais la valeur
 * reelle). 'source' indique si la valeur vient d'une surcharge admin (base)
 * ou du fichier .env.
 */
function mobile_money_etat_admin(PDO $db): array
{
    $effectif = mobile_money_config_effective($db);

    $cles = [];
    foreach (MOBILE_MONEY_CHAMPS as $operateur => $champs) {
        foreach ($champs as $champ) {
            $cles[] = mobile_money_cle_parametre($operateur, $champ);
        }
    }
    $surcharges = [];
    if (!empty($cles)) {
        $placeholders = implode(',', array_fill(0, count($cles), '?'));
        $stmt = $db->prepare("SELECT cle, valeur FROM parametres WHERE cle IN ({$placeholders})");
        $stmt->execute($cles);
        foreach ($stmt->fetchAll() as $row) {
            $surcharges[$row['cle']] = $row['valeur'];
        }
    }

    $etat = [];
    foreach (MOBILE_MONEY_CHAMPS as $operateur => $champs) {
        $champsEtat = [];
        $configureCritiques = true; // tous les champs secrets doivent etre remplis
        foreach ($champs as $champ) {
            $valeur = (string) ($effectif[$operateur][$champ] ?? '');
            $cle = mobile_money_cle_parametre($operateur, $champ);
            $source = isset($surcharges[$cle]) && $surcharges[$cle] !== '' ? 'admin' : 'env';

            if (mobile_money_champ_secret($champ)) {
                if ($valeur === '') {
                    $configureCritiques = false;
                }
                $champsEtat[$champ] = [
                    'renseigne' => $valeur !== '',
                    'apercu' => $valeur !== '' ? '••••' . substr($valeur, -4) : '',
                    'source' => $source,
                ];
            } else {
                $champsEtat[$champ] = [
                    'valeur' => $valeur,
                    'source' => $source,
                ];
            }
        }
        $etat[$operateur] = [
            'champs' => $champsEtat,
            'configure' => $configureCritiques,
        ];
    }

    return $etat;
}

/**
 * Enregistre (ou reinitialise) les identifiants d'un operateur. Un champ
 * absent ou vide dans $valeurs conserve sa valeur actuelle (pas
 * d'ecrasement). $reinitialiser supprime toutes les surcharges de
 * l'operateur (retour aux valeurs de .env).
 *
 * @param array<string, string> $valeurs champ => nouvelle valeur
 */
function mobile_money_enregistrer(PDO $db, string $operateur, array $valeurs, bool $reinitialiser = false): void
{
    if (!isset(MOBILE_MONEY_CHAMPS[$operateur])) {
        throw new InvalidArgumentException('Operateur Mobile Money inconnu : ' . $operateur);
    }

    if ($reinitialiser) {
        foreach (MOBILE_MONEY_CHAMPS[$operateur] as $champ) {
            $db->prepare('DELETE FROM parametres WHERE cle = :cle')
                ->execute(['cle' => mobile_money_cle_parametre($operateur, $champ)]);
        }
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO parametres (cle, valeur) VALUES (:cle, :valeur_ins)
         ON DUPLICATE KEY UPDATE valeur = :valeur_upd'
    );
    foreach (MOBILE_MONEY_CHAMPS[$operateur] as $champ) {
        if (!array_key_exists($champ, $valeurs)) {
            continue;
        }
        $valeur = trim((string) $valeurs[$champ]);
        if ($valeur === '') {
            continue; // vide = on conserve la valeur actuelle
        }
        $stmt->execute([
            'cle' => mobile_money_cle_parametre($operateur, $champ),
            'valeur_ins' => $valeur,
            'valeur_upd' => $valeur,
        ]);
    }
}
