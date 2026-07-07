<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Passerelle de paiement Mobile Money.
 *
 * Chaque driver expose initierPaiement() qui retourne un tableau normalise :
 *   [
 *     'statut'       => 'reussi' | 'en_attente' | 'echec',
 *     'reference'    => string,   // reference de transaction cote operateur/nous
 *     'redirect_url' => ?string,  // page de paiement web (Wave, Orange Money)
 *     'instructions' => ?string,  // message (paiement par push USSD : MTN, Moov)
 *     'payload'      => array,    // reponse brute de l'operateur (audit)
 *   ]
 *
 * Le paiement Mobile Money est ASYNCHRONE : initierPaiement() renvoie en general
 * 'en_attente'. La confirmation definitive arrive via le webhook de l'operateur
 * (voir public/api/payments/webhook_*.php), qui met a jour la table paiements et
 * le statut_paiement de la commande.
 *
 * Tant qu'un operateur n'est pas configure dans .env, son driver bascule en mode
 * simulation (paiement confirme instantanement) pour permettre de tester tout le
 * parcours avant l'obtention des comptes marchands.
 */

interface PaymentDriver
{
    public function initierPaiement(string $numeroTelephone, float $montant, string $referenceCommande, array $contexte = []): array;
}

/**
 * Petit client HTTP JSON base sur curl (aucune dependance externe).
 */
final class HttpClient
{
    /**
     * @return array{code:int, body:array, raw:string}
     */
    public static function requete(string $methode, string $url, array $entetes = [], ?string $corps = null, int $timeout = 20): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $methode,
            CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        if ($corps !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corps);
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log('HttpClient error (' . $url . '): ' . $erreur);
            return ['code' => 0, 'body' => [], 'raw' => ''];
        }

        $body = json_decode((string) $raw, true);
        return ['code' => $code, 'body' => is_array($body) ? $body : [], 'raw' => (string) $raw];
    }
}

abstract class AbstractMobileMoneyDriver implements PaymentDriver
{
    protected array $config;
    protected string $code;

    public function __construct(array $config, string $code)
    {
        $this->config = $config;
        $this->code = $code;
    }

    abstract protected function estConfigure(): bool;

    protected function urlRetour(string $reference): string
    {
        return PUBLIC_BASE_URL . '/client/payment_return.php?ref=' . urlencode($reference);
    }

    protected function urlWebhook(): string
    {
        return PUBLIC_BASE_URL . '/api/payments/webhook_' . $this->code . '.php';
    }

    protected function simulation(string $numero, float $montant, string $reference): array
    {
        return [
            'statut' => 'reussi',
            'reference' => 'SIM-' . strtoupper($this->code) . '-' . strtoupper(bin2hex(random_bytes(4))),
            'redirect_url' => null,
            'instructions' => null,
            'payload' => [
                'mode' => 'simulation',
                'operateur' => $this->code,
                'numero' => $numero,
                'montant' => $montant,
                'commande_ref' => $reference,
                'note' => 'Paiement simule : aucune cle API ' . $this->code . ' configuree dans .env',
            ],
        ];
    }
}

/**
 * Orange Money Web Payment (Cote d'Ivoire).
 * Flux : OAuth2 client_credentials -> creation d'un webpayment -> URL de paiement.
 * Doc : https://developer.orange.com/apis/om-webpay
 */
final class OrangeMoneyDriver extends AbstractMobileMoneyDriver
{
    protected function estConfigure(): bool
    {
        return $this->config['client_id'] !== '' && $this->config['client_secret'] !== '' && $this->config['merchant_key'] !== '';
    }

    private function jeton(): ?string
    {
        $auth = base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']);
        $res = HttpClient::requete('POST', $this->config['base_url'] . '/oauth/v3/token', [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], 'grant_type=client_credentials');

        return $res['body']['access_token'] ?? null;
    }

