<?php
// Navigation commune de l'espace administrateur.
$adminPage = basename($_SERVER["PHP_SELF"] ?? "");
$adminNom = trim((string)(($_SESSION["admin_user"]["prenom"] ?? "") . " " . ($_SESSION["admin_user"]["nom"] ?? "")));
$adminLiens = [
    "dashboard.php" => ["Tableau de bord", "bi-speedometer2"],
    "articles.php" => ["Articles", "bi-box-seam"],
    "commandes.php" => ["Commandes", "bi-receipt"],
    "historique_stock.php" => ["Stock", "bi-boxes"],
    "fournisseurs.php" => ["Fournisseurs", "bi-truck"],
    "approvisionnements.php" => ["Approvisionnements", "bi-arrow-down-square"],
    "depenses.php" => ["Dépenses", "bi-wallet2"],
    "finances.php" => ["Finances", "bi-graph-up-arrow"]
];
?>
<nav class="admin-navigation" aria-label="Navigation administrateur">
    <div class="admin-nav-inner">
        <a class="admin-brand" href="dashboard.php"><i class="bi bi-shop-window me-1"></i> Artisanat d'Afrique</a>
        <div class="admin-links">
            <?php foreach ($adminLiens as $fichier => [$libelle, $icone]): ?>
                <a href="<?= htmlspecialchars($fichier, ENT_QUOTES, "UTF-8") ?>" class="<?= $adminPage === $fichier ? "active" : "" ?>">
                    <i class="bi <?= htmlspecialchars($icone, ENT_QUOTES, "UTF-8") ?> me-1"></i><?= htmlspecialchars($libelle, ENT_QUOTES, "UTF-8") ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($adminNom !== ""): ?><span class="admin-user"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($adminNom, ENT_QUOTES, "UTF-8") ?></span><?php endif; ?>
    </div>
</nav>
