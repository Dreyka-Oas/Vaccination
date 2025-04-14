<?php
// ==========================================================================
// Fichier: gerer_vaccins.php
// Description: Gestion CRUD, Filtrage (par campagne active) et Tri des vaccins.
// Date: [Date de modification] // Correction tri type_names
// ==========================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- Sécurité ---
if (!isset($_SESSION['matricule']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php"); // Ajusté
    exit();
}

// --- Inclusions ---
require_once '../../app/config/config.php';
require_once '../../vendor/autoload.php';

use Faker\Factory;

// --- Initialisation ---
$action = $_GET['action'] ?? 'liste';
$message = '';
$vaccins_liste = [];
$vaccin_edit = null;
$associated_type_ids = [];
$available_campaigns = []; // Campagnes actives pour les dropdowns/filtres
$available_types = [];     // Types pour les checkboxes

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalPages = 1;

// Paramètre pour le filtrage par Campagne Active
$filtre_campagne_id = isset($_GET['filtre_campagne_id']) ? filter_var($_GET['filtre_campagne_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : ''; // ID de la campagne ou '' pour toutes

// Paramètres pour le tri
$allowed_sort_columns = ['nom_vaccin', 'nom_campagne', 'type_names', 'description', 'vaccin_id']; // 'nom_campagne' et 'type_names' sont des alias
$sort_by = $_GET['sort_by'] ?? 'vaccin_id'; // Défaut: tri par ID
$sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC'; // Défaut: DESC

// Validation des paramètres de tri
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'vaccin_id';
}
if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
    $sort_dir = 'DESC';
}

// Construction de la chaîne de requête pour conserver filtre/tri dans les liens
$queryStringParams = [];
if (!empty($filtre_campagne_id)) $queryStringParams['filtre_campagne_id'] = $filtre_campagne_id;
if ($sort_by !== 'vaccin_id' || $sort_dir !== 'DESC') {
    $queryStringParams['sort_by'] = $sort_by;
    $queryStringParams['sort_dir'] = $sort_dir;
}
$currentQueryString = http_build_query($queryStringParams);
$queryStringPrefix = !empty($currentQueryString) ? '&' . $currentQueryString : '';

try {
    // --- Connexion DB ---
    $pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Important pour LIMIT/OFFSET

    // --- Récupérer les campagnes actives et les types ---
    $stmtCampagnes = $pdo->query("SELECT campagne_id, nom_campagne FROM campagnes WHERE is_active = TRUE ORDER BY nom_campagne");
    $available_campaigns = $stmtCampagnes->fetchAll(); // Utilisé pour filtre ET formulaire

    $stmtTypes = $pdo->query("SELECT type_vaccin_id, nom_type FROM type_vaccins ORDER BY nom_type");
    $available_types = $stmtTypes->fetchAll();
    // --- Fin récupération dépendances ---


    // --- 1. TRAITER LES REQUÊTES POST (Ajout / Modification) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $currentPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $matricule = $_SESSION['matricule'];

        // Construire l'URL de redirection de base en conservant filtre ET tri
        $redirectBaseUrl = basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix;

        // Données communes
        $nom_vaccin = $_POST['nom_vaccin'] ?? '';
        $description = $_POST['description'] ?? '';
        $campagne_id = $_POST['campagne_id'] ?? null;
        $selected_type_ids = $_POST['type_ids'] ?? [];

        if ($action == 'ajouter') {
            // Validations...
             if (empty($nom_vaccin) || empty($campagne_id) || empty($selected_type_ids)) {
                 if(empty($nom_vaccin)) $_SESSION['message'] = "Erreur : Le nom du vaccin est requis.";
                 elseif(empty($campagne_id)) $_SESSION['message'] = "Erreur : Veuillez sélectionner une campagne.";
                 else $_SESSION['message'] = "Erreur : Veuillez sélectionner au moins un type de vaccin.";
                 $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_vaccins.php?action=ajouter" . $queryStringPrefix);
                 exit();
             }

            $pdo->beginTransaction();
            try {
                $sqlVaccin = "INSERT INTO vaccins (nom_vaccin, description, campagne_id, matricule_creation) VALUES (?, ?, ?, ?) RETURNING vaccin_id";
                $stmtVaccin = $pdo->prepare($sqlVaccin);
                $stmtVaccin->execute([$nom_vaccin, $description, (int)$campagne_id, $matricule]);
                $new_vaccin_id = $stmtVaccin->fetchColumn();
                if (!$new_vaccin_id) throw new Exception("Impossible de créer le vaccin.");

                $sqlAssoc = "INSERT INTO vaccin_type_associations (vaccin_id, type_vaccin_id) VALUES (?, ?)";
                $stmtAssoc = $pdo->prepare($sqlAssoc);
                foreach ($selected_type_ids as $type_id) {
                    $stmtAssoc->execute([$new_vaccin_id, (int)$type_id]);
                }

                $pdo->commit();
                $_SESSION['message'] = "Vaccin ajouté avec succès.";
                $_SESSION['message_type'] = 'success';
                header("Location: " . $redirectBaseUrl . "&page=1");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Erreur ajout vaccin: " . $e->getMessage());
                $_SESSION['message'] = "Erreur lors de l'ajout du vaccin : " . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
                header("Location: gerer_vaccins.php?action=ajouter" . $queryStringPrefix);
                exit();
            }

        } elseif ($action == 'modifier' && isset($_POST['vaccin_id'])) {
             $vaccin_id = (int)$_POST['vaccin_id'];
             // Validations...
             if (empty($nom_vaccin) || empty($campagne_id) || empty($selected_type_ids)) {
                 if(empty($nom_vaccin)) $_SESSION['message'] = "Erreur : Le nom du vaccin est requis.";
                 elseif(empty($campagne_id)) $_SESSION['message'] = "Erreur : Campagne requise.";
                 else $_SESSION['message'] = "Erreur : Au moins un type est requis.";
                 $_SESSION['message_type'] = 'danger';
                 header("Location: gerer_vaccins.php?action=modifier&id=" . $vaccin_id . "&page=" . $currentPage . $queryStringPrefix);
                 exit();
             }

             $pdo->beginTransaction();
             try {
                $sqlVaccin = "UPDATE vaccins SET nom_vaccin = ?, description = ?, campagne_id = ?, matricule_creation = ? WHERE vaccin_id = ?";
                $stmtVaccin = $pdo->prepare($sqlVaccin);
                $stmtVaccin->execute([$nom_vaccin, $description, (int)$campagne_id, $matricule, $vaccin_id]);

                $pdo->prepare("DELETE FROM vaccin_type_associations WHERE vaccin_id = ?")->execute([$vaccin_id]);
                $sqlInsertAssoc = "INSERT INTO vaccin_type_associations (vaccin_id, type_vaccin_id) VALUES (?, ?)";
                $stmtInsertAssoc = $pdo->prepare($sqlInsertAssoc);
                foreach ($selected_type_ids as $type_id) {
                    $stmtInsertAssoc->execute([$vaccin_id, (int)$type_id]);
                }

                $pdo->commit();
                $_SESSION['message'] = "Vaccin modifié avec succès.";
                $_SESSION['message_type'] = 'success';
                header("Location: " . $redirectBaseUrl . "&page=" . $currentPage);
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Erreur modification vaccin ID {$vaccin_id}: " . $e->getMessage());
                $_SESSION['message'] = "Erreur lors de la modification du vaccin : " . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
                header("Location: gerer_vaccins.php?action=modifier&id=" . $vaccin_id . "&page=" . $currentPage . $queryStringPrefix);
                exit();
            }
        }
    }

    // --- 2. TRAITER LES ACTIONS GET QUI MODIFIENT LES DONNÉES ---

    // --- Action : Générer des vaccins de test ---
    elseif ($action == 'generer_vaccins') {
        if (empty($available_campaigns) || empty($available_types)) {
             if (empty($available_campaigns)) $_SESSION['message'] = "Impossible de générer : Aucune campagne active n'existe.";
             else $_SESSION['message'] = "Impossible de générer : Aucun type de vaccin n'existe.";
             $_SESSION['message_type'] = 'warning';
            header("Location: gerer_vaccins.php?action=liste" . $queryStringPrefix); exit();
        }
        error_log("[DEBUG] Action: generer_vaccins");
        $nombre = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 5;
        if ($nombre < 1) $nombre = 1; if ($nombre > 50) $nombre = 50;
        $matricule_creation = $_SESSION['matricule'] ?? null;
        if (empty($matricule_creation)) { $_SESSION['message'] = "Erreur : Session invalide."; $_SESSION['message_type'] = 'danger'; header("Location: index.php"); exit(); }
        $userCheckStmt = $pdo->prepare("SELECT 1 FROM users WHERE matricule = ?"); $userCheckStmt->execute([$matricule_creation]);
        if ($userCheckStmt->fetchColumn() === false) { $_SESSION['message'] = "Erreur : Utilisateur invalide."; $_SESSION['message_type'] = 'danger'; header("Location: gerer_vaccins.php?action=liste" . $queryStringPrefix); exit(); }

        $faker = Factory::create('fr_FR'); $count_success = 0;
        $sqlVaccin = "INSERT INTO vaccins (nom_vaccin, description, campagne_id, matricule_creation) VALUES (?, ?, ?, ?) RETURNING vaccin_id";
        $sqlAssoc = "INSERT INTO vaccin_type_associations (vaccin_id, type_vaccin_id) VALUES (?, ?)";
        $stmtVaccin = $pdo->prepare($sqlVaccin); $stmtAssoc = $pdo->prepare($sqlAssoc);
        $campaignIds = array_column($available_campaigns, 'campagne_id'); $typeIds = array_column($available_types, 'type_vaccin_id');

        for ($i = 0; $i < $nombre; $i++) {
            $nom_vaccin_gen = 'Vaccin ' . $faker->unique()->company . ' ' . $faker->randomElement(['Alpha', 'Beta', 'Gamma']) . '-' . $faker->randomNumber(3);
            $description_gen = $faker->catchPhrase; $random_campagne_id = $faker->randomElement($campaignIds);
            $numTypesToAssociate = $faker->numberBetween(1, min(count($typeIds), 3)); $random_type_ids = $faker->randomElements($typeIds, $numTypesToAssociate);
            try {
                $pdo->beginTransaction(); $stmtVaccin->execute([$nom_vaccin_gen, $description_gen, $random_campagne_id, $matricule_creation]);
                $new_vaccin_id = $stmtVaccin->fetchColumn(); if (!$new_vaccin_id) throw new Exception("ID non récupéré");
                foreach ($random_type_ids as $type_id) { $stmtAssoc->execute([$new_vaccin_id, $type_id]); }
                $pdo->commit(); $count_success++;
            } catch (\Throwable $e) { $pdo->rollBack(); error_log("[ERROR] Génération vaccin test échouée: " . $e->getMessage()); }
        }
        $faker->unique(true);

        $_SESSION['message'] = sprintf( "%d/%d vaccins de test générés.", $count_success, $nombre );
        $_SESSION['message_type'] = ($count_success == $nombre) ? 'success' : (($count_success > 0) ? 'warning' : 'danger');

        header("Location: gerer_vaccins.php?action=liste" . $queryStringPrefix . "&page=1");
        exit();
    }

    // --- Action: Supprimer tous les vaccins ---
    // ATTENTION: ON DELETE CASCADE est sur vaccin_type_associations, mais RESTRICT sur lots et campagnes.
    // Il faut supprimer les enregistrements et les lots AVANT les vaccins.
    elseif ($action == 'supprimer_tous_vaccins') {
         error_log("[DEBUG] Action: supprimer_tous_vaccins");
        try {
            $pdo->beginTransaction();
             // 1. Supprimer les enregistrements qui dépendent des lots de TOUS les vaccins
             $pdo->exec("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id IS NOT NULL)");
             // 2. Supprimer TOUS les lots qui dépendent des vaccins
             $pdo->exec("DELETE FROM lots WHERE vaccin_id IS NOT NULL");
             // 3. Supprimer les associations (devrait être OK via CASCADE mais par sécurité)
             $pdo->exec("DELETE FROM vaccin_type_associations");
            // 4. Enfin, supprimer TOUS les vaccins
            $deletedRows = $pdo->exec("DELETE FROM vaccins");
            $pdo->commit();
            $_SESSION['message'] = "{$deletedRows} vaccins (et données associées: lots, enregistrements, associations) supprimés.";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
             $pdo->rollBack(); error_log("[ERROR] supprimer_tous_vaccins failed: " . $e->getMessage());
             $_SESSION['message'] = "Erreur lors de la suppression totale : " . $e->getMessage(); $_SESSION['message_type'] = 'danger';
        }
        header("Location: gerer_vaccins.php?action=liste" . $queryStringPrefix . "&page=1");
        exit();
    }

    // --- Action: Supprimer un vaccin spécifique ---
    elseif ($action == 'supprimer' && isset($_GET['id'])) {
        $vaccin_id = (int)$_GET['id'];
        error_log("[DEBUG] Action: supprimer vaccin, ID: " . $vaccin_id);
        try {
            $pdo->beginTransaction();
            // 1. Supprimer les enregistrements liés aux lots de CE vaccin
             $stmtRecs = $pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id = ?)");
             $stmtRecs->execute([$vaccin_id]);
             // 2. Supprimer les lots liés à CE vaccin
             $stmtLots = $pdo->prepare("DELETE FROM lots WHERE vaccin_id = ?");
             $stmtLots->execute([$vaccin_id]);
             // 3. Supprimer les associations de CE vaccin (devrait être OK via CASCADE)
            // $stmtAssoc = $pdo->prepare("DELETE FROM vaccin_type_associations WHERE vaccin_id = ?");
            // $stmtAssoc->execute([$vaccin_id]); // Normalement inutile avec ON DELETE CASCADE
            // 4. Supprimer le vaccin lui-même
            $stmt = $pdo->prepare("DELETE FROM vaccins WHERE vaccin_id = ?");
            $stmt->execute([$vaccin_id]); $rowCount = $stmt->rowCount();
            $pdo->commit();

            if ($rowCount > 0) { $_SESSION['message'] = "Vaccin et données associées supprimés."; $_SESSION['message_type'] = 'success'; }
            else { $_SESSION['message'] = "Vaccin non trouvé ou déjà supprimé."; $_SESSION['message_type'] = 'warning'; }
        } catch (PDOException $e) {
             $pdo->rollBack(); error_log("[ERROR] supprimer vaccin ID {$vaccin_id} failed: " . $e->getMessage());
             $_SESSION['message'] = "Erreur lors de la suppression : " . $e->getMessage(); $_SESSION['message_type'] = 'danger';
        }
        header("Location: gerer_vaccins.php?action=liste&page=" . $page . $queryStringPrefix);
        exit();
    }

    // --- 3. PRÉPARER LES DONNÉES POUR L'AFFICHAGE ---

    // --- Préparer le formulaire de modification (GET) ---
    elseif ($action == 'modifier' && isset($_GET['id'])) {
        error_log("[DEBUG] Action: modifier vaccin (GET), ID: " . $_GET['id']);
        $vaccin_id = (int)$_GET['id'];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1; if ($page < 1) $page = 1;

        $stmt = $pdo->prepare("SELECT * FROM vaccins WHERE vaccin_id = ?");
        $stmt->execute([$vaccin_id]);
        $vaccin_edit = $stmt->fetch();

        if (!$vaccin_edit) {
            $_SESSION['message'] = "Vaccin non trouvé pour modification."; $_SESSION['message_type'] = 'warning';
            header("Location: gerer_vaccins.php?action=liste" . $queryStringPrefix); exit();
        }

        $stmtAssoc = $pdo->prepare("SELECT type_vaccin_id FROM vaccin_type_associations WHERE vaccin_id = ?");
        $stmtAssoc->execute([$vaccin_id]);
        $associated_type_ids = $stmtAssoc->fetchAll(PDO::FETCH_COLUMN, 0);
        error_log("[DEBUG] Associated type IDs for vaccin {$vaccin_id}: " . print_r($associated_type_ids, true));
    }

    // --- Préparer la liste des vaccins (action=liste) ---
    elseif ($action == 'liste') {
        error_log("[DEBUG] Action: liste vaccin, Page: {$page}, Filtre Campagne: '{$filtre_campagne_id}', Sort: {$sort_by} {$sort_dir}");

        $whereConditions = [];
        $params = [];
        if (!empty($filtre_campagne_id)) {
            $whereConditions[] = "v.campagne_id = ?";
            $params[] = $filtre_campagne_id;
        }
        $sqlWhere = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

        // 1. Compter le total filtré
        $sqlCount = "SELECT COUNT(v.vaccin_id) FROM vaccins v" . $sqlWhere;
        $totalStmt = $pdo->prepare($sqlCount);
        $totalStmt->execute($params);
        $totalVaccins = $totalStmt->fetchColumn();

        // 2. Calculer pagination basée sur le total filtré
        $totalPages = ceil($totalVaccins / $perPage); if ($totalPages < 1) $totalPages = 1;
        if ($page > $totalPages) { $page = $totalPages; }
        $offset = max(0, ($page - 1) * $perPage); if ($page < 1) { $page = 1; $offset = 0;}

        // 3. Construire ORDER BY (CORRIGÉ)
         $orderByClause = " ORDER BY ";
         $sortCol = $sort_by; // Utiliser la variable validée

         // Déterminer la colonne ou l'alias à utiliser pour le tri
         $colToSortBy = '';
         switch ($sortCol) {
             case 'nom_vaccin':   $colToSortBy = 'v.nom_vaccin'; break;
             case 'nom_campagne': $colToSortBy = 'c.nom_campagne'; break;
             case 'description':  $colToSortBy = 'v.description'; break;
             case 'type_names':   $colToSortBy = 'type_names'; break; // L'alias lui-même
             case 'vaccin_id':
             default:             $colToSortBy = 'v.vaccin_id'; break;
         }

         // Appliquer LOWER() seulement aux colonnes textuelles NON agrégées
         if (in_array($sort_by, ['nom_vaccin', 'nom_campagne', 'description'])) {
             $orderByClause .= "LOWER(" . $colToSortBy . ") " . $sort_dir;
         } else {
             // Pour 'type_names' (alias agrégé) et 'vaccin_id' (numérique), trier directement
             $orderByClause .= $colToSortBy . " " . $sort_dir;
         }

         // Ajouter tri secondaire pour stabilité
          if ($sort_by !== 'vaccin_id') {
              $orderByClause .= ", v.vaccin_id ASC";
          }


        // 4. Récupérer la liste filtrée, triée et paginée
        $sqlList = "
            SELECT
                v.vaccin_id, v.nom_vaccin, v.description, v.campagne_id,
                c.nom_campagne,
                COALESCE(string_agg(tv.nom_type, ', ' ORDER BY tv.nom_type), 'Aucun') AS type_names
            FROM vaccins v
            JOIN campagnes c ON v.campagne_id = c.campagne_id
            LEFT JOIN vaccin_type_associations vta ON v.vaccin_id = vta.vaccin_id
            LEFT JOIN type_vaccins tv ON vta.type_vaccin_id = tv.type_vaccin_id
            {$sqlWhere}
            GROUP BY v.vaccin_id, c.nom_campagne -- Regrouper par toutes les colonnes non agrégées
            {$orderByClause}
            LIMIT ? OFFSET ?
        ";
        $stmtList = $pdo->prepare($sqlList);

        $bindIndex = 1;
        foreach ($params as $param) { $stmtList->bindValue($bindIndex++, $param, PDO::PARAM_INT); }
        $stmtList->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $stmtList->bindValue($bindIndex++, $offset, PDO::PARAM_INT);

        $stmtList->execute();
        $vaccins_liste = $stmtList->fetchAll();
        error_log("[DEBUG] Nombre de vaccins récupérés pour la liste (filtré/trié/paginé): " . count($vaccins_liste));
    }

} catch (PDOException $e) {
    error_log("[FATAL PDOException in gerer_vaccins.php] Action: {$action}, SortBy: {$sort_by}, Error: " . $e->getMessage());
    $_SESSION['message'] = "Erreur critique de base de données. Vérifiez les logs.";
    $_SESSION['message_type'] = 'danger';
    $fallbackRedirect = "gerer_vaccins.php?action=liste" . $queryStringPrefix;
    if (isset($action) && $action !== 'liste') { header("Location: " . $fallbackRedirect); exit(); }
    else { $message = $_SESSION['message'] ?? "Erreur critique."; unset($_SESSION['message']); $vaccins_liste = []; $totalPages = 1; $page = 1; }
}

