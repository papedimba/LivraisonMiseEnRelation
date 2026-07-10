<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Tarification dynamique ("surge").
 *
 * Calcule un multiplicateur applique au cout de livraison selon des regles
 * parametrables par l'admin :
 *   - surge_manuel : majoration fixe decidee par l'admin (plancher) ;
 *   - heure de pointe : majoration pendant des plages horaires ;
 *   - forte demande : majoration quand le rapport commandes en attente /
 *     livreurs en ligne depasse un seuil.
 *
 * Les facteurs ne se cumulent pas (on retient le plus eleve) et le resultat
 * est borne par surge_max. Retourne 1.0 si la tarification dynamique est
 * desactivee.
 *
 * @return array{facteur: float, actif: bool, raison: string}
 */
function surge_multiplicateur(PDO $db): array
{
    if (parametre($db, 'surge_actif', '0') !== '1') {
        return ['facteur' => 1.0, 'actif' => false, 'raison' => ''];
    }

    $max = (float) parametre($db, 'surge_max', '2.0');
    $manuel = (float) parametre($db, 'surge_manuel', '1.0');
    $facteur = max(1.0, $manuel);
    $raisons = [];
    if ($manuel > 1.0) {
        $raisons[] = 'majoration manuelle';
    }

    if (parametre($db, 'surge_auto', '0') === '1') {
        // Heures de pointe (ex: "11-14,18-21").
        if (surge_heure_de_pointe((int) date('G'), (string) parametre($db, 'surge_heures_pointe', ''))) {
            $fp = (float) parametre($db, 'surge_facteur_pointe', '1.2');
            if ($fp > $facteur) {
                $facteur = $fp;
            }
            $raisons[] = 'heure de pointe';
        }

        // Rapport demande / offre.
        $seuil = (float) parametre($db, 'surge_ratio_seuil', '2');
        if ($seuil > 0) {
            $enAttente = (int) $db->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en_attente'")->fetchColumn();
            $disponibles = (int) $db->query(
                "SELECT COUNT(*) FROM livreur_details WHERE disponibilite = 'en_ligne' AND statut_validation = 'valide'"
            )->fetchColumn();
            $ratioAtteint = $enAttente > 0 && ($disponibles === 0 || ($enAttente / $disponibles) >= $seuil);
            if ($ratioAtteint) {
                $fd = (float) parametre($db, 'surge_facteur_demande', '1.3');
                if ($fd > $facteur) {
                    $facteur = $fd;
                }
                $raisons[] = 'forte demande';
            }
        }
    }

    $facteur = max(1.0, min($facteur, $max));
    $facteur = round($facteur, 2);

    return [
        'facteur' => $facteur,
        'actif' => $facteur > 1.0,
        'raison' => implode(', ', $raisons),
    ];
}

/**
 * Indique si l'heure donnee (0-23) tombe dans l'une des plages "a-b" (b exclu)
 * ou "a" listees, separees par des virgules.
 */
function surge_heure_de_pointe(int $heure, string $plages): bool
{
    foreach (explode(',', $plages) as $plage) {
        $plage = trim($plage);
        if ($plage === '') {
            continue;
        }
        if (str_contains($plage, '-')) {
            [$debut, $fin] = array_map('intval', explode('-', $plage, 2));
            if ($heure >= $debut && $heure < $fin) {
                return true;
            }
        } elseif ((int) $plage === $heure) {
            return true;
        }
    }
    return false;
}

/**
 * Applique la tarification dynamique a un cout de livraison.
 */
function appliquer_surge(float $coutLivraison, array $surge): float
{
    return round($coutLivraison * $surge['facteur']);
}

/**
 * Retourne le moyen de transport actif correspondant a l'id, ou null.
 *
 * @return array{id:int,code:string,nom:string,multiplicateur:string}|null
 */
function moyen_transport_actif(PDO $db, ?int $id): ?array
{
    if ($id === null || $id <= 0) {
        return null;
    }
    $stmt = $db->prepare('SELECT id, code, nom, multiplicateur FROM moyens_transport WHERE id = :id AND actif = 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}
