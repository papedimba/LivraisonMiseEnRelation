-- Migration : marchandage du prix entre client et livreur (courses especes).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS negociations_prix (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    livreur_id INT UNSIGNED NOT NULL,
    montant_propose DECIMAL(10,2) NOT NULL,
    propose_par ENUM('client','livreur') NOT NULL,
    statut ENUM('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_negociation_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    CONSTRAINT fk_negociation_livreur FOREIGN KEY (livreur_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_negociation_commande_livreur (commande_id, livreur_id),
    KEY idx_negociation_commande (commande_id, statut),
    KEY idx_negociation_livreur (livreur_id, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