// --- Fonction Helper pour les liens de tri (inchangée) ---
function renderSortLink($columnName, $label, $currentSortBy, $currentSortDir, $currentFilterCampagneId) {
    $newSortDir = ($currentSortBy === $columnName && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';
    $linkParams = ['action' => 'liste'];
    if (!empty($currentFilterCampagneId)) $linkParams['filtre_campagne_id'] = $currentFilterCampagneId;
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
    <title>Gestion des Vaccins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #0d6efd; text-decoration: underline; }
        th .sort-icon { margin-left: 5px; color: #adb5bd; }
        th.sorted .sort-icon { color: #000; }
        .table-description, .table-types { display: inline-block; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        .required-field::after { content: ' *'; color: red; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
        .filter-form { background-color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem; border: 1px solid #dee2e6; }
        .type-checkbox-group { max-height: 150px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="card">
             <div class="header">
                 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="mb-1"><i class="fas fa-syringe me-2"></i>Gestion des Vaccins</h1>
                        <p class="mb-0 small text-white-50">Ajouter, modifier, filtrer et trier les vaccins</p>
                    </div>
                    <?php if ($action == 'liste'): ?>
                        <div class="btn-group" role="group">
                            <a href="gerer_vaccins.php?action=ajouter<?= $queryStringPrefix ?>" class="btn btn-sm btn-light" title="Nouveau Vaccin"><i class="fas fa-plus me-1 text-success"></i>Nouveau</a>
                            <button type="button" class="btn btn-sm btn-light" onclick="openGenerateVaccinsModal()" title="Générer données test"><i class="fas fa-magic me-1 text-info"></i>Générer</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllVaccinsModal()" title="Supprimer Tous!"><i class="fas fa-trash-alt me-1"></i>Suppr. Tout</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php // Affichage des messages ?>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?> alert-dismissible fade show m-3" role="alert">
                    <?= nl2br(htmlspecialchars($_SESSION['message'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php elseif (!empty($message)): ?>
                 <div class="alert alert-danger m-3"><?= nl2br(htmlspecialchars($message)) ?></div>
            <?php endif; ?>

            <?php // --- Affichage Formulaire ou Liste --- ?>

            <?php // FORMULAIRE AJOUTER / MODIFIER ?>
            <?php if ($action == 'ajouter' || ($action == 'modifier' && $vaccin_edit)): ?>
                <div class="card-body">
                    <h3 class="mb-4 border-bottom pb-3 fs-5">
                        <?= ($action == 'ajouter') ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter un vaccin' : '<i class="fas fa-edit me-2 text-primary"></i>Modifier le vaccin : '.htmlspecialchars($vaccin_edit['nom_vaccin']) ?>
                    </h3>
                     <?php if (empty($available_campaigns) || empty($available_types)): ?>
                         <div class="alert alert-warning">
                             Impossible d'ajouter/modifier un vaccin :
                             <ul class="mb-0">
                                 <?php if (empty($available_campaigns)): ?><li>Aucune campagne active trouvée. <a href="gerer_campagnes.php">Gérer les campagnes</a>.</li><?php endif; ?>
                                 <?php if (empty($available_types)): ?><li>Aucun type de vaccin trouvé. <a href="gerer_type_vaccins.php">Gérer les types</a>.</li><?php endif; ?>
                             </ul>
                              <div class="mt-3"> <a href="gerer_vaccins.php?action=liste<?= $queryStringPrefix ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Retour</a> </div>
                         </div>
                     <?php else: ?>
                        <form method="post" action="gerer_vaccins.php?action=<?= $action ?><?= $queryStringPrefix ?>">
                            <?php if ($action == 'modifier'): ?><input type="hidden" name="vaccin_id" value="<?= htmlspecialchars($vaccin_edit['vaccin_id']) ?>"><?php endif; ?>
                            <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
                            <div class="mb-3"> <label for="nom_vaccin" class="form-label required-field">Nom du Vaccin</label> <input type="text" class="form-control" id="nom_vaccin" name="nom_vaccin" value="<?= htmlspecialchars($vaccin_edit['nom_vaccin'] ?? '') ?>" required maxlength="255"> </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6"> <label for="campagne_id" class="form-label required-field">Campagne Associée</label> <select class="form-select" id="campagne_id" name="campagne_id" required> <option value="">-- Sélectionner une campagne active --</option> <?php foreach ($available_campaigns as $camp): ?> <option value="<?= $camp['campagne_id'] ?>" <?= (isset($vaccin_edit['campagne_id']) && $vaccin_edit['campagne_id'] == $camp['campagne_id']) ? 'selected' : '' ?>><?= htmlspecialchars($camp['nom_campagne']) ?></option> <?php endforeach; ?> </select> <div class="form-text">Seules les campagnes actives sont listées.</div> </div>
                                <div class="col-md-6"> <label class="form-label required-field">Type(s) de Vaccin</label> <div class="border p-3 rounded type-checkbox-group"> <?php if (empty($available_types)): ?> <span class="text-muted">Aucun type disponible.</span> <?php else: ?> <?php foreach ($available_types as $type): ?> <div class="form-check"> <input class="form-check-input" type="checkbox" name="type_ids[]" id="type_<?= $type['type_vaccin_id'] ?>" value="<?= $type['type_vaccin_id'] ?>" <?= ($action == 'modifier' && is_array($associated_type_ids) && in_array($type['type_vaccin_id'], $associated_type_ids)) ? 'checked' : '' ?>> <label class="form-check-label" for="type_<?= $type['type_vaccin_id'] ?>"><?= htmlspecialchars($type['nom_type']) ?></label> </div> <?php endforeach; ?> <?php endif; ?> </div> <div class="form-text">Cochez un ou plusieurs types.</div> </div>
                            </div>
                            <div class="mb-4"> <label for="description" class="form-label">Description <small class="text-muted">(Opt.)</small></label> <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($vaccin_edit['description'] ?? '') ?></textarea> </div>
                            <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2"> <a href="gerer_vaccins.php?action=liste&page=<?= htmlspecialchars($page) ?><?= $queryStringPrefix ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a> <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?></button> </div>
                        </form>
                     <?php endif; ?>
                </div>

            <?php // LISTE DES VACCINS ?>
            <?php elseif ($action == 'liste'): ?>
                 <div class="filter-form mx-3 mt-3">
                    <form method="GET" action="gerer_vaccins.php">
                        <input type="hidden" name="action" value="liste">
                        <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                        <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sort_dir) ?>">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-6"> <label for="filtre_campagne_id" class="form-label small">Filtrer par Campagne Active</label> <select class="form-select form-select-sm" id="filtre_campagne_id" name="filtre_campagne_id"> <option value="" <?= ($filtre_campagne_id === '') ? 'selected' : '' ?>>-- Toutes --</option> <?php foreach ($available_campaigns as $camp): ?> <option value="<?= $camp['campagne_id'] ?>" <?= ($filtre_campagne_id == $camp['campagne_id']) ? 'selected' : '' ?>> <?= htmlspecialchars($camp['nom_campagne']) ?> </option> <?php endforeach; ?> </select> </div>
                            <div class="col-md-6 d-flex gap-2"> <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button> <a href="gerer_vaccins.php?action=liste" class="btn btn-secondary btn-sm flex-grow-1" title="Réinitialiser filtre et tri"><i class="fas fa-times me-1"></i>Reset</a> </div>
                        </div>
                    </form>
                 </div>

                 <div class="table-responsive">
                    <?php if (!empty($vaccins_liste)): ?>
                        <table class="table table-striped table-hover mb-0">
                            <thead> <tr> <?php renderSortLink('nom_vaccin', 'Nom Vaccin', $sort_by, $sort_dir, $filtre_campagne_id); ?> <?php renderSortLink('nom_campagne', 'Campagne', $sort_by, $sort_dir, $filtre_campagne_id); ?> <?php renderSortLink('type_names', 'Type(s)', $sort_by, $sort_dir, $filtre_campagne_id); ?> <?php renderSortLink('description', 'Description', $sort_by, $sort_dir, $filtre_campagne_id); ?> <th class="text-center" style="width: 120px;">Actions</th> </tr> </thead>
                            <tbody>
                                <?php foreach ($vaccins_liste as $vaccin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($vaccin['nom_vaccin']) ?></td>
                                        <td><?= htmlspecialchars($vaccin['nom_campagne']) ?></td>
                                        <td> <span class="table-types" data-bs-toggle="tooltip" title="<?= htmlspecialchars($vaccin['type_names']) ?>"> <?= htmlspecialchars($vaccin['type_names']) ?> </span> </td>
                                         <td> <span class="table-description" data-bs-toggle="tooltip" title="<?= htmlspecialchars($vaccin['description'] ?: 'Aucune description') ?>"> <?= htmlspecialchars($vaccin['description'] ?: '-') ?> </span> </td>
                                        <td class="text-center"> <a href="gerer_vaccins.php?action=modifier&id=<?= $vaccin['vaccin_id'] ?>&page=<?= $page ?><?= $queryStringPrefix ?>" class="btn btn-primary btn-action" data-bs-toggle="tooltip" title="Modifier"><i class="fas fa-edit"></i></a> <a href="gerer_vaccins.php?action=supprimer&id=<?= $vaccin['vaccin_id'] ?>&page=<?= $page ?><?= $queryStringPrefix ?>" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer" onclick="return confirm('Confirmer la suppression du vaccin \'<?= htmlspecialchars(addslashes($vaccin['nom_vaccin']), ENT_QUOTES) ?>\' et de ses données associées (lots, enregistrements...) ?')"><i class="fas fa-trash-alt"></i></a> </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="card-body text-center p-4">
                            <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                             <p class="text-muted mb-3"> <?php if (!empty($filtre_campagne_id)): ?> Aucun vaccin trouvé pour la campagne sélectionnée. <?php else: ?> Aucun vaccin enregistré. <?php endif; ?> </p>
                             <?php if (empty($available_campaigns) || empty($available_types)): ?> <p class="text-warning small mb-3">Vérifiez qu'il existe des <a href="gerer_campagnes.php">campagnes actives</a> et des <a href="gerer_type_vaccins.php">types de vaccins</a>.</p> <?php endif; ?>
                             <?php if (!empty($filtre_campagne_id) || !empty($currentQueryString)): ?> <a href="gerer_vaccins.php?action=liste" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser filtre/tri</a><br> <?php endif; ?>
                            <a href="gerer_vaccins.php?action=ajouter<?= $queryStringPrefix ?>" class="btn btn-success" <?= (empty($available_campaigns) || empty($available_types)) ? 'disabled' : '' ?>><i class="fas fa-plus me-1"></i>Créer un vaccin</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php // Pagination ?>
                <?php if (!empty($vaccins_liste) && $totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation Vaccins">
                            <ul class="pagination justify-content-center mb-0">
                                <?php $pageUrlBase = "?action=liste"; if (!empty($currentQueryString)) $pageUrlBase .= "&" . $currentQueryString; $pageUrlBase .= "&page="; ?>
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

        <div class="text-center my-4"><a href="../admin.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour</a></div>

    </div> <?php // Fin dashboard-container ?>

    <?php // --- Modals (Générer, Supprimer Tout) --- ?>
    <div class="modal fade" id="generateVaccinsModal" tabindex="-1"> <div class="modal-dialog modal-sm"> <div class="modal-content"> <form id="generateVaccinsForm" action="gerer_vaccins.php" method="get"> <div class="modal-header"> <h6 class="modal-title"><i class="fas fa-magic me-2 text-info"></i>Générer Vaccins Test</h6> <button type="button" class="btn-close" data-bs-dismiss="modal"></button> </div> <div class="modal-body"> <input type="hidden" name="action" value="generer_vaccins"> <div class="mb-3"> <label for="nombreVaccins" class="form-label">Nombre (1-50):</label> <input type="number" class="form-control form-control-sm" id="nombreVaccins" name="nombre" value="5" min="1" max="50" required> </div> <?php if (empty($available_campaigns) || empty($available_types)): ?> <small class="text-danger d-block">Requiert campagnes actives et types.</small> <?php endif; ?> </div> <div class="modal-footer"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-sm btn-primary" <?= (empty($available_campaigns) || empty($available_types)) ? 'disabled' : '' ?>>Générer</button> </div> </form> </div> </div> </div>
    <div class="modal fade" id="deleteAllVaccinsModal" tabindex="-1"> <div class="modal-dialog"> <div class="modal-content"> <form id="deleteAllVaccinsForm" action="gerer_vaccins.php" method="GET"> <div class="modal-header bg-danger text-white"> <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button> </div> <div class="modal-body"> <p><strong class="text-danger">Attention : Irréversible !</strong></p> <p>Suppression de <strong>TOUS</strong> les vaccins, associations, lots et enregistrements liés.</p> <div class="form-check mt-3"> <input class="form-check-input" type="checkbox" id="confirmDeleteAllVaccinsCheckbox" required> <label class="form-check-label" for="confirmDeleteAllVaccinsCheckbox">Je confirme vouloir tout supprimer.</label> </div> </div> <div class="modal-footer"> <input type="hidden" name="action" value="supprimer_tous_vaccins"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-danger" id="confirmDeleteAllVaccinsBtn" disabled><i class="fas fa-trash-alt me-1"></i>Supprimer Tout</button> </div> </form> </div> </div> </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openGenerateVaccinsModal() { var myModal = new bootstrap.Modal(document.getElementById('generateVaccinsModal')); myModal.show(); }
        const deleteAllVaccinsModalEl = document.getElementById('deleteAllVaccinsModal');
        if (deleteAllVaccinsModalEl) { const c = deleteAllVaccinsModalEl.querySelector('#confirmDeleteAllVaccinsCheckbox'), b = deleteAllVaccinsModalEl.querySelector('#confirmDeleteAllVaccinsBtn'), i = new bootstrap.Modal(deleteAllVaccinsModalEl); function o(){ c.checked = false; b.disabled = true; i.show(); } c.addEventListener('change', function() { b.disabled = !this.checked; }); window.openDeleteAllVaccinsModal = o; }
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (t) { if (!bootstrap.Tooltip.getInstance(t)) { return new bootstrap.Tooltip(t, { delay: { "show": 300, "hide": 100 }, html: true }); } });
        });
    </script>
</body>
</html>