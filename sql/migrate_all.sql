-- ============================================================================
-- Migration consolidee et IDEMPOTENTE : met une base existante a jour avec
-- TOUTES les evolutions recentes (favoris, chat, notation double sens, moyens
-- de transport, tarification surge, cartographie collaborative, routes/heatmap,
-- etat "pause" livreur). Sans danger a relancer plusieurs fois.
--
-- A appliquer une seule fois :
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < sql/migrate_all.sql
-- (ou via phpMyAdmin : onglet Importer -> choisir ce fichier)
-- ============================================================================
SET NAMES utf8mb4;

-- --- Helper : ajoute une colonne seulement si elle n'existe pas encore -------
-- (MySQL/MariaDB n'ont pas ADD COLUMN IF NOT EXISTS partout : on passe par
--  information_schema + requete preparee.)

-- users.note_client
SET @x := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='note_client');
SET @s := IF(@x=0, 'ALTER TABLE users ADD COLUMN note_client DECIMAL(3,2) NOT NULL DEFAULT 5.00 AFTER email_verifie', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users.nombre_evaluations_client
SET @x := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='nombre_evaluations_client');
SET @s := IF(@x=0, 'ALTER TABLE users ADD COLUMN nombre_evaluations_client INT UNSIGNED NOT NULL DEFAULT 0 AFTER note_client', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- commandes.moyen_transport_id
SET @x := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='commandes' AND column_name='moyen_transport_id');
SET @s := IF(@x=0, 'ALTER TABLE commandes ADD COLUMN moyen_transport_id INT UNSIGNED DEFAULT NULL AFTER type_livraison_id', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- livreur_details.disponibilite : ajoute la valeur 'pause' (MODIFY est sur).
ALTER TABLE livreur_details
    MODIFY disponibilite ENUM('en_ligne','hors_ligne','pause') NOT NULL DEFAULT 'hors_ligne';

-- livreur_details.dette_commission (commission due sur les courses payees en
-- especes, encaissees directement par le livreur, a reverser periodiquement).
SET @x := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='livreur_details' AND column_name='dette_commission');
SET @s := IF(@x=0, 'ALTER TABLE livreur_details ADD COLUMN dette_commission DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER solde', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- Tables (creees seulement si absentes) -----------------------------------
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

CREATE TABLE IF NOT EXISTS routes_matchees (
    commande_id INT UNSIGNED PRIMARY KEY,
    geojson MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_routematch_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reglements_dette (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    livreur_id INT UNSIGNED NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    admin_id INT UNSIGNED DEFAULT NULL,
    methode ENUM('agence','orange_money','mtn_money','moov_money','wave') NOT NULL DEFAULT 'agence',
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_regdette_livreur FOREIGN KEY (livreur_id) REFERENCES users(id),
    CONSTRAINT fk_regdette_admin FOREIGN KEY (admin_id) REFERENCES users(id),
    KEY idx_regdette_livreur (livreur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Si la table existait deja (schema anterieur au reglement en libre-service) :
-- rendre admin_id nullable et ajouter la colonne methode.
ALTER TABLE reglements_dette MODIFY admin_id INT UNSIGNED DEFAULT NULL;
SET @x := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reglements_dette' AND column_name='methode');
SET @s := IF(@x=0, "ALTER TABLE reglements_dette ADD COLUMN methode ENUM('agence','orange_money','mtn_money','moov_money','wave') NOT NULL DEFAULT 'agence' AFTER admin_id", 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS paiements_dette (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    livreur_id INT UNSIGNED NOT NULL,
    methode ENUM('orange_money','mtn_money','moov_money','wave') NOT NULL,
    reference VARCHAR(100) NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    statut ENUM('en_attente','reussi','echec') NOT NULL DEFAULT 'en_attente',
    payload_json TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_paiementdette_livreur FOREIGN KEY (livreur_id) REFERENCES users(id),
    UNIQUE KEY uq_paiementdette_reference (reference),
    KEY idx_paiementdette_livreur (livreur_id),
    KEY idx_paiementdette_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Cle etrangere commandes.moyen_transport_id (si absente) -----------------
SET @x := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='commandes' AND constraint_name='fk_commande_transport');
SET @s := IF(@x=0, 'ALTER TABLE commandes ADD CONSTRAINT fk_commande_transport FOREIGN KEY (moyen_transport_id) REFERENCES moyens_transport(id)', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- Donnees de reference et parametres --------------------------------------
INSERT INTO moyens_transport (code, nom, icone, multiplicateur) VALUES
    ('moto', 'Moto', 'moto', 1.00),
    ('velo', 'Velo', 'velo', 0.90),
    ('tricycle', 'Tricycle', 'tricycle', 1.20),
    ('voiture', 'Voiture', 'voiture', 1.50),
    ('camionnette', 'Camionnette', 'camion', 2.00)
ON DUPLICATE KEY UPDATE code = code;

INSERT INTO parametres (cle, valeur) VALUES
    ('surge_actif', '0'), ('surge_max', '2.0'), ('surge_manuel', '1.0'), ('surge_auto', '0'),
    ('surge_heures_pointe', '11-14,18-21'), ('surge_facteur_pointe', '1.2'),
    ('surge_ratio_seuil', '2'), ('surge_facteur_demande', '1.3'),
    ('carto_auto_alimentation', '1'), ('carto_auto_rayon_m', '40'),
    ('route_matching', '1')
ON DUPLICATE KEY UPDATE valeur = valeur;
