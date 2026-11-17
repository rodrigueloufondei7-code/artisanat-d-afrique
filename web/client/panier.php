
<?php
// =====================================================
// PANIER CLIENT - ARTISANAT D'AFRIQUE
// =====================================================

session_start();

// Vérifier la connexion du client
if (
    empty($_SESSION["client_connecte"]) ||
    empty($_SESSION["client_token"]) ||
    ($_SESSION["client"]["role"] ?? "") !== "client"
) {
    header("Location: connexion.php");
    exit;
}

// Initialiser le panier
if (!isset($_SESSION["panier"]) || !is_array($_SESSION["panier"])) {
    $_SESSION["panier"] = [];
}

// Jeton CSRF
if (empty($_SESSION["csrf_panier"])) {
    $_SESSION["csrf_panier"] = bin2hex(random_bytes(32));
}

$message = "";
$typeMessage = "info";

// Récupération des articles depuis l'API
function recupererArticlesAPI() {
    $url = "http://localhost/artisanat_afrique/backend/api/index.php?route=products";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ["Accept: application/json"]
    ]);

    $reponse = curl_exec($ch);
    $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($reponse === false || $codeHttp >= 400) {
        return null;
    }

    $donnees = json_decode($reponse, true);

    if (!is_array($donnees)) {
        return null;
    }

    if (isset($donnees["articles"]) && is_array($donnees["articles"])) {
        return $donnees["articles"];
    }

    if (isset($donnees["data"]) && is_array($donnees["data"])) {
        return $donnees["data"];
    }

    if (isset($donnees["produits"]) && is_array($donnees["produits"])) {
        return $donnees["produits"];
    }

    if (array_is_list($donnees)) {
        return $donnees;
    }

    return null;
}

// Charger les articles
$articlesAPI = recupererArticlesAPI();

if ($articlesAPI === null) {
    $message = "Impossible de récupérer les articles. Vérifiez que le serveur fonctionne.";
    $typeMessage = "danger";
}

// Indexer les articles par identifiant
$articlesIndex = [];

if (is_array($articlesAPI)) {
    foreach ($articlesAPI as $article) {
        $id = (int)($article["id"] ?? 0);

        if ($id > 0) {
            $articlesIndex[$id] = $article;
        }
    }
}

// Traiter les actions du panier
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        empty($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_panier"], $_POST["csrf_token"])
    ) {
        $message = "Votre session a expiré. Rechargez la page.";
        $typeMessage = "danger";

    } elseif ($articlesAPI === null) {
        $message = "Impossible de modifier le panier pour le moment.";
        $typeMessage = "danger";

    } else {

        $action = $_POST["action"] ?? "";
        $articleId = (int)($_POST["article_id"] ?? 0);

        if ($action === "ajouter") {

            if (!isset($articlesIndex[$articleId])) {
                $message = "Cet article n'existe pas ou n'est plus disponible.";
                $typeMessage = "danger";

            } else {
                $article = $articlesIndex[$articleId];
                $stock = (int)($article["stock"] ?? 0);

                $quantiteActuelle = (int)($_SESSION["panier"][$articleId] ?? 0);

                if ($stock <= 0) {
                    $message = "Cet article est en rupture de stock.";
                    $typeMessage = "warning";

                } elseif ($quantiteActuelle >= $stock) {
                    $message = "La quantité maximale disponible est déjà dans votre panier.";
                    $typeMessage = "warning";

                } else {
                    $_SESSION["panier"][$articleId] = $quantiteActuelle + 1;

                    $message = "Article ajouté au panier avec succès.";
                    $typeMessage = "success";
                }
            }

        } elseif ($action === "modifier") {

            $quantite = filter_var(
                $_POST["quantite"] ?? null,
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );

            if (
                $quantite === false ||
                $quantite === null ||
                !isset($articlesIndex[$articleId])
            ) {
                $message = "Quantité ou article invalide.";
                $typeMessage = "danger";

            } else {
                $stock = (int)($articlesIndex[$articleId]["stock"] ?? 0);

                if ($stock <= 0) {
                    unset($_SESSION["panier"][$articleId]);
                    $message = "Cet article n'est plus disponible. Il a été retiré du panier.";
                    $typeMessage = "warning";

                } elseif ($quantite > $stock) {
                    $_SESSION["panier"][$articleId] = $stock;
                    $message = "La quantité a été limitée au stock disponible : " . $stock . ".";
                    $typeMessage = "warning";

                } else {
                    $_SESSION["panier"][$articleId] = $quantite;
                    $message = "Quantité mise à jour.";
                    $typeMessage = "success";
                }
            }

        } elseif ($action === "supprimer") {

            if (isset($_SESSION["panier"][$articleId])) {
                unset($_SESSION["panier"][$articleId]);

                $message = "Article retiré du panier.";
                $typeMessage = "success";
            }

        } elseif ($action === "vider") {

            $_SESSION["panier"] = [];

            $message = "Votre panier a été vidé.";
            $typeMessage = "success";

        } else {
            $message = "Action inconnue.";
            $typeMessage = "danger";
        }
    }
}

