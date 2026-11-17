<?php
declare(strict_types=1);

/*
 * Gestion des dépenses courantes.
 * Fichier : web/admin/depenses.php
 */

require_once __DIR__ . '/_commun.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("La connexion à la base de données est indisponible.");
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_depenses'])) {
    $_SESSION['csrf_depenses'] = bin2hex(random_bytes(32));
}

function dep_e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function dep_fcfa($value): string
{
    return number_format((float) $value, 0, ',', ' ') . ' FCFA';
}

$categories = [
    'transport' => 'Transport',
    'electricite' => 'Électricité',
    'eau' => 'Eau',
    'emballage' => 'Emballage',
    'salaires' => 'Salaires',
    'loyer' => 'Loyer',
    'entretien' => 'Entretien',
    'communication' => 'Communication',
    'autre' => 'Autre'
];

$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_depenses'], $jeton)) {
        $erreur = "Le formulaire a expiré. Rechargez la page et réessayez.";
    } else {
        $categorie = (string) ($_POST['categorie'] ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $montantBrut = str_replace(',', '.', trim((string) ($_POST['montant'] ?? '')));
        $dateDepense = (string) ($_POST['date_depense'] ?? '');

        $dateValide = DateTime::createFromFormat('Y-m-d', $dateDepense);
        $montantValide = is_numeric($montantBrut) && (float) $montantBrut > 0;

        if (!isset($categories[$categorie])) {
            $erreur = "Veuillez choisir une catégorie valide.";
        } elseif ($description === '' || mb_strlen($description) > 255) {
            $erreur = "La description est obligatoire et ne doit pas dépasser 255 caractères.";
        } elseif (!$montantValide || (float) $montantBrut > 999999999999) {
            $erreur = "Veuillez saisir un montant valide supérieur à zéro.";
        } elseif (!$dateValide || $dateValide->format('Y-m-d') !== $dateDepense) {
            $erreur = "Veuillez saisir une date valide.";
        } else {
            try {
                $adminId = isset($_SESSION['admin_user']['id'])
                    ? (int) $_SESSION['admin_user']['id']
                    : null;

                $stmt = $pdo->prepare("
                    INSERT INTO depenses
                        (utilisateur_id, categorie, description, montant, date_depense)
                    VALUES
                        (:utilisateur_id, :categorie, :description, :montant, :date_depense)
                ");
                $stmt->execute([
                    'utilisateur_id' => $adminId ?: null,
                    'categorie' => $categorie,
                    'description' => $description,
                    'montant' => number_format((float) $montantBrut, 2, '.', ''),
                    'date_depense' => $dateDepense
                ]);

                header('Location: depenses.php?ajout=ok');
                exit;
            } catch (PDOException $exception) {
                error_log('Erreur ajout dépense : ' . $exception->getMessage());
                $erreur = "La dépense n'a pas pu être enregistrée. Vérifiez que la table depenses a bien été créée.";
            }
        }
    }
}

if (($_GET['ajout'] ?? '') === 'ok') {
    $message = "La dépense a été enregistrée avec succès.";
}

$filtreCategorie = (string) ($_GET['categorie'] ?? '');
$recherche = trim((string) ($_GET['recherche'] ?? ''));
$debut = (string) ($_GET['debut'] ?? '');
$fin = (string) ($_GET['fin'] ?? '');

$sql = "
    SELECT d.id, d.categorie, d.description, d.montant, d.date_depense,
           u.nom, u.prenom
    FROM depenses d
    LEFT JOIN utilisateurs u ON u.id = d.utilisateur_id
    WHERE 1 = 1
";
$params = [];

if (isset($categories[$filtreCategorie])) {
    $sql .= " AND d.categorie = :categorie";
    $params['categorie'] = $filtreCategorie;
}
if ($recherche !== '') {
    $sql .= " AND (d.description LIKE :recherche)";
    $params['recherche'] = '%' . $recherche . '%';
}
if ($debut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $debut)) {
    $sql .= " AND d.date_depense >= :debut";
    $params['debut'] = $debut;
}
if ($fin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
    $sql .= " AND d.date_depense <= :fin";
    $params['fin'] = $fin;
}
$sql .= " ORDER BY d.date_depense DESC, d.id DESC LIMIT 100";

