# LivraisonCI - Plateforme de livraison et de mise en relation (Bouake, Cote d'Ivoire)

Application web complete (client, livreur, commercant, administrateur) pour la mise en relation
et la livraison de repas, courses, medicaments, colis, documents, fleurs, cadeaux et plus encore.

## Stack technique

- **Backend** : PHP 8.x vanilla (sans framework, sans Composer)
- **Base de donnees** : MySQL 5.7+ (recherche FULLTEXT sur les produits)
- **Frontend** : HTML / CSS / JavaScript vanilla (aucun framework frontend)
- **IA** : API Anthropic Claude (`claude-sonnet-4-20250514`) via curl PHP, pour l'assistant de support client
- **Cartographie** : Leaflet + OpenStreetMap (CDN, aucune cle API requise)
- **Suivi temps reel** : Server-Sent Events (SSE) pour pousser la position du livreur
  et le statut de la commande au client (avec repli automatique sur du polling)
- **Compatible** hebergement mutualise (cPanel, o2switch, etc.)

### Note sur le suivi temps reel (SSE)

La page de suivi client (`client/track.php`) ouvre une connexion SSE vers
`api/client/track_stream.php`, qui pousse une mise a jour uniquement quand le
statut ou la position du livreur change (rafraichissement serveur toutes les 3 s).
Le flux libere le verrou de session (`session_write_close`) pour ne pas bloquer les
autres requetes de l'utilisateur, se ferme automatiquement quand la commande est
livree/annulee, et vit 5 minutes maximum avant que le navigateur ne se reconnecte
tout seul. Chaque connexion SSE mobilise un worker PHP tant qu'elle est ouverte :
sur un hebergement mutualise a faible nombre de workers, surveillez la charge si
beaucoup de clients suivent une commande simultanement.

## Structure du projet

```
config/         Configuration (.env, connexion PDO, constantes)
includes/       Classes partagees (Auth, Response, PaymentGateway, ClaudeClient, fonctions utilitaires)
api/            Endpoints JSON, organises par espace (auth, client, livreur, commercant, admin, public, support)
public/         Racine web (document root a pointer ici) : pages HTML/PHP + assets CSS/JS
sql/schema.sql  Schema complet de la base de donnees + donnees initiales
storage/        Fichiers uploades (pieces d'identite livreurs, logos boutiques, images produits)
```

## Installation (hebergement mutualise type cPanel / o2switch)

1. **Base de donnees**
   - Creer une base MySQL et un utilisateur associe depuis cPanel.
   - Importer `sql/schema.sql` via phpMyAdmin (ou `mysql -u user -p nom_bdd < sql/schema.sql`).

2. **Fichiers**
   - Deposer tout le contenu du depot dans un dossier hors de la racine web publique
     (ex: `~/livraison_app/`), **sauf** le contenu de `public/` qui doit etre la racine web
     (`public_html/` ou un sous-domaine dedie).
   - Si votre hebergeur impose que tout soit dans `public_html/`, deposez l'ensemble du projet
     tel quel : le fichier `.htaccess` a la racine bloque l'acces direct aux dossiers sensibles
     (`config/`, `includes/`, `sql/`, `storage/`), et seul `public/` reste accessible en pointant
     le document root vers `public_html/public`.

3. **Configuration**
   - Copier `.env.example` en `.env` a la racine du projet et renseigner :
     - Identifiants MySQL (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)
     - Cle API Anthropic (`ANTHROPIC_API_KEY`) pour activer l'assistant IA
     - Cles Mobile Money (Orange/MTN/Moov/Wave) une fois vos comptes marchands obtenus
       (voir la section Paiements ci-dessous)

4. **Permissions**
   - Le dossier `storage/uploads/` doit etre accessible en ecriture par PHP (`chmod 755` ou `775`
     selon la configuration de l'hebergeur).

5. **Compte administrateur initial**
   - Un compte admin est cree par le script SQL :
     - email : `admin@livraisonci.local`
     - mot de passe : `ChangeMoi123!`
   - **Changez immediatement ce mot de passe** apres la premiere connexion via la page
     « Mon compte » (clic sur votre nom dans l'en-tete).

## Notifications push (Web Push)

Les notifications navigateur reposent sur le Web Push avec authentification VAPID, selon le
modele « reveil sans payload » : le serveur envoie un push vide qui reveille le service worker
(`public/sw.js`), lequel va chercher les notifications non lues aupres du serveur avant de les
afficher. Cela evite tout chiffrement de payload cote PHP.

Mise en place :

1. Generer une paire de cles VAPID : `php scripts/generate_vapid.php`
   (ecrit `storage/vapid_private.pem` et affiche la cle publique).
2. Renseigner dans `.env` : `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY_PATH`, `VAPID_SUBJECT`.
3. Executer la migration `sql/push_subscriptions.sql` si la base existe deja
   (la table est incluse dans `schema.sql` pour les nouvelles installations).

**Important** : le Web Push exige un contexte securise (**HTTPS**). Il ne fonctionne donc pas en
`http://` local (sauf `localhost`) mais fonctionnera en production une fois le certificat SSL actif.
Sans cles VAPID configurees, l'application fonctionne normalement : seules les notifications push
sont desactivees (les notifications restent visibles dans l'application via la cloche 🔔). Quand une
page est ouverte, une notification navigateur in-app est aussi affichee si l'utilisateur a accorde
la permission.

## Paiements Mobile Money

Le code est structure autour d'une interface `PaymentDriver` (`includes/PaymentGateway.php`) avec
un driver par operateur (Orange Money, MTN Mobile Money, Moov Money, Wave) et un driver especes.

**Tant qu'aucune cle API n'est renseignee dans `.env`, chaque driver fonctionne en mode simulation** :
il genere une reference et confirme le paiement instantanement, ce qui permet de tester tout le
parcours (commande -> paiement -> livraison -> encaissement du livreur) avant la souscription aux
vraies API des operateurs. Une fois les identifiants marchands obtenus, completez les methodes
`initierPaiement()` de chaque driver avec les vrais appels curl vers les API des operateurs.

## Assistant IA (Claude)

L'assistant de support (`api/support/chat.php`) utilise l'API Anthropic Claude pour repondre aux
questions courantes des utilisateurs (suivi de commande, paiement, reclamation). Sans cle API
configuree, l'assistant repond avec un message invitant a utiliser le formulaire de reclamation.

## Comptes de test

Apres inscription, les comptes livreur et commercant restent au statut `en_attente` jusqu'a
validation par un administrateur (menu **Validations** dans l'espace admin). Les comptes client
sont actifs immediatement.

## Zones couvertes

La table `quartiers` est pre-remplie avec les quartiers de Bouake. Ajoutez d'autres villes et
quartiers via la table `villes` / `quartiers` (ou une future interface admin dediee) pour etendre
la couverture a d'autres villes de Cote d'Ivoire.

## Feuille de route (non couvert par cette premiere version)

- Applications mobiles natives Android (Kotlin) et iOS (Swift). Piste recommandee pour demarrer
  rapidement : encapsuler cette plateforme web responsive dans une WebView native avec notifications
  push et acces GPS natif, en attendant le developpement d'apps 100% natives.
- Interface de navigation par recherche de boutiques/produits cote client (les endpoints API
  `api/public/search.php` et `api/public/stores.php` existent deja et sont prets a etre branches
  a une page de catalogue).
- Vraie integration des API Orange Money / MTN MoMo / Moov Money / Wave (structure prete, cles a
  renseigner).
- Changement de mot de passe en libre-service, verification d'email/SMS.
- Notifications push natives (actuellement : notifications in-app en base + polling).
