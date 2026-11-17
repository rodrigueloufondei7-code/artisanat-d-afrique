
<?php

session_start();

require_once __DIR__ . "/../../backend/config/db.php";

// =====================================================
// HISTORIQUE DES COMMANDES - ARTISANAT D'AFRIQUE
// =====================================================

// Vérification de la connexion
if (
    empty($_SESSION["client_connecte"]) ||
    empty($_SESSION["client_token"]) ||
    ($_SESSION["client"]["role"] ?? "") !== "client"
) {
    header("Location: connexion.php");
    exit;
}

// Protection HTML
function e($valeur) {
    return htmlspecialchars((string)($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

// Formatage du prix
function afficherPrix($prix) {
    return number_format((float)$prix, 0, ",", " ") . " FCFA";
}

// Traduction des statuts
function traduireStatut($statut) {

    $statuts = [
        "en_attente" => "En attente",
        "confirmee" => "Confirmée",
        "en_preparation" => "En préparation",
        "expediee" => "Expédiée",
        "livree" => "Livrée",
        "annulee" => "Annulée"
    ];

    return $statuts[$statut] ?? ucfirst(str_replace("_", " ", (string)$statut));
}

// Couleur du statut
function couleurStatut($statut) {

    $couleurs = [
        "en_attente" => "warning",
        "confirmee" => "primary",
        "en_preparation" => "info",
        "expediee" => "primary",
        "livree" => "success",
        "annulee" => "danger"
    ];

    return $couleurs[$statut] ?? "secondary";
}

// =====================================================
// VÉRIFICATION DU TOKEN CLIENT
// =====================================================

$token = $_SESSION["client_token"];

$requeteUtilisateur = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, u.role
    FROM api_tokens t
    INNER JOIN utilisateurs u ON u.id = t.utilisateur_id
    WHERE t.token_hash = ?
    AND t.date_expiration > NOW()
    LIMIT 1
");

$requeteUtilisateur->execute([
    hash("sha256", $token)
]);

$utilisateur = $requeteUtilisateur->fetch();

if (!$utilisateur || $utilisateur["role"] !== "client") {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: connexion.php?expire=1");
    exit;
}

// =====================================================
// RÉCUPÉRATION DES COMMANDES DU CLIENT
// =====================================================

$requeteCommandes = $pdo->prepare("
    SELECT
        id,
        numero_commande,
        nom_livraison,
        telephone_livraison,
        adresse_livraison,
        ville,
        montant_total,
        mode_paiement,
        statut,
        date_commande
    FROM commandes
    WHERE utilisateur_id = ?
    ORDER BY date_commande DESC, id DESC
");

$requeteCommandes->execute([
    $utilisateur["id"]
]);

$commandes = $requeteCommandes->fetchAll();

// =====================================================
// RÉCUPÉRATION DES ARTICLES DES COMMANDES
// =====================================================

$articlesParCommande = [];

if (!empty($commandes)) {

    $idsCommandes = array_column($commandes, "id");

    $placeholders = implode(
        ",",
        array_fill(0, count($idsCommandes), "?")
    );

    $requeteLignes = $pdo->prepare("
        SELECT
            commande_id,
            article_id,
            nom_article,
            quantite,
            prix_unitaire
        FROM ligne_commandes
        WHERE commande_id IN ($placeholders)
        ORDER BY id ASC
    ");

    $requeteLignes->execute($idsCommandes);

    $lignes = $requeteLignes->fetchAll();

    foreach ($lignes as $ligne) {

        $commandeId = (int)$ligne["commande_id"];

        if (!isset($articlesParCommande[$commandeId])) {
            $articlesParCommande[$commandeId] = [];
        }

        $articlesParCommande[$commandeId][] = $ligne;
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mes commandes - Artisanat d'Afrique</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --couleur-principale: #8b4b27;
            --couleur-foncee: #34251e;
            --couleur-fond: #faf8f5;
        }

        body {
            background: var(--couleur-fond);
            color: var(--couleur-foncee);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .navbar-brand {
            color: var(--couleur-principale);
            font-weight: 800;
        }

        .page-title {
            font-weight: 800;
            color: var(--couleur-foncee);
        }

        .commande-card {
            background: white;
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .commande-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .commande-body {
            padding: 20px;
        }

        .numero-commande {
            color: var(--couleur-principale);
            font-weight: 800;
        }

        .prix-total {
            color: var(--couleur-principale);
            font-size: 1.2rem;
            font-weight: 800;
        }

        .article-ligne {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .article-ligne:last-child {
            border-bottom: none;
        }

        .btn-principal {
            background: var(--couleur-principale);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-weight: 600;
        }

        .btn-principal:hover {
            background: #69391e;
            color: white;
        }

        .empty-card {
            background: white;
            border-radius: 14px;
            padding: 50px 20px;
            text-align: center;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .empty-icon {
            font-size: 4rem;
            color: #b99a83;
        }

    </style>


<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
</head>

<body>

<!-- NAVIGATION -->

<nav class="navbar">

    <div class="container py-2">

        <a href="index.php" class="navbar-brand text-decoration-none">
            <i class="bi bi-shop"></i>
            Artisanat d'Afrique
        </a>

        <div class="d-flex gap-2">

            <a href="mon_compte.php" class="btn btn-outline-secondary">
                <i class="bi bi-person"></i>
                Mon compte
            </a>

            <a href="index.php" class="btn btn-principal">
                <i class="bi bi-shop"></i>
                Boutique
            </a>

        </div>

    </div>

</nav>

<!-- CONTENU PRINCIPAL -->

<div class="container py-5">

    <div class="mb-5">

        <h1 class="page-title">
            <i class="bi bi-receipt"></i>
            Mes commandes
        </h1>

        <p class="text-muted">
            Retrouvez ici toutes vos commandes et leur évolution.
        </p>

        <p>
            Bonjour
            <strong>
                <?= e($utilisateur["prenom"] . " " . $utilisateur["nom"]) ?>
            </strong>
        </p>

    </div>

    <?php if (empty($commandes)): ?>

        <!-- AUCUNE COMMANDE -->

        <div class="empty-card">

            <i class="bi bi-bag-x empty-icon"></i>

            <h3 class="fw-bold mt-3">
                Aucune commande pour le moment
            </h3>

            <p class="text-muted mt-2">
                Vous n'avez pas encore effectué d'achat dans notre boutique.
            </p>

            <a href="index.php" class="btn btn-principal mt-3">
                <i class="bi bi-shop"></i>
                Découvrir nos articles
            </a>

        </div>

    <?php else: ?>

        <!-- LISTE DES COMMANDES -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <h5 class="fw-bold mb-0">
                Historique de vos achats
            </h5>

            <span class="badge text-bg-secondary">
                <?= count($commandes) ?> commande(s)
            </span>

        </div>

        <?php foreach ($commandes as $commande): ?>

            <?php
                $commandeId = (int)$commande["id"];

                $lignesCommande = $articlesParCommande[$commandeId] ?? [];

                $statut = $commande["statut"] ?? "en_attente";

                $date = $commande["date_commande"]
                    ? date("d/m/Y à H:i", strtotime($commande["date_commande"]))
                    : "Date non disponible";
            ?>

            <div class="commande-card">

                <!-- EN-TÊTE -->

                <div class="commande-header">

                    <div class="row align-items-center g-3">

                        <div class="col-md-5">

                            <div class="text-muted small">
                                Numéro de commande
                            </div>

                            <div class="numero-commande">
                                <?= e($commande["numero_commande"]) ?>
                            </div>

                        </div>

                        <div class="col-md-3">

                            <div class="text-muted small">
                                Date
                            </div>

                            <strong>
                                <?= e($date) ?>
                            </strong>

                        </div>

                        <div class="col-md-4 text-md-end">

                            <span class="badge text-bg-<?= e(couleurStatut($statut)) ?>">
                                <?= e(traduireStatut($statut)) ?>
                            </span>

                        </div>

                    </div>

                </div>

                <!-- DÉTAILS -->

                <div class="commande-body">

                    <h6 class="fw-bold mb-3">
                        Articles commandés
                    </h6>

                    <?php if (empty($lignesCommande)): ?>

                        <div class="alert alert-warning">
                            Aucun détail d'article disponible pour cette commande.
                        </div>

                    <?php else: ?>

                        <?php foreach ($lignesCommande as $ligne): ?>

                            <div class="article-ligne">

                                <div class="row align-items-center g-2">

                                    <div class="col-7">

                                        <strong>
                                            <?= e($ligne["nom_article"]) ?>
                                        </strong>

                                        <div class="text-muted small">
                                            Quantité :
                                            <?= (int)$ligne["quantite"] ?>
                                        </div>

                                    </div>

                                    <div class="col-5 text-end">

                                        <strong>
                                            <?= afficherPrix(
                                                (float)$ligne["prix_unitaire"] *
                                                (int)$ligne["quantite"]
                                            ) ?>
                                        </strong>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    <hr>

                    <!-- LIVRAISON ET PAIEMENT -->

                    <div class="row g-3">

                        <div class="col-md-7">

                            <h6 class="fw-bold">
                                <i class="bi bi-geo-alt"></i>
                                Livraison
                            </h6>

                            <p class="mb-1">
                                <?= e($commande["nom_livraison"]) ?>
                            </p>

                            <p class="text-muted small mb-1">
                                <?= nl2br(e($commande["adresse_livraison"])) ?>
                            </p>

                            <p class="text-muted small mb-1">
                                <?= e($commande["ville"]) ?>
                            </p>

                            <p class="text-muted small">
                                Téléphone :
                                <?= e($commande["telephone_livraison"]) ?>
                            </p>

                        </div>

                        <div class="col-md-5">

                            <h6 class="fw-bold">
                                <i class="bi bi-credit-card"></i>
                                Paiement
                            </h6>

                            <p>
                                <?= e($commande["mode_paiement"] ?: "Non renseigné") ?>
                            </p>

                            <div class="text-muted small">
                                Montant total
                            </div>

                            <div class="prix-total">
                                <?= afficherPrix($commande["montant_total"]) ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
