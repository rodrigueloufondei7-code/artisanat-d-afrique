
<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

if (
    !isset($_SESSION["admin_token"]) ||
    !isset($_SESSION["admin_user"]) ||
    ($_SESSION["admin_user"]["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../../backend/config/db.php";

function e($valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    die("Identifiant de commande invalide.");
}

try {
    $requete = $pdo->prepare("
        SELECT
            c.*,
            u.nom,
            u.prenom,
            u.email,
            u.telephone
        FROM commandes c
        INNER JOIN utilisateurs u
            ON u.id = c.utilisateur_id
        WHERE c.id = :id
        LIMIT 1
    ");

    $requete->execute(["id" => $id]);
    $commande = $requete->fetch();

    if (!$commande) {
        http_response_code(404);
        die("Cette commande n'existe pas.");
    }

    $requeteLignes = $pdo->prepare("
        SELECT
            nom_article,
            quantite,
            prix_unitaire
        FROM ligne_commandes
        WHERE commande_id = :commande_id
    ");

    $requeteLignes->execute(["commande_id" => $id]);
    $articles = $requeteLignes->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die("Impossible de générer cette facture.");
}

$statuts = [
    "en_attente" => "En attente",
    "confirmee" => "Confirmée",
    "en_preparation" => "En préparation",
    "expediee" => "Expédiée",
    "livree" => "Livrée",
    "annulee" => "Annulée"
];

$statut = $statuts[$commande["statut"]] ?? $commande["statut"];

$date = date(
    "d/m/Y à H:i",
    strtotime($commande["date_commande"])
);

$devise = "FCFA";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Facture <?= e($commande["numero_commande"]) ?> - Artisanat d'Afrique
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f1f3f5;
            color: #222;
            font-family: Arial, sans-serif;
        }

        .facture {
            max-width: 850px;
            margin: auto;
            padding: 45px;
            background: white;
            box-shadow: 0 3px 15px rgba(0,0,0,.08);
        }

        .entete {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 2px solid #222;
        }

        .nom-boutique {
            font-size: 25px;
            font-weight: bold;
            color: #87552c;
        }

        .titre-facture {
            text-align: right;
        }

        .titre-facture h1 {
            margin: 0 0 10px;
            font-size: 30px;
        }

        .informations {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }

        .bloc h3 {
            margin-bottom: 10px;
            color: #87552c;
        }

        .bloc p {
            margin: 6px 0;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f5eee7;
        }

        .nombre {
            text-align: right;
            white-space: nowrap;
        }

        .total {
            margin-top: 25px;
            text-align: right;
            font-size: 22px;
            font-weight: bold;
            color: #87552c;
        }

        .details {
            margin-top: 30px;
            padding: 18px;
            background: #f8f8f8;
        }

        .pied {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            line-height: 1.6;
        }

        .actions {
            max-width: 850px;
            margin: 20px auto;
            display: flex;
            gap: 10px;
        }

        .bouton {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            background: #87552c;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .bouton-retour {
            background: #444;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .facture {
                padding: 20px;
            }

            .entete {
                flex-direction: column;
            }

            .titre-facture {
                text-align: left;
            }

            .informations {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .facture {
                max-width: none;
                padding: 15px;
                box-shadow: none;
            }

            .actions {
                display: none;
            }

            @page {
                size: A4;
                margin: 15mm;
            }
        }
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body>
<?php require __DIR__ . "/_navigation.php"; ?>


<div class="facture">

    <div class="entete">
        <div>
            <div class="nom-boutique">ARTISANAT D'AFRIQUE</div>
            <p>Commerce de produits artisanaux africains</p>
        </div>

        <div class="titre-facture">
            <h1>FACTURE</h1>
            <strong><?= e($commande["numero_commande"]) ?></strong>
            <p>Date : <?= e($date) ?></p>
        </div>
    </div>

    <div class="informations">

        <div class="bloc">
            <h3>Facturé à</h3>

            <p>
                <strong>
                    <?= e($commande["prenom"] . " " . $commande["nom"]) ?>
                </strong>
            </p>

            <p>Email : <?= e($commande["email"]) ?></p>
            <p>Téléphone : <?= e($commande["telephone"]) ?></p>
        </div>

        <div class="bloc">
            <h3>Adresse de livraison</h3>

            <p><?= e($commande["nom_livraison"]) ?></p>
            <p><?= e($commande["telephone_livraison"]) ?></p>
            <p><?= e($commande["adresse_livraison"]) ?></p>
            <p><?= e($commande["ville"]) ?></p>
        </div>

    </div>

    <h3>Détails de la commande</h3>

    <table>
        <thead>
            <tr>
                <th>Article</th>
                <th class="nombre">Quantité</th>
                <th class="nombre">Prix unitaire</th>
                <th class="nombre">Total</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($articles as $article): ?>

            <tr>
                <td><?= e($article["nom_article"]) ?></td>

                <td class="nombre">
                    <?= (int) $article["quantite"] ?>
                </td>

                <td class="nombre">
                    <?= number_format((float) $article["prix_unitaire"], 0, ",", " ") ?>
                    <?= $devise ?>
                </td>

                <td class="nombre">
                    <?= number_format(
                        (float) $article["prix_unitaire"] * (int) $article["quantite"],
                        0,
                        ",",
                        " "
                    ) ?>
                    <?= $devise ?>
                </td>
            </tr>

        <?php endforeach; ?>

        </tbody>
    </table>

    <div class="total">
        Montant total :
        <?= number_format((float) $commande["montant_total"], 0, ",", " ") ?>
        <?= $devise ?>
    </div>

    <div class="details">
        <p>
            <strong>Mode de paiement :</strong>
            <?= e($commande["mode_paiement"]) ?>
        </p>

        <p>
            <strong>Statut de la commande :</strong>
            <?= e($statut) ?>
        </p>
    </div>

    <div class="pied">
        <strong>Merci pour votre confiance !</strong>
        <p>Artisanat d'Afrique — Valorisons le savoir-faire africain.</p>
    </div>

</div>

<div class="actions">
    <button class="bouton" onclick="window.print()">
        Imprimer la facture
    </button>

    <a href="commandes.php" class="bouton bouton-retour">
        Retour aux commandes
    </a>
</div>

</body>
</html>
