<?php

require_once __DIR__ . "/../../config/db.php";

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {

    $stmt = $pdo->query(
        "SELECT id, nom, description, image_url
         FROM categories
         ORDER BY nom ASC"
    );

    $categories = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "total" => count($categories),
        "data" => $categories
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

if ($method === "POST") {

    require_once __DIR__ . "/../middleware/admin_required.php";
    exigerAdministrateur($pdo);

    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Données JSON invalides."]);
        exit;
    }

    $nom = trim($data["nom"] ?? "");
    $description = trim($data["description"] ?? "");
    $imageUrl = trim($data["image_url"] ?? "");

    if ($nom === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Le nom est obligatoire."]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO categories (nom, description, image_url)
         VALUES (:nom, :description, :image_url)"
    );

    $stmt->execute([
        "nom" => $nom,
        "description" => $description,
        "image_url" => $imageUrl
    ]);

    http_response_code(201);

    echo json_encode([
        "success" => true,
        "message" => "Catégorie créée avec succès.",
        "id" => (int) $pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Méthode non autorisée."
], JSON_UNESCAPED_UNICODE);