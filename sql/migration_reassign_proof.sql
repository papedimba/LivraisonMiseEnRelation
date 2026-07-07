-- Migration : reattribution automatique + preuve de livraison.
SET NAMES utf8mb4;

ALTER TABLE commandes
    ADD COLUMN code_livraison VARCHAR(10) DEFAULT NULL AFTER instructions,
    ADD COLUMN preuve_photo VARCHAR(255) DEFAULT NULL AFTER code_livraison,
    ADD COLUMN preuve_type ENUM('code','photo','livreur') DEFAULT NULL AFTER preuve_photo,
    ADD COLUMN relance_at DATETIME DEFAULT NULL AFTER preuve_type,
    ADD COLUMN nombre_relances INT UNSIGNED NOT NULL DEFAULT 0 AFTER relance_at;

INSERT INTO parametres (cle, valeur) VALUES
    ('relance_commande_minutes', '3'),
    ('annulation_auto_minutes', '20'),
    ('reattribution_acceptee_minutes', '10')
ON DUPLICATE KEY UPDATE valeur = valeur;
