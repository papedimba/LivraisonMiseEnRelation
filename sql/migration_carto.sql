-- Migration : cartographie collaborative (points de repere contribues).
-- A appliquer sur une base existante :
--   mysql -u USER -p BASE < sql/migration_carto.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS points_carte (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    nom VARCHAR(150) NOT NULL,
    categorie ENUM('repere','commerce','carrefour','quartier','sante','education','service_public','autre') NOT NULL DEFAULT 'repere',
    description VARCHAR(500) DEFAULT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    statut ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
    confirmations INT UNSIGNED NOT NULL DEFAULT 0,
    signalements INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_point_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_point_statut (statut),
    KEY idx_point_latlng (latitude, longitude),
    KEY idx_point_categorie (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS points_carte_votes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    point_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('confirme','signale') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pointvote_point FOREIGN KEY (point_id) REFERENCES points_carte(id) ON DELETE CASCADE,
    CONSTRAINT fk_pointvote_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pointvote (point_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
