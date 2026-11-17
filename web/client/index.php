<?php
session_start();

// Jeton CSRF utilisé par le formulaire d'ajout au panier.
if (empty($_SESSION["csrf_panier"])) {
    $_SESSION["csrf_panier"] = bin2hex(random_bytes(32));
}

// Récupération des articles depuis l'API existante.
$urlApi = "http://localhost/artisanat_afrique/backend/api/index.php?route=products";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $urlApi,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPGET => true
]);
$reponseApi = curl_exec($ch);
$erreurCurl = curl_error($ch);
$codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$articles = [];
$erreur = "";
if ($reponseApi === false) {
    $erreur = "Impossible de contacter le serveur. " . $erreurCurl;
} else {
    $donnees = json_decode($reponseApi, true);
    if (!is_array($donnees) || $codeHttp >= 400) {
        $erreur = "Le serveur a retourné une réponse invalide.";
    } elseif (($donnees["success"] ?? null) === false) {
        $erreur = $donnees["message"] ?? "Impossible de charger les articles.";
    } else {
        if (isset($donnees["articles"]) && is_array($donnees["articles"])) {
            $articles = $donnees["articles"];
        } elseif (isset($donnees["data"]) && is_array($donnees["data"])) {
            $articles = $donnees["data"];
        } elseif (isset($donnees["produits"]) && is_array($donnees["produits"])) {
            $articles = $donnees["produits"];
        } elseif (array_is_list($donnees)) {
            $articles = $donnees;
        } else {
            $erreur = "Le format des articles retourné par l'API est inattendu.";
        }
    }
}

