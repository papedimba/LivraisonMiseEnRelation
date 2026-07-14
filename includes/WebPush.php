<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/webpush_util.php';

/**
 * Envoi de notifications Web Push selon le modele "reveil sans payload" :
 * on envoie une requete push vide (sans corps chiffre), ce qui reveille le
 * service worker ; celui-ci va ensuite chercher les notifications non lues
 * aupres du serveur. Cela evite le chiffrement aes128gcm du payload : seule
 * l'authentification VAPID (JWT ES256) est necessaire.
 */
final class WebPush
{
    public static function isConfigured(): bool
    {
        return VAPID_PUBLIC_KEY !== ''
            && VAPID_PRIVATE_KEY_PATH !== ''
            && VAPID_SUBJECT !== ''
            && is_file(self::cheminClePrivee());
    }

    private static function cheminClePrivee(): string
    {
        $chemin = VAPID_PRIVATE_KEY_PATH;
        // Chemin relatif -> resolu depuis la racine du projet.
        if ($chemin !== '' && $chemin[0] !== '/') {
            $chemin = APP_ROOT . '/' . $chemin;
        }
        return $chemin;
    }

    /**
     * Envoie un push de reveil a un abonnement.
     * Retourne le code HTTP renvoye par le service push (201 = succes),
     * ou 0 en cas d'echec reseau, ou null si non configure.
     */
    public static function envoyer(string $endpoint): ?int
    {
        if (!self::isConfigured()) {
            return null;
        }

        $origine = self::origine($endpoint);
        if ($origine === null) {
            return 0;
        }

        $pem = file_get_contents(self::cheminClePrivee());
        if ($pem === false) {
            return 0;
        }

        try {
            $jwt = webpush_sign_jwt([
                'aud' => $origine,
                'exp' => time() + 43200, // 12h
                'sub' => VAPID_SUBJECT,
            ], $pem);
        } catch (Throwable $e) {
            app_log('WebPush JWT error: ' . $e->getMessage());
            return 0;
        }

        $entetes = [
            'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
            'TTL: 2419200',
            'Content-Length: 0',
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code;
    }

    /**
     * Envoie un push de reveil a tous les abonnements d'un utilisateur.
     * Best-effort : les abonnements expires (404/410) sont supprimes.
     */
    public static function envoyerAUtilisateur(PDO $db, int $userId): void
    {
        if (!self::isConfigured()) {
            return;
        }

        $stmt = $db->prepare('SELECT id, endpoint FROM push_subscriptions WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        $abonnements = $stmt->fetchAll();

        foreach ($abonnements as $abonnement) {
            $code = self::envoyer($abonnement['endpoint']);
            if ($code === 404 || $code === 410) {
                $db->prepare('DELETE FROM push_subscriptions WHERE id = :id')
                    ->execute(['id' => $abonnement['id']]);
            }
        }
    }

    private static function origine(string $endpoint): ?string
    {
        $parties = parse_url($endpoint);
        if (!isset($parties['scheme'], $parties['host'])) {
            return null;
        }
        return $parties['scheme'] . '://' . $parties['host'];
    }
}
