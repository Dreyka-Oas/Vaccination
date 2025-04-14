<?php
ini_set('display_errors', 1); // Keep for development
ini_set('display_startup_errors', 1); // Keep for development
error_reporting(E_ALL); // Keep for development

session_start();

// Vérifier connexion et rôle admin
if (!isset($_SESSION['matricule']) || $_SESSION['role'] !== 'admin') {
    // Using absolute path from document root might be more reliable if structure changes
    // header("Location: /gestion-vaccination/public/index.php"); // Example absolute path
    header("Location: ../../index.php"); // Relative path
    exit();
}

require_once '../../app/config/config.php';
require_once '../../vendor/autoload.php';

use Faker\Factory;

// --- Configuration ---
$action = $_GET['action'] ?? 'liste'; // Action par défaut
$message = ''; // Message interne
$lots_liste = []; // Pour la liste principale
$lot_edit = null; // Pour le formulaire de modification
$available_active_campaigns = []; // Pour le dropdown des campagnes (filtre et formulaire)

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10; // Lots par page
$offset = ($page - 1) * $perPage;
$totalPages = 1;

// Paramètre pour le filtrage par Campagne Active
$filtre_campagne_id = isset($_GET['filtre_campagne_id']) ? filter_var($_GET['filtre_campagne_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : ''; // ID campagne ou ''

// Paramètres pour le tri
$allowed_sort_columns = ['nom_lot', 'nom_vaccin', 'date_expiration', 'nom_campagne', 'lot_id']; // 'nom_vaccin' & 'nom_campagne' sont via jointure/alias
$sort_by = $_GET['sort_by'] ?? 'lot_id'; // Défaut: ID Lot
$sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC'; // Défaut: DESC

// Validation tri
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'lot_id';
}
if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
    $sort_dir = 'DESC';
}

// Construction query string pour filtres/tri
$queryStringParams = [];
if (!empty($filtre_campagne_id)) $queryStringParams['filtre_campagne_id'] = $filtre_campagne_id;
if ($sort_by !== 'lot_id' || $sort_dir !== 'DESC') {
    $queryStringParams['sort_by'] = $sort_by;
    $queryStringParams['sort_dir'] = $sort_dir;
}
$currentQueryString = http_build_query($queryStringParams);
$queryStringPrefix = !empty($currentQueryString) ? '&' . $currentQueryString : '';

