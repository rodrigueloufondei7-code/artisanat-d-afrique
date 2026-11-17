
<?php
// =====================================================
// ESPACE PERSONNEL DU CLIENT
// =====================================================

session_start();

// Vérifier la connexion
if (
    empty($_SESSION["client_connecte"]) ||
    empty($_SESSION["client_token"]) ||
    ($_SESSION["client"]["role"] ?? "") !== "client"
) {
    header("Location: connexion.php");
    exit;
}

// Jeton CSRF pour la déconnexion
if (empty($_SESSION["csrf_deconnexion"])) {
    $_SESSION["csrf_deconnexion"] = bin2hex(random_bytes(32));
}

// Adresse de l'API du profil
$urlApi = "http://localhost/artisanat_afrique/backend/api/index.php?route=profile";

$client = $_SESSION["client"] ?? [];
$messageErreur = "";

// Récupérer les informations actualisées depuis l'API
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $urlApi,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $_SESSION["client_token"],
        "Accept: application/json"
    ],
    CURLOPT_TIMEOUT => 15
]);

$reponse = curl_exec($ch);
$codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($reponse === false) {
    $messageErreur = "Impossible de contacter le serveur. Les informations affichées peuvent ne pas être à jour.";

} else {
    $resultat = json_decode($reponse, true);

    // Si le jeton est expiré ou invalide
    if ($codeHttp === 401) {
        $_SESSION = [];
        session_destroy();

        header("Location: connexion.php?expire=1");
        exit;
    }

    if (!is_array($resultat) || $codeHttp >= 400) {
        $messageErreur = "Impossible de récupérer les informations du compte.";

    } else {
        // Accepter les différentes structures de réponse
        if (isset($resultat["utilisateur"]) && is_array($resultat["utilisateur"])) {
            $client = $resultat["utilisateur"];

        } elseif (isset($resultat["user"]) && is_array($resultat["user"])) {
            $client = $resultat["user"];

        } elseif (isset($resultat["data"]) && is_array($resultat["data"])) {
            $client = $resultat["data"];

        } elseif (isset($resultat["id"])) {
            $client = $resultat;
        }

        // Mettre à jour les informations conservées dans la session
        $_SESSION["client"] = $client;
    }
}

// Fonction de protection HTML
function e($valeur) {
    return htmlspecialchars((string)($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

$nom = $client["nom"] ?? "";
$prenom = $client["prenom"] ?? "";
$email = $client["email"] ?? "";
$telephone = $client["telephone"] ?? "Non renseigné";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mon compte - Artisanat d'Afrique</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
</head>

<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">

        <a class="navbar-brand text-decoration-none" href="index.php">
            <i class="bi bi-shop"></i> Artisanat d'Afrique
        </a>

        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Boutique
        </a>

    </div>
</nav>

<section class="account-header">
    <div class="container">
        <h1 class="fw-bold">Mon espace personnel</h1>
        <p class="mb-0">Bienvenue dans votre espace client Artisanat d'Afrique.</p>
    </div>
</section>

<main class="container py-5">

    <?php if ($messageErreur !== ""): ?>
        <div class="alert alert-warning">
            <?= e($messageErreur) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-5">

            <div class="account-card">

                <div class="profile-icon">
                    <i class="bi bi-person-fill"></i>
                </div>

                <h4 class="fw-bold">
                    <?= e(trim($prenom . " " . $nom)) ?: "Mon profil" ?>
                </h4>

                <p class="text-muted">
                    Informations de votre compte client
                </p>

                <hr>

                <div class="mb-3">
                    <div class="info-label">Nom</div>
                    <div class="info-value"><?= e($nom ?: "Non renseigné") ?></div>
                </div>

                <div class="mb-3">
                    <div class="info-label">Prénom</div>
                    <div class="info-value"><?= e($prenom ?: "Non renseigné") ?></div>
                </div>

                <div class="mb-3">
                    <div class="info-label">Adresse e-mail</div>
                    <div class="info-value"><?= e($email ?: "Non renseignée") ?></div>
                </div>

                <div class="mb-3">
                    <div class="info-label">Téléphone</div>
                    <div class="info-value"><?= e($telephone) ?></div>
                </div>

            </div>

        </div>

        <div class="col-lg-7">

            <div class="account-card">

                <h4 class="fw-bold mb-4">Mes activités</h4>

                <div class="row g-3">

                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <i class="bi bi-cart3 fs-2" style="color:#8b4b27;"></i>
                            <h5 class="mt-2 fw-bold">Mon panier</h5>
                            <p class="text-muted small">
                                Retrouvez les articles que vous souhaitez commander.
                            </p>
                            <a href="panier.php" class="btn btn-principal btn-sm">
                                Voir mon panier
                            </a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <i class="bi bi-receipt fs-2" style="color:#8b4b27;"></i>
                            <h5 class="mt-2 fw-bold">Mes commandes</h5>
                            <p class="text-muted small">
                                Consultez l'historique de vos commandes.
                            </p>
                            <a href="commandes.php" class="btn btn-principal btn-sm">
                                Mes commandes
                            </a>
                        </div>
                    </div>

                </div>

                <hr class="my-4">

                <h5 class="fw-bold mb-3">Sécurité du compte</h5>

                <form action="deconnexion.php" method="POST"
                      onsubmit="return confirm('Voulez-vous vraiment vous déconnecter ?');">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION["csrf_deconnexion"]) ?>"
                    >

                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-box-arrow-right"></i>
                        Se déconnecter
                    </button>

                </form>

            </div>

        </div>

    </div>

</main>

<footer class="text-center">
    <small>&copy; <?= date("Y") ?> Artisanat d'Afrique. Tous droits réservés.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
