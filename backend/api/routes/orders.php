
<?php

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../middleware/admin_required.php";

// Vérifier que l'utilisateur connecté est administrateur.
$administrateur = exigerAdministrateur($pdo);

// Fonction pour envoyer une réponse JSON standardisée.
function repondreCommandes(
    bool $success,
    string $message,
    $data = null,
    int $code = 200
): void {
    http_response_code($code);

    $reponse = [
        "success" => $success,
        "message" => $message
    ];

    if ($data !== null) {
        $reponse["data"] = $data;
    }

    echo json_encode($reponse, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

// Liste des statuts autorisés par la base de données.
$statutsAutorises = [
    "en_attente",
    "confirmee",
    "en_preparation",
    "expediee",
    "livree",
    "annulee"
];

// --------------------------------------------------
// GET : récupérer toutes les commandes
// --------------------------------------------------

if ($method === "GET") {

    try {

        $sql = "
            SELECT
                c.id,
                c.utilisateur_id,
                c.numero_commande,
                c.nom_livraison,
                c.telephone_livraison,
                c.adresse_livraison,
                c.ville,
                c.montant_total,
                c.mode_paiement,
                c.statut,
                c.date_commande,
                u.nom AS nom_client,
                u.prenom AS prenom_client,
                u.email AS email_client
            FROM commandes c
            INNER JOIN utilisateurs u
                ON u.id = c.utilisateur_id
        ";

        $conditions = [];
        $parametres = [];

        // Filtrage par statut.
        if (isset($_GET["statut"]) && $_GET["statut"] !== "") {

            if (
                !is_string($_GET["statut"]) ||
                !in_array($_GET["statut"], $statutsAutorises, true)
            ) {
                repondreCommandes(
                    false,
                    "Statut de commande invalide.",
                    null,
                    400
                );
            }

            $conditions[] = "c.statut = :statut";
            $parametres[":statut"] = $_GET["statut"];
        }

        // Recherche par numéro, nom, prénom, email ou nom de livraison.
        if (isset($_GET["recherche"])) {

            if (!is_string($_GET["recherche"])) {
                repondreCommandes(
                    false,
                    "Recherche invalide.",
                    null,
                    400
                );
            }

            $recherche = trim($_GET["recherche"]);

            if ($recherche !== "") {

                if (strlen($recherche) > 100) {
                    repondreCommandes(
                        false,
                        "La recherche est trop longue.",
                        null,
                        400
                    );
                }

                $conditions[] = "(
                    c.numero_commande LIKE :recherche_numero
                    OR c.nom_livraison LIKE :recherche_livraison
                    OR u.nom LIKE :recherche_nom
                    OR u.prenom LIKE :recherche_prenom
                    OR u.email LIKE :recherche_email
                )";

                $valeurRecherche = "%" . $recherche . "%";

                // Chaque paramètre possède un nom différent.
                $parametres[":recherche_numero"] = $valeurRecherche;
                $parametres[":recherche_livraison"] = $valeurRecherche;
                $parametres[":recherche_nom"] = $valeurRecherche;
                $parametres[":recherche_prenom"] = $valeurRecherche;
                $parametres[":recherche_email"] = $valeurRecherche;
            }
        }

        // Ajouter les conditions à la requête.
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        // Les commandes les plus récentes apparaissent en premier.
        $sql .= " ORDER BY c.date_commande DESC, c.id DESC";

        $requete = $pdo->prepare($sql);
        $requete->execute($parametres);

        $commandes = $requete->fetchAll();

        // Préparer la récupération des articles de chaque commande.
        $requeteLignes = $pdo->prepare("
            SELECT
                id,
                commande_id,
                article_id,
                nom_article,
                quantite,
                prix_unitaire
            FROM ligne_commandes
            WHERE commande_id = :commande_id
            ORDER BY id ASC
        ");

        // Ajouter les articles à chaque commande.
        foreach ($commandes as &$commande) {

            $requeteLignes->execute([
                ":commande_id" => $commande["id"]
            ]);

            $commande["articles"] = $requeteLignes->fetchAll();
        }

        unset($commande);

        repondreCommandes(
            true,
            "Commandes récupérées avec succès.",
            [
                "total" => count($commandes),
                "commandes" => $commandes
            ]
        );

    } catch (PDOException $e) {

        error_log("Erreur récupération commandes : " . $e->getMessage());

        repondreCommandes(
            false,
            "Erreur lors de la récupération des commandes.",
            null,
            500
        );
    }
}

// --------------------------------------------------
// PUT : modifier le statut d'une commande
// --------------------------------------------------

if ($method === "PUT") {

    $contenu = file_get_contents("php://input");
    $donnees = json_decode($contenu, true);

    if (!is_array($donnees)) {
        repondreCommandes(
            false,
            "Données JSON invalides.",
            null,
            400
        );
    }

    // Vérifier l'identifiant de la commande.
    $idCommande = filter_var(
        $donnees["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$idCommande || $idCommande < 1) {
        repondreCommandes(
            false,
            "Identifiant de commande invalide.",
            null,
            400
        );
    }

    // Vérifier le nouveau statut.
    $nouveauStatut = $donnees["statut"] ?? null;

    if (
        !is_string($nouveauStatut) ||
        !in_array($nouveauStatut, $statutsAutorises, true)
    ) {
        repondreCommandes(
            false,
            "Ce statut n'est pas autorisé.",
            null,
            400
        );
    }

    try {

        // Vérifier que la commande existe.
        $verification = $pdo->prepare("
            SELECT id
            FROM commandes
            WHERE id = :id
            LIMIT 1
        ");

        $verification->execute([
            ":id" => $idCommande
        ]);

        if (!$verification->fetch()) {
            repondreCommandes(
                false,
                "Commande introuvable.",
                null,
                404
            );
        }

        // Modifier le statut.
        $miseAJour = $pdo->prepare("
            UPDATE commandes
            SET statut = :statut
            WHERE id = :id
        ");

        $miseAJour->execute([
            ":statut" => $nouveauStatut,
            ":id" => $idCommande
        ]);

        repondreCommandes(
            true,
            "Statut de la commande modifié avec succès.",
            [
                "id" => $idCommande,
                "statut" => $nouveauStatut
            ]
        );

    } catch (PDOException $e) {

        error_log("Erreur modification commande : " . $e->getMessage());

        repondreCommandes(
            false,
            "Erreur lors de la modification du statut.",
            null,
            500
        );
    }
}

// --------------------------------------------------
// Méthode HTTP non autorisée
// --------------------------------------------------

header("Allow: GET, PUT");

repondreCommandes(
    false,
    "Méthode HTTP non autorisée.",
    null,
    405
);
