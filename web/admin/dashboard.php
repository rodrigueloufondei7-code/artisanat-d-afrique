<?php
// Configuration de la session : identique à celle de login.php.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Vérification de la connexion administrateur.
if (
    empty($_SESSION['admin_token']) ||
    ($_SESSION['admin_user']['role'] ?? '') !== 'admin'
) {
    unset($_SESSION['admin_token'], $_SESSION['admin_user']);
    header('Location: login.php');
    exit;
}

// Création du jeton CSRF si nécessaire.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

$statistiques = [];
$articlesStockFaible = [];
$erreur = '';
$erreurArticles = '';
$token = $_SESSION['admin_token'];
$baseApi = 'http://localhost/artisanat_afrique/backend/api/index.php';

if (!function_exists('curl_init')) {
    $erreur = "L'extension PHP cURL n'est pas activée.";
} else {
    // Récupération des statistiques du tableau de bord.
    $curl = curl_init($baseApi . '?route=dashboard-stats');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]
    ]);

    $reponse = curl_exec($curl);
    $codeHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($reponse === false) {
        error_log('Erreur cURL dashboard-stats : ' . curl_error($curl));
        $erreur = 'Impossible de contacter le serveur. Réessayez plus tard.';
    } else {
        $resultat = json_decode($reponse, true);

        if (
            !is_array($resultat) ||
            $codeHttp >= 400 ||
            ($resultat['success'] ?? false) !== true ||
            !is_array($resultat['data'] ?? null)
        ) {
            $erreur = is_array($resultat)
                ? ($resultat['message'] ?? 'Impossible de charger les statistiques.')
                : 'Le serveur a retourné une réponse invalide.';
        } else {
            $statistiques = $resultat['data'];
        }
    }
    curl_close($curl);

    // Récupération du catalogue pour afficher les articles dont le stock <= 5.
    // La route GET products est publique dans l'API actuelle du projet.
    $curlArticles = curl_init($baseApi . '?route=products');
    curl_setopt_array($curlArticles, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $reponseArticles = curl_exec($curlArticles);
    $codeArticles = (int) curl_getinfo($curlArticles, CURLINFO_HTTP_CODE);

    if ($reponseArticles === false) {
        error_log('Erreur cURL products : ' . curl_error($curlArticles));
        $erreurArticles = 'Impossible de charger la liste des articles en stock faible.';
    } else {
        $resultatArticles = json_decode($reponseArticles, true);

        if (
            !is_array($resultatArticles) ||
            $codeArticles >= 400 ||
            ($resultatArticles['success'] ?? false) !== true ||
            !is_array($resultatArticles['data'] ?? null)
        ) {
            $erreurArticles = 'La liste des articles n’a pas pu être récupérée.';
        } else {
            foreach ($resultatArticles['data'] as $article) {
                if (isset($article['stock']) && (int) $article['stock'] <= 5) {
                    $articlesStockFaible[] = $article;
                }
            }
        }
    }
    curl_close($curlArticles);
}

