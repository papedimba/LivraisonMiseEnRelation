<?php
declare(strict_types=1);

/**
 * Visionneuse du journal d'erreurs (storage/logs/app.log) directement dans
 * l'admin, sans acces FTP/SSH au serveur. Sert aussi de diagnostic quand le
 * fichier semble "introuvable" : sur certains hebergements mutualises,
 * ini_set('error_log', ...) est ignore (droits, open_basedir, configuration
 * serveur) et PHP continue d'ecrire vers son propre journal par defaut,
 * ailleurs sur le serveur. Comparer LOG_PATH (celui que l'app demande) et
 * ini_get('error_log') (celui reellement actif) permet de le detecter.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$dossierLogs = STORAGE_PATH . '/logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = request_body();
    if ((string) input($body, 'action', '') === 'test_ecriture') {
        // Ecrit une ligne reperable et confirme si error_log() l'a bien
        // deposee au chemin attendu (LOG_PATH) plutot qu'ailleurs.
        $marqueur = 'TEST-JOURNAL-' . date('Y-m-d H:i:s') . '-' . bin2hex(random_bytes(3));
        error_log($marqueur);

        // Laisse le temps au systeme de fichiers de finaliser l'ecriture
        // (generalement instantane, mais certains FS reseau sur mutualise
        // peuvent avoir un leger delai).
        clearstatcache(true, LOG_PATH);
        $contenu = is_file(LOG_PATH) ? lire_fin_fichier(LOG_PATH, 20000) : '';
        $trouve = str_contains($contenu, $marqueur);

        Response::success([
            'marqueur' => $marqueur,
            'trouve_dans_log_path' => $trouve,
        ], $trouve
            ? 'Ecriture confirmee dans storage/logs/app.log.'
            : 'La ligne de test n\'apparait PAS dans storage/logs/app.log : error_log() ecrit ailleurs (voir php_error_log_actif ci-dessous).');
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