    public function initierPaiement(string $numero, float $montant, string $reference, array $contexte = []): array
    {
        if (!$this->estConfigure()) {
            return $this->simulation($numero, $montant, $reference);
        }

        $jeton = $this->jeton();
        if ($jeton === null) {
            return ['statut' => 'echec', 'reference' => $reference, 'redirect_url' => null, 'instructions' => null, 'payload' => ['erreur' => 'jeton OAuth Orange indisponible']];
        }

        $corps = json_encode([
            'merchant_key' => $this->config['merchant_key'],
            'currency' => DEVISE_PAIEMENT === 'XOF' ? 'OUV' : DEVISE_PAIEMENT, // OUV = jeton de test OM
            'order_id' => $reference,
            'amount' => (int) round($montant),
            'return_url' => $this->urlRetour($reference),
            'cancel_url' => $this->urlRetour($reference),
            'notif_url' => $this->urlWebhook(),
            'lang' => 'fr',
            'reference' => $reference,
        ], JSON_UNESCAPED_SLASHES);

        $res = HttpClient::requete('POST', $this->config['base_url'] . '/orange-money-webpay/dev/v1/webpayment', [
            'Authorization: Bearer ' . $jeton,
            'Content-Type: application/json',
            'Accept: application/json',
        ], $corps);

        $urlPaiement = $res['body']['payment_url'] ?? null;
        if ($urlPaiement === null) {
            return ['statut' => 'echec', 'reference' => $reference, 'redirect_url' => null, 'instructions' => null, 'payload' => $res['body']];
        }

        return [
            'statut' => 'en_attente',
            'reference' => (string) ($res['body']['pay_token'] ?? $reference),
            'redirect_url' => $urlPaiement,
            'instructions' => null,
            'payload' => $res['body'],
        ];
    }
}

/**
 * MTN Mobile Money - Collection API (requesttopay, push USSD sur le telephone).
 * Doc : https://momodeveloper.mtn.com
 */
final class MtnMoneyDriver extends AbstractMobileMoneyDriver
{
    protected function estConfigure(): bool
    {
        return $this->config['subscription_key'] !== '' && $this->config['api_user'] !== '' && $this->config['api_key'] !== '';
    }

    private function jeton(): ?string
    {
        $auth = base64_encode($this->config['api_user'] . ':' . $this->config['api_key']);
        $res = HttpClient::requete('POST', $this->config['base_url'] . '/collection/token/', [
            'Authorization: Basic ' . $auth,
            'Ocp-Apim-Subscription-Key: ' . $this->config['subscription_key'],
        ], '');

        return $res['body']['access_token'] ?? null;
    }

    public function initierPaiement(string $numero, float $montant, string $reference, array $contexte = []): array
    {
        if (!$this->estConfigure()) {
            return $this->simulation($numero, $montant, $reference);
        }

        $jeton = $this->jeton();
        if ($jeton === null) {
            return ['statut' => 'echec', 'reference' => $reference, 'redirect_url' => null, 'instructions' => null, 'payload' => ['erreur' => 'jeton MTN indisponible']];
        }

        $referenceId = self::uuid4();
        $corps = json_encode([
            'amount' => (string) (int) round($montant),
            'currency' => DEVISE_PAIEMENT,
            'externalId' => $reference,
            'payer' => ['partyIdType' => 'MSISDN', 'partyId' => preg_replace('/\D/', '', $numero)],
            'payerMessage' => 'Livraison ' . $reference,
            'payeeNote' => 'Commande ' . $reference,
        ], JSON_UNESCAPED_SLASHES);

        $res = HttpClient::requete('POST', $this->config['base_url'] . '/collection/v1_0/requesttopay', [
            'Authorization: Bearer ' . $jeton,
            'X-Reference-Id: ' . $referenceId,
            'X-Target-Environment: ' . $this->config['environment'],
            'Ocp-Apim-Subscription-Key: ' . $this->config['subscription_key'],
            'X-Callback-Url: ' . $this->urlWebhook(),
            'Content-Type: application/json',
        ], $corps);

        // 202 Accepted = demande envoyee, le client doit valider sur son telephone.
        if ($res['code'] === 202) {
            return [
                'statut' => 'en_attente',
                'reference' => $referenceId,
                'redirect_url' => null,
                'instructions' => 'Validez le paiement en saisissant votre code sur votre telephone MTN Mobile Money.',
                'payload' => ['reference_id' => $referenceId],
            ];
        }

        return ['statut' => 'echec', 'reference' => $referenceId, 'redirect_url' => null, 'instructions' => null, 'payload' => $res['body']];
    }

