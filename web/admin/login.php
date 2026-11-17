
<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Configuration du cookie de session.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Vérifier si l'administrateur est déjà connecté.
if (
    !empty($_SESSION["admin_token"]) &&
    ($_SESSION["admin_user"]["role"] ?? "") === "admin"
) {
    header("Location: dashboard.php");
    exit;
}

// Créer le jeton CSRF.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$erreur = "";
$emailSaisi = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $emailSaisi = trim($_POST["email"] ?? "");

    // Vérification CSRF.
    if (
        empty($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $erreur = "Requête invalide. Actualise la page.";

    } else {

        $motDePasse = $_POST["mot_de_passe"] ?? "";

        if ($emailSaisi === "" || $motDePasse === "") {

            $erreur = "Veuillez remplir tous les champs.";

        } elseif (!filter_var($emailSaisi, FILTER_VALIDATE_EMAIL)) {

            $erreur = "Adresse e-mail invalide.";

        } else {

            $donnees = json_encode([
                "email" => $emailSaisi,
                "mot_de_passe" => $motDePasse
            ]);

            $url = "http://localhost/artisanat_afrique/backend/api/index.php?route=auth&action=login&role=admin";

            if (!function_exists("curl_init")) {

                $erreur = "L'extension PHP cURL n'est pas activée.";

            } else {

                $ch = curl_init($url);

                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $donnees,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "Accept: application/json"
                    ],
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 15
                ]);

                $reponse = curl_exec($ch);
                $codeHTTP = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $erreurCurl = curl_error($ch);

                if ($reponse === false) {

                    error_log("Erreur API : " . $erreurCurl);
                    $erreur = "Impossible de contacter le serveur.";

                } else {

                    $resultat = json_decode($reponse, true);

                    if (!is_array($resultat)) {

                        $erreur = "Réponse invalide du serveur.";

                    } elseif (
                        $codeHTTP === 200 &&
                        ($resultat["success"] ?? false) === true &&
                        ($resultat["data"]["utilisateur"]["role"] ?? "") === "admin" &&
                        !empty($resultat["data"]["token"])
                    ) {

                        // Enregistrer les informations administrateur.
                        $_SESSION["admin_token"] = $resultat["data"]["token"];

                        $_SESSION["admin_user"] = $resultat["data"]["utilisateur"];

                        // Renouveler le jeton CSRF.
                        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                        // Enregistrer la session.
                        session_write_close();

                        // Rediriger vers le tableau de bord.
                        header("Location: dashboard.php");
                        exit;

                    } else {

                        $erreur = $resultat["message"]
                            ?? "Identifiants incorrects ou accès refusé.";
                    }
                }

                curl_close($ch);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion administrateur | Artisanat d'Afrique</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin.css">

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class="login-body">

<div class="login-container">

    <div class="login-brand">
        <div class="brand-icon">A</div>
        <h1>Artisanat d'Afrique</h1>
        <p>Administration de la boutique</p>
    </div>

    <div class="login-card">

        <h2>Bienvenue</h2>

        <p class="login-description">
            Connectez-vous à votre espace administrateur.
        </p>

        <?php if ($erreur !== ""): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($erreur, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
            >

            <div class="mb-3">
                <label class="form-label">Adresse e-mail</label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="admin@exemple.com"
                    value="<?= htmlspecialchars($emailSaisi, ENT_QUOTES, "UTF-8") ?>"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="mb-4">
                <label class="form-label">Mot de passe</label>

                <input
                    type="password"
                    name="mot_de_passe"
                    class="form-control"
                    placeholder="Votre mot de passe"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn btn-admin w-100">
                Se connecter
            </button>

        </form>

    </div>

    <p class="login-footer">
        Artisanat d'Afrique &copy; <?= date("Y") ?>
    </p>

</div>

</body>
</html>
