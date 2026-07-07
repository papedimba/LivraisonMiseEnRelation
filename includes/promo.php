<?php
declare(strict_types=1);

/**
 * Exception levee quand un code promo saisi est invalide.
 */
final class PromoException extends RuntimeException
{
}

/**
 * Valide un code promo et calcule la reduction applicable.
 *
 * @return array{0: ?string, 1: float, 2: ?int} [code applique, reduction, id promo]
 * @throws PromoException si un code non vide est invalide
 */
function appliquer_code_promo(PDO $db, string $code, float $montantBrut): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return [null, 0.0, null];
    }

    $stmt = $db->prepare('SELECT * FROM codes_promo WHERE code = :code');
    $stmt->execute(['code' => $code]);
    $promo = $stmt->fetch();

    if (!$promo) {
        throw new PromoException('Code promo introuvable.');
    }
    if ((int) $promo['actif'] !== 1) {
        throw new PromoException('Ce code promo n\'est plus actif.');
    }
    $aujourdhui = date('Y-m-d');
    if ($promo['date_debut'] !== null && $aujourdhui < $promo['date_debut']) {
        throw new PromoException('Ce code promo n\'est pas encore valable.');
    }
    if ($promo['date_fin'] !== null && $aujourdhui > $promo['date_fin']) {
        throw new PromoException('Ce code promo a expire.');
    }
    if ($promo['usage_max'] !== null && (int) $promo['usage_count'] >= (int) $promo['usage_max']) {
        throw new PromoException('Ce code promo a atteint son nombre maximal d\'utilisations.');
    }
    if ($montantBrut < (float) $promo['montant_min']) {
        throw new PromoException('Montant minimum de ' . (int) $promo['montant_min'] . ' FCFA non atteint pour ce code.');
    }

    if ($promo['type'] === 'pourcentage') {
        $reduction = round($montantBrut * (float) $promo['valeur'] / 100, 0);
    } else {
        $reduction = (float) $promo['valeur'];
    }
    $reduction = min($reduction, $montantBrut); // jamais plus que le montant

    return [$promo['code'], (float) $reduction, (int) $promo['id']];
}

/**
 * Incremente le compteur d'utilisation d'un code promo (apres commande reussie).
 */
function incrementer_usage_promo(PDO $db, ?int $promoId): void
{
    if ($promoId === null) {
        return;
    }
    $db->prepare('UPDATE codes_promo SET usage_count = usage_count + 1 WHERE id = :id')
        ->execute(['id' => $promoId]);
}
