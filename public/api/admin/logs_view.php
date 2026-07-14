<?php
declare(strict_types=1);

/**
 * Visionneuse du journal d'erreurs (storage/logs/app.log) directement dans
 * l'admin, sans acces FTP/SSH au serveur. L'ecriture applicative (app_log(),
 * voir config/config.php) se fait par ecriture DIRECTE dans ce fichier, sans
 * dependre de la directive ini 'error_log' - certains hebergements mutualises
 * l'ignorent purement et simplement (droits, configuration serveur imposee),
 * ce que 'php_error_log_actif' ci-dessous permet de detecter a titre informatif
 * (mais n'affecte plus l'ecriture reelle du journal applicatif).
 */

// Cette page EST l'outil de diagnostic : si une erreur imprevue s'y produit
// (y compris au chargement des dependances ci-dessous), afficher le message
// generique "Erreur serveur." (comportement standard en production ailleurs
// dans l'app) serait contre-productif - on remonte donc le vrai message ici,
// quel que soit APP_ENV. Reserve aux admins uniquement, risque de fuite
// d'information minime.
try {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../includes/Response.php';
    require_once __DIR__ . '/../../../includes/Auth.php';

    Auth::requireRole('admin');

    // Filet de securite : LOG_PATH est definie sans condition dans
    // config.php, mais si un deploiement partiel/un cache d'opcode laissait
    // une version anterieure de ce fichier sur le serveur, la constante
    // manquerait ici. On se protege explicitement plutot que de planter.
    if (!defined('LOG_PATH')) {
        define('LOG_PATH', STORAGE_PATH . '/logs/app.log');
    }

    $dossierLogs = STORAGE_PATH . '/logs';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = request_body();
        if ((string) input($body, 'action', '') === 'test_ecriture') {
            // Ecrit une ligne reperable via app_log() (ecriture directe dans
            // le fichier, independante de la directive ini 'error_log') et
            // confirme qu'elle est bien arrivee.
            $marqueur = 'TEST-JOURNAL-' . date('Y-m-d H:i:s') . '-' . bin2hex(random_bytes(3));
            app_log($marqueur);

            clearstatcache(true, LOG_PATH);
            $contenu = is_file(LOG_PATH) ? lire_fin_fichier(LOG_PATH, 20000) : '';
            $trouve = str_contains($contenu, $marqueur);

            Response::success([
                'marqueur' => $marqueur,
                'trouve_dans_log_path' => $trouve,
            ], $trouve
                ? 'Ecriture confirmee dans storage/logs/app.log.'
                : 'Echec d\'ecriture dans storage/logs/app.log : verifiez que le dossier storage/logs est bien inscriptible par PHP (droits/proprietaire sur le serveur).');
        }
        Response::error('Action inconnue.', 422);
    }

    $infos = [
        'log_path' => defined('LOG_PATH') ? LOG_PATH : null,
        'dossier_existe' => is_dir($dossierLogs),
        'dossier_inscriptible' => is_dir($dossierLogs) && is_writable($dossierLogs),
        'fichier_existe' => defined('LOG_PATH') && is_file(LOG_PATH),
        'fichier_taille_octets' => (defined('LOG_PATH') && is_file(LOG_PATH)) ? filesize(LOG_PATH) : null,
        // Chemin REELLEMENT utilise par PHP pour error_log() a cet instant : s'il
        // differe de log_path, c'est que l'hebergeur ignore/bloque notre ini_set()
        // et qu'il faut chercher le journal a cet autre emplacement (ou le
        // demander a l'hebergeur).
        'php_error_log_actif' => ini_get('error_log') ?: '(non defini - repli sur le journal du serveur web)',
        'log_errors_actif' => ini_get('log_errors') === '1',
    ];

    $contenu = (defined('LOG_PATH') && is_file(LOG_PATH)) ? lire_fin_fichier(LOG_PATH, 200000) : '';
    $lignes = $contenu !== '' ? array_slice(array_filter(explode("\n", $contenu)), -300) : [];

    Response::success([
        'infos' => $infos,
        'lignes' => array_values($lignes),
    ]);
} catch (Throwable $e) {
    // Reponse JSON autonome (ne depend pas de Response::error(), qui pourrait
    // ne pas etre chargee si l'erreur survient pendant les require ci-dessus).
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erreur du diagnostic : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
        'errors' => [],
    ], JSON_UNESCAPED_UNICODE);
}
