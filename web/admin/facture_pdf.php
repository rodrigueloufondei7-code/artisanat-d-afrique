
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
    empty($_SESSION["admin_token"]) ||
    ($_SESSION["admin_user"]["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../../backend/config/db.php";
require_once __DIR__ . "/../../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

function e($valeur): string
{
    return htmlspecialchars(
        (string)($valeur ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Identifiant de commande invalide.");
}

try {

    // Récupérer la commande et les informations du client.
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
        exit("Commande introuvable.");
    }

    // Récupérer les articles de la commande.
    $requeteLignes = $pdo->prepare("
        SELECT nom_article, quantite, prix_unitaire
        FROM ligne_commandes
        WHERE commande_id = :commande_id
    ");

    $requeteLignes->execute([
        "commande_id" => $id
    ]);

    $articles = $requeteLignes->fetchAll();

} catch (PDOException $erreur) {

    error_log($erreur->getMessage());

    http_response_code(500);
    exit("Erreur lors de la récupération de la commande.");
}

$statuts = [
    "en_attente" => "En attente",
    "confirmee" => "Confirmée",
    "en_preparation" => "En préparation",
    "expediee" => "Expédiée",
    "livree" => "Livrée",
    "annulee" => "Annulée"
];

$statut = $statuts[$commande["statut"]]
    ?? $commande["statut"];

$date = date(
    "d/m/Y à H:i",
    strtotime($commande["date_commande"])
);

$devise = "FCFA";

// Construire le document HTML de la facture.
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">

<style>

    @page {
        margin: 35px;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #252b26;
        font-size: 12px;
    }

    .entete {
        border-bottom: 3px solid #263b32;
        padding-bottom: 18px;
    }

    .boutique {
        color: #87552c;
        font-size: 23px;
        font-weight: bold;
    }

    .facture {
        text-align: right;
    }

    .facture h1 {
        font-size: 27px;
        margin-bottom: 5px;
    }

    .colonnes {
        width: 100%;
        margin-top: 30px;
    }

    .colonne {
        width: 50%;
        vertical-align: top;
    }

    h3 {
        color: #87552c;
        font-size: 13px;
    }

    .tableau {
        width: 100%;
        border-collapse: collapse;
        margin-top: 25px;
    }

    .tableau th {
        background: #263b32;
        color: white;
        padding: 11px 8px;
        text-align: left;
    }

    .tableau td {
        padding: 11px 8px;
        border-bottom: 1px solid #dddddd;
    }

    .droite {
        text-align: right;
    }

    .total {
        margin-top: 25px;
        text-align: right;
        font-size: 19px;
        font-weight: bold;
        color: #87552c;
    }

    .paiement {
        margin-top: 30px;
        padding: 15px;
        background: #f5f6f5;
    }

    .pied {
        margin-top: 60px;
        border-top: 1px solid #dddddd;
        padding-top: 15px;
        text-align: center;
        color: #666666;
    }

</style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body>

<div class="entete">
    <table width="100%">
        <tr>
            <td>
                <div class="boutique">ARTISANAT D\'AFRIQUE</div>
                <p>Commerce de produits artisanaux africains</p>
            </td>

            <td class="facture">
                <h1>FACTURE</h1>
                <strong>' . e($commande["numero_commande"]) . '</strong>
                <p>Date : ' . e($date) . '</p>
            </td>
        </tr>
    </table>
</div>

<table class="colonnes">
    <tr>
        <td class="colonne">
            <h3>INFORMATIONS CLIENT</h3>
            <strong>' .
                e($commande["prenom"] . " " . $commande["nom"]) .
            '</strong>
            <p>Email : ' . e($commande["email"]) . '</p>
            <p>Téléphone : ' . e($commande["telephone"]) . '</p>
        </td>

        <td class="colonne">
            <h3>ADRESSE DE LIVRAISON</h3>
            <strong>' . e($commande["nom_livraison"]) . '</strong>
            <p>' . e($commande["telephone_livraison"]) . '</p>
            <p>' . e($commande["adresse_livraison"]) . '</p>
            <p>' . e($commande["ville"]) . '</p>
        </td>
    </tr>
</table>

<h3>ARTICLES COMMANDÉS</h3>

<table class="tableau">
    <thead>
        <tr>
            <th>Article</th>
            <th class="droite">Quantité</th>
            <th class="droite">Prix unitaire</th>
            <th class="droite">Total</th>
        </tr>
    </thead>

    <tbody>';

foreach ($articles as $article) {

    $quantite = (int)$article["quantite"];
    $prix = (float)$article["prix_unitaire"];
    $totalLigne = $quantite * $prix;

    $html .= '
        <tr>
            <td>' . e($article["nom_article"]) . '</td>

            <td class="droite">' . $quantite . '</td>

            <td class="droite">' .
                number_format($prix, 0, ",", " ") . ' ' . $devise .
            '</td>

            <td class="droite">' .
                number_format($totalLigne, 0, ",", " ") . ' ' . $devise .
            '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div class="total">
    MONTANT TOTAL :
    ' . number_format(
        (float)$commande["montant_total"],
        0,
        ",",
        " "
    ) . ' ' . $devise . '
</div>

<div class="paiement">
    <strong>Mode de paiement :</strong>
    ' . e($commande["mode_paiement"]) . '

    <br><br>

    <strong>Statut :</strong>
    ' . e($statut) . '
</div>

<div class="pied">
    <strong>Merci pour votre confiance !</strong>
    <p>Artisanat d\'Afrique — Valorisons le savoir-faire africain.</p>
</div>

</body>
</html>
';

// Configuration de Dompdf.
$options = new Options();
$options->set("defaultFont", "DejaVu Sans");
$options->set("isRemoteEnabled", false);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html, "UTF-8");
$dompdf->setPaper("A4", "portrait");
$dompdf->render();

// Télécharger le fichier PDF.
$nomFichier = "facture_" .
    preg_replace(
        "/[^A-Za-z0-9_-]/",
        "_",
        $commande["numero_commande"]
    ) . ".pdf";

$dompdf->stream($nomFichier, [
    "Attachment" => true
]);

exit;