// Préparer les lignes du panier
$lignesPanier = [];
$totalGeneral = 0;
$totalArticles = 0;

foreach ($_SESSION["panier"] as $id => $quantite) {

    $id = (int)$id;
    $quantite = (int)$quantite;

    if (!isset($articlesIndex[$id])) {
        // Article supprimé ou désactivé
        unset($_SESSION["panier"][$id]);
        continue;
    }

    $article = $articlesIndex[$id];

    $nom = $article["nom"] ?? $article["name"] ?? "Article sans nom";
    $prix = (float)($article["prix"] ?? $article["price"] ?? 0);
    $stock = (int)($article["stock"] ?? 0);
    $image = $article["image_url"] ?? $article["image"] ?? "";

    if ($quantite > $stock) {
        $quantite = $stock;
        $_SESSION["panier"][$id] = $quantite;
    }

    if ($quantite <= 0 || $stock <= 0) {
        unset($_SESSION["panier"][$id]);
        continue;
    }

    $sousTotal = $prix * $quantite;

    $totalGeneral += $sousTotal;
    $totalArticles += $quantite;

    $lignesPanier[] = [
        "id" => $id,
        "nom" => $nom,
        "prix" => $prix,
        "stock" => $stock,
        "quantite" => $quantite,
        "image" => $image,
        "sous_total" => $sousTotal
    ];
}

