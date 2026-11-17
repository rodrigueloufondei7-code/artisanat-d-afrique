
<?php
session_start();

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
$categories = [];

/*
|--------------------------------------------------------------------------
| Fonction de communication avec l'API
|--------------------------------------------------------------------------
*/
function appelerApi(string $url, string $token, string $methode = "GET", array $champs = []): array
{
    $curl = curl_init();

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $token
        ]
    ];

    if ($methode === "POST") {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $champs;
    }

    curl_setopt_array($curl, $options);

    $reponse = curl_exec($curl);
    $erreurCurl = curl_error($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($reponse === false) {
        return [
            "success" => false,
            "message" => "Erreur de communication avec le serveur : " . $erreurCurl,
            "code" => 0
        ];
    }

    $resultat = json_decode($reponse, true);

    if (!is_array($resultat)) {
        return [
            "success" => false,
            "message" => "Réponse invalide du serveur.",
            "code" => $code
        ];
    }

    $resultat["code"] = $code;

    return $resultat;
}

/*
|--------------------------------------------------------------------------
| Récupérer les catégories
|--------------------------------------------------------------------------
*/
$resultatCategories = appelerApi(
    $apiBase . "?route=categories",
    $token
);

if (!empty($resultatCategories["success"])) {
    $categories = $resultatCategories["data"] ?? [];
} else {
    $erreur = "Impossible de charger les catégories.";
}

