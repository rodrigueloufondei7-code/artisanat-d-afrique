<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

$route = $_GET["route"] ?? "";
$method = $_SERVER["REQUEST_METHOD"];

// État de l'API
if ($route === "health" && $method === "GET") {
    echo json_encode([
        "success" => true,
        "message" => "API Artisanat d'Afrique opérationnelle"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Authentification
if ($route === "auth" && $method === "POST") {
    require_once __DIR__ . "/routes/auth.php";
    exit;
}

// Profil protégé
if ($route === "profile" && $method === "GET") {
    require_once __DIR__ . "/../config/db.php";
    require_once __DIR__ . "/middleware/auth_required.php";

    $utilisateur = authentifierUtilisateur($pdo);

    echo json_encode([
        "success" => true,
        "message" => "Utilisateur authentifié.",
        "data" => $utilisateur
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Catalogue des articles
if ($route === "products") {
    require_once __DIR__ . "/routes/products.php";
    exit;
}

// Catégories
if ($route === "categories") {
    require_once __DIR__ . "/routes/categories.php";
    exit;
}
if ($route === "dashboard-stats" && $method === "GET") {
    require_once __DIR__ . "/routes/dashboard_stats.php";
    exit;
}

// Gestion des commandes
if ($route === "orders") {
    require_once __DIR__ . "/routes/orders.php";
    exit;
}
http_response_code(404);



echo json_encode([
    "success" => false,
    "message" => "Route API introuvable."
], JSON_UNESCAPED_UNICODE);