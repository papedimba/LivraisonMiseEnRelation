-- Migration : dispatch automatique par proximite (modele d'offre).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dispatch_offres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    livreur_id INT UNSIGNED NOT NULL,
    distance_km DECIMAL(8,2) DEFAULT NULL,
    statut ENUM('en_attente','acceptee','refusee','expiree') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    CONSTRAINT fk_offre_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    CONSTRAINT fk_offre_livreur FOREIGN KEY (livreur_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_offre_livreur (livreur_id, statut),
    KEY idx_offre_commande (commande_id, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO parametres (cle, valeur) VALUES ('dispatch_offre_secondes', '45')
ON DUPLICATE KEY UPDATE valeur = valeur;
