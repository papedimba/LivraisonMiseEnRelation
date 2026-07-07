<?php
/**
 * Point d'entree web pour le traitement periodique des commandes, destine aux
 * hebergements sans cron en ligne de commande. A appeler via un service de cron
 * externe (ex. cron-job.org) :
 *   https://votre-domaine.com/cron.php?token=VOTRE_CRON_TOKEN
 * Definir CRON_TOKEN dans .env. Preferez le cron CLI quand il est disponible :
 *   * * * * * php /chemin/scripts/process_orders.php
 */
require __DIR__ . '/../scripts/process_orders.php';