/*
|--------------------------------------------------------------------------
| Protection CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["csrf_article"])) {
    $_SESSION["csrf_article"] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Traitement du formulaire
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_article"], $_POST["csrf_token"])
    ) {
        $erreur = "La session du formulaire est invalide. Recharge la page.";
    } elseif ($erreur === "") {

        $nom = trim($_POST["nom"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $prix = $_POST["prix"] ?? "";
        $stock = $_POST["stock"] ?? "";
        $categorieId = $_POST["categorie_id"] ?? "";

        if (
            $nom === "" ||
            !is_numeric($prix) ||
            !is_numeric($stock) ||
            (float)$prix < 0 ||
            (int)$stock < 0 ||
            (string)(int)$stock !== (string)$stock ||
            !filter_var($categorieId, FILTER_VALIDATE_INT) ||
            (int)$categorieId < 1
        ) {
            $erreur = "Vérifie les informations saisies.";
        } elseif (
            !isset($_FILES["image"]) ||
            $_FILES["image"]["error"] === UPLOAD_ERR_NO_FILE
        ) {
            $erreur = "Veuillez sélectionner une image pour cet article.";
        } elseif ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            $erreur = "Une erreur est survenue pendant l'envoi de l'image.";
        } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
            $erreur = "L'image ne doit pas dépasser 5 Mo.";
        } else {

            $tmpImage = $_FILES["image"]["tmp_name"];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpImage);

            $formats = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (!in_array($mime, $formats, true) || @getimagesize($tmpImage) === false) {
                $erreur = "Format d'image invalide. Choisis JPG, PNG ou WEBP.";
            } else {

                // Préparer l'image pour l'envoi à l'API
                $image = curl_file_create(
                    $tmpImage,
                    $mime,
                    basename($_FILES["image"]["name"])
                );

                $champs = [
                    "nom" => $nom,
                    "description" => $description,
                    "prix" => $prix,
                    "stock" => $stock,
                    "categorie_id" => $categorieId,
                    "image" => $image
                ];

                $resultat = appelerApi(
                    $apiBase . "?route=products",
                    $token,
                    "POST",
                    $champs
                );

                if (
                    !empty($resultat["success"]) &&
                    $resultat["code"] === 201
                ) {
                    $_SESSION["csrf_article"] = bin2hex(random_bytes(32));

                    header("Location: articles.php?ajout=success");
                    exit;
                }

                $erreur = $resultat["message"] ?? "Impossible d'ajouter l'article.";
            }
        }
    }
}

// Fonction de protection HTML
function e($valeur): string
{
    return htmlspecialchars((string)$valeur, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajouter un article - Artisanat d'Afrique</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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

        .preview-container {
            margin-top: 15px;
            display: none;
        }

        .preview-container img {
            width: 180px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        .required {
            color: #dc3545;
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
        <h2 class="page-title">Ajouter un article</h2>
        <p class="text-muted">
            Enregistre un nouveau produit dans ta boutique.
        </p>
    </div>

    <?php if ($erreur !== ""): ?>
        <div class="alert alert-danger">
            <?= e($erreur) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_article"]) ?>"
            >

            <div class="mb-3">
                <label class="form-label">
                    Nom de l'article <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="nom"
                    class="form-control"
                    placeholder="Exemple : Sac artisanal"
                    maxlength="150"
                    value="<?= e($_POST["nom"] ?? "") ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>

                <textarea
                    name="description"
                    class="form-control"
                    rows="4"
                    placeholder="Décris cet article..."
                ><?= e($_POST["description"] ?? "") ?></textarea>
            </div>

            <div class="row">

                <div class="col-md-6 mb-3">
                    <label class="form-label">
                        Prix (FCFA) <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        name="prix"
                        class="form-control"
                        min="0"
                        step="1"
                        placeholder="Exemple : 10000"
                        value="<?= e($_POST["prix"] ?? "") ?>"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">
                        Quantité en stock <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        name="stock"
                        class="form-control"
                        min="0"
                        step="1"
                        placeholder="Exemple : 15"
                        value="<?= e($_POST["stock"] ?? "") ?>"
                        required
                    >
                </div>

            </div>

            <div class="mb-3">
                <label class="form-label">
                    Catégorie <span class="required">*</span>
                </label>

                <select name="categorie_id" class="form-select" required>
                    <option value="">Choisir une catégorie</option>

                    <?php foreach ($categories as $categorie): ?>
                        <option
                            value="<?= e($categorie["id"]) ?>"
                            <?= (($_POST["categorie_id"] ?? "") == $categorie["id"]) ? "selected" : "" ?>
                        >
                            <?= e($categorie["nom"]) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <?php if (empty($categories)): ?>
                    <small class="text-danger">
                        Aucune catégorie disponible. Vérifie ton API des catégories.
                    </small>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label">
                    Photo de l'article <span class="required">*</span>
                </label>

                <input
                    type="file"
                    name="image"
                    id="image"
                    class="form-control"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    required
                >

                <small class="text-muted">
                    Formats acceptés : JPG, PNG et WEBP. Maximum 5 Mo.
                </small>

                <div class="preview-container" id="previewContainer">
                    <p class="small fw-bold mt-2">Aperçu de l'image :</p>
                    <img src="" id="previewImage" alt="Aperçu de l'article">
                </div>
            </div>

            <hr>

            <div class="d-flex justify-content-between flex-wrap gap-2">
                <a href="articles.php" class="btn btn-light border">
                    Annuler
                </a>

                <button
                    type="submit"
                    class="btn btn-success px-4"
                    <?= empty($categories) ? "disabled" : "" ?>
                >
                    Enregistrer l'article
                </button>
            </div>

        </form>

    </div>

</main>

<script>
const champImage = document.getElementById("image");
const apercu = document.getElementById("previewImage");
const conteneur = document.getElementById("previewContainer");

champImage.addEventListener("change", function () {

    const fichier = this.files[0];

    if (!fichier) {
        conteneur.style.display = "none";
        return;
    }

    const formats = ["image/jpeg", "image/png", "image/webp"];

    if (!formats.includes(fichier.type)) {
        alert("Format refusé. Choisis une image JPG, PNG ou WEBP.");
        this.value = "";
        conteneur.style.display = "none";
        return;
    }

    if (fichier.size > 5 * 1024 * 1024) {
        alert("L'image ne doit pas dépasser 5 Mo.");
        this.value = "";
        conteneur.style.display = "none";
        return;
    }

    const lecteur = new FileReader();

    lecteur.onload = function (evenement) {
        apercu.src = evenement.target.result;
        conteneur.style.display = "block";
    };

    lecteur.readAsDataURL(fichier);
});
</script>

</body>
</html>