function e($valeur): string {
    return htmlspecialchars((string)($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

function valeurArticle($article, array $noms, $defaut = "") {
    foreach ($noms as $nom) {
        if (isset($article[$nom]) && !is_array($article[$nom])) {
            return $article[$nom];
        }
    }
    return $defaut;
}

function adresseImage($chemin) {
    if (empty($chemin)) return null;
    if (preg_match('/^https?:\/\//i', $chemin)) return $chemin;
    if (str_starts_with($chemin, "/")) return $chemin;
    if (str_starts_with($chemin, "uploads/")) return "/artisanat_afrique/backend/" . $chemin;
    return "/artisanat_afrique/backend/" . ltrim($chemin, "/");
}

function afficherPrix($prix): string {
    return number_format((float)$prix, 0, ",", " ") . " FCFA";
}

$clientConnecte = !empty($_SESSION["client_connecte"]) && (($_SESSION["client"]["role"] ?? "") === "client");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#183d32">
    <meta name="description" content="Découvrez et commandez des créations artisanales africaines : décoration, accessoires et objets façonnés avec passion.">
    <title>Artisanat d'Afrique — Le savoir-faire prend vie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://images.unsplash.com">
    <style>
        :root {
            --forest: #183d32;
            --forest-deep: #102b24;
            --terracotta: #bd6847;
            --ochre: #d5a34d;
            --cream: #f8f5ee;
            --ink: #26342d;
            --muted: #748078;
            --line: rgba(24,61,50,.11);
            --shadow: 0 18px 55px rgba(28,45,36,.10);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 90px; }
        body { margin: 0; background: var(--cream); color: var(--ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        a { text-decoration: none; }
        .announcement { background: var(--forest-deep); color: #f7f0e4; text-align: center; padding: 8px 12px; font-size: .78rem; letter-spacing: .04em; }
        .site-nav { background: rgba(250,248,242,.94); backdrop-filter: blur(14px); border-bottom: 1px solid rgba(24,61,50,.08); }
        .navbar { min-height: 78px; }
        .brand-mark { width: 42px; height: 42px; display: inline-grid; place-items: center; border-radius: 14px 14px 14px 4px; color: #fff; background: var(--forest); font-size: 1.25rem; transform: rotate(-3deg); }
        .navbar-brand { display: inline-flex; align-items: center; gap: 11px; color: var(--forest); font-weight: 850; letter-spacing: -.04em; font-size: 1.12rem; }
        .navbar-brand:hover { color: var(--terracotta); }
        .brand-sub { display: block; color: #8b918b; font-size: .61rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; margin-top: 1px; }
        .navbar .nav-link { color: #425149; font-size: .91rem; font-weight: 650; padding: 10px 13px !important; transition: color .2s ease; }
        .navbar .nav-link:hover, .navbar .nav-link.active { color: var(--terracotta); }
        .btn-brand { border: 0; border-radius: 999px; background: var(--terracotta); color: #fff; padding: 12px 20px; font-weight: 700; box-shadow: 0 8px 20px rgba(24,61,50,.16); transition: transform .2s, background .2s, box-shadow .2s; }
        .btn-brand:hover { background: #985039; color: #fff; transform: translateY(-2px); box-shadow: 0 12px 24px rgba(189,104,71,.2); }
        .btn-outline-brand { border: 1px solid rgba(255,255,255,.55); color: white; border-radius: 999px; padding: 11px 20px; font-weight: 700; }
        .btn-outline-brand:hover { background: white; color: var(--forest); }
        .hero { position: relative; isolation: isolate; min-height: 650px; display: flex; align-items: center; overflow: hidden; background: var(--forest-deep); color: white; }
        .hero-slides, .hero-shade { position: absolute; inset: 0; z-index: -2; }
        .hero-slide { position: absolute; inset: 0; background-size: cover; background-position: center 43%; opacity: 0; animation: heroFade 21s infinite; transform: scale(1.04); }
        .hero-slide:nth-child(1) { background-image: url('https://images.unsplash.com/photo-1552683578-311bbc6fd40b?auto=format&fit=crop&w=2200&q=85'); animation-delay: 0s; }
        .hero-slide:nth-child(2) { background-image: url('https://www.malawicrafts.com/assets/heros1.png'); animation-delay: 7s; }
        .hero-slide:nth-child(3) { background-image: url('https://africanodeco.com/cdn/shop/files/african-decoration-studio.png?v=1770732316&width=2400'); animation-delay: 14s; }
        @keyframes heroFade { 0% { opacity: 0; transform: scale(1.04); } 7% { opacity: 1; } 31% { opacity: 1; } 39% { opacity: 0; transform: scale(1); } 100% { opacity: 0; } }
        .hero-shade { z-index: -1; background: linear-gradient(90deg, rgba(12,35,28,.91) 0%, rgba(12,35,28,.75) 47%, rgba(12,35,28,.23) 100%), linear-gradient(0deg, rgba(12,35,28,.28), transparent 45%); }
        .hero-pattern { position: absolute; width: 340px; height: 340px; right: -100px; bottom: -155px; border: 1px solid rgba(255,255,255,.18); border-radius: 50%; box-shadow: 0 0 0 24px rgba(255,255,255,.035), 0 0 0 50px rgba(255,255,255,.025); pointer-events: none; }
        .hero-content { position: relative; padding-top: 90px; padding-bottom: 105px; max-width: 760px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 9px; color: #f1cf8a; font-size: .76rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .eyebrow:before { content: ''; width: 30px; height: 1px; background: currentColor; }
        .hero h1 { max-width: 760px; margin: 20px 0 18px; font-family: Georgia, 'Times New Roman', serif; font-size: clamp(2.8rem, 6vw, 5.45rem); line-height: .99; font-weight: 600; letter-spacing: -.045em; }
        .hero h1 em { color: #e5b76b; font-weight: 500; }
        .hero-copy { max-width: 570px; color: rgba(255,255,255,.82); font-size: clamp(1rem, 1.5vw, 1.12rem); line-height: 1.8; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
        .hero-actions .btn-brand { background: var(--ochre); color: #1d3027; box-shadow: none; padding: 14px 23px; }
        .hero-actions .btn-brand:hover { background: white; }
        .hero-note { display: flex; align-items: center; gap: 11px; margin-top: 34px; color: rgba(255,255,255,.75); font-size: .83rem; }
        .hero-note i { color: #e5b76b; font-size: 1.05rem; }
        .hero-dots { position: absolute; bottom: 31px; left: max(calc((100vw - 1320px)/2), 24px); display: flex; gap: 7px; }
        .hero-dots span { display: block; width: 22px; height: 3px; border-radius: 9px; background: rgba(255,255,255,.45); }
        .hero-dots span:first-child { background: #e5b76b; }
        .trust-strip { position: relative; z-index: 2; margin-top: -1px; background: #fff; border-bottom: 1px solid var(--line); }
        .trust-item { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 22px 10px; color: #536057; font-size: .86rem; font-weight: 650; }
        .trust-item i { color: var(--terracotta); font-size: 1.25rem; }
        .section-space { padding-top: 90px; padding-bottom: 90px; }
        .section-kicker { color: var(--terracotta); font-size: .72rem; font-weight: 850; letter-spacing: .19em; text-transform: uppercase; }
        .section-title { color: var(--forest); font-family: Georgia, 'Times New Roman', serif; font-size: clamp(2.1rem, 4vw, 3.25rem); font-weight: 600; letter-spacing: -.035em; }
        .section-lead { color: var(--muted); line-height: 1.8; max-width: 620px; }
        .catalogue-head { display: flex; justify-content: space-between; align-items: end; gap: 24px; margin-bottom: 34px; }
        .catalogue-tools { display: flex; gap: 10px; width: min(100%, 390px); }
        .search-wrap { position: relative; flex: 1; }
        .search-wrap i { position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #8b958e; }
        .search-wrap input { width: 100%; min-height: 47px; border: 1px solid var(--line); border-radius: 999px; padding: 10px 16px 10px 42px; outline: none; background: white; }
        .search-wrap input:focus { border-color: #9eb1a5; box-shadow: 0 0 0 4px rgba(24,61,50,.07); }
        .article-card { height: 100%; overflow: hidden; border: 1px solid rgba(24,61,50,.07); border-radius: 18px; background: white; box-shadow: 0 7px 24px rgba(24,45,34,.045); transition: transform .28s ease, box-shadow .28s ease, border-color .28s ease; }
        .article-card:hover { transform: translateY(-7px); border-color: rgba(189,104,71,.28); box-shadow: var(--shadow); }
        .article-media { position: relative; overflow: hidden; background: #e9e4d9; }
        .article-image, .image-placeholder { width: 100%; height: 245px; object-fit: cover; transition: transform .5s ease; }
        .article-card:hover .article-image { transform: scale(1.045); }
        .image-placeholder { display: flex; align-items: center; justify-content: center; color: #a49b8d; font-size: 2.8rem; }
        .category-pill { position: absolute; top: 14px; left: 14px; border: 1px solid rgba(255,255,255,.65); border-radius: 999px; background: rgba(250,248,242,.92); color: var(--forest); padding: 6px 11px; font-size: .7rem; font-weight: 800; }
        .article-card .card-body { display: flex; flex-direction: column; padding: 21px; }
        .article-name { color: var(--forest); font-family: Georgia, 'Times New Roman', serif; font-size: 1.22rem; font-weight: 700; line-height: 1.25; }
        .article-description { min-height: 45px; color: #7a847d; font-size: .84rem; line-height: 1.6; }
        .article-price { color: var(--terracotta); font-size: 1.15rem; font-weight: 850; }
        .stock-label { color: #527b63; font-size: .78rem; font-weight: 700; }
        .stock-epuise { color: #b34d43; }
        .btn-card { width: 100%; border: 0; border-radius: 10px; background: var(--forest); color: white; padding: 11px 14px; font-size: .84rem; font-weight: 750; transition: background .2s, transform .2s; }
        .btn-card:hover { background: var(--terracotta); color: white; transform: translateY(-1px); }
        .btn-card:disabled { background: #a8b0aa; }
        .empty-state { padding: 55px 20px; border: 1px dashed #cbd2ca; border-radius: 18px; color: #7c877e; text-align: center; background: rgba(255,255,255,.55); }
        .story-section { background: #eee9dd; overflow: hidden; position: relative; }
        .story-photo { min-height: 430px; height: 100%; border-radius: 18px; background: url('https://images.unsplash.com/photo-1552683578-311bbc6fd40b?auto=format&fit=crop&w=1400&q=85') center/cover; box-shadow: var(--shadow); }
        .story-copy { padding: 35px 0 35px 25px; }
        .story-copy p { color: #6d786f; line-height: 1.9; }
        .story-points { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 25px; }
        .story-point { display: flex; gap: 10px; align-items: center; color: var(--forest); font-size: .88rem; font-weight: 750; }
        .story-point i { color: var(--terracotta); font-size: 1.2rem; }
        .cta-band { padding: 65px 0; background: var(--forest); color: white; position: relative; overflow: hidden; }
        .cta-band:after { content: ''; position: absolute; width: 310px; height: 310px; right: -70px; top: -170px; border: 1px solid rgba(255,255,255,.15); border-radius: 50%; box-shadow: 0 0 0 25px rgba(255,255,255,.035), 0 0 0 50px rgba(255,255,255,.025); }
        .cta-band h2 { font-family: Georgia, 'Times New Roman', serif; font-size: clamp(1.9rem, 3vw, 2.8rem); }
        .cta-band p { color: rgba(255,255,255,.72); margin: 0; }
        .cta-band .btn { position: relative; z-index: 1; border-radius: 999px; padding: 13px 22px; background: var(--ochre); color: #21352b; font-weight: 800; }
        footer { background: #102b24; color: #f7f0e4; padding: 52px 0 24px; }
        .footer-brand { color: #f3d39b; font-family: Georgia, 'Times New Roman', serif; font-size: 1.5rem; }
        .footer-muted { color: rgba(255,255,255,.62); font-size: .88rem; line-height: 1.8; }
        .footer-link { display: inline-block; color: rgba(255,255,255,.75); margin: 5px 14px 5px 0; font-size: .86rem; }
        .footer-link:hover { color: #f3d39b; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,.12); margin-top: 35px; padding-top: 20px; color: rgba(255,255,255,.48); font-size: .78rem; }
        .reveal { animation: riseIn .75s both; }
        @keyframes riseIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 991.98px) { .hero { min-height: 590px; } .catalogue-head { align-items: stretch; flex-direction: column; } .catalogue-tools { width: 100%; } .story-copy { padding: 30px 0 0; } .story-photo { min-height: 340px; } }
        @media (max-width: 575.98px) { .announcement { font-size: .69rem; } .navbar { min-height: 68px; } .hero { min-height: 610px; } .hero-content { padding-top: 75px; padding-bottom: 95px; } .hero h1 { font-size: 2.75rem; } .hero-shade { background: linear-gradient(90deg, rgba(12,35,28,.91), rgba(12,35,28,.66)); } .section-space { padding-top: 65px; padding-bottom: 65px; } .article-image, .image-placeholder { height: 230px; } .story-points { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; } }
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
</head>
<body>
<div class="announcement"><i class="bi bi-stars me-1"></i> Des créations authentiques · Un savoir-faire à partager · Une culture à valoriser</div>

<nav class="navbar navbar-expand-lg site-nav sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php" aria-label="Artisanat d'Afrique - Accueil">
            <span class="brand-mark"><i class="bi bi-flower1"></i></span>
            <span>Artisanat d'Afrique<span class="brand-sub">L'âme de nos traditions</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Ouvrir le menu">
            <i class="bi bi-list fs-2" style="color:var(--forest)"></i>
        </button>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link active" href="index.php">Accueil</a></li>
                <li class="nav-item"><a class="nav-link" href="#catalogue">Nos créations</a></li>
                <li class="nav-item"><a class="nav-link" href="#apropos">Notre histoire</a></li>
                <?php if ($clientConnecte): ?>
                    <li class="nav-item"><a class="nav-link" href="commandes.php"><i class="bi bi-box-seam me-1"></i>Mes commandes</a></li>
                    <li class="nav-item"><a class="nav-link" href="panier.php"><i class="bi bi-bag me-1"></i>Panier</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-brand" href="mon_compte.php"><i class="bi bi-person-circle me-1"></i>Mon compte</a></li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2"><a class="btn btn-brand" href="connexion.php"><i class="bi bi-person me-1"></i>Se connecter</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<header class="hero">
    <div class="hero-slides" aria-hidden="true"><div class="hero-slide"></div><div class="hero-slide"></div><div class="hero-slide"></div></div>
    <div class="hero-shade" aria-hidden="true"></div><div class="hero-pattern" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-content reveal">
            <span class="eyebrow">L'art de nos mains, l'héritage de nos terres</span>
            <h1>Chaque création raconte <em>une histoire.</em></h1>
            <p class="hero-copy">Entrez dans un univers où les matières, les couleurs et les gestes de nos artisans donnent vie à des pièces uniques, inspirées de la richesse des cultures africaines.</p>
            <div class="hero-actions">
                <a href="#catalogue" class="btn btn-brand">Explorer les créations <i class="bi bi-arrow-right ms-2"></i></a>
                <a href="#apropos" class="btn btn-outline-brand">Notre démarche</a>
            </div>
            <div class="hero-note"><i class="bi bi-patch-check-fill"></i><span>Des articles sélectionnés pour célébrer le savoir-faire artisanal.</span></div>
        </div>
    </div>
    <div class="hero-dots" aria-hidden="true"><span></span><span></span><span></span></div>
</header>

<section class="trust-strip">
    <div class="container"><div class="row g-0">
        <div class="col-6 col-lg-3"><div class="trust-item"><i class="bi bi-hand-thumbs-up"></i><span>Savoir-faire valorisé</span></div></div>
        <div class="col-6 col-lg-3"><div class="trust-item"><i class="bi bi-palette2"></i><span>Créations inspirées</span></div></div>
        <div class="col-6 col-lg-3"><div class="trust-item"><i class="bi bi-bag-check"></i><span>Commande en ligne</span></div></div>
        <div class="col-6 col-lg-3"><div class="trust-item"><i class="bi bi-heart"></i><span>Culture célébrée</span></div></div>
    </div></div>
</section>

<main>
<section class="container section-space" id="catalogue">
    <div class="catalogue-head">
        <div>
            <span class="section-kicker">La sélection du moment</span>
            <h2 class="section-title mt-2 mb-2">Nos créations artisanales</h2>
            <p class="section-lead mb-0">Des objets qui portent une identité, un geste et une histoire. Trouvez la pièce qui vous ressemble.</p>
        </div>
        <?php if (empty($erreur) && !empty($articles)): ?>
        <div class="catalogue-tools">
            <div class="search-wrap"><i class="bi bi-search"></i><input type="search" id="rechercheArticles" placeholder="Rechercher une création..." aria-label="Rechercher un article"></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger rounded-4"><i class="bi bi-exclamation-triangle me-2"></i><?= e($erreur) ?></div>
    <?php elseif (empty($articles)): ?>
        <div class="empty-state"><i class="bi bi-basket2 fs-1 d-block mb-3"></i><h3 class="h5">La collection se prépare</h3><p class="mb-0">Aucun article n'est disponible pour le moment. Revenez bientôt découvrir nos nouveautés.</p></div>
    <?php else: ?>
        <div class="row g-4" id="grilleArticles">
            <?php foreach ($articles as $article): ?>
                <?php
                    $id = (int) valeurArticle($article, ["id"], 0);
                    $nom = valeurArticle($article, ["nom", "name"], "Article sans nom");
                    $description = valeurArticle($article, ["description"], "Une création inspirée du savoir-faire artisanal.");
                    $prix = valeurArticle($article, ["prix", "price"], 0);
                    $stock = (int) valeurArticle($article, ["stock"], 0);
                    $image = valeurArticle($article, ["image_url", "image", "photo"]);
                    $categorie = valeurArticle($article, ["categorie_nom", "category_name"], "");
                    if ($categorie === "" && isset($article["categorie"]) && is_array($article["categorie"])) $categorie = $article["categorie"]["nom"] ?? "";
                    $urlImage = adresseImage($image);
                ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 article-item" data-search="<?= e(mb_strtolower($nom . ' ' . $categorie . ' ' . $description, 'UTF-8')) ?>">
                    <article class="card article-card">
                        <div class="article-media">
                            <?php if ($urlImage): ?>
                                <img src="<?= e($urlImage) ?>" class="article-image" alt="<?= e($nom) ?>" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="image-placeholder" style="display:none"><i class="bi bi-image"></i></div>
                            <?php else: ?>
                                <div class="image-placeholder"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                            <?php if ($categorie !== ''): ?><span class="category-pill"><?= e($categorie) ?></span><?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h3 class="article-name mb-2"><?= e($nom) ?></h3>
                            <p class="article-description mb-3"><?= e($description) ?></p>
                            <div class="mt-auto">
                                <p class="article-price mb-2"><?= e(afficherPrix($prix)) ?></p>
                                <?php if ($stock > 0): ?>
                                    <p class="stock-label mb-3"><i class="bi bi-check-circle-fill me-1"></i><?= $stock ?> disponible(s)</p>
                                <?php else: ?>
                                    <p class="stock-label stock-epuise mb-3"><i class="bi bi-x-circle-fill me-1"></i>Rupture de stock</p>
                                <?php endif; ?>
                                <?php if ($clientConnecte): ?>
                                    <?php if ($stock > 0 && $id > 0): ?>
                                        <form method="POST" action="panier.php">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_panier"]) ?>">
                                            <input type="hidden" name="action" value="ajouter">
                                            <input type="hidden" name="article_id" value="<?= $id ?>">
                                            <button type="submit" class="btn btn-card"><i class="bi bi-bag-plus me-2"></i>Ajouter au panier</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-card" disabled>Indisponible</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="connexion.php" class="btn btn-card"><i class="bi bi-person me-2"></i>Connectez-vous pour commander</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="empty-state mt-3 d-none" id="aucunResultat"><i class="bi bi-search fs-1 d-block mb-3"></i><h3 class="h5">Aucun résultat</h3><p class="mb-0">Essayez avec un autre nom ou une autre catégorie.</p></div>
    <?php endif; ?>
</section>

<section class="story-section section-space" id="apropos">
    <div class="container"><div class="row align-items-center g-5">
        <div class="col-12 col-lg-6"><div class="story-photo" role="img" aria-label="Artisane africaine réalisant un panier tressé"></div></div>
        <div class="col-12 col-lg-6"><div class="story-copy">
            <span class="section-kicker">Notre engagement</span>
            <h2 class="section-title mt-2 mb-3">L'artisanat, un patrimoine vivant.</h2>
            <p>Artisanat d'Afrique est une vitrine dédiée à la découverte et à la commande de créations inspirées des traditions africaines. Chaque matière, chaque motif et chaque finition témoignent d'une richesse culturelle à transmettre.</p>
            <p>Notre ambition est de rapprocher les passionnés de belles créations et les savoir-faire qui méritent d'être connus et valorisés.</p>
            <div class="story-points">
                <div class="story-point"><i class="bi bi-check2-circle"></i><span>Identité culturelle</span></div>
                <div class="story-point"><i class="bi bi-check2-circle"></i><span>Créativité artisanale</span></div>
                <div class="story-point"><i class="bi bi-check2-circle"></i><span>Découverte simplifiée</span></div>
                <div class="story-point"><i class="bi bi-check2-circle"></i><span>Commande accessible</span></div>
            </div>
        </div></div>
    </div></div>
</section>


<section class="info-section section-space" id="notre-demarche">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-kicker">Bien plus qu'une boutique</span>
            <h2 class="section-title mt-2">L'artisanat raconte une histoire</h2>
            <p class="section-intro mx-auto">Chaque création porte une identité, une technique et une part de culture. Notre démarche est de rendre ces pièces plus faciles à découvrir et à commander.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4"><article class="info-card h-100"><div class="info-icon"><i class="bi bi-brush"></i></div><h3>Des créations singulières</h3><p>Objets décoratifs, accessoires et pièces utiles : explorez une sélection qui met en valeur les formes, les matières et les inspirations africaines.</p></article></div>
            <div class="col-md-4"><article class="info-card h-100"><div class="info-icon"><i class="bi bi-stars"></i></div><h3>Un savoir-faire à découvrir</h3><p>La vannerie, le travail du bois, le textile, la sculpture et bien d'autres pratiques témoignent de la richesse des gestes artisanaux.</p></article></div>
            <div class="col-md-4"><article class="info-card h-100"><div class="info-icon"><i class="bi bi-heart"></i></div><h3>Une consommation qui a du sens</h3><p>Choisir une pièce artisanale, c'est aussi s'intéresser à son histoire et contribuer à faire connaître les métiers de création.</p></article></div>
        </div>
    </div>
</section>

<section class="steps-section section-space">
    <div class="container">
        <div class="row align-items-end g-3 mb-4">
            <div class="col-lg-8"><span class="section-kicker">Simple et pratique</span><h2 class="section-title mt-2 mb-0">Comment passer commande ?</h2></div>
            <div class="col-lg-4"><p class="section-intro mb-0">Quelques étapes pour retrouver vos articles et suivre vos achats depuis votre espace client.</p></div>
        </div>
        <div class="row g-3">
            <div class="col-6 col-lg-3"><div class="step-card"><span class="step-number">01</span><i class="bi bi-search"></i><h3>Explorez</h3><p>Parcourez le catalogue et recherchez une création.</p></div></div>
            <div class="col-6 col-lg-3"><div class="step-card"><span class="step-number">02</span><i class="bi bi-bag-plus"></i><h3>Ajoutez</h3><p>Ajoutez les articles disponibles à votre panier.</p></div></div>
            <div class="col-6 col-lg-3"><div class="step-card"><span class="step-number">03</span><i class="bi bi-clipboard-check"></i><h3>Validez</h3><p>Connectez-vous puis renseignez vos informations de livraison.</p></div></div>
            <div class="col-6 col-lg-3"><div class="step-card"><span class="step-number">04</span><i class="bi bi-box-seam"></i><h3>Suivez</h3><p>Retrouvez l'évolution de votre commande dans votre compte.</p></div></div>
        </div>
    </div>
</section>

<section class="faq-section section-space">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4"><span class="section-kicker">À savoir</span><h2 class="section-title mt-2">Questions fréquentes</h2><p class="section-intro">Les informations essentielles pour profiter sereinement de la boutique.</p></div>
            <div class="col-lg-8">
                <div class="accordion artisan-accordion" id="faqArtisanat">
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">Faut-il créer un compte pour commander ?</button></h3><div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqArtisanat"><div class="accordion-body">Vous pouvez découvrir les articles librement. Pour finaliser une commande et retrouver son suivi, connectez-vous à votre compte client ou créez-en un.</div></div></div>
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">Comment savoir si un article est disponible ?</button></h3><div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqArtisanat"><div class="accordion-body">La fiche de chaque article indique sa disponibilité selon les informations de stock enregistrées dans la boutique.</div></div></div>
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">Où retrouver mes commandes ?</button></h3><div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqArtisanat"><div class="accordion-body">Après connexion, ouvrez votre espace client puis la rubrique « Mes commandes » pour consulter l'historique et les statuts disponibles.</div></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-band"><div class="container"><div class="row align-items-center g-4">
    <div class="col-lg-8"><h2 class="mb-2">Une pièce vous a séduit ?</h2><p>Parcourez notre collection et laissez-vous inspirer par les créations disponibles.</p></div>
    <div class="col-lg-4 text-lg-end"><a href="#catalogue" class="btn">Voir la collection <i class="bi bi-arrow-up-right ms-2"></i></a></div>
</div></div></section>

    <section class="video-section section-space" id="decouvrir-artisanat" style="background:#f0e9df">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-5">
                    <span class="text-uppercase fw-bold small" style="letter-spacing:.16em;color:#b96545">Culture & transmission</span>
                    <h2 class="display-6 fw-bold mt-2 mb-3">L'artisanat africain, une histoire à découvrir</h2>
                    <p class="text-secondary lh-lg">Découvrez les gestes, les motifs et les savoir-faire transmis entre générations. Cette vidéo documentaire vous invite à mieux comprendre la richesse des textiles artisanaux et le travail des artisans.</p>
                    <a href="https://www.youtube.com/watch?v=400xLHvSf18" target="_blank" rel="noopener" class="btn btn-brand" style="background:#b96545"><i class="bi bi-youtube me-2"></i>Voir la vidéo sur YouTube</a>
                </div>
                <div class="col-lg-7">
                    <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow">
                        <iframe src="https://www.youtube-nocookie.com/embed/400xLHvSf18" title="Bogolan : c'est tout pour moi — artisans textiles à Ségou" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                    <p class="small text-secondary mt-2 mb-0">Documentaire consacré aux ateliers de bogolan à Ségou, au Mali. Source : YouTube.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-5"><div class="footer-brand">Artisanat d'Afrique</div><p class="footer-muted mt-2 mb-0">Une boutique dédiée aux créations, aux matières et aux traditions qui font la richesse de l'artisanat africain.</p></div>
            <div class="col-6 col-lg-3"><h3 class="h6 fw-bold">Navigation</h3><a class="footer-link" href="index.php">Accueil</a><br><a class="footer-link" href="#catalogue">Nos créations</a><br><a class="footer-link" href="#apropos">À propos</a></div>
            <div class="col-6 col-lg-4"><h3 class="h6 fw-bold">Votre espace</h3><?php if ($clientConnecte): ?><a class="footer-link" href="mon_compte.php">Mon compte</a><br><a class="footer-link" href="commandes.php">Mes commandes</a><br><a class="footer-link" href="panier.php">Mon panier</a><?php else: ?><a class="footer-link" href="connexion.php">Connexion</a><br><a class="footer-link" href="inscription.php">Créer un compte</a><?php endif; ?></div>
        </div>
        <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2"><span>© <?= date("Y") ?> Artisanat d'Afrique. Tous droits réservés.</span><span>Valorisons ensemble le savoir-faire africain.</span></div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Recherche instantanée dans les articles déjà chargés par l'API.
(() => {
    const input = document.getElementById('rechercheArticles');
    const cards = [...document.querySelectorAll('.article-item')];
    const empty = document.getElementById('aucunResultat');
    if (!input) return;
    input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase('fr');
        let visible = 0;
        cards.forEach(card => {
            const match = (card.dataset.search || '').toLocaleLowerCase('fr').includes(query);
            card.classList.toggle('d-none', !match);
            if (match) visible++;
        });
        if (empty) empty.classList.toggle('d-none', visible !== 0);
    });
})();
</script>
</body>
</html>
