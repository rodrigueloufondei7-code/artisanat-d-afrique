-- Schéma SQL nettoyé pour le dépôt GitHub
-- Aucune donnée personnelle, commande, adresse, coordonnée client, empreinte de mot de passe ou jeton API n’est incluse.
-- Créez d’abord une base nommée artisanat_afrique, puis importez ce fichier.
-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 17 nov. 2026 à 03:36
-- Version du serveur : 10.11.16-MariaDB
-- Version de PHP : 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `artisanat_afrique`
--

-- --------------------------------------------------------

--
-- Structure de la table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `date_expiration` datetime NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `approvisionnements`
--

CREATE TABLE `approvisionnements` (
  `id` int(10) UNSIGNED NOT NULL,
  `fournisseur_id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `reference` varchar(50) NOT NULL,
  `montant_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `observation` text DEFAULT NULL,
  `date_approvisionnement` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `articles`
--

CREATE TABLE `articles` (
  `id` int(10) UNSIGNED NOT NULL,
  `categorie_id` int(10) UNSIGNED DEFAULT NULL,
  `nom` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `prix` decimal(12,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `stock` int(10) UNSIGNED DEFAULT 0,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `numero_commande` varchar(50) NOT NULL,
  `nom_livraison` varchar(200) NOT NULL,
  `telephone_livraison` varchar(30) NOT NULL,
  `adresse_livraison` text NOT NULL,
  `ville` varchar(100) NOT NULL,
  `montant_total` decimal(12,2) NOT NULL,
  `mode_paiement` varchar(50) DEFAULT NULL,
  `statut` enum('en_attente','confirmee','en_preparation','expediee','livree','annulee') DEFAULT 'en_attente',
  `date_commande` timestamp NULL DEFAULT current_timestamp()
) ;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

CREATE TABLE `depenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `categorie` enum('transport','electricite','eau','emballage','salaires','loyer','entretien','communication','autre') NOT NULL DEFAULT 'autre',
  `description` varchar(255) NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `date_depense` date NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

CREATE TABLE `favoris` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `article_id` int(10) UNSIGNED NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

CREATE TABLE `fournisseurs` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `lignes_approvisionnement`
--

CREATE TABLE `lignes_approvisionnement` (
  `id` int(10) UNSIGNED NOT NULL,
  `approvisionnement_id` int(10) UNSIGNED NOT NULL,
  `article_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL,
  `prix_unitaire` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sous_total` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `ligne_commandes`
--

CREATE TABLE `ligne_commandes` (
  `id` int(10) UNSIGNED NOT NULL,
  `commande_id` int(10) UNSIGNED NOT NULL,
  `article_id` int(10) UNSIGNED DEFAULT NULL,
  `nom_article` varchar(200) NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL,
  `prix_unitaire` decimal(12,2) NOT NULL
) ;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_stock`
--

CREATE TABLE `mouvements_stock` (
  `id` int(10) UNSIGNED NOT NULL,
  `article_id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `type_mouvement` enum('entree','sortie','correction') NOT NULL,
  `quantite` int(11) NOT NULL,
  `stock_avant` int(10) UNSIGNED NOT NULL,
  `stock_apres` int(10) UNSIGNED NOT NULL,
  `motif` varchar(255) NOT NULL,
  `date_mouvement` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('client','admin') DEFAULT 'client',
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Données volontairement omises : ce dépôt contient uniquement la structure.

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `approvisionnements`
--
ALTER TABLE `approvisionnements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `fk_appro_fournisseur` (`fournisseur_id`),
  ADD KEY `fk_appro_utilisateur` (`utilisateur_id`);

--
-- Index pour la table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categorie_id` (`categorie_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_commande` (`numero_commande`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_depenses_date` (`date_depense`),
  ADD KEY `idx_depenses_categorie` (`categorie`),
  ADD KEY `fk_depenses_utilisateur` (`utilisateur_id`);

--
-- Index pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favori` (`utilisateur_id`,`article_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Index pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `lignes_approvisionnement`
--
ALTER TABLE `lignes_approvisionnement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ligne_appro` (`approvisionnement_id`),
  ADD KEY `fk_ligne_article` (`article_id`);

--
-- Index pour la table `ligne_commandes`
--
ALTER TABLE `ligne_commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `commande_id` (`commande_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Index pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mouvement_article` (`article_id`),
  ADD KEY `fk_mouvement_utilisateur` (`utilisateur_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT pour la table `approvisionnements`
--
ALTER TABLE `approvisionnements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depenses`
--
ALTER TABLE `depenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `favoris`
--
ALTER TABLE `favoris`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `lignes_approvisionnement`
--
ALTER TABLE `lignes_approvisionnement`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `ligne_commandes`
--
ALTER TABLE `ligne_commandes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `approvisionnements`
--
ALTER TABLE `approvisionnements`
  ADD CONSTRAINT `fk_appro_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`),
  ADD CONSTRAINT `fk_appro_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD CONSTRAINT `fk_depenses_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD CONSTRAINT `favoris_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoris_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `lignes_approvisionnement`
--
ALTER TABLE `lignes_approvisionnement`
  ADD CONSTRAINT `fk_ligne_appro` FOREIGN KEY (`approvisionnement_id`) REFERENCES `approvisionnements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ligne_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

--
-- Contraintes pour la table `ligne_commandes`
--
ALTER TABLE `ligne_commandes`
  ADD CONSTRAINT `ligne_commandes_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ligne_commandes_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD CONSTRAINT `fk_mouvement_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `fk_mouvement_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
