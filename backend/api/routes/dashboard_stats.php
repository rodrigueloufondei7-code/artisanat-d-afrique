
<?php

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../middleware/admin_required.php";

// Vérifier que l'utilisateur est administrateur
$admin = exigerAdministrateur($pdo);

try {

    // Nombre total de clients
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'"
    );
    $totalClients = (int) $stmt->fetchColumn();

    // Nombre d'articles actifs
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM articles WHERE statut = 'actif'"
    );
    $totalArticles = (int) $stmt->fetchColumn();

    // Nombre de catégories
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM categories"
    );
    $totalCategories = (int) $stmt->fetchColumn();

    // Nombre total de commandes
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM commandes"
    );
    $totalCommandes = (int) $stmt->fetchColumn();

    // Articles dont le stock est faible (5 ou moins)
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM articles
         WHERE statut = 'actif' AND stock <= 5"
    );
    $alertesStock = (int) $stmt->fetchColumn();
http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Statistiques récupérées.",
        "data" => [
            "clients" => $totalClients,
            "articles" => $totalArticles,
            "categories" => $totalCategories,
            "commandes" => $totalCommandes,
            "alertes_stock" => $alertesStock
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Erreur lors du chargement des statistiques."
    ], JSON_UNESCAPED_UNICODE);
}
