-- Migration : map-matching OSRM (traces calees sur les rues, en cache).
-- La heatmap se calcule a la volee depuis les commandes livrees (pas de table).
-- A appliquer sur une base existante :
--   mysql -u USER -p BASE < sql/migration_routes_heatmap.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS routes_matchees (
    commande_id INT UNSIGNED PRIMARY KEY,
    geojson MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_routematch_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO parametres (cle, valeur) VALUES ('route_matching', '1')
ON DUPLICATE KEY UPDATE valeur = valeur;
