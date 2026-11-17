<?php
// Page d'historique des mouvements de stock - Artisanat d'Afrique

session_start();

// Vérifier que l'administrateur est connecté.
if (
    empty($_SESSION['admin_token']) ||
    ($_SESSION['admin_user']['role'] ?? '') !== 'admin'
) {
    header('Location: login.php');
    exit;
}

// Connexion PDO existante du projet.
require_once __DIR__ . '/../../backend/config/db.php';

// Protection contre les injections HTML.
function e($valeur): string
{
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

$erreur = '';
$mouvements = [];
$totalMouvements = 0;

// Filtres envoyés en GET.
$recherche = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$typesAutorises = ['entree', 'sortie', 'correction'];
if (!in_array($type, $typesAutorises, true)) {
    $type = '';
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page !== false && $page !== null && $page > 0) ? $page : 1;
$parPage = 15;
$decalage = ($page - 1) * $parPage;

try {
    // Construire les conditions SQL uniquement à partir de filtres validés.
    $conditions = [];
    $parametres = [];

    if ($recherche !== '') {
        $conditions[] = '(a.nom LIKE :recherche_nom OR m.motif LIKE :recherche_motif)';
        $motifRecherche = '%' . $recherche . '%';
        $parametres[':recherche_nom'] = $motifRecherche;
        $parametres[':recherche_motif'] = $motifRecherche;
    }

    if ($type !== '') {
        $conditions[] = 'm.type_mouvement = :type_mouvement';
        $parametres[':type_mouvement'] = $type;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Compter les résultats pour la pagination.
    $sqlCompte = "
        SELECT COUNT(*)
        FROM mouvements_stock m
        INNER JOIN articles a ON a.id = m.article_id
        $where
    ";
    $stmtCompte = $pdo->prepare($sqlCompte);
    foreach ($parametres as $cle => $valeur) {
        $stmtCompte->bindValue($cle, $valeur, PDO::PARAM_STR);
    }
    $stmtCompte->execute();
    $totalMouvements = (int)$stmtCompte->fetchColumn();

    $totalPages = max(1, (int)ceil($totalMouvements / $parPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $decalage = ($page - 1) * $parPage;
    }

    // Charger les mouvements et le nom de l'article associé.
    $sql = "
        SELECT
            m.id,
            m.article_id,
            a.nom AS article,
            m.utilisateur_id,
            m.type_mouvement,
            m.quantite,
            m.stock_avant,
            m.stock_apres,
            m.motif,
            m.date_mouvement
        FROM mouvements_stock m
        INNER JOIN articles a ON a.id = m.article_id
        $where
        ORDER BY m.date_mouvement DESC, m.id DESC
        LIMIT :limite OFFSET :decalage
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($parametres as $cle => $valeur) {
        $stmt->bindValue($cle, $valeur, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limite', $parPage, PDO::PARAM_INT);
    $stmt->bindValue(':decalage', $decalage, PDO::PARAM_INT);
    $stmt->execute();
    $mouvements = $stmt->fetchAll();

} catch (PDOException $exception) {
    error_log('Erreur historique stock : ' . $exception->getMessage());
    $erreur = 'Impossible de charger l’historique du stock pour le moment.';
}

$totalPages = max(1, (int)ceil($totalMouvements / $parPage));

function libelleType(string $type): string
{
    return match ($type) {
        'entree' => 'Entrée',
        'sortie' => 'Sortie',
        'correction' => 'Correction',
        default => 'Inconnu',
    };
}

function classeType(string $type): string
{
    return match ($type) {
        'entree' => 'success',
        'sortie' => 'danger',
        'correction' => 'warning',
        default => 'secondary',
    };
}

// Conserver les filtres lors du changement de page.
function urlPage(int $numero): string
{
    $parametres = $_GET;
    $parametres['page'] = $numero;
    return '?' . http_build_query($parametres);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique du stock - Artisanat d'Afrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6f8; color: #252b26; font-family: Arial, sans-serif; }
        .topbar { background: #263b32; color: #fff; padding: 20px 0; }
        .main-container { padding-top: 35px; padding-bottom: 50px; }
        .page-title { color: #263b32; font-weight: bold; }
        .content-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 3px 15px rgba(0,0,0,.04); }
        .table td, .table th { vertical-align: middle; padding: 13px 10px; }
        .stock-transition { white-space: nowrap; font-weight: 600; }
        .filter-card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 3px 15px rgba(0,0,0,.04); }
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . "/_navigation.php"; ?>

<header class="topbar">
    <div class="container d-flex justify-content-between align-items-center gap-3">
        <div>
            <h4 class="mb-0">Artisanat d'Afrique</h4>
            <small>Espace administrateur</small>
        </div>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Retour au tableau de bord</a>
    </div>
</header>

<main class="container main-container">
    <div class="mb-4">
        <h2 class="page-title mb-1">Historique des mouvements de stock</h2>
        <p class="text-muted mb-0">Consulte les entrées, sorties et corrections de stock.</p>
    </div>

    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger"><?= e($erreur) ?></div>
    <?php else: ?>
        <div class="filter-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Rechercher</label>
                    <input type="search" class="form-control" id="q" name="q" value="<?= e($recherche) ?>" placeholder="Nom d'article ou motif">
                </div>
                <div class="col-12 col-md-3">
                    <label for="type" class="form-label">Type de mouvement</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Tous les mouvements</option>
                        <option value="entree" <?= $type === 'entree' ? 'selected' : '' ?>>Entrées</option>
                        <option value="sortie" <?= $type === 'sortie' ? 'selected' : '' ?>>Sorties</option>
                        <option value="correction" <?= $type === 'correction' ? 'selected' : '' ?>>Corrections</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="historique_stock.php" class="btn btn-outline-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0">Mouvements enregistrés</h5>
                <span class="badge text-bg-success"><?= number_format($totalMouvements, 0, ',', ' ') ?> résultat(s)</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Article</th>
                            <th>Type</th>
                            <th>Quantité</th>
                            <th>Stock avant → après</th>
                            <th>Motif</th>
                            <th>Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($mouvements): ?>
                        <?php foreach ($mouvements as $mouvement): ?>
                            <tr>
                                <td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($mouvement['date_mouvement']))) ?></td>
                                <td>
                                    <strong><?= e($mouvement['article']) ?></strong>
                                    <div class="small text-muted">ID : <?= e($mouvement['article_id']) ?></div>
                                </td>
                                <td><span class="badge text-bg-<?= e(classeType($mouvement['type_mouvement'])) ?>"><?= e(libelleType($mouvement['type_mouvement'])) ?></span></td>
                                <td><?= e($mouvement['quantite']) ?></td>
                                <td class="stock-transition"><?= e($mouvement['stock_avant']) ?> → <?= e($mouvement['stock_apres']) ?></td>
                                <td><?= e($mouvement['motif']) ?></td>
                                <td><?= $mouvement['utilisateur_id'] !== null ? 'Utilisateur #' . e($mouvement['utilisateur_id']) : 'Non renseigné' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Aucun mouvement de stock trouvé.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Pagination de l'historique">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(urlPage(max(1, $page - 1))) ?>">Précédent</a>
                        </li>
                        <?php for ($numero = max(1, $page - 2); $numero <= min($totalPages, $page + 2); $numero++): ?>
                            <li class="page-item <?= $numero === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(urlPage($numero)) ?>"><?= $numero ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(urlPage(min($totalPages, $page + 1))) ?>">Suivant</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