$nombreAlertesStock = count($articlesStockFaible);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#302a26">
    <title>Tableau de bord | Artisanat d'Afrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <style>
        body.admin-dashboard{background:#f3f0e9 url('https://images.unsplash.com/photo-1552683578-311bbc6fd40b?auto=format&fit=crop&w=2000&q=80') center/cover fixed;background-blend-mode:soft-light}
        .dashboard-shell{background:rgba(248,246,240,.92);min-height:calc(100vh - 60px);backdrop-filter:blur(3px)}
        .dash-hero{position:relative;overflow:hidden;border-radius:22px;padding:34px;color:#fff;background:linear-gradient(105deg,rgba(38,34,30,.94),rgba(104,67,49,.82)),url('https://www.malawicrafts.com/assets/heros1.png') center/cover;box-shadow:0 18px 45px rgba(34,28,23,.16)}
        .dash-hero:after{content:"";position:absolute;width:250px;height:250px;border:1px solid rgba(255,255,255,.18);border-radius:50%;right:-55px;top:-110px;box-shadow:0 0 0 25px rgba(255,255,255,.04),0 0 0 55px rgba(255,255,255,.03)}
        .dash-hero h1{color:#fff;font-weight:850;letter-spacing:-.04em;font-size:clamp(1.7rem,3vw,2.45rem)}
        .dash-hero p{color:rgba(255,255,255,.78);max-width:620px}
        .dash-eyebrow{font-size:.72rem;text-transform:uppercase;letter-spacing:.17em;color:#e9c58b;font-weight:800}
        .dash-hero .btn{border-radius:999px;padding:11px 18px}
        .dash-hero .btn-primary{background:#bd6847!important;border-color:#bd6847!important}
        .dash-hero .btn-outline-light:hover{background:#fff;color:#3a302a}
        .dash-section-title{font-weight:800;color:#3c332d;letter-spacing:-.025em}
        .dash-stat{height:100%;background:rgba(255,255,255,.96);border:1px solid #e9e1d7;border-radius:17px;padding:20px;box-shadow:0 8px 25px rgba(47,37,29,.055);position:relative;overflow:hidden}
        .dash-stat:after{content:"";position:absolute;right:-25px;bottom:-42px;width:100px;height:100px;border-radius:50%;background:var(--wash,#f6e9df)}
        .dash-stat-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
        .dash-stat-icon{width:43px;height:43px;display:grid;place-items:center;border-radius:13px;background:var(--wash,#f6e9df);color:var(--tone,#a85f42);font-size:1.2rem}
        .dash-stat-label{font-size:.82rem;color:#82786f;font-weight:700}
        .dash-stat-value{font-size:2rem;font-weight:850;color:#332d28;letter-spacing:-.04em;margin:12px 0 0}
        .dash-panel{height:100%;background:#fff;border:1px solid #e9e1d7;border-radius:18px;padding:23px;box-shadow:0 8px 25px rgba(47,37,29,.05)}
        .dash-module{height:100%;display:flex;align-items:center;gap:14px;padding:16px;border:1px solid #ece5dc;border-radius:14px;background:#fff;color:#3d342e;text-decoration:none;transition:.2s}
        .dash-module:hover{transform:translateY(-3px);border-color:#d8a17e;box-shadow:0 10px 22px rgba(68,46,31,.08);color:#9a563c}
        .dash-module-icon{width:43px;height:43px;flex:0 0 43px;border-radius:13px;display:grid;place-items:center;background:#f5e9df;color:#a75f42;font-size:1.15rem}
        .dash-module strong{display:block;font-size:.92rem}
        .dash-module small{display:block;color:#8b8178;margin-top:3px}
        .dash-table-wrap{border:1px solid #eee6dc;border-radius:13px;overflow:hidden}
        .dash-table-wrap .table{margin:0}
        .dash-table-wrap .table thead th{background:#f6f1e9;color:#6b5849}
        .stock-pill{display:inline-flex;align-items:center;padding:5px 10px;border-radius:99px;background:#fff1df;color:#a35d25;font-weight:800;font-size:.78rem}
        .admin-logout{border-radius:99px}
        @media(max-width:767px){.dash-hero{padding:25px 22px}.dash-panel{padding:18px}.dash-stat{padding:17px}}
    </style>
</head>
<body class="admin-dashboard">
<?php require __DIR__ . "/_navigation.php"; ?>
<div class="dashboard-shell">
<main class="container py-4 py-lg-5">
    <section class="dash-hero mb-4 mb-lg-5">
        <div class="position-relative" style="z-index:1">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="dash-eyebrow mb-2"><i class="bi bi-stars me-1"></i> Espace de pilotage</div>
                    <h1 class="mb-2">Bienvenue dans votre atelier numérique</h1>
                    <p class="mb-0">Pilotez votre boutique, suivez les commandes et valorisez les créations artisanales depuis un seul espace.</p>
                </div>
                <form method="POST" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="btn btn-outline-light admin-logout"><i class="bi bi-box-arrow-right me-1"></i> Déconnexion</button>
                </form>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a href="ajouter_article.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Ajouter un article</a>
                <a href="commandes.php" class="btn btn-outline-light"><i class="bi bi-receipt me-1"></i> Voir les commandes</a>
            </div>
        </div>
    </section>

    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger"><?= e($erreur) ?></div>
    <?php else: ?>
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <div><div class="dash-eyebrow" style="color:#a86a4a">Vue d'ensemble</div><h2 class="h4 dash-section-title mb-0">Les chiffres de votre boutique</h2></div>
        <span class="small text-secondary"><i class="bi bi-arrow-repeat me-1"></i> Données issues de votre boutique</span>
    </div>
    <div class="row g-3 mb-4 mb-lg-5">
        <div class="col-6 col-lg">
            <div class="dash-stat" style="--tone:#a85f42;--wash:#f7e9df"><div class="dash-stat-top"><span class="dash-stat-label">Clients</span><span class="dash-stat-icon"><i class="bi bi-people"></i></span></div><p class="dash-stat-value"><?= e($statistiques['clients'] ?? 0) ?></p></div>
        </div>
        <div class="col-6 col-lg">
            <div class="dash-stat" style="--tone:#a67a2e;--wash:#f8f0dd"><div class="dash-stat-top"><span class="dash-stat-label">Articles actifs</span><span class="dash-stat-icon"><i class="bi bi-box-seam"></i></span></div><p class="dash-stat-value"><?= e($statistiques['articles'] ?? 0) ?></p></div>
        </div>
        <div class="col-6 col-lg">
            <div class="dash-stat" style="--tone:#687b83;--wash:#eaf0f1"><div class="dash-stat-top"><span class="dash-stat-label">Catégories</span><span class="dash-stat-icon"><i class="bi bi-grid"></i></span></div><p class="dash-stat-value"><?= e($statistiques['categories'] ?? 0) ?></p></div>
        </div>
        <div class="col-6 col-lg">
            <div class="dash-stat" style="--tone:#92704b;--wash:#f1e9de"><div class="dash-stat-top"><span class="dash-stat-label">Commandes</span><span class="dash-stat-icon"><i class="bi bi-bag-check"></i></span></div><p class="dash-stat-value"><?= e($statistiques['commandes'] ?? 0) ?></p></div>
        </div>
        <div class="col-12 col-lg">
            <div class="dash-stat" style="--tone:#b16b36;--wash:#fff0df"><div class="dash-stat-top"><span class="dash-stat-label">Alertes de stock</span><span class="dash-stat-icon"><i class="bi bi-exclamation-triangle"></i></span></div><p class="dash-stat-value"><?= e($nombreAlertesStock) ?></p><small class="text-secondary">5 unités ou moins</small></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="dash-panel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div><h2 class="h5 dash-section-title mb-1">Articles à réapprovisionner</h2><p class="text-secondary small mb-0">Produits actifs avec un stock de cinq unités ou moins.</p></div>
                    <a href="articles.php" class="btn btn-sm btn-outline-secondary">Gérer le catalogue</a>
                </div>
                <?php if ($erreurArticles !== ''): ?>
                    <div class="alert alert-warning mb-0"><?= e($erreurArticles) ?></div>
                <?php elseif ($nombreAlertesStock === 0): ?>
                    <div class="text-center py-4"><i class="bi bi-check-circle display-6 text-success"></i><p class="fw-semibold mt-2 mb-0">Aucune alerte de stock pour le moment.</p></div>
                <?php else: ?>
                    <div class="alert alert-warning py-2"><i class="bi bi-info-circle me-1"></i><?= e($nombreAlertesStock) ?> article(s) à surveiller.</div>
                    <div class="dash-table-wrap table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Article</th><th>Stock restant</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($articlesStockFaible as $article): ?>
                                <tr><td class="fw-semibold"><?= e($article['nom'] ?? 'Article sans nom') ?></td><td><span class="stock-pill"><?= e((int)$article['stock']) ?> unité(s)</span></td><td class="text-end"><a href="articles.php" class="btn btn-sm btn-outline-secondary">Gérer</a></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="dash-panel">
                <div class="mb-3"><h2 class="h5 dash-section-title mb-1">Accès rapide</h2><p class="text-secondary small mb-0">Les outils essentiels de gestion.</p></div>
                <div class="row g-2">
                    <div class="col-12 col-sm-6"><a class="dash-module" href="articles.php"><span class="dash-module-icon"><i class="bi bi-box-seam"></i></span><span><strong>Articles</strong><small>Catalogue et prix</small></span></a></div>
                    <div class="col-12 col-sm-6"><a class="dash-module" href="commandes.php"><span class="dash-module-icon"><i class="bi bi-receipt"></i></span><span><strong>Commandes</strong><small>Suivi des ventes</small></span></a></div>
                    <div class="col-12 col-sm-6"><a class="dash-module" href="fournisseurs.php"><span class="dash-module-icon"><i class="bi bi-truck"></i></span><span><strong>Fournisseurs</strong><small>Contacts et partenaires</small></span></a></div>
                    <div class="col-12 col-sm-6"><a class="dash-module" href="approvisionnement_ajouter.php"><span class="dash-module-icon"><i class="bi bi-box-arrow-in-down"></i></span><span><strong>Réceptionner</strong><small>Nouvel approvisionnement</small></span></a></div>
                    <div class="col-12 col-sm-6"><a class="dash-module" href="historique_stock.php"><span class="dash-module-icon"><i class="bi bi-arrow-left-right"></i></span><span><strong>Mouvements</strong><small>Historique du stock</small></span></a></div>
                    <div class="col-12 col-sm-6"><a class="dash-module" href="depenses.php"><span class="dash-module-icon"><i class="bi bi-wallet2"></i></span><span><strong>Dépenses</strong><small>Charges de la boutique</small></span></a></div>
                    <div class="col-12 col-sm-6"><a class="dash-module" href="finances.php"><span class="dash-module-icon"><i class="bi bi-graph-up-arrow"></i></span><span><strong>Finances</strong><small>Vue financière</small></span></a></div>
                    <div class="col-12 col-sm-6"><a class="dash-module" href="approvisionnements.php"><span class="dash-module-icon"><i class="bi bi-clipboard-data"></i></span><span><strong>Réceptions</strong><small>Historique fournisseurs</small></span></a></div>
                </div>
            </section>
        </div>
    </div>
    <div class="text-center small text-secondary mt-4"><i class="bi bi-palette me-1"></i> Artisanat d'Afrique · Gestion et valorisation du savoir-faire artisanal</div>
</main>
</div>
</body>
</html>
