
<?php

session_start();

// Vérifier la session administrateur
if (
    !isset($_SESSION["admin_token"]) ||
    !isset($_SESSION["admin_user"]) ||
    ($_SESSION["admin_user"]["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION["admin_token"];
$apiBase = "http://localhost/artisanat_afrique/backend/api/index.php";

$erreur = "";
$article = null;
$categories = [];

/**
 * Appeler l'API.
 * Les données sont envoyées en multipart/form-data.
 * Le fichier est facultatif.
 */
function appelerApiModifier(
    string $url,
    string $token,
    string $methode = "GET",
    ?array $donnees = null,
    ?array $fichier = null
): array {

    $curl = curl_init();

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $token
        ]
    ];

    if ($methode === "PUT") {

        $options[CURLOPT_CUSTOMREQUEST] = "PUT";

        $champs = $donnees ?? [];

        // Ajouter le nouveau fichier si l'utilisateur en a choisi un
        if ($fichier !== null) {
            $champs["image"] = curl_file_create(
                $fichier["tmp_name"],
                $fichier["type"],
                $fichier["name"]
            );
        }

        $options[CURLOPT_POSTFIELDS] = $champs;
    }

    curl_setopt_array($curl, $options);
$reponse = curl_exec($curl);
$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$erreurCurl = curl_error($curl);
$tailleReponse = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);
$typeReponse = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

curl_close($curl);

error_log("===== DIAGNOSTIC MODIFICATION ARTICLE =====");
error_log("HTTP : " . $code);
error_log("Taille réponse : " . $tailleReponse);
error_log("Type réponse : " . ($typeReponse ?? "inconnu"));
error_log("Erreur cURL : " . $erreurCurl);
error_log("Réponse brute : " . var_export($reponse, true));

    if ($reponse === false) {
        return [
            "success" => false,
            "message" => "Erreur de communication : " . $erreurCurl,
            "code" => 0
        ];
    }

    $resultat = json_decode($reponse, true);

   if (!is_array($resultat)) {
    error_log("Réponse API non JSON : " . $reponse);

    return [
        "success" => false,
        "message" => "Erreur API HTTP " . $code . " : " . substr(strip_tags($reponse), 0, 1000),
        "code" => $code
    ];
}

    return $resultat;
}