    private static function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}

/**
 * Moov Money - modele collection/token (structure generique a ajuster selon le
 * contrat marchand fourni par Moov Africa Cote d'Ivoire).
 */
final class MoovMoneyDriver extends AbstractMobileMoneyDriver
{
    protected function estConfigure(): bool
    {
        return $this->config['base_url'] !== '' && $this->config['client_id'] !== '' && $this->config['client_secret'] !== '';
    }

    private function jeton(): ?string
    {
        $res = HttpClient::requete('POST', $this->config['base_url'] . '/token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]));

        return $res['body']['access_token'] ?? null;
    }

    public function initierPaiement(string $numero, float $montant, string $reference, array $contexte = []): array
    {
        if (!$this->estConfigure()) {
            return $this->simulation($numero, $montant, $reference);
        }

        $jeton = $this->jeton();
        if ($jeton === null) {
            return ['statut' => 'echec', 'reference' => $reference, 'redirect_url' => null, 'instructions' => null, 'payload' => ['erreur' => 'jeton Moov indisponible']];
        }

        $corps = json_encode([
            'amount' => (int) round($montant),
            'currency' => DEVISE_PAIEMENT,
            'reference' => $reference,
            'msisdn' => preg_replace('/\D/', '', $numero),
            'callback_url' => $this->urlWebhook(),
            'merchant_id' => $this->config['merchant_id'],
        ], JSON_UNESCAPED_SLASHES);

        $res = HttpClient::requete('POST', $this->config['base_url'] . '/collection/requesttopay', [
            'Authorization: Bearer ' . $jeton,
            'Content-Type: application/json',
        ], $corps);

        if (in_array($res['code'], [200, 202], true)) {
            return [
                'statut' => 'en_attente',
                'reference' => (string) ($res['body']['transaction_id'] ?? $reference),
                'redirect_url' => null,
                'instructions' => 'Validez le paiement sur votre telephone Moov Money.',
                'payload' => $res['body'],
            ];
        }

        return ['statut' => 'echec', 'reference' => $reference, 'redirect_url' => null, 'instructions' => null, 'payload' => $res['body']];
    }
}

/**
 * Wave - Checkout API (Cote d'Ivoire). Cree une session de paiement et renvoie
 * une URL (wave_launch_url) vers laquelle rediriger le client.
 * Doc : https://docs.wave.com
 */
final class WaveDriver extends AbstractMobileMoneyDriver
{
    protected function estConfigure(): bool
    {
        return $this->config['api_key'] !== '';
    }

    public function initierPaiement(string $numero, float $montant, string $reference, array $contexte = []): array
    {
        if (!$this->estConfigure()) {
            return $this->simulation($numero, $montant, $reference);
        }

        $corps = json_encode([
            'amount' => (string) (int) round($montant),
            'currency' => DEVISE_PAIEMENT,
            'error_url' => $this->urlRetour($reference),
            'success_url' => $this->urlRetour($reference),
            'client_reference' => $reference,
        ], JSON_UNESCAPED_SLASHES);

        $res = HttpClient::requete('POST', $this->config['base_url'] . '/v1/checkout/sessions', [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json',
        ], $corps);

        $url = $res['body']['wave_launch_url'] ?? null;
        if ($url === null) {
            return ['statut' => 'echec', 'reference' => $reference, 'redirect_url' => null, 'instructions' => null, 'payload' => $res['body']];
        }

        return [
            'statut' => 'en_attente',
            'reference' => (string) ($res['body']['id'] ?? $reference),
            'redirect_url' => $url,
            'instructions' => null,
            'payload' => $res['body'],
        ];
    }
}

final class EspecesDriver implements PaymentDriver
{
    public function initierPaiement(string $numero, float $montant, string $reference, array $contexte = []): array
    {
        return [
            'statut' => 'en_attente',
            'reference' => 'CASH-' . strtoupper(bin2hex(random_bytes(4))),
            'redirect_url' => null,
            'instructions' => 'Paiement en especes a la livraison, confirme par le livreur.',
            'payload' => ['mode' => 'especes'],
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
