
<?php
session_start();

// Adresse de l'API d'inscription
$urlApi = "http://localhost/artisanat_afrique/backend/api/index.php?route=auth&action=register";

$message = "";
$typeMessage = "";

// Protection CSRF
if (empty($_SESSION["csrf_inscription"])) {
    $_SESSION["csrf_inscription"] = bin2hex(random_bytes(32));
}

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Vérification du jeton CSRF
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_inscription"], $_POST["csrf_token"])
    ) {
        $message = "Votre session a expiré. Rechargez la page.";
        $typeMessage = "danger";

    } else {

        $nom = trim($_POST["nom"] ?? "");
        $prenom = trim($_POST["prenom"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $telephone = trim($_POST["telephone"] ?? "");
        $motDePasse = $_POST["mot_de_passe"] ?? "";
        $confirmation = $_POST["confirmation"] ?? "";

        // Vérifications locales
        if (
            $nom === "" ||
            $prenom === "" ||
            $email === "" ||
            $motDePasse === ""
        ) {
            $message = "Veuillez remplir tous les champs obligatoires.";
            $typeMessage = "danger";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Veuillez saisir une adresse e-mail valide.";
            $typeMessage = "danger";

        } elseif (strlen($motDePasse) < 8) {
            $message = "Le mot de passe doit contenir au moins 8 caractères.";
            $typeMessage = "danger";

        } elseif ($motDePasse !== $confirmation) {
            $message = "Les deux mots de passe ne correspondent pas.";
            $typeMessage = "danger";

        } else {

            // Préparation des données destinées à l'API
            $donnees = [
                "nom" => $nom,
                "prenom" => $prenom,
                "email" => $email,
                "telephone" => $telephone,
                "mot_de_passe" => $motDePasse
            ];

            // Envoi à l'API avec cURL
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $urlApi,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($donnees),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Accept: application/json"
                ],
                CURLOPT_TIMEOUT => 15
            ]);

            $reponse = curl_exec($ch);
            $erreurCurl = curl_error($ch);
            $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($reponse === false) {
                $message = "Impossible de contacter le serveur.";
                $typeMessage = "danger";

            } else {

                $resultat = json_decode($reponse, true);

                if (!is_array($resultat)) {
                    $message = "Le serveur a retourné une réponse invalide.";
                    $typeMessage = "danger";

                } elseif (
                    $codeHttp >= 400 ||
                    !isset($resultat["success"]) ||
                    $resultat["success"] !== true
                ) {
                    $message = $resultat["message"] ?? "L'inscription a échoué.";
                    $typeMessage = "danger";

                } else {
                    // Inscription réussie
                    $_SESSION["inscription_reussie"] = true;

                    header("Location: inscription.php?success=1");
                    exit;
                }
            }
        }
    }
}

// Message après redirection
if (isset($_GET["success"]) && $_GET["success"] == "1") {
    $message = "Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.";
    $typeMessage = "success";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Créer un compte - Artisanat d'Afrique</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: #faf8f5;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .navbar-brand {
            color: #8b4b27;
            font-weight: 800;
        }

        .page-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 15px;
        }

        .form-card {
            background: white;
            width: 100%;
            max-width: 520px;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.07);
        }

        .form-title {
            color: #34251e;
            font-weight: 800;
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: #8b4b27;
            box-shadow: 0 0 0 0.2rem rgba(139,75,39,0.12);
        }

        .btn-principal {
            background: #8b4b27;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
        }

        .btn-principal:hover {
            background: #69391e;
            color: white;
        }

        .lien {
            color: #8b4b27;
            font-weight: 600;
            text-decoration: none;
        }

        .lien:hover {
            text-decoration: underline;
        }

        footer {
            background: #34251e;
            color: white;
            padding: 20px;
            text-align: center;
        }
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
</head>

<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand text-decoration-none" href="index.php">
            <i class="bi bi-shop"></i> Artisanat d'Afrique
        </a>

        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Retour à la boutique
        </a>
    </div>
</nav>

<main class="page-container">

    <div class="form-card">

        <div class="text-center mb-4">
            <div class="mb-3">
                <i class="bi bi-person-plus-fill" style="font-size: 45px; color: #8b4b27;"></i>
            </div>

            <h2 class="form-title">Créer un compte</h2>

            <p class="text-muted">
                Rejoignez Artisanat d'Afrique et découvrez nos articles.
            </p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="alert alert-<?= htmlspecialchars($typeMessage) ?>">
                <?= htmlspecialchars($message) ?>

                <?php if ($typeMessage === "success"): ?>
                    <div class="mt-3">
                        <a href="connexion.php" class="btn btn-success btn-sm">
                            Se connecter
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_GET["success"])): ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_inscription"]) ?>"
            >

            <div class="row">

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Nom *</label>
                    <input
                        type="text"
                        name="nom"
                        class="form-control"
                        placeholder="Votre nom"
                        value="<?= htmlspecialchars($_POST["nom"] ?? "") ?>"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Prénom *</label>
                    <input
                        type="text"
                        name="prenom"
                        class="form-control"
                        placeholder="Votre prénom"
                        value="<?= htmlspecialchars($_POST["prenom"] ?? "") ?>"
                        maxlength="100"
                        required
                    >
                </div>

            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Adresse e-mail *</label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="exemple@email.com"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    maxlength="255"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Téléphone</label>

                <input
                    type="tel"
                    name="telephone"
                    class="form-control"
                    placeholder="Votre numéro de téléphone"
                    value="<?= htmlspecialchars($_POST["telephone"] ?? "") ?>"
                    maxlength="30"
                >
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Mot de passe *</label>

                <input
                    type="password"
                    name="mot_de_passe"
                    class="form-control"
                    placeholder="Minimum 8 caractères"
                    minlength="8"
                    required
                >
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Confirmer le mot de passe *</label>

                <input
                    type="password"
                    name="confirmation"
                    class="form-control"
                    placeholder="Répétez votre mot de passe"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="btn btn-principal w-100">
                <i class="bi bi-person-check"></i> Créer mon compte
            </button>

        </form>

        <?php endif; ?>

        <div class="text-center mt-4">
            <span class="text-muted">Vous avez déjà un compte ?</span>
            <a href="connexion.php" class="lien">Connectez-vous</a>
        </div>

    </div>

</main>

<footer>
    <small>&copy; <?= date("Y") ?> Artisanat d'Afrique. Tous droits réservés.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