$depenses = [];
$totalFiltre = 0.0;
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $depenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($depenses as $depense) {
        $totalFiltre += (float) $depense['montant'];
    }
} catch (PDOException $exception) {
    error_log('Erreur liste dépenses : ' . $exception->getMessage());
    $erreur = $erreur ?: "Impossible de charger l'historique des dépenses.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des dépenses - Artisanat d'Afrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6f8; color: #252b26; font-family: Arial, sans-serif; }
        .topbar { background: #263b32; color: white; padding: 18px 0; }
        .main-container { padding-top: 32px; padding-bottom: 50px; }
        .panel { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 3px 15px rgba(0,0,0,.04); }
        .page-title { color: #263b32; font-weight: 700; }
        .table thead th { white-space: nowrap; color: #526057; font-size: 13px; }
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
            <small>Espace administrateur · Dépenses</small>
        </div>
        <a href="finances.php" class="btn btn-outline-light btn-sm">Retour aux finances</a>
    </div>
</header>

<main class="container main-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title mb-1">Gestion des dépenses</h2>
            <p class="text-muted mb-0">Enregistrez les charges autres que les achats de marchandises.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-success">Tableau de bord</a>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= dep_e($message) ?></div><?php endif; ?>
    <?php if ($erreur !== ''): ?><div class="alert alert-danger"><?= dep_e($erreur) ?></div><?php endif; ?>

    <section class="panel mb-4">
        <h3 class="h5 mb-3">Enregistrer une dépense</h3>
        <form method="POST" action="depenses.php" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= dep_e($_SESSION['csrf_depenses']) ?>">
            <div class="col-12 col-md-4">
                <label for="categorie" class="form-label">Catégorie *</label>
                <select name="categorie" id="categorie" class="form-select" required>
                    <option value="">Choisir une catégorie</option>
                    <?php foreach ($categories as $valeur => $libelle): ?>
                        <option value="<?= dep_e($valeur) ?>" <?= (($_POST['categorie'] ?? '') === $valeur) ? 'selected' : '' ?>>
                            <?= dep_e($libelle) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="montant" class="form-label">Montant (FCFA) *</label>
                <input type="number" name="montant" id="montant" class="form-control"
                       min="1" step="0.01" required value="<?= dep_e($_POST['montant'] ?? '') ?>"
                       placeholder="Ex. 25000">
            </div>
            <div class="col-12 col-md-4">
                <label for="date_depense" class="form-label">Date de la dépense *</label>
                <input type="date" name="date_depense" id="date_depense" class="form-control"
                       required value="<?= dep_e($_POST['date_depense'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Description *</label>
                <input type="text" name="description" id="description" class="form-control"
                       maxlength="255" required value="<?= dep_e($_POST['description'] ?? '') ?>"
                       placeholder="Ex. Paiement de la facture d'électricité">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success">Enregistrer la dépense</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1">Historique des dépenses</h3>
                <p class="text-muted mb-0">Les 100 dernières dépenses correspondant aux filtres.</p>
            </div>
            <strong>Total affiché : <?= dep_e(dep_fcfa($totalFiltre)) ?></strong>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md-3">
                <select name="categorie" class="form-select">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $valeur => $libelle): ?>
                        <option value="<?= dep_e($valeur) ?>" <?= $filtreCategorie === $valeur ? 'selected' : '' ?>>
                            <?= dep_e($libelle) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <input type="text" name="recherche" class="form-control" placeholder="Rechercher une description"
                       value="<?= dep_e($recherche) ?>">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="debut" class="form-control" aria-label="Date de début" value="<?= dep_e($debut) ?>">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="fin" class="form-control" aria-label="Date de fin" value="<?= dep_e($fin) ?>">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-outline-success flex-fill" type="submit">Filtrer</button>
                <a href="depenses.php" class="btn btn-outline-secondary">Effacer</a>
            </div>
        </form>

        <?php if (!$depenses): ?>
            <div class="alert alert-light mb-0">Aucune dépense ne correspond à votre recherche.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr><th>Date</th><th>Catégorie</th><th>Description</th><th>Enregistré par</th><th>Montant</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($depenses as $d): ?>
                        <tr>
                            <td><?= dep_e(date('d/m/Y', strtotime($d['date_depense']))) ?></td>
                            <td><?= dep_e($categories[$d['categorie']] ?? $d['categorie']) ?></td>
                            <td><?= dep_e($d['description']) ?></td>
                            <td><?= dep_e(trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? '')) ?: '—') ?></td>
                            <td class="fw-semibold"><?= dep_e(dep_fcfa($d['montant'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
