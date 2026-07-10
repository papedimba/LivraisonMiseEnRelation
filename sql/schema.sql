-- ============================================================================
-- Schema MySQL - Plateforme de livraison et mise en relation
-- Compatible MySQL 5.7+ / hebergement mutualise (cPanel, o2switch, etc.)
-- Charset: utf8mb4
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Villes et quartiers (couverture geographique)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS villes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quartiers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ville_id INT UNSIGNED NOT NULL,
    nom VARCHAR(150) NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quartier_ville FOREIGN KEY (ville_id) REFERENCES villes(id) ON DELETE CASCADE,
    KEY idx_quartier_ville (ville_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Utilisateurs (table unique multi-role : client, livreur, commercant, admin)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('client','livreur','commercant','admin') NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    telephone VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    quartier_id INT UNSIGNED DEFAULT NULL,
    adresse VARCHAR(255) DEFAULT NULL,
    statut ENUM('actif','suspendu','en_attente') NOT NULL DEFAULT 'actif',
    email_verifie TINYINT(1) NOT NULL DEFAULT 0,
    note_client DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    nombre_evaluations_client INT UNSIGNED NOT NULL DEFAULT 0,
    derniere_connexion DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_telephone (telephone),
    KEY idx_users_role (role),
    KEY idx_users_quartier (quartier_id),
    CONSTRAINT fk_users_quartier FOREIGN KEY (quartier_id) REFERENCES quartiers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Details specifiques livreur
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS livreur_details (
    user_id INT UNSIGNED PRIMARY KEY,
    type_vehicule ENUM('moto','velo','voiture','tricycle','a_pied') NOT NULL DEFAULT 'moto',
    numero_piece VARCHAR(60) DEFAULT NULL,
    piece_identite_path VARCHAR(255) DEFAULT NULL,
    permis_path VARCHAR(255) DEFAULT NULL,
    carte_grise_path VARCHAR(255) DEFAULT NULL,
    statut_validation ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
    motif_rejet VARCHAR(255) DEFAULT NULL,
    disponibilite ENUM('en_ligne','hors_ligne','pause') NOT NULL DEFAULT 'hors_ligne',
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    derniere_position_at DATETIME DEFAULT NULL,
    solde DECIMAL(12,2) NOT NULL DEFAULT 0,
    note_moyenne DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    nombre_courses INT UNSIGNED NOT NULL DEFAULT 0,
    valide_par INT UNSIGNED DEFAULT NULL,
    valide_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_livreur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_livreur_dispo (disponibilite),
    KEY idx_livreur_statut (statut_validation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Details specifiques commercant + boutique
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commercant_details (
    user_id INT UNSIGNED PRIMARY KEY,
    nom_boutique VARCHAR(150) NOT NULL,
    categorie ENUM('restaurant','maquis','supermarche','pharmacie','boutique','fleuriste','autre') NOT NULL DEFAULT 'boutique',
    description TEXT,
    logo_path VARCHAR(255) DEFAULT NULL,
    adresse VARCHAR(255) DEFAULT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    statut_validation ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
    abonnement_premium TINYINT(1) NOT NULL DEFAULT 0,
    abonnement_expire_at DATETIME DEFAULT NULL,
    note_moyenne DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    CONSTRAINT fk_commercant_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_commercant_categorie (categorie),
    KEY idx_commercant_statut (statut_validation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Types de livraison (parametrable par l'admin)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS types_livraison (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    icone VARCHAR(50) DEFAULT NULL,
    tarif_base DECIMAL(10,2) NOT NULL DEFAULT 500,
    tarif_km DECIMAL(10,2) NOT NULL DEFAULT 150,
    supplement_express DECIMAL(10,2) NOT NULL DEFAULT 1000,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_type_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Produits des commercants (avec recherche FULLTEXT)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commercant_id INT UNSIGNED NOT NULL,
    nom VARCHAR(150) NOT NULL,
    description TEXT,
    prix DECIMAL(10,2) NOT NULL,
    categorie VARCHAR(80) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    stock INT DEFAULT NULL,
    disponible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_produit_commercant FOREIGN KEY (commercant_id) REFERENCES commercant_details(user_id) ON DELETE CASCADE,
    KEY idx_produit_commercant (commercant_id),
    KEY idx_produit_disponible (disponible),
    FULLTEXT KEY ft_produit_recherche (nom, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Commandes (livraisons)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commandes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    livreur_id INT UNSIGNED DEFAULT NULL,
    commercant_id INT UNSIGNED DEFAULT NULL,
    type_livraison_id INT UNSIGNED NOT NULL,
    statut ENUM('en_attente','acceptee','recuperee','en_cours','livree','annulee') NOT NULL DEFAULT 'en_attente',
    adresse_depart VARCHAR(255) NOT NULL,
    lat_depart DECIMAL(10,7) NOT NULL,
    lng_depart DECIMAL(10,7) NOT NULL,
    adresse_arrivee VARCHAR(255) NOT NULL,
    lat_arrivee DECIMAL(10,7) NOT NULL,
    lng_arrivee DECIMAL(10,7) NOT NULL,
    distance_km DECIMAL(8,2) NOT NULL DEFAULT 0,
    est_express TINYINT(1) NOT NULL DEFAULT 0,
    instructions TEXT,
    code_livraison VARCHAR(10) DEFAULT NULL,
    preuve_photo VARCHAR(255) DEFAULT NULL,
    preuve_type ENUM('code','photo','livreur') DEFAULT NULL,
    relance_at DATETIME DEFAULT NULL,
    nombre_relances INT UNSIGNED NOT NULL DEFAULT 0,
    code_promo VARCHAR(40) DEFAULT NULL,
    reduction DECIMAL(10,2) NOT NULL DEFAULT 0,
    montant_estime DECIMAL(10,2) NOT NULL DEFAULT 0,
    montant_final DECIMAL(10,2) DEFAULT NULL,
    commission_taux DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    commission_montant DECIMAL(10,2) DEFAULT NULL,
    mode_paiement ENUM('especes','orange_money','mtn_money','moov_money','wave') NOT NULL DEFAULT 'especes',
    statut_paiement ENUM('en_attente','paye','echec','rembourse') NOT NULL DEFAULT 'en_attente',
    motif_annulation VARCHAR(255) DEFAULT NULL,
    accepted_at DATETIME DEFAULT NULL,
    recovered_at DATETIME DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_commande_reference (reference),
    CONSTRAINT fk_commande_client FOREIGN KEY (client_id) REFERENCES users(id),
    CONSTRAINT fk_commande_livreur FOREIGN KEY (livreur_id) REFERENCES users(id),
    CONSTRAINT fk_commande_commercant FOREIGN KEY (commercant_id) REFERENCES commercant_details(user_id),
    CONSTRAINT fk_commande_type FOREIGN KEY (type_livraison_id) REFERENCES types_livraison(id),
    KEY idx_commande_statut (statut),
    KEY idx_commande_client (client_id),
    KEY idx_commande_livreur (livreur_id),
    KEY idx_commande_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Lignes de produits par commande (cas boutique / repas / courses)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commande_produits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    produit_id INT UNSIGNED NOT NULL,
    quantite INT UNSIGNED NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_cp_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_produit FOREIGN KEY (produit_id) REFERENCES produits(id),
    KEY idx_cp_commande (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Suivi de position du livreur pendant une course (historique trajectoire)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suivi_positions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    livreur_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_suivi_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    KEY idx_suivi_commande (commande_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Paiements (especes + mobile money) - interface prete pour vrais webhooks
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS paiements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    methode ENUM('especes','orange_money','mtn_money','moov_money','wave') NOT NULL,
    reference_transaction VARCHAR(100) DEFAULT NULL,
    montant DECIMAL(10,2) NOT NULL,
    statut ENUM('en_attente','reussi','echec','rembourse') NOT NULL DEFAULT 'en_attente',
    payload_json TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_paiement_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    KEY idx_paiement_commande (commande_id),
    KEY idx_paiement_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Retraits des gains livreur
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS retraits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    livreur_id INT UNSIGNED NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    methode ENUM('orange_money','mtn_money','moov_money','wave','especes') NOT NULL,
    numero_reception VARCHAR(30) NOT NULL,
    statut ENUM('en_attente','traite','rejete') NOT NULL DEFAULT 'en_attente',
    reference VARCHAR(100) DEFAULT NULL,
    traite_par INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_retrait_livreur FOREIGN KEY (livreur_id) REFERENCES users(id),
    KEY idx_retrait_livreur (livreur_id),
    KEY idx_retrait_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Evaluations (client -> livreur, client -> commercant)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evaluations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    livreur_id INT UNSIGNED DEFAULT NULL,
    commercant_id INT UNSIGNED DEFAULT NULL,
    note TINYINT UNSIGNED NOT NULL,
    commentaire VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eval_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    UNIQUE KEY uq_eval_commande (commande_id),
    KEY idx_eval_livreur (livreur_id),
    KEY idx_eval_commercant (commercant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Evaluations du client par le livreur (notation a double sens)
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Reclamations / service client
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reclamations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    commande_id INT UNSIGNED DEFAULT NULL,
    sujet VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    statut ENUM('ouverte','en_cours','resolue','fermee') NOT NULL DEFAULT 'ouverte',
    reponse TEXT DEFAULT NULL,
    traite_par INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reclamation_user FOREIGN KEY (user_id) REFERENCES users(id),
    KEY idx_reclamation_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Messages support / assistant IA (Claude)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages_support (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    conversation_id VARCHAR(64) NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msgsupport_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_msgsupport_conv (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Notifications
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    titre VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'info',
    lien VARCHAR(255) DEFAULT NULL,
    lu TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_notif_user (user_id, lu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Abonnements Web Push (notifications navigateur)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh VARCHAR(255) DEFAULT NULL,
    auth VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_endpoint (endpoint(191)),
    KEY idx_push_user (user_id),
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Offres de dispatch (attribution automatique par proximite)
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Codes promo
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Parametres globaux (commission par defaut, cles API, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres (
    cle VARCHAR(100) PRIMARY KEY,
    valeur TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Journal des connexions / securite (tentatives, verification identite)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_connexions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    email_tente VARCHAR(190) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    succes TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_journal_user (user_id),
    KEY idx_journal_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Adresses favorites du client ("Maison", "Bureau"...) pour commander vite
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Messagerie in-app client <-> livreur, rattachee a une commande
-- ----------------------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Donnees initiales
-- ============================================================================

INSERT INTO villes (nom) VALUES ('Bouake'), ('Abidjan'), ('Yamoussoukro');

INSERT INTO quartiers (ville_id, nom) VALUES
(1, 'Air France 1'), (1, 'Air France 2'), (1, 'Belleville'), (1, 'Commerce'),
(1, 'Dar Es Salam'), (1, 'Gonfreville'), (1, 'Kennedy'), (1, 'Koko'),
(1, 'Liberte'), (1, 'N Gattakro'), (1, 'Nimbo'), (1, 'Sokoura'),
(1, 'Sokoura Extension'), (1, 'Tolakouadiokro'), (1, 'Zone Industrielle');

INSERT INTO types_livraison (code, nom, icone, tarif_base, tarif_km, supplement_express) VALUES
('repas', 'Repas', 'utensils', 500, 150, 1000),
('courses', 'Courses', 'shopping-cart', 500, 150, 1000),
('medicaments', 'Medicaments', 'pill', 500, 150, 500),
('colis', 'Colis', 'package', 700, 150, 1000),
('documents', 'Documents administratifs', 'file-text', 500, 150, 1000),
('cadeaux', 'Cadeaux', 'gift', 600, 150, 1000),
('fleurs', 'Fleurs', 'flower', 600, 150, 1000),
('boissons', 'Boissons', 'wine', 500, 150, 1000),
('materiaux', 'Materiaux legers', 'box', 1000, 200, 1500),
('express', 'Livraison express', 'zap', 1000, 200, 0);

INSERT INTO parametres (cle, valeur) VALUES
('commission_taux_defaut', '15'),
('devise', 'FCFA'),
('anthropic_model', 'claude-sonnet-4-20250514'),
('app_nom', 'CityHub 225'),
('ville_defaut', 'Bouake'),
('relance_commande_minutes', '3'),
('annulation_auto_minutes', '20'),
('reattribution_acceptee_minutes', '10'),
('dispatch_offre_secondes', '45'),
('dispatch_rayon_max_km', '10'),
('dispatch_poids_note', '0.5'),
('surge_actif', '0'),
('surge_max', '2.0'),
('surge_manuel', '1.0'),
('surge_auto', '0'),
('surge_heures_pointe', '11-14,18-21'),
('surge_facteur_pointe', '1.2'),
('surge_ratio_seuil', '2'),
('surge_facteur_demande', '1.3');

-- Compte admin par defaut (mot de passe: ChangeMoi123! - a changer immediatement)
-- Hash genere avec password_hash('ChangeMoi123!', PASSWORD_BCRYPT)
INSERT INTO users (role, nom, prenom, email, telephone, password_hash, statut, email_verifie) VALUES
('admin', 'Admin', 'Principal', 'admin@livraisonci.local', '0700000000',
'$2y$12$3BBOoZ7.nu5ZHKqaw/U30uKxP3hPNX/BhTMqkYRFnMJlggZPZNGfe', 'actif', 1);
