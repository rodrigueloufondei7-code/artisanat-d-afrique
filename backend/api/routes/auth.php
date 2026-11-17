<?php

require_once __DIR__ . "/../../config/db.php";

header("Content-Type: application/json; charset=UTF-8");

function repondre($success, $message, $data = null, $code = 200)
{
    http_response_code($code);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

// Lire les données JSON envoyées par le client
$donnees = json_decode(file_get_contents("php://input"), true);

if (!is_array($donnees)) {
    repondre(false, "Données JSON invalides.", null, 400);
}

$action = $_GET["action"] ?? "";

/*
|--------------------------------------------------------------------------
| INSCRIPTION
|--------------------------------------------------------------------------
*/
if ($action === "register") {

    $nom = trim($donnees["nom"] ?? "");
    $prenom = trim($donnees["prenom"] ?? "");
    $email = strtolower(trim($donnees["email"] ?? ""));
    $telephone = trim($donnees["telephone"] ?? "");
    $motDePasse = $donnees["mot_de_passe"] ?? "";

    if ($nom === "" || $prenom === "" || $email === "" || $motDePasse === "") {
        repondre(false, "Veuillez remplir tous les champs obligatoires.", null, 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        repondre(false, "Adresse e-mail invalide.", null, 400);
    }

    if (strlen($motDePasse) < 8) {
        repondre(false, "Le mot de passe doit contenir au moins 8 caractères.", null, 400);
    }

    // Vérifier si l'adresse e-mail existe déjà
    $verification = $pdo->prepare(
        "SELECT id FROM utilisateurs WHERE email = :email LIMIT 1"
    );

    $verification->execute(["email" => $email]);

    if ($verification->fetch()) {
        repondre(false, "Cette adresse e-mail est déjà utilisée.", null, 409);
    }

    // Hacher le mot de passe
    $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);

    // Le rôle est toujours client lors d'une inscription publique
    $insertion = $pdo->prepare(
        "INSERT INTO utilisateurs
        (nom, prenom, email, telephone, mot_de_passe, role)
        VALUES
        (:nom, :prenom, :email, :telephone, :mot_de_passe, 'client')"
    );

    $insertion->execute([
        "nom" => $nom,
        "prenom" => $prenom,
        "email" => $email,
        "telephone" => $telephone,
        "mot_de_passe" => $motDePasseHash
    ]);

    $utilisateurId = (int) $pdo->lastInsertId();

    repondre(true, "Compte client créé avec succès.", [
        "id" => $utilisateurId,
        "nom" => $nom,
        "prenom" => $prenom,
        "email" => $email,
        "role" => "client"
    ], 201);
}

/*
|--------------------------------------------------------------------------
| CONNEXION
|--------------------------------------------------------------------------
*/
if ($action === "login") {

    $roleAttendu = $_GET["role"] ?? "";

    if (!in_array($roleAttendu, ["admin", "client"], true)) {
        repondre(false, "Espace de connexion invalide.", null, 400);
    }

    $email = strtolower(trim($donnees["email"] ?? ""));
    $motDePasse = $donnees["mot_de_passe"] ?? "";

    if ($email === "" || $motDePasse === "") {
        repondre(false, "Veuillez saisir votre e-mail et votre mot de passe.", null, 400);
    }

    $requete = $pdo->prepare(
        "SELECT id, nom, prenom, email, telephone, mot_de_passe, role
         FROM utilisateurs
         WHERE email = :email
         LIMIT 1"
    );

    $requete->execute(["email" => $email]);
    $utilisateur = $requete->fetch();

    if (!$utilisateur || !password_verify($motDePasse, $utilisateur["mot_de_passe"])) {
        repondre(false, "Adresse e-mail ou mot de passe incorrect.", null, 401);
    }

    if ($utilisateur["role"] !== $roleAttendu) {
        repondre(false, "Ce compte n'est pas autorisé à accéder à cet espace.", null, 403);
    }

    // Créer un jeton aléatoire
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash("sha256", $token);

    // Le jeton expire après 30 jours
    $expiration = date("Y-m-d H:i:s", time() + (30 * 24 * 60 * 60));

    $enregistrement = $pdo->prepare(
        "INSERT INTO api_tokens
        (utilisateur_id, token_hash, date_expiration)
        VALUES
        (:utilisateur_id, :token_hash, :date_expiration)"
    );

    $enregistrement->execute([
        "utilisateur_id" => $utilisateur["id"],
        "token_hash" => $tokenHash,
        "date_expiration" => $expiration
    ]);

    unset($utilisateur["mot_de_passe"]);

    repondre(true, "Connexion réussie.", [
        "utilisateur" => $utilisateur,
        "token" => $token,
        "expiration" => $expiration
    ]);
}

repondre(false, "Action d'authentification inconnue.", null, 404);