try {
    // --- Connexion PDO ---
    $pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Important

    // --- Gestion des requêtes AJAX pour les vaccins ---
    if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_vaccins') {
        header('Content-Type: application/json');
        $campagneId = isset($_GET['campagne_id']) ? (int)$_GET['campagne_id'] : 0;
        $vaccins = [];
        if ($campagneId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT vaccin_id, nom_vaccin FROM vaccins WHERE campagne_id = ? ORDER BY nom_vaccin");
                $stmt->execute([$campagneId]);
                $vaccins = $stmt->fetchAll();
            } catch (PDOException $e) { error_log("Erreur AJAX get_vaccins pour campagne ID {$campagneId}: " . $e->getMessage()); }
        }
        echo json_encode($vaccins); exit;
    }
    // --- Fin gestion AJAX ---

    // --- Récupérer Campagnes ACTIVES pour filtres et formulaires ---
    $stmtCampagnes = $pdo->query("SELECT campagne_id, nom_campagne FROM campagnes WHERE is_active = TRUE ORDER BY nom_campagne");
    $available_active_campaigns = $stmtCampagnes->fetchAll();


    // --- 1. TRAITER LES REQUÊTES POST (Ajout / Modification) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $currentPage = isset($_POST['page']) ? (int)$_POST['page'] : 1; if ($currentPage < 1) $currentPage = 1;
        $matricule = $_SESSION['matricule'];

        // Construire URL de redirection (conserve filtre/tri)
        $redirectBaseUrl = basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix;

        $nom_lot = $_POST['nom_lot'] ?? null;
        $date_expiration = $_POST['date_expiration'] ?? null;
        $campagne_id = $_POST['campagne_id'] ?? null;
        $vaccin_id = $_POST['vaccin_id'] ?? null;

        // --- Action AJOUTER ---
        if ($action == 'ajouter') {
            if (empty($nom_lot) || empty($date_expiration) || empty($campagne_id) || empty($vaccin_id)) {
                 $_SESSION['message'] = "Erreur : Tous les champs sont requis."; $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_lots.php?action=ajouter" . $queryStringPrefix); exit(); // Conserve état pour retour
            }
            $vaccinCampagneCheckStmt = $pdo->prepare("SELECT 1 FROM vaccins WHERE vaccin_id = ? AND campagne_id = ?");
            $vaccinCampagneCheckStmt->execute([(int)$vaccin_id, (int)$campagne_id]);
            if ($vaccinCampagneCheckStmt->fetchColumn() === false) {
                 $_SESSION['message'] = "Erreur : Le vaccin ne correspond pas à la campagne."; $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_lots.php?action=ajouter" . $queryStringPrefix); exit();
            }

            try {
                $sql = "INSERT INTO lots (nom_lot, date_expiration, campagne_id, vaccin_id, matricule_creation) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom_lot, $date_expiration, (int)$campagne_id, (int)$vaccin_id, $matricule]);
                $_SESSION['message'] = "Lot '{$nom_lot}' ajouté."; $_SESSION['message_type'] = 'success';
                header("Location: " . $redirectBaseUrl . "&page=1"); exit(); // Retour page 1 liste filtrée/triée
            } catch (PDOException $e) {
                 // Gestion erreurs (unique, fk...)
                 if ($e->getCode() == '23505') $_SESSION['message'] = "Erreur : Le nom de lot '{$nom_lot}' existe déjà.";
                 else { error_log("Erreur ajout lot: " . $e->getMessage()); $_SESSION['message'] = "Erreur lors de l'ajout du lot."; }
                 $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_lots.php?action=ajouter" . $queryStringPrefix); exit();
            }
        }
        // --- Action MODIFIER ---
        elseif ($action == 'modifier' && isset($_POST['lot_id'])) {
            $lot_id = (int)$_POST['lot_id'];
             if (empty($nom_lot) || empty($date_expiration) || empty($campagne_id) || empty($vaccin_id)) {
                 $_SESSION['message'] = "Erreur : Tous les champs sont requis."; $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_lots.php?action=modifier&id=" . $lot_id . "&page=" . $currentPage . $queryStringPrefix); exit();
             }
            $vaccinCampagneCheckStmt = $pdo->prepare("SELECT 1 FROM vaccins WHERE vaccin_id = ? AND campagne_id = ?");
            $vaccinCampagneCheckStmt->execute([(int)$vaccin_id, (int)$campagne_id]);
            if ($vaccinCampagneCheckStmt->fetchColumn() === false) {
                 $_SESSION['message'] = "Erreur : Le vaccin ne correspond pas à la campagne."; $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_lots.php?action=modifier&id=" . $lot_id . "&page=" . $currentPage . $queryStringPrefix); exit();
            }

             try {
                $sql = "UPDATE lots SET nom_lot = ?, date_expiration = ?, campagne_id = ?, vaccin_id = ?, matricule_creation = ? WHERE lot_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom_lot, $date_expiration, (int)$campagne_id, (int)$vaccin_id, $matricule, $lot_id]);
                $_SESSION['message'] = "Lot '{$nom_lot}' modifié."; $_SESSION['message_type'] = 'success';
                header("Location: " . $redirectBaseUrl . "&page=" . $currentPage); exit(); // Retour page actuelle liste filtrée/triée
            } catch (PDOException $e) {
                 // Gestion erreurs (unique, fk...)
                 if ($e->getCode() == '23505') $_SESSION['message'] = "Erreur : Le nom de lot '{$nom_lot}' existe déjà.";
                 else { error_log("Erreur modif lot ID {$lot_id}: " . $e->getMessage()); $_SESSION['message'] = "Erreur lors de la modification."; }
                 $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_lots.php?action=modifier&id=" . $lot_id . "&page=" . $currentPage . $queryStringPrefix); exit();
            }
        }
    }

    // --- 2. TRAITER LES ACTIONS GET QUI MODIFIENT LES DONNÉES ---
    // --- Action GENERER ---
    elseif ($action == 'generer_lots') {
        // ... (Logique de génération inchangée) ...
        $stmtVaccinsActifs = $pdo->query("SELECT v.vaccin_id, v.campagne_id FROM vaccins v JOIN campagnes c ON v.campagne_id = c.campagne_id WHERE c.is_active = TRUE");
        $validVaccinData = $stmtVaccinsActifs->fetchAll();
        if (empty($available_active_campaigns) || empty($validVaccinData)) {
             $_SESSION['message'] = "Impossible de générer : Aucune campagne active ou vaccin associé."; $_SESSION['message_type'] = 'warning';
             header("Location: gerer_lots.php?action=liste" . $queryStringPrefix); exit();
        }
        $matricule_creation = $_SESSION['matricule'] ?? null;
        if (empty($matricule_creation)) { header("Location: index.php"); exit(); }
        $userCheckStmt = $pdo->prepare("SELECT 1 FROM users WHERE matricule = ?"); $userCheckStmt->execute([$matricule_creation]);
        if ($userCheckStmt->fetchColumn() === false) { header("Location: gerer_lots.php?action=liste" . $queryStringPrefix); exit(); }

        error_log("[DEBUG] Action: generer_lots");
        $nombre = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 10; if ($nombre < 1) $nombre = 1; if ($nombre > 100) $nombre = 100;
        $faker = Factory::create('fr_FR'); $count_success = 0;
        $sqlInsert = "INSERT INTO lots (nom_lot, date_expiration, campagne_id, vaccin_id, matricule_creation) VALUES (?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);

        for ($i = 0; $i < $nombre; $i++) {
            try {
                $randomVaccinInfo = $faker->randomElement($validVaccinData); $selected_vaccin_id = $randomVaccinInfo['vaccin_id']; $selected_campagne_id = $randomVaccinInfo['campagne_id'];
                $nom_lot_gen = 'LOT-' . $faker->unique()->bothify('??######??'); $date_exp_gen = $faker->dateTimeBetween('+3 months', '+24 months')->format('Y-m-d');
                if ($stmtInsert->execute([ $nom_lot_gen, $date_exp_gen, $selected_campagne_id, $selected_vaccin_id, $matricule_creation ])) $count_success++;
             } catch (\PDOException $e) { error_log("[ERROR] Génération lot PDO: " . $e->getMessage()); $faker->unique(true); }
               catch (\Throwable $e) { error_log("[ERROR] Génération lot General: " . $e->getMessage()); }
        }
        $faker->unique(true);
        $_SESSION['message'] = sprintf("%d/%d lots générés.", $count_success, $nombre); $_SESSION['message_type'] = ($count_success > 0) ? 'success' : 'danger';
        header("Location: gerer_lots.php?action=liste" . $queryStringPrefix . "&page=1"); exit(); // Redirige vers liste filtrée/triée page 1
    }
    // --- Action SUPPRIMER TOUS ---
    elseif ($action == 'supprimer_tous_lots') {
        // ... (Logique suppression globale inchangée) ...
        error_log("[DEBUG] Action: supprimer_tous_lots");
        try {
            $pdo->beginTransaction();
            $deletedRecs = $pdo->exec("DELETE FROM vaccination_records WHERE lot_id IS NOT NULL"); // Condition WHERE non nécessaire mais ok
            $deletedLots = $pdo->exec("DELETE FROM lots");
            $pdo->commit();
            $_SESSION['message'] = "{$deletedLots} lots et {$deletedRecs} enregistrements supprimés."; $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) { $pdo->rollBack(); error_log("[ERROR] suppr tous lots: " . $e->getMessage()); $_SESSION['message'] = "Erreur suppression totale."; $_SESSION['message_type'] = 'danger'; }
        header("Location: gerer_lots.php?action=liste" . $queryStringPrefix . "&page=1"); exit(); // Redirige vers liste filtrée/triée page 1
    }
    // --- Action SUPPRIMER UN LOT ---
    elseif ($action == 'supprimer' && isset($_GET['id'])) {
        // ... (Logique suppression individuelle inchangée) ...
         $lot_id = (int)$_GET['id']; error_log("[DEBUG] Action: supprimer lot ID: " . $lot_id);
         try {
            $pdo->beginTransaction();
            $stmtDelRecs = $pdo->prepare("DELETE FROM vaccination_records WHERE lot_id = ?"); $stmtDelRecs->execute([$lot_id]); $deletedRecs = $stmtDelRecs->rowCount();
            $stmtDelLot = $pdo->prepare("DELETE FROM lots WHERE lot_id = ?"); $stmtDelLot->execute([$lot_id]); $rowCount = $stmtDelLot->rowCount();
            $pdo->commit();
            if ($rowCount > 0) $_SESSION['message'] = "Lot supprimé" . ($deletedRecs > 0 ? " (avec {$deletedRecs} enregistrement(s))" : "") . "."; else $_SESSION['message'] = "Lot non trouvé."; $_SESSION['message_type'] = $rowCount > 0 ? 'success' : 'warning';
         } catch (PDOException $e) { $pdo->rollBack(); error_log("[ERROR] suppr lot ID {$lot_id}: " . $e->getMessage()); $_SESSION['message'] = "Erreur suppression lot."; $_SESSION['message_type'] = 'danger'; }
         header("Location: gerer_lots.php?action=liste&page=" . $page . $queryStringPrefix); exit(); // Redirige vers page actuelle liste filtrée/triée
    }


    // --- 3. PRÉPARER LES DONNÉES POUR L'AFFICHAGE ---

    // --- Préparation pour afficher le formulaire de MODIFICATION (GET) ---
    elseif ($action == 'modifier' && isset($_GET['id'])) {
        error_log("[DEBUG] Action: modifier lot (GET), ID: " . $_GET['id']);
        $lot_id = (int)$_GET['id'];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1; if ($page < 1) $page = 1; // Conserver page + filtre/tri pour Annuler

        $stmt = $pdo->prepare("SELECT lot_id, nom_lot, date_expiration, campagne_id, vaccin_id FROM lots WHERE lot_id = ?");
        $stmt->execute([$lot_id]);
        $lot_edit = $stmt->fetch();

        if (!$lot_edit) { $_SESSION['message'] = "Lot non trouvé."; $_SESSION['message_type'] = 'warning'; header("Location: gerer_lots.php?action=liste" . $queryStringPrefix); exit(); }
        if (empty($available_active_campaigns)) { $_SESSION['message_warning'] = "Attention : Aucune campagne active disponible."; }
    }

    // --- Préparation pour afficher la LISTE des lots ---
    elseif ($action == 'liste') {
        error_log("[DEBUG] Action: liste lot, Page: {$page}, Filtre Campagne: '{$filtre_campagne_id}', Sort: {$sort_by} {$sort_dir}");

        // Construction WHERE et paramètres pour le filtre
        $whereConditions = [];
        $params = [];
        if (!empty($filtre_campagne_id)) {
            $whereConditions[] = "l.campagne_id = ?";
            $params[] = $filtre_campagne_id;
        }
        $sqlWhere = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

        // 1. Compter total filtré
        $sqlCount = "SELECT COUNT(l.lot_id)
                     FROM lots l " . $sqlWhere; // Pas besoin de jointures pour compter si on filtre juste sur lots.campagne_id
        $totalStmt = $pdo->prepare($sqlCount);
        $totalStmt->execute($params);
        $totalLots = $totalStmt->fetchColumn();

        // 2. Calculer pagination basée sur total filtré
        $totalPages = ceil($totalLots / $perPage); if ($totalPages < 1) $totalPages = 1;
        if ($page > $totalPages) { $page = $totalPages; }
        $offset = max(0, ($page - 1) * $perPage); if ($page < 1) { $page = 1; $offset = 0;}

        // 3. Construire ORDER BY
         $orderByClause = " ORDER BY ";
         $sortCol = $sort_by; // Variable validée

         // Mapper paramètre user vers colonne DB/alias
         $colToSortBy = '';
         switch ($sortCol) {
             case 'nom_lot':        $colToSortBy = 'l.nom_lot'; break;
             case 'nom_vaccin':     $colToSortBy = 'v.nom_vaccin'; break;
             case 'date_expiration':$colToSortBy = 'l.date_expiration'; break;
             case 'nom_campagne':   $colToSortBy = 'c.nom_campagne'; break;
             case 'lot_id':
             default:               $colToSortBy = 'l.lot_id'; break;
         }

         // Appliquer LOWER() aux chaînes
         if (in_array($sort_by, ['nom_lot', 'nom_vaccin', 'nom_campagne'])) {
             $orderByClause .= "LOWER(" . $colToSortBy . ") " . $sort_dir;
         } else { // Date ou ID
             $orderByClause .= $colToSortBy . " " . $sort_dir;
         }
         // Tri secondaire
          if ($sort_by !== 'lot_id') { $orderByClause .= ", l.lot_id ASC"; }


        // 4. Récupérer la liste filtrée, triée, paginée
        $sqlList = "
            SELECT l.lot_id, l.nom_lot, l.date_expiration, c.nom_campagne, v.nom_vaccin
            FROM lots l
            JOIN campagnes c ON l.campagne_id = c.campagne_id
            JOIN vaccins v ON l.vaccin_id = v.vaccin_id
            {$sqlWhere}
            {$orderByClause}
            LIMIT ? OFFSET ?
        ";
        $stmtList = $pdo->prepare($sqlList);

        $bindIndex = 1;
        foreach ($params as $param) { $stmtList->bindValue($bindIndex++, $param, PDO::PARAM_INT); }
        $stmtList->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $stmtList->bindValue($bindIndex++, $offset, PDO::PARAM_INT);

        $stmtList->execute();
        $lots_liste = $stmtList->fetchAll();
        error_log("[DEBUG] Nombre de lots récupérés (filtré/trié/paginé): " . count($lots_liste));
    }

} catch (PDOException $e) {
     error_log("[FATAL PDOException in gerer_lots.php] Action: {$action}, SortBy: {$sort_by}, Filtre: {$filtre_campagne_id}. Error: " . $e->getMessage());
     $_SESSION['message'] = "Erreur base de données."; $_SESSION['message_type'] = 'danger';
     $fallbackRedirect = "gerer_lots.php?action=liste" . $queryStringPrefix;
     if (isset($action) && !in_array($action, ['liste', 'ajouter', 'modifier'])) { header("Location: " . $fallbackRedirect); exit(); }
     else { $message = $_SESSION['message']; unset($_SESSION['message']); $lots_liste = []; $lot_edit = null; $totalPages = 1; $page = 1; }
} catch (Exception $e) {
    error_log("[FATAL Exception in gerer_lots.php] " . $e->getMessage());
    $_SESSION['message'] = "Erreur interne."; $_SESSION['message_type'] = 'danger';
    header("Location: gerer_lots.php?action=liste" . $queryStringPrefix); exit();
}