// Vérifier l'identifiant de l'article
$id = filter_var(
    $_GET["id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header("Location: articles.php");
    exit;
}


// Récupérer les articles
$resultatArticles = appelerApiModifier(
    $apiBase . "?route=products",
    $token
);

if (empty($resultatArticles["success"])) {

    $erreur = "Impossible de récupérer les articles.";

} else {

    foreach (($resultatArticles["data"] ?? []) as $element) {

        if ((int)$element["id"] === (int)$id) {
            $article = $element;
            break;
        }
    }

    if (!$article) {
        $erreur = "Article introuvable.";
    }
}


// Récupérer les catégories
$resultatCategories = appelerApiModifier(
    $apiBase . "?route=categories",
    $token
);

if (!empty($resultatCategories["success"])) {
    $categories = $resultatCategories["data"] ?? [];
}


// Protection CSRF
if (empty($_SESSION["csrf_modifier"])) {
    $_SESSION["csrf_modifier"] = bin2hex(random_bytes(32));
}


// Traitement du formulaire
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $article !== null
) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_modifier"],
            $_POST["csrf_token"]
        )
    ) {

        $erreur = "Session du formulaire invalide. Recharge la page.";

    } else {

        $nom = trim($_POST["nom"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $prix = $_POST["prix"] ?? "";
        $stock = $_POST["stock"] ?? "";
        $categorieId = $_POST["categorie_id"] ?? "";

        // Vérifier les informations
        if (
            $nom === "" ||
            !is_numeric($prix) ||
            !is_numeric($stock) ||
            (float)$prix < 0 ||
            (float)$stock < 0 ||
            (float)$stock != (int)$stock ||
            !filter_var($categorieId, FILTER_VALIDATE_INT) ||
            (int)$categorieId < 1
        ) {

            $erreur = "Vérifie les informations saisies.";

        } else {

            $nouveauFichier = null;

            // Vérifier si une nouvelle photo a été sélectionnée
            if (
                isset($_FILES["image"]) &&
                $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {

                    $erreur = "Une erreur est survenue pendant l'envoi de la photo.";

                } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {

                    $erreur = "La photo ne doit pas dépasser 5 Mo.";

                } else {

                    $fichierTemporaire = $_FILES["image"]["tmp_name"];

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $typeMime = $finfo->file($fichierTemporaire);

                    $typesAutorises = [
                        "image/jpeg",
                        "image/png",
                        "image/webp"
                    ];

                    if (
                        !is_uploaded_file($fichierTemporaire) ||
                        !in_array($typeMime, $typesAutorises, true) ||
                        getimagesize($fichierTemporaire) === false
                    ) {

                        $erreur = "Format de photo invalide. Utilise JPG, PNG ou WEBP.";

                    } else {

                        $nouveauFichier = [
                            "tmp_name" => $fichierTemporaire,
                            "type" => $typeMime,
                            "name" => basename($_FILES["image"]["name"])
                        ];
                    }
                }
            }

            // Envoyer les modifications si aucune erreur n'est présente
            if ($erreur === "") {

                $donnees = [
                    "nom" => $nom,
                    "description" => $description,
                    "prix" => $prix,
                    "stock" => $stock,
                    "categorie_id" => $categorieId
                ];

                $resultat = appelerApiModifier(
                    $apiBase . "?route=products&id=" . $id,
                    $token,
                    "PUT",
                    $donnees,
                    $nouveauFichier
                );

                if (!empty($resultat["success"])) {

                    header("Location: articles.php?modification=success");
                    exit;

                } else {

                    $erreur = $resultat["message"] ?? "La modification a échoué.";
                }
            }
        }
    }
}


// Protection contre les injections HTML
function eModifier($valeur): string
{
    return htmlspecialchars(
        (string)($valeur ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


// Construire l'adresse de l'image
function urlImageModifier($image): string
{
    if (empty($image)) {
        return "";
    }

    if (preg_match('~^https?://~i', $image)) {
        return $image;
    }

    return "/artisanat_afrique/backend/" . ltrim($image, "/");
}


// Valeurs du formulaire
$nomActuel = $_POST["nom"] ?? ($article["nom"] ?? "");

$descriptionActuelle = $_POST["description"] ?? ($article["description"] ?? "");

$prixActuel = $_POST["prix"] ?? ($article["prix"] ?? "");

$stockActuel = $_POST["stock"] ?? ($article["stock"] ?? "");

$categorieActuelle = $_POST["categorie_id"] ?? ($article["categorie_id"] ?? "");

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Modifier un article - Artisanat d'Afrique</title>

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
            max-width: 950px;
            padding-top: 35px;
            padding-bottom: 50px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,.04);
        }

        .page-title {
            color: #263b32;
            font-weight: bold;
        }

        .form-label {
            font-weight: 600;
            color: #34443c;
        }

        .form-control,
        .form-select {
            padding: 12px;
        }

        .article-image {
            width: 170px;
            height: 140px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        .preview-container {
            margin-top: 15px;
            display: none;
        }

        .photo-info {
            font-size: 13px;
            color: #777;
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

        <a href="articles.php" class="btn btn-outline-light btn-sm">
            Retour aux articles
        </a>

    </div>

</header>


<main class="container main-container">

    <div class="mb-4">

        <h2 class="page-title">Modifier un article</h2>

        <p class="text-muted">
            Article numéro <?= eModifier($id) ?>
        </p>

    </div>


    <?php if ($erreur !== ""): ?>

        <div class="alert alert-danger">
            <?= eModifier($erreur) ?>
        </div>

    <?php endif; ?>


    <?php if ($article !== null): ?>

        <div class="form-card">

            <form method="POST" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= eModifier($_SESSION["csrf_modifier"]) ?>"
                >


                <div class="mb-3">

                    <label class="form-label">
                        Nom de l'article
                    </label>

                    <input
                        type="text"
                        name="nom"
                        class="form-control"
                        maxlength="150"
                        value="<?= eModifier($nomActuel) ?>"
                        required
                    >

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Description
                    </label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="4"
                    ><?= eModifier($descriptionActuelle) ?></textarea>

                </div>


                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Prix (FCFA)
                        </label>

                        <input
                            type="number"
                            name="prix"
                            class="form-control"
                            min="0"
                            step="1"
                            value="<?= eModifier($prixActuel) ?>"
                            required
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Stock
                        </label>

                        <input
                            type="number"
                            name="stock"
                            class="form-control"
                            min="0"
                            step="1"
                            value="<?= eModifier($stockActuel) ?>"
                            required
                        >

                    </div>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Catégorie
                    </label>

                    <select
                        name="categorie_id"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Choisir une catégorie
                        </option>

                        <?php foreach ($categories as $categorie): ?>

                            <option
                                value="<?= eModifier($categorie["id"]) ?>"
                                <?= ((string)$categorieActuelle === (string)$categorie["id"]) ? "selected" : "" ?>
                            >

                                <?= eModifier($categorie["nom"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <hr class="my-4">


                <div class="mb-4">

                    <label class="form-label">
                        Photo actuelle
                    </label>

                    <div class="mb-3">

                        <?php if (!empty($article["image_url"])): ?>

                            <img
                                id="imageActuelle"
                                src="<?= eModifier(urlImageModifier($article["image_url"])) ?>"
                                class="article-image"
                                alt="Photo actuelle de l'article"
                            >

                        <?php else: ?>

                            <p class="text-muted" id="imageActuelle">
                                Aucune photo enregistrée.
                            </p>

                        <?php endif; ?>

                    </div>


                    <label class="form-label">
                        Choisir une nouvelle photo
                    </label>

                    <input
                        type="file"
                        name="image"
                        id="image"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <div class="form-text">
                        Formats acceptés : JPG, PNG et WEBP. Taille maximale : 5 Mo.
                    </div>


                    <div class="preview-container" id="previewContainer">

                        <p class="form-label">
                            Aperçu de la nouvelle photo
                        </p>

                        <img
                            src=""
                            id="nouvelleImage"
                            class="article-image"
                            alt="Aperçu de la nouvelle photo"
                        >

                    </div>

                </div>


                <hr>


                <div class="d-flex justify-content-between flex-wrap gap-2">

                    <a href="articles.php" class="btn btn-light border">
                        Annuler
                    </a>

                    <button type="submit" class="btn btn-success px-4">
                        Enregistrer les modifications
                    </button>

                </div>

            </form>

        </div>

    <?php endif; ?>

</main>


<script>

const champImage = document.getElementById("image");
const previewContainer = document.getElementById("previewContainer");
const nouvelleImage = document.getElementById("nouvelleImage");

if (champImage) {

    champImage.addEventListener("change", function () {

        const fichier = this.files[0];

        if (!fichier) {
            previewContainer.style.display = "none";
            nouvelleImage.src = "";
            return;
        }

        const typesAutorises = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!typesAutorises.includes(fichier.type)) {

            alert("Format invalide. Choisis une image JPG, PNG ou WEBP.");

            this.value = "";
            previewContainer.style.display = "none";

            return;
        }

        if (fichier.size > 5 * 1024 * 1024) {

            alert("La photo ne doit pas dépasser 5 Mo.");

            this.value = "";
            previewContainer.style.display = "none";

            return;
        }

        const lecteur = new FileReader();

        lecteur.onload = function (evenement) {

            nouvelleImage.src = evenement.target.result;

            previewContainer.style.display = "block";
        };

        lecteur.readAsDataURL(fichier);

    });

}

</script>

</body>
</html>
