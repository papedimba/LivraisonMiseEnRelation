<?php
declare(strict_types=1);

/**
 * Lanceur de tests.
 *
 *   php tests/run.php                 -> tests unitaires uniquement
 *   TEST_BASE_URL=http://... php tests/run.php  -> + tests d'integration API
 *
 * Le plus simple : ./tests/run.sh (prepare une base et un serveur de test).
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/unit_test.php';

echo "\033[1mLivraisonCI - suite de tests\033[0m\n";

tests_unitaires();

$base = getenv('TEST_BASE_URL');
if ($base !== false && $base !== '') {
    require_once __DIR__ . '/api_test.php';
    tests_api(rtrim($base, '/'));
} else {
    echo "\n(Tests d'integration API ignores : definir TEST_BASE_URL pour les activer.)\n";
}

exit(t_bilan());
