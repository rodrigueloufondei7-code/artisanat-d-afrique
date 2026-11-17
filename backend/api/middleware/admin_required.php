<?php

require_once __DIR__ . "/auth_required.php";

function exigerAdministrateur(PDO $pdo): array
{
    $utilisateur = authentifierUtilisateur($pdo);

    if ($utilisateur["role"] !== "admin") {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Accès réservé aux administrateurs."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    return $utilisateur;
}