<?php
declare(strict_types=1);

/*
 * Module financier - Artisanat d'Afrique
 * À placer dans : web/admin/finances.php
 * Utilise la connexion PDO et la protection administrateur de _commun.php.
 */

require_once __DIR__ . '/_commun.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("La connexion à la base de données est indisponible.");
}

$anneeActuelle = (int) date('Y');
$annee = filter_input(INPUT_GET, 'annee', FILTER_VALIDATE_INT);
if (!$annee || $annee < 2000 || $annee > $anneeActuelle + 1) {
    $annee = $anneeActuelle;
}

$debutAnnee = sprintf('%04d-01-01 00:00:00', $annee);
$finAnnee = sprintf('%04d-01-01 00:00:00', $annee + 1);

$recettes = 0.0;
$depenses = 0.0;
$depensesAppro = 0.0;
$autresDepenses = 0.0;
$commandesLivrees = 0;
$nombreApprovisionnements = 0;
$rapportMensuel = [];
$derniersApprovisionnements = [];
$dernieresCommandes = [];
$erreur = '';

try {
    // Seules les commandes livrées sont comptées comme recettes.
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(montant_total), 0), COUNT(*)
        FROM commandes
        WHERE statut = 'livree'
          AND date_commande >= :debut
          AND date_commande < :fin
    ");
    $stmt->execute(['debut' => $debutAnnee, 'fin' => $finAnnee]);
    [$recettes, $commandesLivrees] = $stmt->fetch(PDO::FETCH_NUM);
    $recettes = (float) $recettes;
    $commandesLivrees = (int) $commandesLivrees;

    // Les dépenses correspondent aux montants des réceptions fournisseurs.
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(montant_total), 0), COUNT(*)
        FROM approvisionnements
        WHERE date_approvisionnement >= :debut
          AND date_approvisionnement < :fin
    ");
    $stmt->execute(['debut' => $debutAnnee, 'fin' => $finAnnee]);
    [$depensesAppro, $nombreApprovisionnements] = $stmt->fetch(PDO::FETCH_NUM);
    $depensesAppro = (float) $depensesAppro;
    $nombreApprovisionnements = (int) $nombreApprovisionnements;

    // Dépenses courantes (transport, électricité, emballage, etc.).
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(montant), 0)
        FROM depenses
        WHERE date_depense >= :debut
          AND date_depense < :fin
    ");
    $stmt->execute(['debut' => $debutAnnee, 'fin' => $finAnnee]);
    $autresDepenses = (float) $stmt->fetchColumn();

    $depenses = $depensesAppro + $autresDepenses;

    // Agrégation mensuelle des recettes et dépenses.
    $stmt = $pdo->prepare("
        SELECT mois,
               SUM(recettes) AS recettes,
               SUM(depenses) AS depenses
        FROM (
            SELECT MONTH(date_commande) AS mois,
                   SUM(montant_total) AS recettes,
                   0 AS depenses
            FROM commandes
            WHERE statut = 'livree'
              AND date_commande >= :debut_recettes
              AND date_commande < :fin_recettes
            GROUP BY MONTH(date_commande)

            UNION ALL

            SELECT MONTH(date_approvisionnement) AS mois,
                   0 AS recettes,
                   SUM(montant_total) AS depenses
            FROM approvisionnements
            WHERE date_approvisionnement >= :debut_appro
              AND date_approvisionnement < :fin_appro
            GROUP BY MONTH(date_approvisionnement)

            UNION ALL

            SELECT MONTH(date_depense) AS mois,
                   0 AS recettes,
                   SUM(montant) AS depenses
            FROM depenses
            WHERE date_depense >= :debut_autres
              AND date_depense < :fin_autres
            GROUP BY MONTH(date_depense)
        ) mouvements
        GROUP BY mois
        ORDER BY mois
    ");
    $stmt->execute([
        'debut_recettes' => $debutAnnee,
        'fin_recettes' => $finAnnee,
        'debut_appro' => $debutAnnee,
        'fin_appro' => $finAnnee,
        'debut_autres' => $debutAnnee,
        'fin_autres' => $finAnnee
    ]);

    $moisNoms = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars',
        4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre',
        10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];

    $donneesMois = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
        $donneesMois[(int) $ligne['mois']] = $ligne;
    }

    for ($m = 1; $m <= 12; $m++) {
        $ligne = $donneesMois[$m] ?? [];
        $r = (float) ($ligne['recettes'] ?? 0);
        $d = (float) ($ligne['depenses'] ?? 0);
        $rapportMensuel[] = [
            'nom' => $moisNoms[$m],
            'recettes' => $r,
            'depenses' => $d,
            'resultat' => $r - $d
        ];
    }

    $stmt = $pdo->prepare("
        SELECT a.reference, f.nom AS fournisseur, a.montant_total,
               a.date_approvisionnement
        FROM approvisionnements a
        INNER JOIN fournisseurs f ON f.id = a.fournisseur_id
        WHERE a.date_approvisionnement >= :debut
          AND a.date_approvisionnement < :fin
        ORDER BY a.date_approvisionnement DESC
        LIMIT 5
    ");
    $stmt->execute(['debut' => $debutAnnee, 'fin' => $finAnnee]);
    $derniersApprovisionnements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT categorie, description, montant, date_depense
        FROM depenses
        WHERE date_depense >= :debut
          AND date_depense < :fin
        ORDER BY date_depense DESC, id DESC
        LIMIT 5
    ");
    $stmt->execute(['debut' => $debutAnnee, 'fin' => $finAnnee]);
    $dernieresDepenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT numero_commande, nom_livraison, montant_total, date_commande
        FROM commandes
        WHERE statut = 'livree'
          AND date_commande >= :debut
          AND date_commande < :fin
        ORDER BY date_commande DESC
        LIMIT 5
    ");
    $stmt->execute(['debut' => $debutAnnee, 'fin' => $finAnnee]);
    $dernieresCommandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {
    error_log('Erreur module financier : ' . $exception->getMessage());
    $erreur = "Impossible de charger les données financières. Vérifiez la structure de vos tables.";
}

$resultatGlobal = $recettes - $depenses;

function finance_e($valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

function finance_fcfa($montant): string
{
    return number_format((float) $montant, 0, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion financière - Artisanat d'Afrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6f8; color: #252b26; font-family: Arial, sans-serif; }
        .topbar { background: #263b32; color: white; padding: 18px 0; }
        .main-container { padding-top: 32px; padding-bottom: 50px; }
        .page-title { color: #263b32; font-weight: 700; }
        .finance-card, .panel { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 3px 15px rgba(0,0,0,.04); height: 100%; }
        .finance-label { color: #6c757d; font-size: 14px; }
        .finance-value { font-size: clamp(20px, 3vw, 28px); font-weight: 700; margin: 8px 0 0; color: #263b32; overflow-wrap: anywhere; }
        .table thead th { white-space: nowrap; color: #526057; font-size: 13px; }
        .positive { color: #198754; }
        .negative { color: #b45309; }
        .bar-track { height: 9px; background: #edf0ee; border-radius: 20px; overflow: hidden; min-width: 65px; }
        .bar-fill { height: 100%; background: #527b63; border-radius: 20px; }
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
            <small>Espace administrateur · Gestion financière</small>
        </div>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Retour au tableau de bord</a>
    </div>
</header>

<main class="container main-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title mb-1">Gestion financière</h2>
            <p class="text-muted mb-0">Suivi des recettes, des dépenses et du résultat estimé.</p>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="annee" class="form-label mb-0">Année</label>
            <select name="annee" id="annee" class="form-select" onchange="this.form.submit()">
                <?php for ($y = $anneeActuelle; $y >= $anneeActuelle - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $annee === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <a href="depenses.php" class="btn btn-success">Gérer les autres dépenses</a>
    </div>

    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger"><?= finance_e($erreur) ?></div>
    <?php else: ?>
        <div class="alert alert-info">
            Les recettes comptabilisées concernent uniquement les commandes livrées.
            Les dépenses comprennent les approvisionnements et les autres charges saisies.
            Le résultat affiché ne déduit pas les charges qui n'ont pas été enregistrées.
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="finance-label">Recettes des commandes livrées</div>
                    <p class="finance-value"><?= finance_e(finance_fcfa($recettes)) ?></p>
                    <small class="text-muted"><?= $commandesLivrees ?> commande(s) livrée(s)</small>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="finance-label">Total des dépenses</div>
                    <p class="finance-value"><?= finance_e(finance_fcfa($depenses)) ?></p>
                    <small class="text-muted">
                        Approvisionnements : <?= finance_e(finance_fcfa($depensesAppro)) ?><br>
                        Autres dépenses : <?= finance_e(finance_fcfa($autresDepenses)) ?>
                    </small>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="finance-label">Résultat estimé</div>
                    <p class="finance-value <?= $resultatGlobal >= 0 ? 'positive' : 'negative' ?>">
                        <?= finance_e(finance_fcfa($resultatGlobal)) ?>
                    </p>
                    <small class="text-muted">Recettes − toutes les dépenses enregistrées</small>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="finance-label">Année consultée</div>
                    <p class="finance-value"><?= finance_e($annee) ?></p>
                    <small class="text-muted">Rapport annuel</small>
                </div>
            </div>
        </div>

        <section class="panel mb-4">
            <h3 class="h5 mb-3">Rapport mensuel — <?= finance_e($annee) ?></h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th>Recettes</th>
                            <th>Dépenses</th>
                            <th>Résultat estimé</th>
                            <th>Comparaison</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rapportMensuel as $ligne): ?>
                        <?php
                            $maximum = max($ligne['recettes'], $ligne['depenses'], 1);
                            $largeur = min(100, ($ligne['recettes'] / $maximum) * 100);
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= finance_e($ligne['nom']) ?></td>
                            <td><?= finance_e(finance_fcfa($ligne['recettes'])) ?></td>
                            <td><?= finance_e(finance_fcfa($ligne['depenses'])) ?></td>
                            <td class="<?= $ligne['resultat'] >= 0 ? 'positive' : 'negative' ?> fw-semibold">
                                <?= finance_e(finance_fcfa($ligne['resultat'])) ?>
                            </td>
                            <td>
                                <div class="bar-track" title="Recettes par rapport au montant le plus élevé du mois">
                                    <div class="bar-fill" style="width: <?= finance_e($largeur) ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <section class="panel">
                    <h3 class="h5 mb-3">Derniers approvisionnements</h3>
                    <?php if (!$derniersApprovisionnements): ?>
                        <p class="text-muted mb-0">Aucun approvisionnement pour cette année.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Référence</th><th>Fournisseur</th><th>Montant</th><th>Date</th></tr></thead>
                                <tbody>
                                <?php foreach ($derniersApprovisionnements as $a): ?>
                                    <tr>
                                        <td><?= finance_e($a['reference']) ?></td>
                                        <td><?= finance_e($a['fournisseur']) ?></td>
                                        <td><?= finance_e(finance_fcfa($a['montant_total'])) ?></td>
                                        <td><?= finance_e(date('d/m/Y', strtotime($a['date_approvisionnement']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <a href="approvisionnements.php" class="btn btn-outline-success btn-sm mt-3">Voir tous les approvisionnements</a>
                </section>
            </div>
            <div class="col-12 col-lg-6">
                <section class="panel">
                    <h3 class="h5 mb-3">Dernières commandes livrées</h3>
                    <?php if (!$dernieresCommandes): ?>
                        <p class="text-muted mb-0">Aucune commande livrée pour cette année.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Commande</th><th>Client</th><th>Montant</th><th>Date</th></tr></thead>
                                <tbody>
                                <?php foreach ($dernieresCommandes as $c): ?>
                                    <tr>
                                        <td><?= finance_e($c['numero_commande']) ?></td>
                                        <td><?= finance_e($c['nom_livraison'] ?? 'Client') ?></td>
                                        <td><?= finance_e(finance_fcfa($c['montant_total'])) ?></td>
                                        <td><?= finance_e(date('d/m/Y', strtotime($c['date_commande']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <a href="commandes.php" class="btn btn-outline-success btn-sm mt-3">Voir toutes les commandes</a>
                </section>
            </div>
        </div>
        <section class="panel mt-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h3 class="h5 mb-0">Dernières autres dépenses</h3>
                <a href="depenses.php" class="btn btn-outline-success btn-sm">Gérer les dépenses</a>
            </div>
            <?php if (empty($dernieresDepenses)): ?>
                <p class="text-muted mb-0">Aucune autre dépense enregistrée pour cette année.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Catégorie</th><th>Description</th><th>Montant</th></tr></thead>
                        <tbody>
                        <?php foreach ($dernieresDepenses as $d): ?>
                            <tr>
                                <td><?= finance_e(date('d/m/Y', strtotime($d['date_depense']))) ?></td>
                                <td><?= finance_e(ucfirst(str_replace('_', ' ', $d['categorie']))) ?></td>
                                <td><?= finance_e($d['description']) ?></td>
                                <td><?= finance_e(finance_fcfa($d['montant'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
