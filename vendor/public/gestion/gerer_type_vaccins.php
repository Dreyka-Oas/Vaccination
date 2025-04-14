<?php
// ==========================================================================
// Fichier: gerer_type_vaccins.php
// Description: Gestion CRUD et Tri des types de vaccins.
// Date: [Date de modification] // Ajout du tri
// ==========================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- Sécurité ---
if (!isset($_SESSION['matricule']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

// --- Inclusions ---
require_once '../../app/config/config.php';
require_once '../../vendor/autoload.php';

use Faker\Factory;

// --- Initialisation ---
$action = $_GET['action'] ?? 'liste';
$message = '';
$type_vaccins = [];
$type_vaccin_edit = null;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalPages = 1;

// Paramètres pour le tri
$allowed_sort_columns = ['nom_type', 'description', 'type_vaccin_id']; // Colonnes autorisées
$sort_by = $_GET['sort_by'] ?? 'type_vaccin_id'; // Défaut: tri par ID
$sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC'; // Défaut: DESC

// Validation des paramètres de tri
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'type_vaccin_id'; // Revenir au défaut
}
if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
    $sort_dir = 'DESC'; // Revenir au défaut
}

// Construction de la chaîne de requête pour conserver le tri dans les liens
$queryStringParams = [];
// On ajoute les paramètres de tri seulement s'ils ne sont pas les valeurs par défaut strictes (ID DESC)
if ($sort_by !== 'type_vaccin_id' || $sort_dir !== 'DESC') {
    $queryStringParams['sort_by'] = $sort_by;
    $queryStringParams['sort_dir'] = $sort_dir;
}
$currentQueryString = http_build_query($queryStringParams);
$queryStringPrefix = !empty($currentQueryString) ? '&' . $currentQueryString : ''; // Préfixe pour ajouter aux URLs existantes

