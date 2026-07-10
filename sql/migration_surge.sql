-- Migration : tarification dynamique (surge pricing).
-- A appliquer sur une base existante :
--   mysql -u USER -p BASE < sql/migration_surge.sql
SET NAMES utf8mb4;

INSERT INTO parametres (cle, valeur) VALUES
    ('surge_actif', '0'),
    ('surge_max', '2.0'),
    ('surge_manuel', '1.0'),
    ('surge_auto', '0'),
    ('surge_heures_pointe', '11-14,18-21'),
    ('surge_facteur_pointe', '1.2'),
    ('surge_ratio_seuil', '2'),
    ('surge_facteur_demande', '1.3')
ON DUPLICATE KEY UPDATE valeur = valeur;
