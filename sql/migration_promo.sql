-- Migration : codes promo. A executer sur une base existante.
-- (Les colonnes code_promo/reduction de la table commandes sont dans
--  migration_reassign_proof.sql pour les colonnes, ou ajoutees ici si besoin.)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS codes_promo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    type ENUM('pourcentage','montant') NOT NULL DEFAULT 'pourcentage',
    valeur DECIMAL(10,2) NOT NULL,
    montant_min DECIMAL(10,2) NOT NULL DEFAULT 0,
    usage_max INT UNSIGNED DEFAULT NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    date_debut DATE DEFAULT NULL,
    date_fin DATE DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_code_promo (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colonnes de reduction sur la commande.
ALTER TABLE commandes
    ADD COLUMN code_promo VARCHAR(40) DEFAULT NULL,
    ADD COLUMN reduction DECIMAL(10,2) NOT NULL DEFAULT 0;
