-- Migration : moyens de transport (choix client + multiplicateur tarifaire).
-- A appliquer sur une base existante :
--   mysql -u USER -p BASE < sql/migration_transport.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS moyens_transport (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    icone VARCHAR(50) DEFAULT NULL,
    multiplicateur DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transport_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO moyens_transport (code, nom, icone, multiplicateur) VALUES
('moto', 'Moto', 'moto', 1.00),
('velo', 'Velo', 'velo', 0.90),
('tricycle', 'Tricycle', 'tricycle', 1.20),
('voiture', 'Voiture', 'voiture', 1.50),
('camionnette', 'Camionnette', 'camion', 2.00)
ON DUPLICATE KEY UPDATE code = code;

-- Ajoute la colonne + la cle etrangere seulement si elles n'existent pas encore.
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'commandes' AND column_name = 'moyen_transport_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE commandes ADD COLUMN moyen_transport_id INT UNSIGNED DEFAULT NULL AFTER type_livraison_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema = DATABASE() AND table_name = 'commandes' AND constraint_name = 'fk_commande_transport');
SET @sql := IF(@fk = 0,
    'ALTER TABLE commandes ADD CONSTRAINT fk_commande_transport FOREIGN KEY (moyen_transport_id) REFERENCES moyens_transport(id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
