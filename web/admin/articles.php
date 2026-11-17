
<?php

session_start();

// Vérifier que l'administrateur est connecté
if (
    !isset($_SESSION["admin_token"]) ||
    !isset($_SESSION["admin_user"]) ||
    ($_SESSION["admin_user"]["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION["admin_token"];

$articles = [];
$erreur = "";

// Adresse de l'API
$url = "http://localhost/artisanat_afrique/backend/api/index.php?route=products";

// Fonction pour communiquer avec l'API
function appelerApiArticles(
    string $url,
    string $token,
    string $methode = "GET"
): array {

    $curl = curl_init();

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $token
        ]
    ];

    if ($methode === "DELETE") {
        $options[CURLOPT_CUSTOMREQUEST] = "DELETE";
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($response === false) {
        return [
            "success" => false,
            "message" => "Erreur de communication : " . $curlError,
            "code" => 0
        ];
    }

    $resultat = json_decode($response, true);

    if (!is_array($resultat)) {
        return [
            "success" => false,
            "message" => "Réponse invalide du serveur.",
            "code" => $httpCode
        ];
    }

    $resultat["code"] = $httpCode;

    return $resultat;
}


// Protection CSRF
if (empty($_SESSION["csrf_articles"])) {
    $_SESSION["csrf_articles"] = bin2hex(random_bytes(32));
}


// Traitement de la désactivation
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_articles"],
            $_POST["csrf_token"]
        )
    ) {

        $_SESSION["message_articles"] = [
            "type" => "danger",
            "texte" => "Requête invalide. Recharge la page."
        ];

        header("Location: articles.php");
        exit;

    }

    $idArticle = filter_var(
        $_POST["article_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$idArticle || $idArticle < 1) {

        $_SESSION["message_articles"] = [
            "type" => "danger",
            "texte" => "Identifiant d'article invalide."
        ];

        header("Location: articles.php");
        exit;
    }

    // Appeler l'API pour désactiver l'article
    $resultatDesactivation = appelerApiArticles(
        $url . "&id=" . $idArticle,
        $token,
        "DELETE"
    );

    if (
        !empty($resultatDesactivation["success"]) &&
        $resultatDesactivation["code"] >= 200 &&
        $resultatDesactivation["code"] < 300
    ) {

        $_SESSION["message_articles"] = [
            "type" => "success",
            "texte" => "L'article a été désactivé avec succès."
        ];

    } else {

        $_SESSION["message_articles"] = [
            "type" => "danger",
            "texte" => $resultatDesactivation["message"]
                ?? "La désactivation de l'article a échoué."
        ];
    }

    header("Location: articles.php");
    exit;
}


// Récupérer le message après une opération
$message = $_SESSION["message_articles"] ?? null;
unset($_SESSION["message_articles"]);


// Récupérer les articles depuis l'API
$resultat = appelerApiArticles($url, $token);

if (
    $resultat["code"] === 200 &&
    !empty($resultat["success"])
) {

    $articles = $resultat["data"] ?? [];

} else {

    $erreur = $resultat["message"]
        ?? "Erreur lors du chargement des articles.";
}


// Protection contre les injections HTML
function e($valeur): string
{
    return htmlspecialchars(
        (string)($valeur ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


// Construire l'adresse d'une image
function urlImageArticle($image): string
{
    if (empty($image)) {
        return "";
    }

    if (preg_match('~^https?://~i', $image)) {
        return $image;
    }

    return "/artisanat_afrique/backend/" . ltrim($image, "/");
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des articles - Artisanat d'Afrique</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f6f8;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #263b32;
            color: white;
            padding: 20px 0;
        }

        .main-container {
            padding-top: 35px;
            padding-bottom: 50px;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 3px 15px rgba(0,0,0,.04);
        }

        .product-image {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
            background: #eee;
        }

        .image-placeholder {
            width: 65px;
            height: 65px;
            border-radius: 8px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #777;
            font-size: 11px;
        }

        .table td,
        .table th {
            vertical-align: middle;
            padding: 14px 10px;
        }

        .page-title {
            color: #263b32;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

    </style>


<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body>
<?php require __DIR__ . "/_navigation.php"; ?>


<header class="topbar">

    <div class="container d-flex justify-content-between align-items-center">

        <div>
            <h4 class="mb-0">Artisanat d'Afrique</h4>
            <small>Espace administrateur</small>
        </div>

        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            Retour au tableau de bord
        </a>

    </div>

</header>


<main class="container main-container">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

        <div>

            <h2 class="page-title">
                Gestion des articles
            </h2>

            <p class="text-muted mb-0">
                Consulte les articles enregistrés dans la boutique.
            </p>

        </div>

        <a href="ajouter_article.php" class="btn btn-success">
            + Ajouter un article
        </a>

    </div>


    <?php if ($message): ?>

        <div class="alert alert-<?= e($message["type"]) ?> alert-dismissible fade show">

            <?= e($message["texte"]) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Fermer"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($erreur !== ""): ?>

        <div class="alert alert-danger">
            <?= e($erreur) ?>
        </div>

    <?php else: ?>

        <div class="content-card">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Liste des articles
                </h5>

                <span class="badge text-bg-success">
                    <?= count($articles) ?> article(s)
                </span>

            </div>


            <div class="table-responsive">

                <table class="table table-hover">

                    <thead class="table-light">

                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Disponibilité</th>
                            <th>Actions</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (count($articles) > 0): ?>

                        <?php foreach ($articles as $article): ?>

                            <tr>

                                <td>

                                    <?php if (!empty($article["image_url"])): ?>

                                        <img
                                            src="<?= e(urlImageArticle($article["image_url"])) ?>"
                                            alt="<?= e($article["nom"]) ?>"
                                            class="product-image"
                                        >

                                    <?php else: ?>

                                        <div class="image-placeholder">
                                            Sans image
                                        </div>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <strong>
                                        <?= e($article["nom"]) ?>
                                    </strong>

                                    <div class="small text-muted">
                                        ID : <?= e($article["id"]) ?>
                                    </div>

                                </td>


                                <td>
                                    <?= e($article["categorie"] ?? "Non classé") ?>
                                </td>


                                <td>
                                    <?= number_format((float)$article["prix"], 0, ",", " ") ?>
                                    FCFA
                                </td>


                                <td>
                                    <?= e($article["stock"]) ?>
                                </td>


                                <td>

                                    <?php if ((int)$article["stock"] > 0): ?>

                                        <span class="badge text-bg-success">
                                            En stock
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-danger">
                                            Épuisé
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="actions">

                                        <a
                                            href="modifier_article.php?id=<?= e($article["id"]) ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Modifier
                                        </a>


                                        <form
                                            method="POST"
                                            class="form-desactivation"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["csrf_articles"]) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="article_id"
                                                value="<?= e($article["id"]) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                            >
                                                Désactiver
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7" class="text-center text-muted py-4">
                                Aucun article disponible.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</main>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

// Demander une confirmation avant la désactivation
document.querySelectorAll(".form-desactivation").forEach(function(form) {

    form.addEventListener("submit", function(event) {

        const confirmation = confirm(
            "Veux-tu vraiment désactiver cet article ?\n\n" +
            "Il ne sera plus visible dans la boutique."
        );

        if (!confirmation) {
            event.preventDefault();
        }

    });

});

</script>

</body>
</html>
