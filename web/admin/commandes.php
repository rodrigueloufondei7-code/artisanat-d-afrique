
<?php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

if (
    empty($_SESSION["admin_token"]) ||
    ($_SESSION["admin_user"]["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($valeur): string
{
    return htmlspecialchars((string)($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

$token = $_SESSION["admin_token"];
$csrf = $_SESSION["csrf_token"];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des commandes - Artisanat d'Afrique</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f6f8;
            color: #252b26;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #263b32;
            color: white;
            padding: 18px 0;
        }

        .main-container {
            padding-top: 35px;
            padding-bottom: 50px;
        }

        .page-title {
            color: #263b32;
            font-weight: bold;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 3px 15px rgba(0,0,0,.04);
        }

        .table th {
            white-space: nowrap;
            color: #526158;
            font-size: 13px;
        }

        .table td {
            vertical-align: middle;
        }

        .commande-numero {
            font-weight: bold;
            color: #263b32;
        }

        .badge-statut {
            font-size: 12px;
            padding: 7px 10px;
            border-radius: 20px;
        }

        .details-commande {
            background: #f8faf9;
            border-radius: 10px;
            padding: 18px;
            margin-top: 10px;
        }

        .article-ligne {
            border-bottom: 1px solid #e9ecef;
            padding: 10px 0;
        }

        .article-ligne:last-child {
            border-bottom: none;
        }

        .btn-vert {
            background: #263b32;
            color: white;
        }

        .btn-vert:hover {
            background: #1b2b24;
            color: white;
        }

        .montant {
            font-weight: bold;
            color: #263b32;
        }

        .actions-commande {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .content-card {
                padding: 12px;
            }
        }
    </style>

<link rel="stylesheet" href="/artisanat_afrique/web/assets/css/refonte.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body>
<?php require __DIR__ . "/_navigation.php"; ?>


<header class="topbar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">Artisanat d'Afrique</h4>
            <small>Espace administrateur</small>
        </div>

        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            Retour au tableau de bord
        </a>
    </div>
</header>

<main class="container main-container">

    <div class="mb-4">
        <h2 class="page-title">Gestion des commandes</h2>
        <p class="text-muted mb-0">
            Consultez et gérez les commandes de vos clients.
        </p>
    </div>

    <div id="message"></div>

    <div class="content-card mb-4">
        <div class="row g-3">

            <div class="col-md-7">
                <label for="recherche" class="form-label">
                    Rechercher une commande
                </label>

                <input
                    type="text"
                    id="recherche"
                    class="form-control"
                    placeholder="Numéro, nom ou email du client..."
                >
            </div>

            <div class="col-md-3">
                <label for="filtreStatut" class="form-label">
                    Filtrer par statut
                </label>

                <select id="filtreStatut" class="form-select">
                    <option value="">Toutes les commandes</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirmee">Confirmées</option>
                    <option value="en_preparation">En préparation</option>
                    <option value="expediee">Expédiées</option>
                    <option value="livree">Livrées</option>
                    <option value="annulee">Annulées</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-vert w-100" onclick="chargerCommandes()">
                    Rechercher
                </button>
            </div>

        </div>
    </div>

    <div class="content-card">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Liste des commandes</h5>

            <button class="btn btn-outline-secondary btn-sm" onclick="chargerCommandes()">
                Actualiser
            </button>
        </div>

        <div id="chargement" class="text-center py-4">
            Chargement des commandes...
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tableCommandes" style="display:none">

                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="listeCommandes"></tbody>

            </table>
        </div>

        <div id="aucuneCommande" class="text-center text-muted py-4" style="display:none">
            Aucune commande trouvée.
        </div>

    </div>

</main>

<!-- Fenêtre des détails -->
<div class="modal fade" id="modalDetails" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Détails de la commande</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="contenuDetails"></div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Fermer
                </button>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

const API_URL = "../../backend/api/index.php?route=orders";
const TOKEN = <?= json_encode($token) ?>;
const CSRF = <?= json_encode($csrf) ?>;

let commandes = [];

const nomsStatuts = {
    en_attente: "En attente",
    confirmee: "Confirmée",
    en_preparation: "En préparation",
    expediee: "Expédiée",
    livree: "Livrée",
    annulee: "Annulée"
};

const couleursStatuts = {
    en_attente: "warning",
    confirmee: "info",
    en_preparation: "primary",
    expediee: "secondary",
    livree: "success",
    annulee: "danger"
};

function echapper(texte) {
    const div = document.createElement("div");
    div.textContent = String(texte ?? "");
    return div.innerHTML;
}

function afficherMessage(message, type = "success") {
    document.getElementById("message").innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show">
            ${echapper(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

function formaterMontant(montant) {
    return Number(montant || 0).toLocaleString("fr-FR") + " FCFA";
}

function formaterDate(date) {
    if (!date) return "Date inconnue";

    const valeur = new Date(String(date).replace(" ", "T"));

    if (Number.isNaN(valeur.getTime())) return echapper(date);

    return valeur.toLocaleString("fr-FR");
}

// Charger les commandes depuis l'API.
async function chargerCommandes() {

    const chargement = document.getElementById("chargement");
    const tableau = document.getElementById("tableCommandes");
    const aucune = document.getElementById("aucuneCommande");

    chargement.style.display = "block";
    chargement.textContent = "Chargement des commandes...";
    tableau.style.display = "none";
    aucune.style.display = "none";

    const recherche = document.getElementById("recherche").value.trim();
    const statut = document.getElementById("filtreStatut").value;

    const params = new URLSearchParams();

    if (recherche) params.set("recherche", recherche);
    if (statut) params.set("statut", statut);

    try {

        const reponse = await fetch(
            API_URL + "&" + params.toString(),
            {
                method: "GET",
                headers: {
                    "Authorization": "Bearer " + TOKEN,
                    "Accept": "application/json"
                }
            }
        );

        const resultat = await reponse.json();

        if (!reponse.ok || resultat.success !== true) {
            throw new Error(resultat.message || "Erreur de chargement.");
        }

        commandes = resultat.data.commandes || [];

        afficherCommandes();

    } catch (erreur) {

        chargement.textContent = "Impossible de charger les commandes.";
        afficherMessage(erreur.message, "danger");
    }
}

// Afficher les commandes dans le tableau.
function afficherCommandes() {

    const tbody = document.getElementById("listeCommandes");
    const tableau = document.getElementById("tableCommandes");
    const chargement = document.getElementById("chargement");
    const aucune = document.getElementById("aucuneCommande");

    tbody.innerHTML = "";
    chargement.style.display = "none";

    if (commandes.length === 0) {
        tableau.style.display = "none";
        aucune.style.display = "block";
        return;
    }

    tableau.style.display = "table";
    aucune.style.display = "none";

    commandes.forEach(commande => {

        const statut = commande.statut;
        const couleur = couleursStatuts[statut] || "secondary";

        const nomClient =
            `${commande.prenom_client || ""} ${commande.nom_client || ""}`.trim();

        const id = Number(commande.id);

        const tr = document.createElement("tr");

        tr.innerHTML = `
            <td>
                <span class="commande-numero">
                    ${echapper(commande.numero_commande)}
                </span>
            </td>

            <td>
                <strong>${echapper(nomClient || commande.nom_livraison)}</strong>
                <br>
                <small class="text-muted">
                    ${echapper(commande.email_client)}
                </small>
            </td>

            <td>${formaterDate(commande.date_commande)}</td>

            <td class="montant">
                ${formaterMontant(commande.montant_total)}
            </td>

            <td>
                <span class="badge bg-${couleur} badge-statut">
                    ${echapper(nomsStatuts[statut] || statut)}
                </span>
            </td>

            <td>
                <div class="actions-commande">

                    <button
                        class="btn btn-sm btn-outline-success"
                        onclick="voirDetails(${id})"
                    >
                        Détails
                    </button>

                    <a
                        href="facture.php?id=${id}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn btn-sm btn-outline-primary"
                    >
                        Facture
                    </a>
                    <a
    href="facture_pdf.php?id=${id}"
    target="_blank"
    rel="noopener noreferrer"
    class="btn btn-sm btn-outline-danger"
>
    PDF
</a>
                </div>
            </td>
        `;

        tbody.appendChild(tr);
    });
}

// Afficher les détails d'une commande.
function voirDetails(id) {

    const commande = commandes.find(c => Number(c.id) === Number(id));

    if (!commande) {
        afficherMessage("Commande introuvable.", "danger");
        return;
    }

    const nomClient =
        `${commande.prenom_client || ""} ${commande.nom_client || ""}`.trim();

    let articlesHTML = "";

    if (commande.articles && commande.articles.length > 0) {

        commande.articles.forEach(article => {

            const quantite = Number(article.quantite);
            const prix = Number(article.prix_unitaire);
            const total = quantite * prix;

            articlesHTML += `
                <div class="article-ligne d-flex justify-content-between gap-3">
                    <div>
                        <strong>${echapper(article.nom_article)}</strong>
                        <br>
                        <small class="text-muted">
                            Quantité : ${quantite}
                        </small>
                    </div>

                    <div class="text-end">
                        ${formaterMontant(total)}
                        <br>
                        <small class="text-muted">
                            ${formaterMontant(prix)} / unité
                        </small>
                    </div>
                </div>
            `;
        });

    } else {
        articlesHTML = "<p class='text-muted'>Aucun article enregistré.</p>";
    }

    let optionsStatuts = "";

    Object.entries(nomsStatuts).forEach(([valeur, nom]) => {

        const selection = valeur === commande.statut ? "selected" : "";

        optionsStatuts += `
            <option value="${valeur}" ${selection}>
                ${nom}
            </option>
        `;
    });

    document.getElementById("contenuDetails").innerHTML = `

        <div class="details-commande">

            <h6>Informations de la commande</h6>

            <p><strong>Numéro :</strong> ${echapper(commande.numero_commande)}</p>
            <p><strong>Date :</strong> ${formaterDate(commande.date_commande)}</p>
            <p><strong>Montant total :</strong> ${formaterMontant(commande.montant_total)}</p>
            <p><strong>Mode de paiement :</strong> ${echapper(commande.mode_paiement)}</p>

            <hr>

            <h6>Informations du client</h6>

            <p><strong>Nom :</strong> ${echapper(nomClient || commande.nom_livraison)}</p>
            <p><strong>Email :</strong> ${echapper(commande.email_client)}</p>
            <p><strong>Téléphone :</strong> ${echapper(commande.telephone_livraison)}</p>
            <p><strong>Adresse :</strong> ${echapper(commande.adresse_livraison)}</p>
            <p><strong>Ville :</strong> ${echapper(commande.ville)}</p>

            <hr>

            <h6>Articles commandés</h6>

            ${articlesHTML}

            <hr>

            <h6>Modifier le statut</h6>

            <div class="d-flex gap-2 flex-wrap">

                <select id="nouveauStatut" class="form-select" style="max-width:250px">
                    ${optionsStatuts}
                </select>

                <button
                    class="btn btn-vert"
                    onclick="modifierStatut(${Number(commande.id)})"
                >
                    Enregistrer
                </button>

            </div>

            <hr>

            <a
                href="facture.php?id=${Number(commande.id)}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-outline-primary"
            >
                Voir la facture
            </a>

        </div>
    `;

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById("modalDetails")
    ).show();
}

// Modifier le statut d'une commande.
async function modifierStatut(id) {

    const statut = document.getElementById("nouveauStatut").value;

    if (!nomsStatuts[statut]) {
        afficherMessage("Statut invalide.", "danger");
        return;
    }

    if (!confirm("Confirmer la modification du statut ?")) {
        return;
    }

    try {

        const reponse = await fetch(API_URL, {
            method: "PUT",
            headers: {
                "Authorization": "Bearer " + TOKEN,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                id: id,
                statut: statut
            })
        });

        const resultat = await reponse.json();

        if (!reponse.ok || resultat.success !== true) {
            throw new Error(resultat.message || "Modification impossible.");
        }

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById("modalDetails")
        ).hide();

        afficherMessage("Le statut de la commande a été modifié.");
        await chargerCommandes();

    } catch (erreur) {

        afficherMessage(erreur.message, "danger");
    }
}

// Chargement initial.
document.addEventListener("DOMContentLoaded", chargerCommandes);

// Recherche avec la touche Entrée.
document.getElementById("recherche").addEventListener("keydown", event => {

    if (event.key === "Enter") {
        chargerCommandes();
    }
});

</script>

</body>
</html>
