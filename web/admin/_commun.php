<?php
// Fichier commun : connexion PDO, protection administrateur, CSRF et messages.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../backend/config/db.php";

if (
    !isset($_SESSION["admin_token"]) ||
    !isset($_SESSION["admin_user"]) ||
    ($_SESSION["admin_user"]["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["csrf_appro"])) {
    $_SESSION["csrf_appro"] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function verifierCsrf(): void
{
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_appro"], (string)$_POST["csrf_token"])
    ) {
        http_response_code(400);
        exit("Requête invalide. Recharge la page puis réessaie.");
    }
}

function messageFlash(string $type, string $texte): void
{
    $_SESSION["flash_appro"] = ["type" => $type, "texte" => $texte];
}

function afficherFlash(): void
{
    if (!empty($_SESSION["flash_appro"])) {
        $flash = $_SESSION["flash_appro"];
        unset($_SESSION["flash_appro"]);
        echo '<div class="alert alert-' . e($flash["type"]) . ' alert-dismissible fade show">';
        echo e($flash["texte"]);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>';
        echo '</div>';
    }
}

function utilisateurAdminId(): ?int
{
    $id = filter_var($_SESSION["admin_user"]["id"] ?? null, FILTER_VALIDATE_INT);
    return ($id && $id > 0) ? $id : null;
}
