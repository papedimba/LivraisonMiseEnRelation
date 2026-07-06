<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Interface commune pour les moyens de paiement Mobile Money.
 * Chaque driver expose initierPaiement() qui retourne un tableau normalise :
 *   ['statut' => 'reussi'|'en_attente'|'echec', 'reference' => string, 'payload' => array]
 *
 * En l'absence de cles API configurees (.env), les drivers fonctionnent en mode
 * "simulation" : ils generent une reference et marquent le paiement comme reussi,
 * ce qui permet de tester tout le parcours commande -> paiement -> livraison
 * avant la souscription aux vraies API des operateurs.
 */
interface PaymentDriver
{
    public function initierPaiement(string $numeroTelephone, float $montant, string $referenceCommande): array;
}

abstract class AbstractMobileMoneyDriver implements PaymentDriver
{
    protected array $config;
    protected string $nomOperateur;

    public function __construct(array $config, string $nomOperateur)
    {
        $this->config = $config;
        $this->nomOperateur = $nomOperateur;
    }

    protected function estConfigure(): bool
    {
        return !empty(array_filter($this->config));
    }

    protected function modeSimulation(string $numeroTelephone, float $montant, string $referenceCommande): array
    {
        return [
            'statut' => 'reussi',
            'reference' => 'SIM-' . strtoupper($this->nomOperateur) . '-' . strtoupper(bin2hex(random_bytes(4))),
            'payload' => [
                'mode' => 'simulation',
                'operateur' => $this->nomOperateur,
                'numero' => $numeroTelephone,
                'montant' => $montant,
                'commande_ref' => $referenceCommande,
                'note' => 'Paiement simule car aucune cle API ' . $this->nomOperateur . ' n\'est configuree dans .env',
            ],
        ];
    }
}

final class OrangeMoneyDriver extends AbstractMobileMoneyDriver
{
    public function initierPaiement(string $numeroTelephone, float $montant, string $referenceCommande): array
    {
        if (!$this->estConfigure()) {
            return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
        }

        // Emplacement pour le vrai appel a l'API Orange Money Web Payment
        // Documentation : https://developer.orange.com/apis/om-webpay
        // A implementer avec curl une fois les identifiants marchands obtenus.
        return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
    }
}

final class MtnMoneyDriver extends AbstractMobileMoneyDriver
{
    public function initierPaiement(string $numeroTelephone, float $montant, string $referenceCommande): array
    {
        if (!$this->estConfigure()) {
            return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
        }

        // Emplacement pour le vrai appel a l'API MTN MoMo Collection
        return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
    }
}

final class MoovMoneyDriver extends AbstractMobileMoneyDriver
{
    public function initierPaiement(string $numeroTelephone, float $montant, string $referenceCommande): array
    {
        if (!$this->estConfigure()) {
            return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
        }

        // Emplacement pour le vrai appel a l'API Moov Money
        return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
    }
}

final class WaveDriver extends AbstractMobileMoneyDriver
{
    public function initierPaiement(string $numeroTelephone, float $montant, string $referenceCommande): array
    {
        if (!$this->estConfigure()) {
            return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
        }

        // Emplacement pour le vrai appel a l'API Wave Checkout
        // Documentation : https://docs.wave.com
        return $this->modeSimulation($numeroTelephone, $montant, $referenceCommande);
    }
}

final class EspecesDriver implements PaymentDriver
{
    public function initierPaiement(string $numeroTelephone, float $montant, string $referenceCommande): array
    {
        return [
            'statut' => 'en_attente',
            'reference' => 'CASH-' . strtoupper(bin2hex(random_bytes(4))),
            'payload' => [
                'mode' => 'especes',
                'note' => 'Paiement en especes a la livraison, confirme par le livreur.',
            ],
        ];
    }
}

final class PaymentGateway
{
    public static function driver(string $methode): PaymentDriver
    {
        $config = MOBILE_MONEY_CONFIG;

        return match ($methode) {
            'orange_money' => new OrangeMoneyDriver($config['orange_money'], 'orange_money'),
            'mtn_money' => new MtnMoneyDriver($config['mtn_money'], 'mtn_money'),
            'moov_money' => new MoovMoneyDriver($config['moov_money'], 'moov_money'),
            'wave' => new WaveDriver($config['wave'], 'wave'),
            'especes' => new EspecesDriver(),
            default => throw new InvalidArgumentException('Methode de paiement inconnue : ' . $methode),
        };
    }
}
