<?php
require_once __DIR__ . "/_commun.php";

$erreur = "";
$fournisseurs = $pdo->query("SELECT id, nom FROM fournisseurs WHERE statut = 'actif' ORDER BY nom")->fetchAll();
$articles = $pdo->query("SELECT id, nom, stock, prix FROM articles WHERE statut = 'actif' ORDER BY nom")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifierCsrf();

    $fournisseurId = filter_var($_POST["fournisseur_id"] ?? null, FILTER_VALIDATE_INT);
    $observation = trim($_POST["observation"] ?? "");
    $articleIds = $_POST["article_id"] ?? [];
    $quantites = $_POST["quantite"] ?? [];
    $prixUnitaires = $_POST["prix_unitaire"] ?? [];

    try {
        if (!$fournisseurId || $fournisseurId < 1) {
            throw new RuntimeException("Choisis un fournisseur valide.");
        }
        if (!is_array($articleIds) || !is_array($quantites) || !is_array($prixUnitaires)) {
            throw new RuntimeException("Les lignes d'articles sont invalides.");
        }

        $lignes = [];
        foreach ($articleIds as $i => $articleIdBrut) {
            if ($articleIdBrut === "" || $articleIdBrut === null) {
                continue;
            }
            $articleId = filter_var($articleIdBrut, FILTER_VALIDATE_INT);
            $qte = filter_var($quantites[$i] ?? null, FILTER_VALIDATE_INT);
            $prix = filter_var($prixUnitaires[$i] ?? null, FILTER_VALIDATE_FLOAT);

            if (!$articleId || $articleId < 1 || !$qte || $qte < 1 || $prix === false || $prix < 0) {
                throw new RuntimeException("Vérifie l'article, la quantité et le prix de chaque ligne.");
            }
            $lignes[] = ["article_id" => $articleId, "quantite" => $qte, "prix" => (float)$prix];
        }

        if (!$lignes) {
            throw new RuntimeException("Ajoute au moins un article à l'approvisionnement.");
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM fournisseurs WHERE id = ? AND statut = 'actif' FOR UPDATE");
        $stmt->execute([$fournisseurId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException("Ce fournisseur n'existe pas ou est désactivé.");
        }

        $reference = "APP-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));
        $montantTotal = 0;
        foreach ($lignes as $ligne) {
            $montantTotal += $ligne["quantite"] * $ligne["prix"];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO approvisionnements
             (fournisseur_id, utilisateur_id, reference, montant_total, observation)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $fournisseurId,
            utilisateurAdminId(),
            $reference,
            $montantTotal,
            $observation ?: null
        ]);
        $approvisionnementId = (int)$pdo->lastInsertId();

        $selectArticle = $pdo->prepare(
            "SELECT id, nom, stock FROM articles WHERE id = ? AND statut = 'actif' FOR UPDATE"
        );
        $updateStock = $pdo->prepare("UPDATE articles SET stock = ? WHERE id = ?");
        $insertLigne = $pdo->prepare(
            "INSERT INTO lignes_approvisionnement
             (approvisionnement_id, article_id, quantite, prix_unitaire, sous_total)
             VALUES (?, ?, ?, ?, ?)"
        );
        $insertMouvement = $pdo->prepare(
            "INSERT INTO mouvements_stock
             (article_id, utilisateur_id, type_mouvement, quantite, stock_avant, stock_apres, motif)
             VALUES (?, ?, 'entree', ?, ?, ?, ?)"
        );

        foreach ($lignes as $ligne) {
            $selectArticle->execute([$ligne["article_id"]]);
            $article = $selectArticle->fetch();
            if (!$article) {
                throw new RuntimeException("Un article sélectionné n'existe plus ou est désactivé.");
            }

            $stockAvant = (int)$article["stock"];
            $stockApres = $stockAvant + $ligne["quantite"];
            if ($stockApres > 4294967295) {
                throw new RuntimeException("La quantité dépasse la capacité de stock autorisée.");
            }
            $sousTotal = $ligne["quantite"] * $ligne["prix"];

            $updateStock->execute([$stockApres, $ligne["article_id"]]);
            $insertLigne->execute([
                $approvisionnementId,
                $ligne["article_id"],
                $ligne["quantite"],
                $ligne["prix"],
                $sousTotal
            ]);
            $insertMouvement->execute([
                $ligne["article_id"],
                utilisateurAdminId(),
                $ligne["quantite"],
                $stockAvant,
                $stockApres,
                "Approvisionnement " . $reference
            ]);
        }

        $pdo->commit();
        messageFlash("success", "Approvisionnement " . $reference . " enregistré. Les stocks ont été mis à jour.");
        header("Location: approvisionnement_details.php?id=" . $approvisionnementId);
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erreur = $ex instanceof PDOException
            ? "Une erreur SQL est survenue. Aucun stock n'a été modifié. Vérifie la base de données."
            : $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouvel approvisionnement - Artisanat d'Afrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f5f6f8;font-family:Arial,sans-serif}.topbar{background:#263b32;color:#fff;padding:18px 0}
        .main-container{padding-top:32px;padding-bottom:50px}.content-card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 3px 15px rgba(0,0,0,.04)}
        .page-title{color:#263b32;font-weight:700}.line-row{border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:12px}
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . "/_navigation.php"; ?>

<header class="topbar"><div class="container d-flex justify-content-between align-items-center"><div><h4 class="mb-0">Artisanat d'Afrique</h4><small>Espace administrateur</small></div><a href="dashboard.php" class="btn btn-outline-light btn-sm">Tableau de bord</a></div></header>
<main class="container main-container">
    <div class="mb-4"><h2 class="page-title">Nouvel approvisionnement</h2><p class="text-muted mb-0">Enregistre les articles reçus d'un fournisseur.</p></div>
    <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>
    <?php if (!$fournisseurs): ?><div class="alert alert-warning">Aucun fournisseur actif. <a href="fournisseurs.php">Ajoute d'abord un fournisseur</a>.</div><?php endif; ?>
    <?php if (!$articles): ?><div class="alert alert-warning">Aucun article actif disponible pour l'approvisionnement.</div><?php endif; ?>
    <form method="post" id="form-appro">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_appro"]) ?>">
        <div class="content-card mb-4">
            <h5 class="mb-3">Informations générales</h5>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Fournisseur *</label>
                    <select name="fournisseur_id" class="form-select" required>
                        <option value="">Sélectionner un fournisseur</option>
                        <?php foreach ($fournisseurs as $f): ?><option value="<?= (int)$f["id"] ?>"><?= e($f["nom"]) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Observation</label><input name="observation" class="form-control" maxlength="2000" placeholder="Ex. Réception de la livraison du jour"></div>
            </div>
        </div>
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0">Articles reçus</h5>
                <button type="button" class="btn btn-outline-success btn-sm" id="ajouter-ligne">+ Ajouter un article</button>
            </div>
            <div id="lignes">
                <div class="line-row">
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-5"><label class="form-label">Article *</label>
                            <select name="article_id[]" class="form-select" required>
                                <option value="">Choisir un article</option>
                                <?php foreach ($articles as $a): ?><option value="<?= (int)$a["id"] ?>"><?= e($a["nom"]) ?> (stock actuel : <?= (int)$a["stock"] ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2"><label class="form-label">Quantité *</label><input type="number" name="quantite[]" min="1" step="1" class="form-control" required></div>
                        <div class="col-6 col-lg-3"><label class="form-label">Prix d'achat unitaire (FCFA)</label><input type="number" name="prix_unitaire[]" min="0" step="0.01" value="0" class="form-control" required></div>
                        <div class="col-lg-2"><button type="button" class="btn btn-outline-danger w-100 supprimer-ligne" disabled>Retirer</button></div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
                <a href="approvisionnements.php" class="btn btn-light">Annuler</a>
                <button class="btn btn-success" <?= (!$fournisseurs || !$articles) ? "disabled" : "" ?>>Enregistrer l'approvisionnement</button>
            </div>
        </div>
    </form>
</main>
<script>
const lignes = document.getElementById("lignes");
const modele = lignes.querySelector(".line-row");
function actualiserBoutons() {
    const rows = lignes.querySelectorAll(".line-row");
    rows.forEach(row => row.querySelector(".supprimer-ligne").disabled = rows.length === 1);
}
document.getElementById("ajouter-ligne").addEventListener("click", () => {
    const clone = modele.cloneNode(true);
    clone.querySelectorAll("select").forEach(el => el.selectedIndex = 0);
    clone.querySelectorAll("input").forEach(el => el.value = el.name === "prix_unitaire[]" ? "0" : "");
    lignes.appendChild(clone);
    actualiserBoutons();
});
lignes.addEventListener("click", event => {
    if (event.target.classList.contains("supprimer-ligne")) {
        event.target.closest(".line-row").remove();
        actualiserBoutons();
    }
});
actualiserBoutons();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
