<?php
/**
 * Configuration PDO de la base de données.
 * En local XAMPP, les valeurs par défaut fonctionnent avec MySQL root sans mot de passe.
 * En hébergement, définissez DB_HOST, DB_NAME, DB_USER et DB_PASS dans l'environnement.
 */
$host = getenv("DB_HOST") ?: "localhost";
$dbname = getenv("DB_NAME") ?: "artisanat_afrique";
$username = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASS");
if ($password === false) {
    $password = "";
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Artisanat d'Afrique - erreur de connexion BDD : " . $e->getMessage());
    http_response_code(500);
    die("Erreur de connexion à la base de données.");
}