try {
    // --- Connexion DB ---
    $pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // --- 1. TRAITEMENT POST (Ajout / Modification Formulaire) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $currentPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $matricule = $_SESSION['matricule'];

        // Construire l'URL de redirection de base en conservant le tri
        $redirectBaseUrl = basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix;


        // --- Action Ajouter ---
        if ($action == 'ajouter') {
            $nom_type = $_POST['nom_type'] ?? null;
            $description = $_POST['description'] ?? '';

            if (empty($nom_type)) {
                $_SESSION['message'] = "Erreur : Le nom du type est obligatoire.";
                $_SESSION['message_type'] = 'danger';
                // Conserver le tri même en cas d'erreur sur le formulaire d'ajout
                header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=ajouter" . $queryStringPrefix);
                exit();
            }
             if (empty($matricule)) { /* ... Session error handling ... */ header("Location: ../../index.php"); exit(); }

            $sql = "INSERT INTO type_vaccins (nom_type, description, matricule_creation) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom_type, $description, $matricule]);

            $_SESSION['message'] = "Type de vaccin ajouté avec succès.";
            $_SESSION['message_type'] = 'success';
            // Retour page 1 de la liste triée après ajout
            header("Location: " . $redirectBaseUrl . "&page=1");
            exit();

        // --- Action Modifier ---
        } elseif ($action == 'modifier' && isset($_POST['type_vaccin_id'])) {
            $type_vaccin_id = (int)$_POST['type_vaccin_id'];
            $nom_type = $_POST['nom_type'] ?? null;
            $description = $_POST['description'] ?? '';

            if (empty($nom_type)) {
                $_SESSION['message'] = "Erreur : Le nom du type est obligatoire.";
                $_SESSION['message_type'] = 'danger';
                 // Conserver tri et page en cas d'erreur sur formulaire de modif
                header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=modifier&id=" . $type_vaccin_id . "&page=" . $currentPage . $queryStringPrefix);
                exit();
            }
            if (empty($matricule)) { /* ... Session error handling ... */ header("Location: ../../index.php"); exit(); }

            $sql = "UPDATE type_vaccins SET nom_type = ?, description = ?, matricule_creation = ? WHERE type_vaccin_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom_type, $description, $matricule, $type_vaccin_id]);

            $_SESSION['message'] = "Type de vaccin modifié avec succès.";
            $_SESSION['message_type'] = 'success';
            // Retour à la page où était l'élément, en conservant le tri
            header("Location: " . $redirectBaseUrl . "&page=" . $currentPage);
            exit();
        }
    }

    // --- 2. TRAITEMENT GET (Actions qui modifient et redirigent) ---

    // --- Action Générer ---
    elseif ($action == 'generer_types_vaccins') {
        // ... (logique de génération inchangée) ...
        error_log("[DEBUG] Action: generer_types_vaccins");
        $nombre_types = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 5;
        if ($nombre_types < 1) $nombre_types = 1; if ($nombre_types > 50) $nombre_types = 50;

        $faker = Factory::create('fr_FR');
        $count_success = 0;
        $matricule_creation = $_SESSION['matricule'];
        if (empty($matricule_creation)) { /* ... Session error handling ... */ header("Location: ../../index.php"); exit(); }

        $sqlInsert = "INSERT INTO type_vaccins (nom_type, description, matricule_creation) VALUES (?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);

         $maxNumStmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(nom_type FROM E'^Type .*?(\\\\d+)$') AS INTEGER)) FROM type_vaccins WHERE nom_type ~ E'^Type .*?\\\\d+$'");
         $startNum = ($maxNumStmt->fetchColumn() ?: 0);

        for ($i = 0; $i < $nombre_types; $i++) {
            $nom_type = 'Type ' . $faker->unique()->word . ' ' . ($startNum + $i + 1);
            $description = $faker->sentence(6);
            try {
                if ($stmtInsert->execute([$nom_type, $description, $matricule_creation])) { $count_success++; }
            } catch (PDOException $e) { error_log("[ERROR] Failed to insert fake vaccine type: " . $nom_type . " - PDO Error: " . $e->getMessage()); }
        }
        $faker->unique(true);

        $_SESSION['message'] = "{$count_success}/{$nombre_types} types de vaccins de test générés.";
        $_SESSION['message_type'] = ($count_success > 0) ? (($count_success == $nombre_types) ? 'success' : 'warning') : 'danger';
        // Rediriger vers la liste (page 1, tri par défaut ou conservé si déjà défini)
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix . "&page=1");
        exit();
    }

    // --- Action Supprimer Tous ---
    elseif ($action == 'supprimer_tous_types_vaccins') {
        // ... (logique de suppression globale inchangée) ...
         error_log("[DEBUG] Action: supprimer_tous_types_vaccins");
        try {
            $pdo->beginTransaction();
            $vaccin_ids_all = $pdo->query("SELECT vaccin_id FROM vaccin_type_associations")->fetchAll(PDO::FETCH_COLUMN);
            if(!empty($vaccin_ids_all)) {
                $placeholders_v_all = implode(',', array_fill(0, count($vaccin_ids_all), '?'));
                $pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id IN ($placeholders_v_all))")->execute($vaccin_ids_all);
                $pdo->prepare("DELETE FROM lots WHERE vaccin_id IN ($placeholders_v_all)")->execute($vaccin_ids_all);
            }
            $pdo->exec("DELETE FROM vaccin_type_associations");
            $deletedRows = $pdo->exec("DELETE FROM type_vaccins");
            $pdo->commit();
            $_SESSION['message'] = "{$deletedRows} types de vaccins et toutes les données associées supprimés.";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("[ERROR] supprimer_tous_types_vaccins failed: " . $e->getMessage());
             $_SESSION['message'] = "Erreur lors de la suppression totale : " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
        // Rediriger vers la liste (page 1, tri par défaut ou conservé)
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix . "&page=1");
        exit();
    }

    // --- Action Supprimer Individuel ---
    elseif ($action == 'supprimer' && isset($_GET['id'])) {
        // ... (logique de suppression individuelle inchangée) ...
        $type_vaccin_id = (int)$_GET['id'];
        error_log("[DEBUG] Action: supprimer type_vaccin, ID: " . $type_vaccin_id);
        try {
            $pdo->beginTransaction();
            $findVaccinsStmt = $pdo->prepare("SELECT vaccin_id FROM vaccin_type_associations WHERE type_vaccin_id = ?");
            $findVaccinsStmt->execute([$type_vaccin_id]);
            $vaccin_ids = $findVaccinsStmt->fetchAll(PDO::FETCH_COLUMN);
            $deletedItemsMsg = "";

            if (!empty($vaccin_ids)) {
                error_log("Found associated vaccin_ids: " . implode(', ', $vaccin_ids));
                $placeholders = implode(',', array_fill(0, count($vaccin_ids), '?'));
                $stmtVr = $pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id IN ($placeholders))");
                $stmtVr->execute($vaccin_ids);
                $deletedItemsMsg .= $stmtVr->rowCount() . " enregistrement(s), ";
                $stmtLots = $pdo->prepare("DELETE FROM lots WHERE vaccin_id IN ($placeholders)");
                $stmtLots->execute($vaccin_ids);
                $deletedItemsMsg .= $stmtLots->rowCount() . " lot(s), ";
            } else { error_log("No vaccines found associated with type_vaccin_id {$type_vaccin_id}."); }

            $stmtAssoc = $pdo->prepare("DELETE FROM vaccin_type_associations WHERE type_vaccin_id = ?");
            $stmtAssoc->execute([$type_vaccin_id]);
            $deletedItemsMsg .= $stmtAssoc->rowCount() . " association(s)";

            $stmt = $pdo->prepare("DELETE FROM type_vaccins WHERE type_vaccin_id = ?");
            $stmt->execute([$type_vaccin_id]);
            $rowCount = $stmt->rowCount();

            $pdo->commit();

            if ($rowCount > 0) { $_SESSION['message'] = "Type de vaccin supprimé avec succès (et {$deletedItemsMsg})."; $_SESSION['message_type'] = 'success'; }
            else { $_SESSION['message'] = "Type de vaccin non trouvé ou déjà supprimé."; $_SESSION['message_type'] = 'warning'; }

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("[ERROR] supprimer type_vaccin ID {$type_vaccin_id} failed: " . $e->getMessage());
            $_SESSION['message'] = "Erreur lors de la suppression du type (ID: {$type_vaccin_id}) : " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
        // Rediriger vers la page actuelle de la liste, en conservant le tri
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste&page=" . $page . $queryStringPrefix);
        exit();
    }

    // --- 3. PRÉPARATION AFFICHAGE (action=liste ou action=modifier GET) ---

    // Préparer le formulaire de modification si action=modifier et requête GET
    elseif ($action == 'modifier' && isset($_GET['id'])) {
        error_log("[DEBUG] Action: modifier type_vaccin (GET), ID: " . $_GET['id']);
        $type_vaccin_id = (int)$_GET['id'];
        // Conserver page et tri pour le lien Annuler
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;

        $stmt = $pdo->prepare("SELECT * FROM type_vaccins WHERE type_vaccin_id = ?");
        $stmt->execute([$type_vaccin_id]);
        $type_vaccin_edit = $stmt->fetch();

        if (!$type_vaccin_edit) {
            $_SESSION['message'] = "Type de vaccin non trouvé pour modification (ID: {$type_vaccin_id}).";
            $_SESSION['message_type'] = 'warning';
            // Rediriger vers la liste en conservant le tri
            header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix);
            exit();
        }
        // Les données sont dans $type_vaccin_edit, le HTML suivra
    }

    // Préparer la liste si action=liste (ou action par défaut)
    elseif ($action == 'liste') {
        error_log("[DEBUG] Action: liste type_vaccin, Page: {$page}, Sort: {$sort_by} {$sort_dir}");
        // Compter total (ne dépend pas du tri)
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM type_vaccins");
        $totalTypesVaccins = $totalStmt->fetchColumn();
        $totalPages = ceil($totalTypesVaccins / $perPage);
        if ($totalPages < 1) $totalPages = 1;

        // Ajuster page si nécessaire (après calcul du total)
        if ($page > $totalPages) { $page = $totalPages; $offset = max(0, ($page - 1) * $perPage); if ($page < 1) $page = 1; }

        // Construire la clause ORDER BY (sécurisée car $sort_by et $sort_dir sont validés)
        // Utiliser LOWER() pour trier les textes sans tenir compte de la casse
        $orderByClause = " ORDER BY ";
        if ($sort_by === 'nom_type' || $sort_by === 'description') {
            $orderByClause .= "LOWER(" . $sort_by . ") " . $sort_dir;
        } else {
            // Pour l'ID, pas besoin de LOWER()
             $orderByClause .= $sort_by . " " . $sort_dir;
        }
        // Ajouter un tri secondaire par ID pour la stabilité si on trie par autre chose
         if ($sort_by !== 'type_vaccin_id') {
             $orderByClause .= ", type_vaccin_id ASC"; // Ou DESC selon préférence
         }

        // Récupérer types paginés ET triés
        $sqlList = "SELECT * FROM type_vaccins" . $orderByClause . " LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sqlList);
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $type_vaccins = $stmt->fetchAll();
        error_log("[DEBUG] Found " . count($type_vaccins) . " types for page " . $page . " (sorted)");
    }

} catch (PDOException $e) {
    // --- Gestion Erreur PDO Globale ---
    error_log("[FATAL PDOException in " . basename(__FILE__) . "] " . $e->getMessage());
    $message = "Erreur critique de base de données. Consultez les logs.";
    // Tenter redirection vers liste avec message si erreur sur une autre action
    if (isset($action) && $action !== 'liste') {
       $_SESSION['message'] = $message;
       $_SESSION['message_type'] = 'danger';
        // Conserver le tri même en cas de redirection d'erreur
       header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix);
       exit();
    }
    // Sinon (erreur sur la liste), le message sera affiché directement
    $type_vaccins = []; $totalPages = 1; $page = 1; // Réinitialiser
}

