-- Migration : notation a double sens (le livreur note aussi le client).
-- A appliquer sur une base existante :
--   mysql -u USER -p BASE < sql/migration_notation_client.sql
SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN note_client DECIMAL(3,2) NOT NULL DEFAULT 5.00 AFTER email_verifie,
    ADD COLUMN nombre_evaluations_client INT UNSIGNED NOT NULL DEFAULT 0 AFTER note_client;

CREATE TABLE IF NOT EXISTS evaluations_client (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    livreur_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    note TINYINT UNSIGNED NOT NULL,
    commentaire VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evalcli_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    UNIQUE KEY uq_evalcli_commande (commande_id),
    KEY idx_evalcli_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
