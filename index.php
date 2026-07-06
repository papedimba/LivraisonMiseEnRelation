<?php
/**
 * Ce fichier ne sert qu'a eviter une erreur 403 si le document root de
 * l'hebergement pointe par erreur sur la racine du projet au lieu du
 * dossier /public (configuration correcte a faire dans cPanel/o2switch).
 */
header('Location: /public/');
exit;
