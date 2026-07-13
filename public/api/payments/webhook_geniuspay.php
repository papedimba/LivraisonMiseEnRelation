<?php
declare(strict_types=1);

/**
 * Webhook GeniusPay (agregateur Mobile Money).
 *
 * ============================================================================
 * SQUELETTE EN ATTENTE DE LA DOCUMENTATION OFFICIELLE GENIUSPAY.
 * ============================================================================
 * Le format exact du payload (nom des champs de reference/statut) et le
 * mecanisme de signature (s'il existe) ne sont pas connus. Ce fichier se
 * contente donc de JOURNALISER la requete recue, sans agir sur aucune
 * commande ni aucune dette — deviner ces formats serait risque pour un
 * webhook qui declenche des mouvements d'argent.
 *
 * A completer des reception de la doc/des identifiants GeniusPay, sur le
 * modele des autres webhooks de ce dossier (voir webhook_orange.php pour un
 * champ simple, webhook_wave.php pour la verification de signature HMAC) :
 *
 *   1. Verifier la signature si GeniusPay en fournit une
 *      (cf. GENIUSPAY_WEBHOOK_SECRET dans config/config.php).
 *   2. Extraire la reference de transaction et le statut du paiement du
 *      payload reel (remplacer les noms de champs ci-dessous par les vrais).
 *   3. Convertir le statut GeniusPay vers 'reussi' | 'echec' (voir le pattern
 *      $statut = match(...) dans les autres webhooks).
 *   4. Appeler confirmer_paiement_par_commande() puis, si aucune commande ne
 *      correspond, confirmer_reglement_dette() en repli (voir
 *      includes/payments.php) — meme sequence que les autres webhooks.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/payments.php';

$raw = file_get_contents('php://input') ?: '';

// Journalisation uniquement : aucune action metier tant que le format reel
// n'est pas confirme (voir commentaire d'en-tete).
error_log('Webhook GeniusPay recu (integration non finalisee, payload journalise pour analyse) : ' . substr($raw, 0, 2000));

http_response_code(200);
echo 'ok';
