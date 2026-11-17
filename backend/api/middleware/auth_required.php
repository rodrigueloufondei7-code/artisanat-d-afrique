<?php

function authentifierUtilisateur(PDO $pdo): array
{
    // Récupérer l'en-tête Authorization
    $headers = function_exists("getallheaders") ? getallheaders() : [];

    $authorization = $headers["Authorization"]
        ?? $headers["authorization"]
        ?? $_SERVER["HTTP_AUTHORIZATION"]
        ?? "";

    // Vérifier le format Bearer
    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Authentification requise."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $token = $matches[1];
    $tokenHash = hash("sha256", $token);

    // Rechercher un jeton valide et non expiré
    $requete = $pdo->prepare(
        "SELECT
            u.id,
            u.nom,
            u.prenom,
            u.email,
            u.telephone,
            u.role
         FROM api_tokens t
         INNER JOIN utilisateurs u
            ON u.id = t.utilisateur_id
         WHERE t.token_hash = :token_hash
           AND t.date_expiration > NOW()
         LIMIT 1"
    );

    $requete->execute([
        "token_hash" => $tokenHash
    ]);

    $utilisateur = $requete->fetch();

    if (!$utilisateur) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Jeton invalide ou expiré."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    return $utilisateur;
}