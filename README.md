# Artisanat d’Afrique

Application web de boutique et de gestion d’articles artisanaux. Ce dépôt est présenté comme **projet de portfolio** : il permet de consulter le code et d’installer une version locale pour découvrir les fonctionnalités développées.

## Présentation

Artisanat d’Afrique regroupe une interface destinée aux clients et un espace d’administration pour gérer une boutique d’articles artisanaux.

### Fonctionnalités présentes dans le code

- Espace client : inscription, connexion, catalogue, panier, commande, historique des commandes et compte client.
- Espace administrateur : authentification, tableau de bord, gestion des articles et des commandes.
- Gestion des stocks : mouvements, historique, alertes et approvisionnements.
- Gestion des fournisseurs et des dépenses.
- Consultation des informations financières.
- Génération de factures PDF avec Dompdf.
- API PHP pour l’authentification, les catégories, les articles, les commandes et les statistiques.

> Les fonctionnalités listées correspondent aux modules présents dans le code source. Elles ne constituent pas une certification de sécurité ni une garantie de fonctionnement sur tous les environnements.

## Technologies

- PHP et PDO
- MySQL / MariaDB
- HTML, CSS et JavaScript
- Bootstrap 5 et Bootstrap Icons
- Composer
- Dompdf pour les factures PDF

## Arborescence principale

```text
artisanat_afrique/
├── backend/
│   ├── api/                 # API et routes
│   ├── config/db.php        # Connexion PDO
│   └── uploads/             # Répertoire des fichiers téléversés
├── database/schema.sql     # Structure SQL sans données personnelles
├── web/
│   ├── admin/               # Espace administrateur
│   ├── client/              # Espace client
│   ├── assets/              # CSS et ressources
│   └── index.php            # Page d’accueil
├── composer.json
├── composer.lock
└── README.md
```

## Installation locale avec XAMPP

1. Installer XAMPP avec Apache, MySQL et PHP. Activer les extensions PHP nécessaires, notamment `pdo_mysql`, `curl`, `mbstring` et `dom`.
2. Copier le dossier `artisanat_afrique` dans `C:\xampp\htdocs\`.
3. Démarrer Apache et MySQL.
4. Dans phpMyAdmin, créer une base nommée `artisanat_afrique` avec le jeu de caractères `utf8mb4`.
5. Importer `database/schema.sql` dans cette base. Ce fichier crée les tables mais **ne contient aucun compte utilisateur, aucune commande et aucun catalogue de démonstration**.
6. Vérifier la connexion à la base dans `backend/config/db.php`. Par défaut, le fichier utilise les paramètres habituels de XAMPP (`localhost`, base `artisanat_afrique`, utilisateur `root`, mot de passe vide). Pour un autre environnement, définir les variables `DB_HOST`, `DB_NAME`, `DB_USER` et `DB_PASS` dans l’environnement PHP.
7. Ouvrir un terminal dans le dossier du projet et exécuter :

   ```bash
   composer install
   ```

8. Ouvrir `http://localhost/artisanat_afrique/web/` dans le navigateur.

## Données de démonstration et accès administrateur

Le schéma public est volontairement vierge : les données du dump fourni ont été exclues afin de ne pas publier de renseignements clients, de mots de passe hachés ou de jetons d’API. Le script `ajouter_15_articles.sql` est un script complémentaire d’insertion d’articles et de catégories ; il ne remplace pas le schéma complet.

L’inscription publique crée un compte client. Aucun identifiant administrateur de démonstration n’est fourni dans ce dépôt. Pour présenter l’espace d’administration, configurez un compte administrateur de test dans une base locale dédiée, sans publier ses identifiants ni son empreinte de mot de passe.

## Points à connaître avant un déploiement en ligne

- Plusieurs pages PHP utilisent encore des chemins d’API contenant `http://localhost/artisanat_afrique/` et certains chemins de ressources commencent par `/artisanat_afrique/`. L’application est donc préparée pour une installation locale XAMPP sous ce chemin ; une adaptation des URL est nécessaire pour un autre domaine ou un sous-dossier différent.
- Le paramètre de cookie de session `secure` et la configuration des sessions doivent être adaptés à HTTPS en production.
- Le dépôt ne fournit pas d’hébergement PHP/MySQL. GitHub sert à héberger le code source, pas à exécuter directement cette application PHP.
- Avant tout usage réel, effectuer une revue de sécurité complète (authentification, autorisations, validation des entrées, téléversements, sessions, CSRF, configuration serveur et sauvegardes).

## Confidentialité et sécurité du dépôt

- Ne jamais ajouter de mots de passe, jetons, clés, exports de base réels ou fichiers `.env` personnels.
- Le fichier `database/schema.sql` ne contient que la structure de la base, sans lignes `INSERT`.
- Les fichiers présents dans `backend/uploads/` sont ignorés par Git, sauf le fichier `.gitkeep`.
- Le dossier `vendor/` est ignoré ; les dépendances sont restaurées avec `composer install` à partir de `composer.json` et `composer.lock`.

## Captures d’écran

Pour enrichir le portfolio, ajoutez des captures anonymisées dans `docs/screenshots/` : accueil, catalogue, panier, tableau de bord administrateur, gestion des articles et facture PDF. Vérifiez qu’aucune adresse e-mail, donnée client ou information de connexion n’est visible.

## Statut du projet

Projet PHP/MySQL de portfolio, préparé pour consultation publique du code et installation locale. Il n’est pas présenté comme une version prête à être déployée en production.