// Fonctions d'affichage
function e($valeur) {
    return htmlspecialchars((string)($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

function prixFCFA($prix) {
    return number_format((float)$prix, 0, ",", " ") . " FCFA";
}

function urlImagePanier($chemin) {
    if (empty($chemin)) {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $chemin)) {
        return $chemin;
    }

    if (str_starts_with($chemin, "/")) {
        return $chemin;
    }

    return "/artisanat_afrique/backend/" . ltrim($chemin, "/");
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mon panier - Artisanat d'Afrique</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background: #faf8f5;
            color: #34251e;
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .navbar-brand {
            color: #8b4b27;
            font-weight: 800;
        }

        .page-title {
            font-weight: 800;
            color: #34251e;
        }

        .cart-card, .summary-card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .cart-image {
            width: 95px;
            height: 95px;
            object-fit: cover;
            border-radius: 9px;
            background: #eee8e2;
        }

        .image-empty {
            width: 95px;
            height: 95px;
            border-radius: 9px;
            background: #eee8e2;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #967d6b;
            font-size: 30px;
        }

        .product-name {
            font-weight: 700;
            color: #34251e;
        }

        .price {
            color: #8b4b27;
            font-weight: 800;
        }

        .btn-principal {
            background: #8b4b27;
            color: white;
            border: none;
        }

        .btn-principal:hover {
            background: #69391e;
            color: white;
        }

        .quantity-input {
            width: 85px;
        }

        footer {
            background: #34251e;
            color: white;
            padding: 20px;
            margin-top: 60px;
        }
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
</head>

<body>

<nav class="navbar">
    <div class="container py-2">

        <a class="navbar-brand text-decoration-none" href="index.php">
            <i class="bi bi-shop"></i> Artisanat d'Afrique
        </a>

        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Boutique
            </a>

            <a href="mon_compte.php" class="btn btn-principal btn-sm">
                <i class="bi bi-person"></i> Mon compte
            </a>
        </div>

    </div>
</nav>

<main class="container py-5">

    <div class="mb-4">
        <h1 class="page-title">
            <i class="bi bi-cart3"></i> Mon panier
        </h1>

        <p class="text-muted">
            Vérifiez vos articles avant de passer commande.
        </p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= e($typeMessage) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($lignesPanier)): ?>

        <div class="cart-card text-center py-5">

            <i class="bi bi-cart-x" style="font-size: 65px; color: #8b4b27;"></i>

            <h3 class="fw-bold mt-3">Votre panier est vide</h3>

            <p class="text-muted">
                Découvrez nos articles et ajoutez ceux qui vous intéressent.
            </p>

            <a href="index.php" class="btn btn-principal px-4">
                Découvrir les articles
            </a>

        </div>

    <?php else: ?>

        <div class="row g-4">

            <div class="col-lg-8">

                <div class="cart-card">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0">
                            Articles sélectionnés (<?= $totalArticles ?>)
                        </h4>

                        <form method="POST" onsubmit="return confirm('Vider entièrement le panier ?');">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_panier"]) ?>">
                            <input type="hidden" name="action" value="vider">

                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i> Vider
                            </button>
                        </form>
                    </div>

                    <?php foreach ($lignesPanier as $ligne): ?>

                        <div class="row align-items-center g-3 py-3 border-bottom">

                            <div class="col-4 col-md-2">

                                <?php if ($ligne["image"] !== ""): ?>

                                    <img
                                        src="<?= e(urlImagePanier($ligne["image"])) ?>"
                                        class="cart-image"
                                        alt="<?= e($ligne["nom"]) ?>"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                    >

                                    <div class="image-empty" style="display:none;">
                                        <i class="bi bi-image"></i>
                                    </div>

                                <?php else: ?>

                                    <div class="image-empty">
                                        <i class="bi bi-image"></i>
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="col-8 col-md-4">

                                <h6 class="product-name"><?= e($ligne["nom"]) ?></h6>

                                <div class="price">
                                    <?= e(prixFCFA($ligne["prix"])) ?>
                                </div>

                                <small class="text-muted">
                                    Stock : <?= $ligne["stock"] ?>
                                </small>

                            </div>

                            <div class="col-7 col-md-3">

                                <form method="POST" class="d-flex gap-2 align-items-center">

                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_panier"]) ?>">
                                    <input type="hidden" name="action" value="modifier">
                                    <input type="hidden" name="article_id" value="<?= $ligne["id"] ?>">

                                    <input
                                        type="number"
                                        name="quantite"
                                        class="form-control form-control-sm quantity-input"
                                        min="1"
                                        max="<?= $ligne["stock"] ?>"
                                        value="<?= $ligne["quantite"] ?>"
                                        required
                                    >

                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Mettre à jour">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>

                                </form>

                            </div>

                            <div class="col-5 col-md-3 text-end">

                                <div class="price mb-2">
                                    <?= e(prixFCFA($ligne["sous_total"])) ?>
                                </div>

                                <form method="POST" onsubmit="return confirm('Retirer cet article du panier ?');">

                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_panier"]) ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="article_id" value="<?= $ligne["id"] ?>">

                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash"></i> Supprimer
                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="col-lg-4">

                <div class="summary-card">

                    <h4 class="fw-bold mb-4">Résumé de la commande</h4>

                    <div class="d-flex justify-content-between mb-3">
                        <span>Quantité totale</span>
                        <strong><?= $totalArticles ?></strong>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span>Sous-total</span>
                        <strong><?= e(prixFCFA($totalGeneral)) ?></strong>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-4">
                        <strong>Total</strong>
                        <strong class="price fs-5">
                            <?= e(prixFCFA($totalGeneral)) ?>
                        </strong>
                    </div>

                    <a href="commande.php" class="btn btn-principal w-100 py-2">
                        <i class="bi bi-credit-card"></i>
                        Passer la commande
                    </a>

                    <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
                        Continuer mes achats
                    </a>

                    <p class="text-muted small mt-3 mb-0">
                        Le total est calculé à partir des prix actuels des articles.
                    </p>

                </div>

            </div>

        </div>

    <?php endif; ?>

</main>

<footer class="text-center">
    <small>&copy; <?= date("Y") ?> Artisanat d'Afrique. Tous droits réservés.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
