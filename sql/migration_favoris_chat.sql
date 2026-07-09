-- Migration : adresses favorites du client + messagerie in-app client<->livreur.
-- A appliquer sur une base existante :
--   mysql -u USER -p BASE < sql/migration_favoris_chat.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS adresses_favorites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    libelle VARCHAR(80) NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_adr_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_adr_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages_course (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    expediteur_id INT UNSIGNED NOT NULL,
    expediteur_role ENUM('client','livreur') NOT NULL,
    message VARCHAR(1000) NOT NULL,
    lu TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msgcourse_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    CONSTRAINT fk_msgcourse_user FOREIGN KEY (expediteur_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_msgcourse_commande (commande_id, created_at),
    KEY idx_msgcourse_lu (commande_id, expediteur_role, lu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
