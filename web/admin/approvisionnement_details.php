<?php
require_once __DIR__ . "/_commun.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit("Identifiant d'approvisionnement invalide.");
}

$stmt = $pdo->prepare(
    "SELECT ap.*, f.nom AS fournisseur_nom, f.telephone AS fournisseur_telephone,
            f.email AS fournisseur_email, f.adresse AS fournisseur_adresse,
            u.nom AS utilisateur_nom, u.prenom AS utilisateur_prenom
     FROM approvisionnements ap
     INNER JOIN fournisseurs f ON f.id = ap.fournisseur_id
     LEFT JOIN utilisateurs u ON u.id = ap.utilisateur_id
     WHERE ap.id = ?"
);
$stmt->execute([$id]);
$appro = $stmt->fetch();
if (!$appro) {
    http_response_code(404);
    exit("Approvisionnement introuvable.");
}

$stmt = $pdo->prepare(
    "SELECT la.quantite, la.prix_unitaire, la.sous_total, a.nom AS article_nom
     FROM lignes_approvisionnement la
     INNER JOIN articles a ON a.id = la.article_id
     WHERE la.approvisionnement_id = ?
     ORDER BY la.id"
);
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Détail approvisionnement - Artisanat d'Afrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f5f6f8;font-family:Arial,sans-serif}.topbar{background:#263b32;color:#fff;padding:18px 0}
        .main-container{padding-top:32px;padding-bottom:50px}.content-card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 3px 15px rgba(0,0,0,.04)}
        .page-title{color:#263b32;font-weight:700}.table td,.table th{vertical-align:middle;padding:13px 10px}
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . "/_navigation.php"; ?>

<header class="topbar"><div class="container d-flex justify-content-between align-items-center"><div><h4 class="mb-0">Artisanat d'Afrique</h4><small>Espace administrateur</small></div><a href="dashboard.php" class="btn btn-outline-light btn-sm">Tableau de bord</a></div></header>
<main class="container main-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div><h2 class="page-title mb-1">Détail de l'approvisionnement</h2><p class="text-muted mb-0"><?= e($appro["reference"]) ?></p></div>
        <a href="approvisionnements.php" class="btn btn-outline-success">Retour à l'historique</a>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-6"><div class="content-card h-100"><h5>Fournisseur</h5>
            <p class="mb-1"><strong><?= e($appro["fournisseur_nom"]) ?></strong></p>
            <p class="mb-1"><?= e($appro["fournisseur_telephone"]) ?></p>
            <p class="mb-1"><?= e($appro["fournisseur_email"]) ?></p>
            <p class="mb-0 text-muted"><?= e($appro["fournisseur_adresse"]) ?></p>
        </div></div>
        <div class="col-md-6"><div class="content-card h-100"><h5>Informations</h5>
            <p class="mb-1">Date : <strong><?= e(date("d/m/Y H:i", strtotime($appro["date_approvisionnement"]))) ?></strong></p>
            <p class="mb-1">Enregistré par : <strong><?= e(trim(($appro["utilisateur_prenom"] ?? "") . " " . ($appro["utilisateur_nom"] ?? "")) ?: "Non renseigné") ?></strong></p>
            <p class="mb-1">Montant total : <strong><?= number_format((float)$appro["montant_total"], 0, ",", " ") ?> FCFA</strong></p>
            <p class="mb-0 text-muted"><?= e($appro["observation"]) ?></p>
        </div></div>
    </div>
    <div class="content-card">
        <h5 class="mb-3">Articles réceptionnés</h5>
        <div class="table-responsive"><table class="table">
            <thead class="table-light"><tr><th>Article</th><th>Quantité reçue</th><th>Prix unitaire</th><th>Sous-total</th></tr></thead>
            <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <tr><td><?= e($ligne["article_nom"]) ?></td><td><?= (int)$ligne["quantite"] ?></td>
                    <td><?= number_format((float)$ligne["prix_unitaire"], 0, ",", " ") ?> FCFA</td>
                    <td><?= number_format((float)$ligne["sous_total"], 0, ",", " ") ?> FCFA</td></tr>
            <?php endforeach; ?>
            <?php if (!$lignes): ?><tr><td colspan="4" class="text-center text-muted">Aucune ligne enregistrée.</td></tr><?php endif; ?>
            </tbody>
            <tfoot><tr><th colspan="3" class="text-end">Total</th><th><?= number_format((float)$appro["montant_total"], 0, ",", " ") ?> FCFA</th></tr></tfoot>
        </table></div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
