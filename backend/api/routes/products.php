<?php

require_once __DIR__ . "/../../config/db.php";

$method = $_SERVER["REQUEST_METHOD"];
error_log("DIAGNOSTIC PRODUCTS : méthode reçue = " . $method);

/*
|--------------------------------------------------------------------------
| Réponse JSON
|--------------------------------------------------------------------------
*/
function repondre(array $contenu, int $code = 200): void
{
    http_response_code($code);

    echo json_encode(
        $contenu,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Récupération des données envoyées
|--------------------------------------------------------------------------
*/
function recupererDonnees(): array
{
    global $fichiers;

    $type = $_SERVER["CONTENT_TYPE"] ?? "";
    $fichiers = [];

    // Formulaire multipart envoyé en POST
    if (stripos($type, "multipart/form-data") !== false) {

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $fichiers = $_FILES;
            return $_POST;
        }

        // Formulaire multipart envoyé en PUT
        if (
            $_SERVER["REQUEST_METHOD"] === "PUT" &&
            function_exists("request_parse_body")
        ) {
            try {
                [$donnees, $fichiersRecus] = request_parse_body();

                $fichiers = is_array($fichiersRecus)
                    ? $fichiersRecus
                    : [];

                return is_array($donnees) ? $donnees : [];

            } catch (Throwable $e) {
                error_log(
                    "Erreur lecture formulaire PUT : " . $e->getMessage()
                );

                repondre([
                    "success" => false,
                    "message" => "Impossible de lire les données envoyées. Consulte les journaux PHP."
                ], 400);
            }
        }

        repondre([
            "success" => false,
            "message" => "Impossible de lire les fichiers envoyés."
        ], 400);
    }

    // Données JSON
    $contenu = file_get_contents("php://input");
    $donnees = json_decode($contenu, true);

    return is_array($donnees) ? $donnees : [];
}

/*
|--------------------------------------------------------------------------
| Validation et enregistrement d'une image
|--------------------------------------------------------------------------
*/
function enregistrerImage(): ?string
{
    global $fichiers;

    if (
        !isset($fichiers["image"]) ||
        $fichiers["image"]["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $fichier = $fichiers["image"];

    if ($fichier["error"] !== UPLOAD_ERR_OK) {
        repondre([
            "success" => false,
            "message" => "Erreur lors de l'envoi de l'image."
        ], 400);
    }

    if (
        !isset($fichier["tmp_name"]) ||
        !is_uploaded_file($fichier["tmp_name"])
    ) {
        repondre([
            "success" => false,
            "message" => "Fichier envoyé invalide."
        ], 400);
    }

    // Limite de 5 Mo
    if ($fichier["size"] > 5 * 1024 * 1024) {
        repondre([
            "success" => false,
            "message" => "L'image ne doit pas dépasser 5 Mo."
        ], 400);
    }

    // Vérifier le véritable type du fichier
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fichier["tmp_name"]);

    $formatsAutorises = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    if (!isset($formatsAutorises[$mime])) {
        repondre([
            "success" => false,
            "message" => "Format refusé. Utilise JPG, PNG ou WEBP."
        ], 400);
    }

    if (@getimagesize($fichier["tmp_name"]) === false) {
        repondre([
            "success" => false,
            "message" => "Le fichier envoyé n'est pas une image valide."
        ], 400);
    }

    // Dossier de destination
    $dossier = __DIR__ . "/../../uploads/";

    if (!is_dir($dossier)) {
        if (!mkdir($dossier, 0755, true)) {
            repondre([
                "success" => false,
                "message" => "Impossible de créer le dossier des images."
            ], 500);
        }
    }

    // Nom aléatoire
    $nomFichier = bin2hex(random_bytes(16))
        . "." . $formatsAutorises[$mime];

    $destination = $dossier . $nomFichier;

    if (!move_uploaded_file($fichier["tmp_name"], $destination)) {
        repondre([
            "success" => false,
            "message" => "Impossible d'enregistrer l'image."
        ], 500);
    }

    return "uploads/" . $nomFichier;
}

/*
|--------------------------------------------------------------------------
| Suppression sécurisée d'une ancienne image
|--------------------------------------------------------------------------
*/
function supprimerAncienneImage(?string $chemin): void
{
    if (!$chemin || !preg_match(
        '/^uploads\/[a-f0-9]{32}\.(jpg|png|webp)$/',
        $chemin
    )) {
        return;
    }

    $dossier = realpath(__DIR__ . "/../../uploads");

    if ($dossier === false) {
        return;
    }

    $fichier = realpath($dossier . DIRECTORY_SEPARATOR . basename($chemin));

    if (
        $fichier !== false &&
        dirname($fichier) === $dossier &&
        is_file($fichier)
    ) {
        unlink($fichier);
    }
}

/*
|--------------------------------------------------------------------------
| Vérification d'une catégorie
|--------------------------------------------------------------------------
*/
function categorieExiste(PDO $pdo, int $categorieId): bool
{
    $check = $pdo->prepare(
        "SELECT id FROM categories WHERE id = ?"
    );

    $check->execute([$categorieId]);

    return (bool) $check->fetch();
}

/*
|--------------------------------------------------------------------------
| CONSULTATION PUBLIQUE DES ARTICLES
|--------------------------------------------------------------------------
*/
if ($method === "GET") {

    $sql = "SELECT
                a.id,
                a.nom,
                a.description,
                a.prix,
                a.image_url,
                a.stock,
                a.categorie_id,
                c.nom AS categorie
            FROM articles a
            LEFT JOIN categories c ON a.categorie_id = c.id
            WHERE a.statut = 'actif'
            ORDER BY a.date_creation DESC";

    $stmt = $pdo->query($sql);
    $articles = $stmt->fetchAll();

    repondre([
        "success" => true,
        "total" => count($articles),
        "data" => $articles
    ]);
}

/*
|--------------------------------------------------------------------------
| PROTECTION DES OPÉRATIONS ADMINISTRATEUR
|--------------------------------------------------------------------------
*/
require_once __DIR__ . "/../middleware/admin_required.php";

$administrateur = exigerAdministrateur($pdo);

$fichiers = [];
$data = recupererDonnees();

error_log("DIAGNOSTIC PRODUCTS : données récupérées");
error_log("DIAGNOSTIC PRODUCTS : " . json_encode(array_keys($data)));

/*
|--------------------------------------------------------------------------
| AJOUTER UN ARTICLE
|--------------------------------------------------------------------------
*/
if ($method === "POST") {

    $nom = trim($data["nom"] ?? "");
    $description = trim($data["description"] ?? "");
    $prix = $data["prix"] ?? null;
    $stock = $data["stock"] ?? null;
    $categorieId = filter_var(
        $data["categorie_id"] ?? null,
        FILTER_VALIDATE_INT
    );
    $imageUrl = trim($data["image_url"] ?? "");

    if (
        $nom === "" ||
        !is_numeric($prix) ||
        !is_numeric($stock) ||
        (float) $prix < 0 ||
        (int) $stock < 0 ||
        $categorieId === false ||
        $categorieId < 1
    ) {
        repondre([
            "success" => false,
            "message" => "Informations de l'article invalides."
        ], 400);
    }

    if (!categorieExiste($pdo, $categorieId)) {
        repondre([
            "success" => false,
            "message" => "Catégorie introuvable."
        ], 400);
    }

    $imageImportee = enregistrerImage();

    if ($imageImportee !== null) {
        $imageUrl = $imageImportee;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO articles
            (categorie_id, nom, description, prix, image_url, stock, statut)
            VALUES (?, ?, ?, ?, ?, ?, 'actif')"
        );

        $stmt->execute([
            $categorieId,
            $nom,
            $description,
            $prix,
            $imageUrl,
            (int) $stock
        ]);

        $nouvelArticleId = (int) $pdo->lastInsertId();

        // Enregistrer le stock initial
        if ((int) $stock > 0) {
            $mouvement = $pdo->prepare(
                "INSERT INTO mouvements_stock
                (
                    article_id,
                    utilisateur_id,
                    type_mouvement,
                    quantite,
                    stock_avant,
                    stock_apres,
                    motif
                )
                VALUES (?, ?, 'entree', ?, 0, ?, ?)"
            );

            $mouvement->execute([
                $nouvelArticleId,
                $administrateur["id"],
                (int) $stock,
                (int) $stock,
                "Stock initial lors de la création de l'article"
            ]);
        }

        $pdo->commit();

        repondre([
            "success" => true,
            "message" => "Article ajouté avec succès.",
            "id" => $nouvelArticleId,
            "image_url" => $imageUrl
        ], 201);

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        repondre([
            "success" => false,
            "message" => "Erreur lors de l'enregistrement de l'article."
        ], 500);
    }
} // FIN DU BLOC POST

/*
|--------------------------------------------------------------------------
| MODIFIER UN ARTICLE
|--------------------------------------------------------------------------
*/
if ($method === "PUT") {

    error_log("DIAGNOSTIC PRODUCTS : bloc PUT exécuté");

    $id = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    error_log("DIAGNOSTIC PUT : ID reçu = " . var_export($id, true));

    if (!$id || $id < 1) {
        repondre([
            "success" => false,
            "message" => "Identifiant invalide."
        ], 400);
    }

    // Récupérer l'article existant
    $recherche = $pdo->prepare(
        "SELECT id, image_url, stock
         FROM articles
         WHERE id = ? AND statut = 'actif'"
    );

    $recherche->execute([$id]);
    $articleExistant = $recherche->fetch();

    error_log("DIAGNOSTIC PUT : recherche de l'article terminée");

    if (!$articleExistant) {
        repondre([
            "success" => false,
            "message" => "Article introuvable."
        ], 404);
    }

    $nom = trim($data["nom"] ?? "");
    $description = trim($data["description"] ?? "");
    $prix = $data["prix"] ?? null;
    $stock = $data["stock"] ?? null;
    $categorieId = filter_var(
        $data["categorie_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $nom === "" ||
        !is_numeric($prix) ||
        !is_numeric($stock) ||
        (float) $prix < 0 ||
        (int) $stock < 0 ||
        $categorieId === false ||
        $categorieId < 1
    ) {
        repondre([
            "success" => false,
            "message" => "Informations invalides."
        ], 400);
    }

    if (!categorieExiste($pdo, $categorieId)) {
        repondre([
            "success" => false,
            "message" => "Catégorie introuvable."
        ], 400);
    }

    // Par défaut, conserver l'ancienne photo
    $ancienneImage = $articleExistant["image_url"];
    $imageUrl = trim($data["image_url"] ?? $ancienneImage);

    // Si une nouvelle photo est envoyée, elle remplace l'ancienne
    $nouvelleImage = enregistrerImage();

    if ($nouvelleImage !== null) {
        $imageUrl = $nouvelleImage;
    }

    try {
        error_log("DIAGNOSTIC PUT : début de la transaction");

        $pdo->beginTransaction();

        $stockAvant = (int) $articleExistant["stock"];
        $stockApres = (int) $stock;

        // Modifier les informations de l'article
        $stmt = $pdo->prepare(
            "UPDATE articles
             SET categorie_id = ?,
                 nom = ?,
                 description = ?,
                 prix = ?,
                 image_url = ?,
                 stock = ?
             WHERE id = ? AND statut = 'actif'"
        );

        $stmt->execute([
            $categorieId,
            $nom,
            $description,
            $prix,
            $imageUrl,
            $stockApres,
            $id
        ]);

        // Enregistrer le mouvement uniquement si le stock change
        if ($stockAvant !== $stockApres) {

            if ($stockApres > $stockAvant) {
                $typeMouvement = "entree";
                $quantiteMouvement = $stockApres - $stockAvant;
            } else {
                $typeMouvement = "sortie";
                $quantiteMouvement = $stockAvant - $stockApres;
            }

            $mouvement = $pdo->prepare(
                "INSERT INTO mouvements_stock
                (
                    article_id,
                    utilisateur_id,
                    type_mouvement,
                    quantite,
                    stock_avant,
                    stock_apres,
                    motif
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $mouvement->execute([
                $id,
                $administrateur["id"],
                $typeMouvement,
                $quantiteMouvement,
                $stockAvant,
                $stockApres,
                "Ajustement du stock lors de la modification de l'article"
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Supprimer la nouvelle image si l'opération échoue
        if ($nouvelleImage !== null) {
            supprimerAncienneImage($nouvelleImage);
        }

        error_log($e->getMessage());

        repondre([
            "success" => false,
            "message" => "Erreur lors de la modification de l'article."
        ], 500);
    }

    // Supprimer l'ancienne photo uniquement après la mise à jour
    if (
        $nouvelleImage !== null &&
        $ancienneImage !== $nouvelleImage
    ) {
        supprimerAncienneImage($ancienneImage);
    }

    repondre([
        "success" => true,
        "message" => "Article modifié avec succès.",
        "image_url" => $imageUrl
    ]);
}

/*
|--------------------------------------------------------------------------
| DÉSACTIVER UN ARTICLE
|--------------------------------------------------------------------------
*/
if ($method === "DELETE") {

    $id = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        repondre([
            "success" => false,
            "message" => "Identifiant invalide."
        ], 400);
    }

    // Conserver l'article pour préserver l'historique des commandes
    $stmt = $pdo->prepare(
        "UPDATE articles
         SET statut = 'inactif'
         WHERE id = ? AND statut = 'actif'"
    );

    $stmt->execute([$id]);

    repondre([
        "success" => true,
        "message" => $stmt->rowCount() > 0
            ? "Article désactivé."
            : "Article introuvable ou déjà désactivé."
    ]);
}

/*
|--------------------------------------------------------------------------
| MÉTHODE NON AUTORISÉE
|--------------------------------------------------------------------------
*/
repondre([
    "success" => false,
    "message" => "Méthode non autorisée."
], 405);
