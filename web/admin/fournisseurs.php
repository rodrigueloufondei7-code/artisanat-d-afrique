<?php
require_once __DIR__ . "/_commun.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifierCsrf();
    $action = $_POST["action"] ?? "";

    try {
        if ($action === "enregistrer") {
            $id = filter_var($_POST["id"] ?? 0, FILTER_VALIDATE_INT);
            $nom = trim($_POST["nom"] ?? "");
            $telephone = trim($_POST["telephone"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $adresse = trim($_POST["adresse"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if ($nom === "" || mb_strlen($nom) > 150) {
                throw new RuntimeException("Le nom du fournisseur est obligatoire (150 caractères maximum).");
            }
            if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException("L'adresse e-mail n'est pas valide.");
            }

            if ($id) {
                $stmt = $pdo->prepare(
                    "UPDATE fournisseurs
                     SET nom = ?, telephone = ?, email = ?, adresse = ?, description = ?
                     WHERE id = ?"
                );
                $stmt->execute([
                    $nom, $telephone ?: null, $email ?: null,
                    $adresse ?: null, $description ?: null, $id
                ]);
                messageFlash("success", "Fournisseur modifié avec succès.");
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO fournisseurs (nom, telephone, email, adresse, description)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $nom, $telephone ?: null, $email ?: null,
                    $adresse ?: null, $description ?: null
                ]);
                messageFlash("success", "Fournisseur ajouté avec succès.");
            }
        } elseif ($action === "statut") {
            $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
            $statut = ($_POST["statut"] ?? "") === "inactif" ? "inactif" : "actif";
            if (!$id || $id < 1) {
                throw new RuntimeException("Identifiant de fournisseur invalide.");
            }
            $stmt = $pdo->prepare("UPDATE fournisseurs SET statut = ? WHERE id = ?");
            $stmt->execute([$statut, $id]);
            messageFlash("success", "Statut du fournisseur mis à jour.");
        } else {
            throw new RuntimeException("Action inconnue.");
        }
    } catch (Throwable $ex) {
        messageFlash("danger", $ex instanceof PDOException
            ? "Une erreur SQL est survenue. Vérifie les informations saisies."
            : $ex->getMessage());
    }

    header("Location: fournisseurs.php");
    exit;
}

$fournisseurAModifier = null;
$idEdition = filter_input(INPUT_GET, "modifier", FILTER_VALIDATE_INT);
if ($idEdition) {
    $stmt = $pdo->prepare("SELECT * FROM fournisseurs WHERE id = ?");
    $stmt->execute([$idEdition]);
    $fournisseurAModifier = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM fournisseurs ORDER BY date_creation DESC, id DESC");
$fournisseurs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fournisseurs - Artisanat d'Afrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f5f6f8;font-family:Arial,sans-serif}
        .topbar{background:#263b32;color:#fff;padding:18px 0}
        .main-container{padding-top:32px;padding-bottom:50px}
        .content-card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 3px 15px rgba(0,0,0,.04)}
        .page-title{color:#263b32;font-weight:700}
        .table td,.table th{vertical-align:middle;padding:12px 10px}
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . "/_navigation.php"; ?>

<header class="topbar">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div><h4 class="mb-0">Artisanat d'Afrique</h4><small>Espace administrateur</small></div>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Tableau de bord</a>
    </div>
</header>
<main class="container main-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div><h2 class="page-title mb-1">Gestion des fournisseurs</h2><p class="text-muted mb-0">Enregistre et gère tes partenaires.</p></div>
        <div class="d-flex gap-2">
            <a href="approvisionnements.php" class="btn btn-outline-success">Approvisionnements</a>
            <a href="approvisionnement_ajouter.php" class="btn btn-success">Nouvel approvisionnement</a>
        </div>
    </div>
    <?php afficherFlash(); ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="content-card">
                <h5 class="mb-3"><?= $fournisseurAModifier ? "Modifier le fournisseur" : "Ajouter un fournisseur" ?></h5>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_appro"]) ?>">
                    <input type="hidden" name="action" value="enregistrer">
                    <input type="hidden" name="id" value="<?= e($fournisseurAModifier["id"] ?? "") ?>">
                    <div class="mb-3">
                        <label class="form-label">Nom du fournisseur *</label>
                        <input class="form-control" name="nom" maxlength="150" required value="<?= e($fournisseurAModifier["nom"] ?? "") ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone</label>
                        <input class="form-control" name="telephone" maxlength="30" value="<?= e($fournisseurAModifier["telephone"] ?? "") ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input class="form-control" type="email" name="email" maxlength="190" value="<?= e($fournisseurAModifier["email"] ?? "") ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adresse</label>
                        <textarea class="form-control" name="adresse" rows="2"><?= e($fournisseurAModifier["adresse"] ?? "") ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description / Notes</label>
                        <textarea class="form-control" name="description" rows="2"><?= e($fournisseurAModifier["description"] ?? "") ?></textarea>
                    </div>
                    <button class="btn btn-success w-100">Enregistrer</button>
                    <?php if ($fournisseurAModifier): ?>
                        <a href="fournisseurs.php" class="btn btn-light w-100 mt-2">Annuler</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="content-card">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="mb-0">Liste des fournisseurs</h5>
                    <span class="badge text-bg-success"><?= count($fournisseurs) ?> fournisseur(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light"><tr><th>Nom</th><th>Contact</th><th>Statut</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($fournisseurs as $f): ?>
                            <tr>
                                <td><strong><?= e($f["nom"]) ?></strong><div class="small text-muted"><?= e($f["adresse"]) ?></div></td>
                                <td><?= e($f["telephone"]) ?><div class="small text-muted"><?= e($f["email"]) ?></div></td>
                                <td><span class="badge text-bg-<?= $f["statut"] === "actif" ? "success" : "secondary" ?>"><?= e($f["statut"]) ?></span></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="fournisseurs.php?modifier=<?= (int)$f["id"] ?>">Modifier</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_appro"]) ?>">
                                            <input type="hidden" name="action" value="statut">
                                            <input type="hidden" name="id" value="<?= (int)$f["id"] ?>">
                                            <input type="hidden" name="statut" value="<?= $f["statut"] === "actif" ? "inactif" : "actif" ?>">
                                            <button class="btn btn-sm btn-outline-<?= $f["statut"] === "actif" ? "danger" : "success" ?>"><?= $f["statut"] === "actif" ? "Désactiver" : "Activer" ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$fournisseurs): ?><tr><td colspan="4" class="text-center text-muted py-4">Aucun fournisseur enregistré.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