// --- Fonction Helper pour les liens de tri ---
function renderSortLink($columnName, $label, $currentSortBy, $currentSortDir) {
    $newSortDir = ($currentSortBy === $columnName && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';
    $linkParams = ['action' => 'liste'];
    // Pas de filtre ici, juste le tri
    $linkParams['sort_by'] = $columnName;
    $linkParams['sort_dir'] = $newSortDir;
    // Cliquer sur un tri remet à la page 1
    // $linkParams['page'] = 1;

    $url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($linkParams);
    $iconClass = 'fas fa-sort'; // Icône par défaut
    $thClass = '';
    if ($currentSortBy === $columnName) {
        $iconClass = ($currentSortDir === 'ASC') ? 'fas fa-sort-up' : 'fas fa-sort-down';
        $thClass = 'sorted'; // Classe pour styler l'icône si trié
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
    <title>Gestion des Types de Vaccins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        /* Style pour les en-têtes de tableau triables */
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #0d6efd; text-decoration: underline; }
        th .sort-icon { margin-left: 5px; color: #adb5bd; /* Gris léger par défaut */}
        th.sorted .sort-icon { color: #000; /* Plus visible si trié */ }
        .table-description { display: inline-block; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        .required-field::after { content: ' *'; color: red; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="card">
             <div class="header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="mb-1"><i class="fas fa-tags me-2"></i>Gestion des Types de Vaccins</h1>
                        <p class="mb-0 small text-white-50">Ajouter, modifier ou supprimer des types</p>
                    </div>
                    <?php if ($action == 'liste'): ?>
                        <div class="btn-group" role="group">
                            <?php // Lien Nouveau : pas besoin de conserver le tri ici ?>
                            <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=ajouter" class="btn btn-sm btn-light" title="Nouveau Type"><i class="fas fa-plus me-1 text-success"></i>Nouveau</a>
                            <button type="button" class="btn btn-sm btn-light" onclick="openGenerateTypesVaccinsModal()" title="Générer données test"><i class="fas fa-flask me-1 text-info"></i>Générer Test</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllTypesVaccinsModal()" title="Supprimer Tous!"><i class="fas fa-trash-alt me-1"></i>Tout Supprimer</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php // -- Affichage Messages -- ?>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?> alert-dismissible fade show m-3" role="alert">
                    <?= nl2br(htmlspecialchars($_SESSION['message'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php elseif (!empty($message)): // Erreur interne non session ?>
                 <div class="alert alert-danger m-3" role="alert"><?= nl2br(htmlspecialchars($message)) ?></div>
            <?php endif; ?>

            <?php // --- Affichage Contenu: Formulaire ou Liste --- ?>

            <?php // CAS 1 : Formulaire (Ajout ou Modif) ?>
            <?php if ($action == 'ajouter' || ($action == 'modifier' && $type_vaccin_edit)): ?>
                <div class="card-body">
                    <h3 class="mb-4 border-bottom pb-3 fs-5">
                        <?= ($action == 'ajouter') ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter un type' : '<i class="fas fa-edit me-2 text-primary"></i>Modifier le type : ' . htmlspecialchars($type_vaccin_edit['nom_type']) ?>
                    </h3>
                    <?php // Le formulaire POST inclut les paramètres de tri pour la redirection ?>
                    <form method="post" action="<?= basename($_SERVER['PHP_SELF']) ?>?action=<?= $action ?><?= $queryStringPrefix ?>">
                        <?php if ($action == 'modifier'): ?><input type="hidden" name="type_vaccin_id" value="<?= htmlspecialchars($type_vaccin_edit['type_vaccin_id']) ?>"><?php endif; ?>
                        <input type="hidden" name="page" value="<?= htmlspecialchars($page) // Page pour la redirection correcte ?>">
                        <div class="mb-3">
                            <label for="nom_type" class="form-label required-field">Nom du type</label>
                            <input type="text" class="form-control" id="nom_type" name="nom_type" value="<?= htmlspecialchars($type_vaccin_edit['nom_type'] ?? '') ?>" required maxlength="255">
                        </div>
                        <div class="mb-4">
                            <label for="description" class="form-label">Description <small class="text-muted">(Optionnel)</small></label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($type_vaccin_edit['description'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
                            <?php // Lien Annuler : retourne à la liste triée et paginée ?>
                            <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste&page=<?= htmlspecialchars($page) ?><?= $queryStringPrefix ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= ($action == 'ajouter') ? 'Ajouter le type' : 'Enregistrer' ?></button>
                        </div>
                    </form>
                </div>

            <?php // CAS 2 : Liste ?>
            <?php elseif ($action == 'liste'): ?>
                 <div class="table-responsive">
                    <?php if (!empty($type_vaccins)): ?>
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php // Utilisation de la fonction pour les en-têtes triables ?>
                                    <?php renderSortLink('nom_type', 'Nom', $sort_by, $sort_dir); ?>
                                    <?php renderSortLink('description', 'Description', $sort_by, $sort_dir); ?>
                                    <th class="text-center" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($type_vaccins as $type_vaccin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($type_vaccin['nom_type']) ?></td>
                                        <td >
                                            <span class="table-description" data-bs-toggle="tooltip" title="<?= htmlspecialchars($type_vaccin['description'] ?: 'Aucune description') ?>">
                                                <?= htmlspecialchars($type_vaccin['description'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                             <?php // Liens d'action conservent page ET tri ?>
                                            <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=modifier&id=<?= $type_vaccin['type_vaccin_id'] ?>&page=<?= $page ?><?= $queryStringPrefix ?>" class="btn btn-primary btn-action" data-bs-toggle="tooltip" title="Modifier"><i class="fas fa-edit"></i></a>
                                            <button type="button" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer"
                                                    onclick="openDeleteTypeModal(<?= $type_vaccin['type_vaccin_id'] ?>, '<?= htmlspecialchars(addslashes($type_vaccin['nom_type']), ENT_QUOTES) ?>', <?= $page ?>, '<?= addslashes($queryStringPrefix) // Passer le préfixe de tri au JS ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: // Aucun type trouvé ?>
                        <div class="card-body text-center p-4">
                            <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-3">Aucun type de vaccin enregistré.</p>
                            <?php // Bouton reset si tri actif ?>
                            <?php if (!empty($currentQueryString)): ?>
                                <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser le tri</a><br>
                            <?php endif; ?>
                            <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=ajouter" class="btn btn-success"><i class="fas fa-plus me-1"></i>Créer le premier type</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php // -- Pagination -- ?>
                <?php if (!empty($type_vaccins) && $totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation Types Vaccins">
                            <ul class="pagination justify-content-center mb-0">
                                <?php // Construction de l'URL de base pour la pagination (conserve tri)
                                    $pageUrlBase = "?action=liste";
                                    if (!empty($currentQueryString)) $pageUrlBase .= "&" . $currentQueryString;
                                    $pageUrlBase .= "&page=";
                                ?>
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page - 1) ?>">«</a></li>
                                <?php // Logique pagination complexe inchangée, utilise $pageUrlBase ?>
                                <?php $linksToShow = 5; $startPage = max(1, $page - floor($linksToShow / 2)); $endPage = min($totalPages, $page + floor($linksToShow / 2)); if ($endPage - $startPage + 1 < $linksToShow) { if ($startPage === 1) { $endPage = min($totalPages, $startPage + $linksToShow - 1); } elseif ($endPage === $totalPages) { $startPage = max(1, $endPage - $linksToShow + 1); } } ?>
                                <?php if ($startPage > 1): ?><li class="page-item"><a class="page-link" href="<?= $pageUrlBase ?>1">1</a></li><?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><?php endif; ?>
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?><li class="page-item <?= $page == $i ? 'active' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . $i ?>"><?= $i ?></a></li><?php endfor; ?>
                                <?php if ($endPage < $totalPages): ?><?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= $pageUrlBase . $totalPages ?>"><?= $totalPages ?></a></li><?php endif; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page + 1) ?>">»</a></li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php endif; // Fin du if/elseif pour action=liste ou autre ?>

        </div> <?php // Fin card ?>

        <div class="text-center my-4"><a href="../admin.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour Admin</a></div>

    </div> <?php // Fin dashboard-container ?>

    <?php // --- Modals (Génération, Suppression globale, Suppression individuelle) --- ?>
    <?php // Les modales elles-mêmes n'ont pas besoin de connaître le tri actif ?>
    <!-- Modal Génération Test -->
    <div class="modal fade" id="generateTypesVaccinsModal" tabindex="-1" aria-labelledby="generateTypesVaccinsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm"> <div class="modal-content"> <form id="generateTypesVaccinsForm" action="<?= basename($_SERVER['PHP_SELF']) ?>" method="get"> <div class="modal-header"> <h6 class="modal-title" id="generateTypesVaccinsModalLabel"><i class="fas fa-flask me-2 text-info"></i>Générer Types Test</h6> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <input type="hidden" name="action" value="generer_types_vaccins"> <div class="mb-3"> <label for="nombreTypesVaccins" class="form-label">Nombre (1-50):</label> <input type="number" class="form-control form-control-sm" id="nombreTypesVaccins" name="nombre" value="5" min="1" max="50" required> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-sm btn-primary">Générer</button> </div> </form> </div> </div>
    </div>

    <!-- Modal Suppression Globale -->
    <div class="modal fade" id="deleteAllTypesVaccinsModal" tabindex="-1" aria-labelledby="deleteAllTypesVaccinsModalLabel" aria-hidden="true">
        <div class="modal-dialog"> <div class="modal-content"> <form id="deleteAllTypesVaccinsForm" action="<?= basename($_SERVER['PHP_SELF']) ?>" method="GET"> <div class="modal-header bg-danger text-white"> <h5 class="modal-title" id="deleteAllTypesVaccinsModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <p><strong class="text-danger">Attention : Action Irréversible !</strong></p> <p>Suppression définitive de :</p> <ul class="list-unstyled mb-3 small"> <li><i class="fas fa-tags text-danger me-2 fa-fw"></i><strong>TOUS</strong> les types de vaccins.</li> <li><i class="fas fa-link text-danger me-2 fa-fw"></i><strong>TOUTES</strong> les associations vaccin-type.</li> <li><i class="fas fa-boxes text-danger me-2 fa-fw"></i><strong>TOUS</strong> les lots liés (via vaccins).</li> <li><i class="fas fa-file-medical text-danger me-2 fa-fw"></i><strong>TOUS</strong> les enregistrements de vaccination liés (via lots).</li> </ul> <div class="form-check mt-3"> <input class="form-check-input" type="checkbox" value="" id="confirmDeleteAllTypesVaccinsCheckbox" required> <label class="form-check-label" for="confirmDeleteAllTypesVaccinsCheckbox">Je confirme vouloir tout supprimer.</label> </div> </div> <div class="modal-footer"> <input type="hidden" name="action" value="supprimer_tous_types_vaccins"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-danger" id="confirmDeleteAllTypesVaccinsBtn" disabled> <i class="fas fa-trash-alt me-1"></i>Supprimer Tout </button> </div> </form> </div> </div>
    </div>

    <!-- Modal Confirmation Suppression Individuelle -->
    <div class="modal fade" id="deleteTypeVaccinModal" tabindex="-1" aria-labelledby="deleteTypeVaccinModalLabel" aria-hidden="true">
        <div class="modal-dialog"> <div class="modal-content"> <div class="modal-header bg-danger text-white"> <h5 class="modal-title" id="deleteTypeVaccinModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <p><strong class="text-danger">Attention : Action Irréversible !</strong></p> <p>Vous allez supprimer le type de vaccin "<strong id="deleteTypeName"></strong>".</p> <p>Cela supprimera aussi définitivement les données associées (associations, lots liés, enregistrements liés).</p> <div class="form-check mt-3"> <input class="form-check-input" type="checkbox" value="" id="confirmDeleteTypeCheckbox" required> <label class="form-check-label" for="confirmDeleteTypeCheckbox">Je confirme vouloir supprimer ce type et ses données associées.</label> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="button" class="btn btn-danger" id="confirmDeleteTypeBtn" disabled> <i class="fas fa-trash-alt me-1"></i>Supprimer ce Type </button> </div> </div> </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Génération Test ---
        function openGenerateTypesVaccinsModal() { var m=document.getElementById('generateTypesVaccinsModal'); if(m){new bootstrap.Modal(m).show();} }

        // --- Suppression Globale ---
        const deleteAllTypesVaccinsModalEl = document.getElementById('deleteAllTypesVaccinsModal');
        if (deleteAllTypesVaccinsModalEl) {
            const c=deleteAllTypesVaccinsModalEl.querySelector('#confirmDeleteAllTypesVaccinsCheckbox'), b=deleteAllTypesVaccinsModalEl.querySelector('#confirmDeleteAllTypesVaccinsBtn'), i=new bootstrap.Modal(deleteAllTypesVaccinsModalEl);
            window.openDeleteAllTypesVaccinsModal=function(){c.checked=false; b.disabled=true; i.show();};
            c.addEventListener('change', function(){b.disabled = !this.checked;});
        }

        // --- Suppression Individuelle ---
        const deleteTypeModalEl = document.getElementById('deleteTypeVaccinModal');
        if(deleteTypeModalEl) {
            const confirmCheckboxType = deleteTypeModalEl.querySelector('#confirmDeleteTypeCheckbox');
            const confirmBtnType = deleteTypeModalEl.querySelector('#confirmDeleteTypeBtn');
            const typeNameSpan = deleteTypeModalEl.querySelector('#deleteTypeName');
            const modalInstanceType = new bootstrap.Modal(deleteTypeModalEl);
            let deleteUrl = '#'; // Variable pour stocker l'URL de suppression

            // Fonction pour ouvrir la modale individuelle, accepte le préfixe de tri
            window.openDeleteTypeModal = function(id, name, page, sortPrefix) {
                typeNameSpan.textContent = name; // Met à jour le nom
                // Construit l'URL de suppression en ajoutant le préfixe de tri
                // Important: Assurez-vous que sortPrefix est correctement échappé s'il contient des caractères spéciaux (normalement http_build_query le fait).
                // Le addslashes() en PHP sur le sortPrefix est pour l'intégrer dans la chaîne JS, mais pas nécessaire pour l'URL elle-même.
                deleteUrl = `<?= basename($_SERVER['PHP_SELF']) ?>?action=supprimer&id=${id}&page=${page}${sortPrefix}`;
                confirmCheckboxType.checked = false; // Réinitialise checkbox
                confirmBtnType.disabled = true; // Réinitialise bouton
                modalInstanceType.show(); // Affiche la modale
            }

            // Activer bouton sur coche
            confirmCheckboxType.addEventListener('change', function() {
                confirmBtnType.disabled = !this.checked;
            });

            // Action du bouton de confirmation
            confirmBtnType.addEventListener('click', function() {
                if (!confirmCheckboxType.checked) return;
                window.location.href = deleteUrl; // Redirige vers l'URL construite
            });
        }

        // --- Initialisation Tooltips ---
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, { delay: { "show": 300, "hide": 100 }, html: true });
                }
            });
        });
    </script>
</body>
</html>