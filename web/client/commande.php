
<?php

session_start();

require_once __DIR__ . "/../../backend/config/db.php";

// =====================================================
// COMMANDE - ARTISANAT D'AFRIQUE
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

// Création du jeton CSRF
if (empty($_SESSION["csrf_commande"])) {
    $_SESSION["csrf_commande"] = bin2hex(random_bytes(32));
}

// Protection HTML
function e($valeur) {
    return htmlspecialchars((string)($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

// Récupération de l'utilisateur connecté
$token = $_SESSION["client_token"];

$requeteUtilisateur = $pdo->prepare("
    SELECT
        u.id,
        u.nom,
        u.prenom,
        u.email,
        u.telephone,
        u.role
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
    session_unset();
    session_destroy();

    header("Location: connexion.php?expire=1");
    exit;
}

// Vérification du panier
if (
    empty($_SESSION["panier"]) ||
    !is_array($_SESSION["panier"])
) {
    header("Location: panier.php");
    exit;
}

// Normalisation des identifiants et quantités
$panier = [];

foreach ($_SESSION["panier"] as $id => $quantite) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    $quantite = filter_var($quantite, FILTER_VALIDATE_INT);

    if ($id !== false && $id > 0 && $quantite !== false && $quantite > 0) {
        $panier[(int)$id] = (int)$quantite;
    }
}

if (empty($panier)) {
    header("Location: panier.php");
    exit;
}

$erreur = "";
$succes = "";
$commandeCreee = null;

// =====================================================
// TRAITEMENT DE LA VALIDATION DE COMMANDE
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        empty($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_commande"], $_POST["csrf_token"])
    ) {
        $erreur = "La vérification de sécurité a échoué. Rechargez la page.";
    } else {

        $nomLivraison = trim($_POST["nom_livraison"] ?? "");
        $telephone = trim($_POST["telephone_livraison"] ?? "");
        $adresse = trim($_POST["adresse_livraison"] ?? "");
        $ville = trim($_POST["ville"] ?? "");

        if (
            $nomLivraison === "" ||
            $telephone === "" ||
            $adresse === "" ||
            $ville === ""
        ) {
            $erreur = "Veuillez remplir tous les champs de livraison.";

        } elseif (
            strlen($nomLivraison) > 200 ||
            strlen($telephone) > 30 ||
            strlen($adresse) > 10000 ||
            strlen($ville) > 100
        ) {
            $erreur = "Un des champs dépasse la longueur autorisée.";

        } else {

            try {

                // Début de la transaction SQL
                $pdo->beginTransaction();

                // Récupération et verrouillage des articles
                $identifiants = array_keys($panier);

                sort($identifiants);

                $placeholders = implode(
                    ",",
                    array_fill(0, count($identifiants), "?")
                );

                $sqlArticles = "
                    SELECT id, nom, prix, stock, statut
                    FROM articles
                    WHERE id IN ($placeholders)
                    ORDER BY id
                    FOR UPDATE
                ";

                $requeteArticles = $pdo->prepare($sqlArticles);
                $requeteArticles->execute($identifiants);

                $articles = $requeteArticles->fetchAll();

                $articlesParId = [];

                foreach ($articles as $article) {
                    $articlesParId[(int)$article["id"]] = $article;
                }

                // Vérification de tous les articles
                foreach ($panier as $id => $quantite) {

                    if (!isset($articlesParId[$id])) {
                        throw new Exception(
                            "Un article de votre panier n'existe plus."
                        );
                    }

                    $article = $articlesParId[$id];

                    if ($article["statut"] !== "actif") {
                        throw new Exception(
                            "L'article " . $article["nom"] . " n'est plus disponible."
                        );
                    }

                    $stock = (int)($article["stock"] ?? 0);

                    if ($stock < $quantite) {
                        throw new Exception(
                            "Stock insuffisant pour : " . $article["nom"] .
                            ". Stock disponible : " . $stock
                        );
                    }
                }

                // Calcul du montant total en centimes
                $totalCentimes = 0;
                $lignesCommande = [];

                foreach ($panier as $id => $quantite) {

                    $article = $articlesParId[$id];

                    $prixCentimes = (int)round(
                        (float)$article["prix"] * 100
                    );

                    $sousTotal = $prixCentimes * $quantite;

                    $totalCentimes += $sousTotal;

                    $lignesCommande[] = [
                        "article_id" => $id,
                        "nom" => $article["nom"],
                        "quantite" => $quantite,
                        "prix" => $article["prix"]
                    ];
                }

                $montantTotal = number_format(
                    $totalCentimes / 100,
                    2,
                    ".",
                    ""
                );

                // Création d'un numéro de commande unique
                $numeroCommande = "AA-" .
                    date("Ymd") . "-" .
                    strtoupper(bin2hex(random_bytes(4)));

                // Mode de paiement retenu
                $modePaiement = "Paiement à la livraison";

                // Création de la commande
                // Le statut et la date utilisent les valeurs par défaut SQL.
                $sqlCommande = "
                    INSERT INTO commandes (
                        utilisateur_id,
                        numero_commande,
                        nom_livraison,
                        telephone_livraison,
                        adresse_livraison,
                        ville,
                        montant_total,
                        mode_paiement
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";

                $requeteCommande = $pdo->prepare($sqlCommande);

                $requeteCommande->execute([
                    $utilisateur["id"],
                    $numeroCommande,
                    $nomLivraison,
                    $telephone,
                    $adresse,
                    $ville,
                    $montantTotal,
                    $modePaiement
                ]);

                $commandeId = (int)$pdo->lastInsertId();

                // Préparation de l'enregistrement des lignes
                $sqlLigne = "
                    INSERT INTO ligne_commandes (
                        commande_id,
                        article_id,
                        nom_article,
                        quantite,
                        prix_unitaire
                    ) VALUES (?, ?, ?, ?, ?)
                ";

                $requeteLigne = $pdo->prepare($sqlLigne);

                // Préparation de la diminution du stock
                $sqlStock = "
                    UPDATE articles
                    SET stock = stock - ?
                    WHERE id = ?
                    AND statut = 'actif'
                    AND stock >= ?
                ";

                $requeteStock = $pdo->prepare($sqlStock);

                foreach ($lignesCommande as $ligne) {

                    // Enregistrement de l'article commandé
                    $requeteLigne->execute([
                        $commandeId,
                        $ligne["article_id"],
                        $ligne["nom"],
                        $ligne["quantite"],
                        $ligne["prix"]
                    ]);

                    // Mise à jour du stock
                    $requeteStock->execute([
                        $ligne["quantite"],
                        $ligne["article_id"],
                        $ligne["quantite"]
                    ]);

                    if ($requeteStock->rowCount() !== 1) {
                        throw new Exception(
                            "Impossible de mettre à jour le stock de " .
                            $ligne["nom"]
                        );
                    }
                }

                // Validation définitive de toutes les opérations
                $pdo->commit();

                // Vider le panier seulement après le succès
                $_SESSION["panier"] = [];

                $commandeCreee = [
                    "numero" => $numeroCommande,
                    "total" => $montantTotal,
                    "id" => $commandeId
                ];

                // Renouvellement du jeton CSRF
                $_SESSION["csrf_commande"] = bin2hex(random_bytes(32));

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    "Erreur commande Artisanat d'Afrique : " .
                    $exception->getMessage()
                );

                $erreur = $exception instanceof PDOException
                    ? "Une erreur technique est survenue. Votre commande n'a pas été enregistrée."
                    : $exception->getMessage();
            }
        }
    }
}

// =====================================================
// RÉCUPÉRATION DU PANIER POUR L'AFFICHAGE
// =====================================================

$articlesPanier = [];
$totalAffichage = 0;

if (!$commandeCreee) {

    if (!empty($panier)) {

        $ids = array_keys($panier);

        $placeholders = implode(
            ",",
            array_fill(0, count($ids), "?")
        );

        $requete = $pdo->prepare("
            SELECT id, nom, prix, image_url, stock, statut
            FROM articles
            WHERE id IN ($placeholders)
        ");

        $requete->execute($ids);

        $resultats = $requete->fetchAll();

        foreach ($resultats as $article) {

            $id = (int)$article["id"];
            $quantite = $panier[$id] ?? 0;

            $article["quantite"] = $quantite;

            $article["sous_total"] =
                (float)$article["prix"] * $quantite;

            $articlesPanier[] = $article;

            $totalAffichage += $article["sous_total"];
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Finaliser ma commande - Artisanat d'Afrique</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #faf8f5;
            font-family: Arial, sans-serif;
            color: #34251e;
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .navbar-brand {
            color: #8b4b27;
            font-weight: 800;
        }

        .page-title {
            color: #34251e;
            font-weight: 800;
        }

        .commande-card {
            background: white;
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
            padding: 25px;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .btn-principal {
            background: #8b4b27;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 700;
        }

        .btn-principal:hover {
            background: #69391e;
            color: white;
        }

        .prix-total {
            color: #8b4b27;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .article-ligne {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }

        .success-card {
            background: white;
            padding: 40px 25px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .success-icon {
            font-size: 4rem;
            color: #198754;
        }

    </style>


<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
</head>

<body>

<nav class="navbar">

    <div class="container">

        <a href="index.php" class="navbar-brand text-decoration-none">
            <i class="bi bi-shop"></i>
            Artisanat d'Afrique
        </a>

        <a href="panier.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
            Retour au panier
        </a>

    </div>

</nav>

<div class="container py-5">

    <?php if ($commandeCreee): ?>

        <!-- CONFIRMATION DE COMMANDE -->

        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="success-card">

                    <i class="bi bi-check-circle-fill success-icon"></i>

                    <h1 class="page-title mt-3">
                        Commande enregistrée !
                    </h1>

                    <p class="text-muted mt-3">
                        Votre commande a été enregistrée avec succès.
                        Vous pourrez régler votre achat à la livraison.
                    </p>

                    <div class="bg-light rounded p-3 my-4">

                        <p class="mb-2">
                            Numéro de commande :
                        </p>

                        <h4 class="fw-bold">
                            <?= e($commandeCreee["numero"]) ?>
                        </h4>

                        <p class="mb-1 mt-3">
                            Montant total :
                        </p>

                        <div class="prix-total">
                            <?= number_format(
                                (float)$commandeCreee["total"],
                                0,
                                ",",
                                " "
                            ) ?> FCFA
                        </div>

                    </div>

                    <a href="index.php" class="btn btn-principal">
                        <i class="bi bi-shop"></i>
                        Continuer mes achats
                    </a>

                    <a href="mon_compte.php" class="btn btn-outline-secondary ms-2">
                        Mon compte
                    </a>

                </div>

            </div>

        </div>

    <?php else: ?>

        <div class="text-center mb-5">

            <h1 class="page-title">
                Finaliser ma commande
            </h1>

            <p class="text-muted">
                Renseignez vos informations pour recevoir votre commande.
            </p>

        </div>

        <?php if ($erreur): ?>

            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <?= e($erreur) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="commande.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_commande"]) ?>"
            >

            <div class="row g-4">

                <!-- INFORMATIONS DE LIVRAISON -->

                <div class="col-lg-7">

                    <div class="commande-card">

                        <h4 class="fw-bold mb-4">
                            <i class="bi bi-geo-alt"></i>
                            Informations de livraison
                        </h4>

                        <div class="mb-3">

                            <label class="form-label">
                                Nom complet
                            </label>

                            <input
                                type="text"
                                name="nom_livraison"
                                class="form-control"
                                maxlength="200"
                                required
                                value="<?= e(
                                    $_POST["nom_livraison"] ??
                                    ($utilisateur["prenom"] . " " . $utilisateur["nom"])
                                ) ?>"
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Numéro de téléphone
                            </label>

                            <input
                                type="tel"
                                name="telephone_livraison"
                                class="form-control"
                                maxlength="30"
                                required
                                value="<?= e(
                                    $_POST["telephone_livraison"] ??
                                    $utilisateur["telephone"]
                                ) ?>"
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Adresse de livraison
                            </label>

                            <textarea
                                name="adresse_livraison"
                                class="form-control"
                                rows="3"
                                maxlength="10000"
                                required
                            ><?= e($_POST["adresse_livraison"] ?? "") ?></textarea>

                        </div>

                        <div class="mb-4">

                            <label class="form-label">
                                Ville
                            </label>

                            <input
                                type="text"
                                name="ville"
                                class="form-control"
                                maxlength="100"
                                required
                                value="<?= e($_POST["ville"] ?? "") ?>"
                            >

                        </div>

                        <hr>

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-credit-card"></i>
                            Mode de paiement
                        </h5>

                        <div class="alert alert-light border">

                            <i class="bi bi-cash-stack text-success"></i>

                            <strong>Paiement à la livraison</strong>

                            <p class="text-muted mb-0 mt-2">
                                Vous paierez votre commande au moment de sa réception.
                            </p>

                        </div>

                    </div>

                </div>

                <!-- RÉCAPITULATIF -->

                <div class="col-lg-5">

                    <div class="commande-card">

                        <h4 class="fw-bold mb-3">
                            Récapitulatif
                        </h4>

                        <?php if (empty($articlesPanier)): ?>

                            <div class="alert alert-warning">
                                Votre panier ne contient aucun article disponible.
                            </div>

                            <a href="panier.php" class="btn btn-principal w-100">
                                Retour au panier
                            </a>

                        <?php else: ?>

                            <?php foreach ($articlesPanier as $article): ?>

                                <div class="article-ligne">

                                    <div class="d-flex justify-content-between gap-2">

                                        <div>

                                            <strong>
                                                <?= e($article["nom"]) ?>
                                            </strong>

                                            <div class="text-muted small">
                                                Quantité :
                                                <?= (int)$article["quantite"] ?>
                                            </div>

                                        </div>

                                        <strong>
                                            <?= number_format(
                                                $article["sous_total"],
                                                0,
                                                ",",
                                                " "
                                            ) ?> FCFA
                                        </strong>

                                    </div>

                                    <?php if (
                                        $article["statut"] !== "actif" ||
                                        (int)($article["stock"] ?? 0) < $article["quantite"]
                                    ): ?>

                                        <div class="text-danger small mt-2">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Disponibilité à vérifier avant validation.
                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                            <div class="d-flex justify-content-between mt-4">

                                <strong>Montant total</strong>

                                <span class="prix-total">
                                    <?= number_format(
                                        $totalAffichage,
                                        0,
                                        ",",
                                        " "
                                    ) ?> FCFA
                                </span>

                            </div>

                            <p class="text-muted small mt-2">
                                Les frais de livraison éventuels ne sont pas inclus.
                            </p>

                            <button
                                type="submit"
                                class="btn btn-principal w-100 mt-3"
                            >
                                <i class="bi bi-check-circle"></i>
                                Confirmer ma commande
                            </button>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </form>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