// --- Fonction Helper pour les liens de tri ---
function renderSortLink($columnName, $label, $currentSortBy, $currentSortDir, $currentFilterCampagneId) {
    $newSortDir = ($currentSortBy === $columnName && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';
    $linkParams = ['action' => 'liste'];
    if (!empty($currentFilterCampagneId)) $linkParams['filtre_campagne_id'] = $currentFilterCampagneId; // Conserve filtre
    $linkParams['sort_by'] = $columnName;
    $linkParams['sort_dir'] = $newSortDir;

    $url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($linkParams);
    $iconClass = 'fas fa-sort'; $thClass = '';
    if ($currentSortBy === $columnName) {
        $iconClass = ($currentSortDir === 'ASC') ? 'fas fa-sort-up' : 'fas fa-sort-down';
        $thClass = 'sorted';
    }
    echo "<th class=\"{$thClass}\"><a href=\"{$url}\">" . htmlspecialchars($label) . "<i class=\"sort-icon {$iconClass}\"></i></a></th>";
}

// --- 4. AFFICHAGE HTML ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Lots de Vaccins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #0d6efd; text-decoration: underline; }
        th .sort-icon { margin-left: 5px; color: #adb5bd; }
        th.sorted .sort-icon { color: #000; }
        .expired-date { color: red; font-weight: bold; }
        .soon-expired-date { color: orange; }
        .required-field::after { content: ' *'; color: red; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
        .filter-form { background-color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem; border: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="card">
             <div class="header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="mb-1"><i class="fas fa-boxes me-2"></i>Gestion des Lots</h1>
                        <p class="mb-0 small text-white-50">Suivi, filtrage et tri des lots</p>
                    </div>
                    <?php if ($action == 'liste'): ?>
                        <div class="btn-group" role="group">
                            <a href="gerer_lots.php?action=ajouter<?= $queryStringPrefix ?>" class="btn btn-sm btn-light" title="Ajouter lot"><i class="fas fa-plus me-1 text-success"></i>Nouveau</a>
                            <button type="button" class="btn btn-sm btn-light" onclick="openGenerateLotsModal()" title="Générer tests"><i class="fas fa-magic me-1 text-info"></i>Générer</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllLotsModal()" title="Supprimer tout!"><i class="fas fa-trash-alt me-1"></i>Tout Suppr.</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php // --- Affichage des messages --- ?>
            <?php if (isset($_SESSION['message'])): ?> <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?> alert-dismissible fade show m-3"> <?= nl2br(htmlspecialchars($_SESSION['message'])) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>
            <?php if (isset($_SESSION['message_warning'])): ?> <div class="alert alert-warning alert-dismissible fade show m-3"> <?= nl2br(htmlspecialchars($_SESSION['message_warning'])) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php unset($_SESSION['message_warning']); endif; ?>
            <?php if (!empty($message)): ?> <div class="alert alert-danger m-3"><?= nl2br(htmlspecialchars($message)) ?></div> <?php endif; ?>


            <?php // --- Affichage Formulaire ou Liste --- ?>

            <?php // FORMULAIRE AJOUTER / MODIFIER ?>
            <?php if ($action == 'ajouter' || ($action == 'modifier' && $lot_edit)): ?>
                <div class="card-body">
                    <h3 class="mb-4 border-bottom pb-3 fs-5"> <?= ($action == 'ajouter') ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter un lot' : '<i class="fas fa-edit me-2 text-primary"></i>Modifier: '.htmlspecialchars($lot_edit['nom_lot']) ?> </h3>
                    <?php if (empty($available_active_campaigns)): ?>
                         <div class="alert alert-warning"> Impossible d'ajouter/modifier : Aucune campagne active trouvée. <a href="gerer_campagnes.php">Gérer</a>. <a href="gerer_lots.php?action=liste<?= $queryStringPrefix ?>" class="btn btn-secondary btn-sm ms-2">Retour</a> </div>
                    <?php else: ?>
                        <form method="post" action="gerer_lots.php?action=<?= $action ?><?= $queryStringPrefix // Conserve état pour redirection post ?>" id="lotForm">
                            <?php if ($action == 'modifier'): ?><input type="hidden" name="lot_id" value="<?= htmlspecialchars($lot_edit['lot_id']) ?>"><?php endif; ?>
                            <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6"> <label for="nom_lot" class="form-label required-field">Nom / N° Lot</label> <input type="text" class="form-control" id="nom_lot" name="nom_lot" value="<?= htmlspecialchars($lot_edit['nom_lot'] ?? '') ?>" required maxlength="255"> <div class="form-text">Identifiant unique.</div> </div>
                                <div class="col-md-6"> <label for="date_expiration" class="form-label required-field">Expiration</label> <input type="date" class="form-control" id="date_expiration" name="date_expiration" value="<?= htmlspecialchars($lot_edit['date_expiration'] ?? '') ?>" required min="<?= date('Y-m-d') ?>"> </div>
                            </div>
                            <div class="row g-3 mb-4">
                                 <div class="col-md-6"> <label for="campagne_id" class="form-label required-field">Campagne (Active)</label> <select class="form-select" id="campagne_id" name="campagne_id" required> <option value="">-- Sélectionner --</option> <?php foreach ($available_active_campaigns as $camp): $selected = (isset($lot_edit['campagne_id']) && $lot_edit['campagne_id'] == $camp['campagne_id']); ?> <option value="<?= $camp['campagne_id'] ?>" <?= $selected ? 'selected' : '' ?>> <?= htmlspecialchars($camp['nom_campagne']) ?> </option> <?php endforeach; ?> </select> <div class="form-text">Seules les campagnes actives.</div> </div>
                                 <div class="col-md-6"> <label for="vaccin_id" class="form-label required-field">Vaccin</label> <select class="form-select" id="vaccin_id" name="vaccin_id" required disabled> <option value="">-- Choisir campagne d'abord --</option> </select> <div class="form-text">Doit appartenir à la campagne.</div> <div id="vaccin-loading" class="text-muted small mt-1" style="display: none;"><i class="fas fa-spinner fa-spin me-1"></i>Chargement...</div> <div id="vaccin-error" class="text-danger small mt-1" style="display: none;"><i class="fas fa-exclamation-triangle me-1"></i>Erreur.</div> </div>
                            </div>
                            <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2"> <a href="gerer_lots.php?action=liste&page=<?= htmlspecialchars($page) ?><?= $queryStringPrefix // Conserve état pour Annuler ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a> <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fas fa-save me-1"></i><?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?></button> </div>
                        </form>
                    <?php endif; ?>
                </div>

            <?php // LISTE DES LOTS ?>
            <?php elseif ($action == 'liste'): ?>
                 <!-- Formulaire Filtre Campagne -->
                 <div class="filter-form mx-3 mt-3">
                    <form method="GET" action="gerer_lots.php">
                        <input type="hidden" name="action" value="liste">
                        <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) // Conserve tri actuel ?>">
                        <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sort_dir) // Conserve tri actuel ?>">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-6"> <label for="filtre_campagne_id" class="form-label small">Filtrer par Campagne (Active)</label> <select class="form-select form-select-sm" id="filtre_campagne_id" name="filtre_campagne_id"> <option value="" <?= ($filtre_campagne_id === '') ? 'selected' : '' ?>>-- Toutes --</option> <?php foreach ($available_active_campaigns as $camp): ?> <option value="<?= $camp['campagne_id'] ?>" <?= ($filtre_campagne_id == $camp['campagne_id']) ? 'selected' : '' ?>> <?= htmlspecialchars($camp['nom_campagne']) ?> </option> <?php endforeach; ?> </select> </div>
                            <div class="col-md-6 d-flex gap-2"> <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button> <a href="gerer_lots.php?action=liste" class="btn btn-secondary btn-sm flex-grow-1" title="Réinitialiser filtre et tri"><i class="fas fa-times me-1"></i>Reset</a> </div>
                        </div>
                    </form>
                 </div>

                 <div class="table-responsive">
                    <?php if (!empty($lots_liste)): ?>
                        <table class="table table-striped table-hover mb-0">
                             <thead> <tr> <?php renderSortLink('nom_lot', 'Nom Lot', $sort_by, $sort_dir, $filtre_campagne_id); ?> <?php renderSortLink('nom_vaccin', 'Vaccin', $sort_by, $sort_dir, $filtre_campagne_id); ?> <?php renderSortLink('date_expiration', 'Expire le', $sort_by, $sort_dir, $filtre_campagne_id); ?> <?php renderSortLink('nom_campagne', 'Campagne', $sort_by, $sort_dir, $filtre_campagne_id); ?> <th class="text-center" style="width: 120px;">Actions</th> </tr> </thead>
                            <tbody>
                                <?php $today = new DateTime(); $today->setTime(0,0,0); $warning_limit = (new DateTime())->modify('+30 days')->setTime(0,0,0); ?>
                                <?php foreach ($lots_liste as $lot): ?>
                                    <?php $date_class = ''; $formatted_date = '-'; try { if ($lot['date_expiration']) { $expiration_date = new DateTime($lot['date_expiration']); $expiration_date->setTime(0,0,0); if ($expiration_date < $today) $date_class = 'expired-date'; elseif ($expiration_date < $warning_limit) $date_class = 'soon-expired-date'; $formatted_date = $expiration_date->format('d/m/Y'); } } catch (Exception $ex) { $date_class = 'text-danger'; $formatted_date = 'Date invalide'; } ?>
                                    <tr>
                                        <td><?= htmlspecialchars($lot['nom_lot']) ?></td>
                                        <td><?= htmlspecialchars($lot['nom_vaccin']) ?></td>
                                        <td class="<?= $date_class ?>"><?= $formatted_date ?></td>
                                        <td><?= htmlspecialchars($lot['nom_campagne']) ?></td>
                                        <td class="text-center"> <?php // Liens actions conservent état (page, filtre, tri) ?> <a href="gerer_lots.php?action=modifier&id=<?= $lot['lot_id'] ?>&page=<?= $page ?><?= $queryStringPrefix ?>" class="btn btn-primary btn-action" data-bs-toggle="tooltip" title="Modifier"><i class="fas fa-edit"></i></a> <a href="gerer_lots.php?action=supprimer&id=<?= $lot['lot_id'] ?>&page=<?= $page ?><?= $queryStringPrefix ?>" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer" onclick="return confirm('Supprimer lot \'<?= htmlspecialchars(addslashes($lot['nom_lot']), ENT_QUOTES) ?>\' et enregistrements associés ?')"><i class="fas fa-trash-alt"></i></a> </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="card-body text-center p-4">
                             <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-3"> <?php if (!empty($filtre_campagne_id)): ?> Aucun lot trouvé pour cette campagne. <?php else: ?> Aucun lot enregistré. <?php endif; ?> </p>
                            <?php if (empty($available_active_campaigns)): ?> <p class="text-warning small mb-3">Vérifiez qu'il existe des <a href="gerer_campagnes.php">campagnes actives</a>.</p> <?php endif; ?>
                             <?php // Bouton Reset si filtre ou tri actif ?>
                             <?php if (!empty($filtre_campagne_id) || !empty($currentQueryString)): ?> <a href="gerer_lots.php?action=liste" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser</a><br> <?php endif; ?>
                             <a href="gerer_lots.php?action=ajouter<?= $queryStringPrefix ?>" class="btn btn-success <?= empty($available_active_campaigns) ? 'disabled' : '' ?>"><i class="fas fa-plus me-1"></i>Ajouter un lot</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php // Pagination ?>
                 <?php if (!empty($lots_liste) && $totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation Lots">
                             <ul class="pagination justify-content-center mb-0">
                                 <?php // Liens pagination conservent état (filtre, tri)
                                     $pageUrlBase = "?action=liste"; if (!empty($currentQueryString)) $pageUrlBase .= "&" . $currentQueryString; $pageUrlBase .= "&page=";
                                 ?>
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page - 1) ?>">«</a></li>
                                <?php $linksToShow = 5; $startPage = max(1, $page - floor($linksToShow / 2)); $endPage = min($totalPages, $page + floor($linksToShow / 2)); if ($endPage - $startPage + 1 < $linksToShow) { if ($startPage === 1) { $endPage = min($totalPages, $startPage + $linksToShow - 1); } elseif ($endPage === $totalPages) { $startPage = max(1, $endPage - $linksToShow + 1); } } ?>
                                <?php if ($startPage > 1): ?><li class="page-item"><a class="page-link" href="<?= $pageUrlBase ?>1">1</a></li><?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><?php endif; ?>
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?><li class="page-item <?= $page == $i ? 'active' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . $i ?>"><?= $i ?></a></li><?php endfor; ?>
                                <?php if ($endPage < $totalPages): ?><?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= $pageUrlBase . $totalPages ?>"><?= $totalPages ?></a></li><?php endif; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page + 1) ?>">»</a></li>
                            </ul>
                        </nav>
                    </div>
                 <?php endif; ?>

            <?php endif; ?>

        </div> <?php // Fin card ?>

        <div class="text-center my-4"> <a href="../admin.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour</a> </div>

    </div> <?php // Fin dashboard-container ?>

    <?php // --- Modals (Génération, Suppression globale) --- ?>
    <div class="modal fade" id="generateLotsModal" tabindex="-1"> <div class="modal-dialog modal-sm"> <div class="modal-content"> <form id="generateLotsForm" action="gerer_lots.php" method="get"> <div class="modal-header"> <h6 class="modal-title"><i class="fas fa-magic me-2 text-info"></i>Générer Lots Test</h6> <button type="button" class="btn-close" data-bs-dismiss="modal"></button> </div> <div class="modal-body"> <input type="hidden" name="action" value="generer_lots"> <div class="mb-3"> <label for="nombreLots" class="form-label">Nombre (1-100):</label> <input type="number" class="form-control form-control-sm" id="nombreLots" name="nombre" value="10" min="1" max="100" required> </div> <?php $canGenerate = !empty($available_active_campaigns); if (!$canGenerate) { echo '<small class="text-danger d-block">Requiert campagnes actives.</small>'; } ?> </div> <div class="modal-footer"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-sm btn-primary" <?= !$canGenerate ? 'disabled' : '' ?>>Générer</button> </div> </form> </div> </div> </div>
    <div class="modal fade" id="deleteAllLotsModal" tabindex="-1"> <div class="modal-dialog"> <div class="modal-content"> <form id="deleteAllLotsForm" action="gerer_lots.php" method="GET"> <div class="modal-header bg-danger text-white"> <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button> </div> <div class="modal-body"> <p><strong class="text-danger">Attention : Irréversible !</strong></p> <p>Suppression de <strong>TOUS</strong> les lots et <strong>TOUS</strong> les enregistrements associés.</p> <div class="form-check mt-3"> <input class="form-check-input" type="checkbox" id="confirmDeleteAllLotsCheckbox" required> <label class="form-check-label" for="confirmDeleteAllLotsCheckbox">Je confirme.</label> </div> </div> <div class="modal-footer"> <input type="hidden" name="action" value="supprimer_tous_lots"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-danger" id="confirmDeleteAllLotsBtn" disabled><i class="fas fa-trash-alt me-1"></i>Supprimer Tout</button> </div> </form> </div> </div> </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- JavaScript pour les Modals et Tooltips (Inchangé) ---
        function openGenerateLotsModal() { var m = new bootstrap.Modal(document.getElementById('generateLotsModal')); m.show(); }
        const deleteAllLotsModalEl = document.getElementById('deleteAllLotsModal'); if (deleteAllLotsModalEl) { const c=deleteAllLotsModalEl.querySelector('#confirmDeleteAllLotsCheckbox'), b=deleteAllLotsModalEl.querySelector('#confirmDeleteAllLotsBtn'), i=new bootstrap.Modal(deleteAllLotsModalEl); window.openDeleteAllLotsModal=function(){c.checked=false; b.disabled=true; i.show();} ; c.addEventListener('change', function(){b.disabled = !this.checked;}); }
        document.addEventListener('DOMContentLoaded', function () { var t=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')); t.map(function(e){if(!bootstrap.Tooltip.getInstance(e)){return new bootstrap.Tooltip(e,{delay:{"show":300,"hide":100}, html: true});}}); });


        // --- JavaScript pour le chargement dynamique des Vaccins (Inchangé) ---
        document.addEventListener('DOMContentLoaded', function() {
            const campagneSelect = document.getElementById('campagne_id');
            const vaccinSelect = document.getElementById('vaccin_id');
            const vaccinLoading = document.getElementById('vaccin-loading');
            const vaccinError = document.getElementById('vaccin-error');
            const submitBtn = document.getElementById('submitBtn');
            const lotForm = document.getElementById('lotForm');
            const lotEditData = <?php echo ($action == 'modifier' && isset($lot_edit)) ? json_encode($lot_edit) : 'null'; ?>;

            async function loadVaccinsForCampagne(vaccinToSelect = null) {
                const selectedCampagneId = campagneSelect.value;
                vaccinSelect.innerHTML = '<option value="">-- Sélectionner --</option>';
                vaccinSelect.disabled = true; vaccinLoading.style.display = 'none'; vaccinError.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                if (!selectedCampagneId) { vaccinSelect.innerHTML = '<option value="">-- Choisir campagne d\'abord --</option>'; if (submitBtn) submitBtn.disabled = false; return; }
                vaccinLoading.style.display = 'block';

                try {
                    const response = await fetch(`gerer_lots.php?ajax_action=get_vaccins&campagne_id=${selectedCampagneId}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const vaccins = await response.json();
                    vaccinLoading.style.display = 'none';

                    if (vaccins.length > 0) {
                         vaccinSelect.innerHTML = '<option value="">-- Sélectionner un vaccin --</option>';
                        vaccins.forEach(vaccin => { const o=document.createElement('option'); o.value=vaccin.vaccin_id; o.textContent=vaccin.nom_vaccin; vaccinSelect.appendChild(o); });
                        vaccinSelect.disabled = false; if (submitBtn) submitBtn.disabled = false;
                        if (vaccinToSelect) { vaccinSelect.value = vaccinToSelect; if (vaccinSelect.value !== vaccinToSelect.toString()) console.warn(`Vaccin ID ${vaccinToSelect} non trouvé pour campagne ${selectedCampagneId}.`); }
                    } else { vaccinSelect.innerHTML = '<option value="">-- Aucun vaccin trouvé --</option>'; if (submitBtn) submitBtn.disabled = true; }
                } catch (error) { console.error('Erreur chargement vaccins:', error); vaccinLoading.style.display = 'none'; vaccinError.style.display = 'block'; vaccinSelect.innerHTML = '<option value="">-- Erreur --</option>'; if (submitBtn) submitBtn.disabled = true; }
            }

            if (campagneSelect && vaccinSelect) {
                campagneSelect.addEventListener('change', () => { loadVaccinsForCampagne(); });
                if (lotEditData && lotEditData.campagne_id) { campagneSelect.value = lotEditData.campagne_id; loadVaccinsForCampagne(lotEditData.vaccin_id); }
                else { loadVaccinsForCampagne(); }
            }

            if (lotForm) {
                lotForm.addEventListener('submit', function(event) { if (campagneSelect.value && !vaccinSelect.value) { alert("Sélectionner un vaccin."); event.preventDefault(); vaccinSelect.focus(); } else if (vaccinSelect.disabled && campagneSelect.value) { alert("Impossible: aucun vaccin valide ou erreur."); event.preventDefault(); } });
            }
        });
    </script>
</body>
</html>