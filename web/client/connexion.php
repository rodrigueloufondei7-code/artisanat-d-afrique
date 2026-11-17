
<?php
// =====================================================
// CONNEXION CLIENT - ARTISANAT D'AFRIQUE
// Authentification effectuée par l'API PHP.
// =====================================================

// Configuration de la session
ini_set("session.use_strict_mode", "1");

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/artisanat_afrique/",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

// Si le client est déjà connecté
if (
    !empty($_SESSION["client_connecte"]) &&
    !empty($_SESSION["client_token"]) &&
    ($_SESSION["client"]["role"] ?? "") === "client"
) {
    header("Location: index.php");
    exit;
}

unset(
    $_SESSION["client_connecte"],
    $_SESSION["client_token"],
    $_SESSION["client"]
);

// Adresse de l'API de connexion
$urlApi = "http://localhost/artisanat_afrique/backend/api/index.php?route=auth&action=login&role=client";

$message = "";
$typeMessage = "";

// Création du jeton CSRF
if (empty($_SESSION["csrf_connexion"])) {
    $_SESSION["csrf_connexion"] = bin2hex(random_bytes(32));
}

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Vérification CSRF
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_connexion"], $_POST["csrf_token"])
    ) {
        $message = "Votre session a expiré. Rechargez la page.";
        $typeMessage = "danger";

    } else {

        $email = trim($_POST["email"] ?? "");
        $motDePasse = $_POST["mot_de_passe"] ?? "";

        if ($email === "" || $motDePasse === "") {
            $message = "Veuillez remplir tous les champs.";
            $typeMessage = "danger";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Veuillez saisir une adresse e-mail valide.";
            $typeMessage = "danger";

        } else {

            // Données envoyées à l'API
            $donnees = [
                "email" => $email,
                "mot_de_passe" => $motDePasse
            ];

            // Appel API
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
                    $message = $resultat["message"] ?? "Adresse e-mail ou mot de passe incorrect.";
                    $typeMessage = "danger";

                } elseif (($resultat["data"]["utilisateur"]["role"] ?? "") !== "client") {
                    $message = "Ce compte n'est pas autorisé à accéder à l'espace client.";
                    $typeMessage = "danger";

                } else {

                    // Récupération du jeton et des informations utilisateur
                    $token = $resultat["token"] ?? ($resultat["data"]["token"] ?? "");

                    $utilisateur = $resultat["utilisateur"]
                        ?? $resultat["user"]
                        ?? $resultat["data"]["utilisateur"]
                        ?? $resultat["data"]["user"]
                        ?? [];

                    // Le jeton est indispensable pour les futures requêtes API
                    if (!is_string($token) || $token === "") {
                        $message = "Connexion refusée : le serveur n'a pas fourni de jeton.";
                        $typeMessage = "danger";

                    } else {

                        // Renouvellement de l'identifiant de session
                        session_regenerate_id(true);

                        // Stockage des informations de connexion
                        $_SESSION["client_connecte"] = true;
                        $_SESSION["client_token"] = $token;
                        $_SESSION["client"] = is_array($utilisateur) ? $utilisateur : [];

                        // Jeton CSRF renouvelé
                        $_SESSION["csrf_connexion"] = bin2hex(random_bytes(32));

                        // Redirection après connexion
                        header("Location: connexion.php?success=1");
                        exit;
                    }
                }
            }
        }
    }
}

// Message après connexion
if (
    isset($_GET["success"]) &&
    $_GET["success"] == "1" &&
    !empty($_SESSION["client_connecte"]) &&
    ($_SESSION["client"]["role"] ?? "") === "client"
) {
    $message = "Connexion réussie ! Votre session client est maintenant active.";
    $typeMessage = "success";
}

// Éviter d'afficher un message de succès si la session n'existe plus
if (
    isset($_GET["success"]) &&
    (
        empty($_SESSION["client_connecte"]) ||
        ($_SESSION["client"]["role"] ?? "") !== "client"
    )
) {
    header("Location: connexion.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion - Artisanat d'Afrique</title>

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
            max-width: 450px;
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

<nav class="navbar">
    <div class="container">
        <a class="navbar-brand text-decoration-none" href="index.php">
            <i class="bi bi-shop"></i> Artisanat d'Afrique
        </a>

        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Boutique
        </a>
    </div>
</nav>

<main class="page-container">

    <div class="form-card">

        <div class="text-center mb-4">
            <i class="bi bi-person-circle"
               style="font-size: 48px; color: #8b4b27;"></i>

            <h2 class="form-title mt-3">Connexion</h2>

            <p class="text-muted">
                Connectez-vous à votre compte client.
            </p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="alert alert-<?= htmlspecialchars($typeMessage) ?>">
                <?= htmlspecialchars($message) ?>

                <?php if ($typeMessage === "success"): ?>
                    <div class="mt-3">
                        <a href="index.php" class="btn btn-success btn-sm">
                            Retour à la boutique
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($_SESSION["client_connecte"])): ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["csrf_connexion"]) ?>"
                >

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Adresse e-mail
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="exemple@email.com"
                        value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        Mot de passe
                    </label>

                    <input
                        type="password"
                        name="mot_de_passe"
                        class="form-control"
                        placeholder="Votre mot de passe"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-principal w-100">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Se connecter
                </button>

            </form>

        <?php else: ?>

            <div class="text-center">
                <p class="text-success fw-bold">
                    <i class="bi bi-check-circle-fill"></i>
                    Vous êtes connecté.
                </p>

                <a href="index.php" class="btn btn-principal w-100">
                    Retour à la boutique
                </a>
            </div>

        <?php endif; ?>

        <div class="text-center mt-4">
            <span class="text-muted">Vous n'avez pas encore de compte ?</span>
            <a href="inscription.php" class="lien">Inscrivez-vous</a>
        </div>

    </div>

</main>

<footer>
    <small>&copy; <?= date("Y") ?> Artisanat d'Afrique. Tous droits réservés.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
