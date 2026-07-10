-- Migration : suivi de flotte (etat "pause" pour les livreurs).
-- A appliquer sur une base existante :
--   mysql -u USER -p BASE < sql/migration_flotte.sql
SET NAMES utf8mb4;

ALTER TABLE livreur_details
    MODIFY disponibilite ENUM('en_ligne','hors_ligne','pause') NOT NULL DEFAULT 'hors_ligne';
