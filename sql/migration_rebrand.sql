-- Migration : renommage de l'application en "CityHub 225".
SET NAMES utf8mb4;

UPDATE parametres SET valeur = 'CityHub 225' WHERE cle = 'app_nom';
