<?php
require_once __DIR__ . "/_commun.php";

$recherche = trim($_GET["q"] ?? "");
$sql = "SELECT ap.id, ap.reference, ap.montant_total, ap.date_approvisionnement,
               f.nom AS fournisseur, u.nom AS utilisateur_nom, u.prenom AS utilisateur_prenom
        FROM approvisionnements ap
        INNER JOIN fournisseurs f ON f.id = ap.fournisseur_id
        LEFT JOIN utilisateurs u ON u.id = ap.utilisateur_id";
$params = [];
if ($recherche !== "") {
    $sql .= " WHERE ap.reference LIKE ? OR f.nom LIKE ?";
    $params = ["%" . $recherche . "%", "%" . $recherche . "%"];
}
$sql .= " ORDER BY ap.date_approvisionnement DESC, ap.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$approvisionnements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approvisionnements - Artisanat d'Afrique</title>
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
        <div><h2 class="page-title mb-1">Historique des approvisionnements</h2><p class="text-muted mb-0">Toutes les réceptions enregistrées.</p></div>
        <div class="d-flex gap-2"><a href="fournisseurs.php" class="btn btn-outline-success">Fournisseurs</a><a href="approvisionnement_ajouter.php" class="btn btn-success">+ Nouvel approvisionnement</a></div>
    </div>
    <?php afficherFlash(); ?>
    <div class="content-card">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-6"><input name="q" class="form-control" value="<?= e($recherche) ?>" placeholder="Rechercher par référence ou fournisseur"></div>
            <div class="col-auto"><button class="btn btn-outline-success">Rechercher</button><a href="approvisionnements.php" class="btn btn-light">Effacer</a></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light"><tr><th>Référence</th><th>Fournisseur</th><th>Date</th><th>Montant total</th><th>Enregistré par</th><th>Détails</th></tr></thead>
                <tbody>
                <?php foreach ($approvisionnements as $ap): ?>
                    <tr>
                        <td><strong><?= e($ap["reference"]) ?></strong></td>
                        <td><?= e($ap["fournisseur"]) ?></td>
                        <td><?= e(date("d/m/Y H:i", strtotime($ap["date_approvisionnement"]))) ?></td>
                        <td><?= number_format((float)$ap["montant_total"], 0, ",", " ") ?> FCFA</td>
                        <td><?= e(trim(($ap["utilisateur_prenom"] ?? "") . " " . ($ap["utilisateur_nom"] ?? "")) ?: "Non renseigné") ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="approvisionnement_details.php?id=<?= (int)$ap["id"] ?>">Consulter</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$approvisionnements): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucun approvisionnement trouvé.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted small"><?= count($approvisionnements) ?> résultat(s)</div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
