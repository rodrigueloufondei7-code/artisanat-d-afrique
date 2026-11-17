
<?php
// =====================================================
// DÉCONNEXION CLIENT
// =====================================================

session_start();

// Vérifier que la demande vient bien du formulaire
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: mon_compte.php");
    exit;
}

if (
    empty($_SESSION["csrf_deconnexion"]) ||
    empty($_POST["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_deconnexion"], $_POST["csrf_token"])
) {
    http_response_code(403);
    exit("Demande de déconnexion invalide.");
}

// Vider les données de session
$_SESSION = [];

// Supprimer le cookie de session du navigateur
if (ini_get("session.use_cookies")) {
    $parametres = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 42000,
            "path" => $parametres["path"],
            "domain" => $parametres["domain"],
            "secure" => $parametres["secure"],
            "httponly" => $parametres["httponly"],
            "samesite" => $parametres["samesite"] ?? "Lax"
        ]
    );
}

// Détruire la session
session_destroy();

// Retour à la boutique
header("Location: index.php?deconnexion=success");
exit;
