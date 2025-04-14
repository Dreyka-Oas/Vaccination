<?php
// ==========================================================================
// Fichier: system/system_gerer_campagnes.php
// Description: Logique métier pour la Gestion CRUD, Filtrage et Tri des campagnes vaccinales.
//              (Inclus par gerer_campagnes.php)
// Auteur: [Votre Nom/Équipe]
// Date: [Date de création/modification] // Mise à jour pour séparation logique/présentation
// ==========================================================================

// --- Configuration et Initialisation ---
// Error reporting should be ideally set in a global config or bootstrap file,
// but kept here for self-containment as per original file.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Sécurité et Contrôle d'accès ---
if (!isset($_SESSION['matricule']) || $_SESSION['role'] !== 'admin') {
    // Redirect relative to the *original* calling script's location
    header("Location: ../../index.php");
    exit();
}

// --- Inclusions et Dépendances ---
// Adjust paths: Go up one more level (../) because this file is in 'system/'
require_once __DIR__ . '../../../../app/config/config.php';
require_once __DIR__ . '../../../../vendor/autoload.php';

use Faker\Factory;

// --- Initialisation des Variables ---
$action = $_GET['action'] ?? 'liste';
$message = ''; // Message direct pour erreurs critiques d'affichage liste
$campagnes = [];
$campagne_edit = null;

// Paramètres pour la pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10;
// $offset calculated later based on total filtered count

// Paramètre pour le filtrage par statut (uniquement)
$filtre_statut = $_GET['filtre_statut'] ?? ''; // Valeur du filtre statut ('', '1', '0')

// Paramètres pour le tri
$allowed_sort_columns = ['nom_campagne', 'date_debut', 'date_fin', 'description', 'is_active', 'campagne_id']; // Ajout ID pour le défaut
$sort_by = $_GET['sort_by'] ?? 'campagne_id'; // Défaut: tri par ID
$sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC'; // Défaut: DESC

// Validation des paramètres de tri
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'campagne_id'; // Revenir au défaut si invalide
}
if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
    $sort_dir = 'DESC'; // Revenir au défaut si invalide
}

// Construction de la chaîne de requête pour conserver les filtres/tri dans les liens
$queryStringParams = [];
if ($filtre_statut !== '') $queryStringParams['filtre_statut'] = $filtre_statut;
// N'ajoute que si ce n'est pas le défaut strict (ID DESC)
if ($sort_by !== 'campagne_id' || $sort_dir !== 'DESC') {
    $queryStringParams['sort_by'] = $sort_by;
    $queryStringParams['sort_dir'] = $sort_dir;
}
$currentQueryString = http_build_query($queryStringParams); // Ex: filtre_statut=1&sort_by=nom_campagne&sort_dir=ASC


// --- Helper Function for Sort Links (used by presentation layer) ---
function renderSortLink($columnName, $label, $currentSortBy, $currentSortDir, $currentFilterStatut) {
    $newSortDir = ($currentSortBy === $columnName && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';
    $linkParams = ['action' => 'liste']; // Reset page on sort
    if ($currentFilterStatut !== '') $linkParams['filtre_statut'] = $currentFilterStatut;
    $linkParams['sort_by'] = $columnName;
    $linkParams['sort_dir'] = $newSortDir;

    $callingScript = basename($_SERVER['PHP_SELF']); // Get the name of the calling script (gerer_campagnes.php)
    $url = $callingScript . '?' . http_build_query($linkParams);

    $iconClass = 'fas fa-sort'; // Default icon
    $thClass = '';
    if ($currentSortBy === $columnName) {
        $iconClass = ($currentSortDir === 'ASC') ? 'fas fa-sort-up' : 'fas fa-sort-down';
        $thClass = 'sorted'; // Class to style the sorted column header/icon
    }
    // Use htmlspecialchars on label just in case, though usually static
    echo "<th class=\"{$thClass}\"><a href=\"{$url}\">" . htmlspecialchars($label) . "<i class=\"sort-icon {$iconClass}\"></i></a></th>";
}


// --- Traitement Principal ---
try {
    // --- Connexion à la Base de Données (PDO) ---
    // Constants DB_HOST etc. are loaded from config.php
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $callingScript = basename($_SERVER['PHP_SELF']); // e.g., 'gerer_campagnes.php'

    // --- 1. TRAITEMENT DES REQUÊTES POST (Ajout / Modification) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $currentPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $matricule = $_SESSION['matricule']; // Assumed valid from security check

        // Construction de l'URL de redirection conservant les paramètres GET actuels (filtre/tri)
        $redirectBaseUrl = $callingScript . "?action=liste&page=" . $currentPage;
        if (!empty($currentQueryString)) {
            $redirectBaseUrl .= "&" . $currentQueryString;
        }
        $formRedirectBaseUrl = $callingScript . "?"; // For errors redirecting back to forms

        // --- Action POST : Ajouter une campagne ---
        if ($action == 'ajouter') {
            $nom_campagne = $_POST['nom_campagne'] ?? null;
            $date_debut = empty($_POST['date_debut']) ? null : $_POST['date_debut'];
            $date_fin = empty($_POST['date_fin']) ? null : $_POST['date_fin'];
            $description = $_POST['description'] ?? '';
            $is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1';

            if (empty($nom_campagne)) {
                $_SESSION['message'] = "Erreur : Le nom de la campagne est obligatoire.";
                $_SESSION['message_type'] = 'danger';
                // Redirect back to add form, preserve sort/filter if any were present in URL
                header("Location: " . $formRedirectBaseUrl . "action=ajouter" . (!empty($currentQueryString) ? '&' . $currentQueryString : ''));
                exit();
            }
            // User existence check (good practice)
            $userCheckStmt = $pdo->prepare("SELECT 1 FROM users WHERE matricule = ?");
            $userCheckStmt->execute([$matricule]);
            if ($userCheckStmt->fetchColumn() === false) {
                 $_SESSION['message'] = "Erreur : L'utilisateur de création (matricule: " . htmlspecialchars($matricule) . ") n'existe pas.";
                 $_SESSION['message_type'] = 'danger';
                 header("Location: " . $formRedirectBaseUrl . "action=ajouter" . (!empty($currentQueryString) ? '&' . $currentQueryString : ''));
                 exit();
            }

            $sql = "INSERT INTO campagnes (nom_campagne, date_debut, date_fin, description, is_active, matricule_creation)
                    VALUES (:nom, :debut, :fin, :desc, :active, :matricule)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nom', $nom_campagne);
            $stmt->bindValue(':debut', $date_debut);
            $stmt->bindValue(':fin', $date_fin);
            $stmt->bindValue(':desc', $description);
            $stmt->bindValue(':active', $is_active, PDO::PARAM_BOOL);
            $stmt->bindValue(':matricule', $matricule);
            $stmt->execute();

            $_SESSION['message'] = "Campagne '" . htmlspecialchars($nom_campagne) . "' ajoutée avec succès.";
            $_SESSION['message_type'] = 'success';
            header("Location: " . $redirectBaseUrl); // Redirige vers la liste avec filtre/tri/page
            exit();
        }
        // --- Action POST : Modifier une campagne ---
        elseif ($action == 'modifier' && isset($_POST['campagne_id'])) {
            $campagne_id = (int)$_POST['campagne_id'];
            $nom_campagne = $_POST['nom_campagne'] ?? null;
            $date_debut = empty($_POST['date_debut']) ? null : $_POST['date_debut'];
            $date_fin = empty($_POST['date_fin']) ? null : $_POST['date_fin'];
            $description = $_POST['description'] ?? '';
            $is_active = (isset($_POST['is_active']) && $_POST['is_active'] == '1');

            if (empty($nom_campagne)) {
                $_SESSION['message'] = "Erreur : Le nom de la campagne est obligatoire.";
                $_SESSION['message_type'] = 'danger';
                // Rediriger vers le formulaire de modif en conservant les params
                $modifUrl = $formRedirectBaseUrl . "action=modifier&id=" . $campagne_id . "&page=" . $currentPage;
                if (!empty($currentQueryString)) $modifUrl .= "&" . $currentQueryString;
                header("Location: " . $modifUrl);
                exit();
            }

            $sql = "UPDATE campagnes SET
                           nom_campagne = :nom,
                           date_debut = :debut,
                           date_fin = :fin,
                           description = :desc,
                           is_active = :active,
                           matricule_creation = :matricule -- Ou matricule_modification
                    WHERE campagne_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nom', $nom_campagne);
            $stmt->bindValue(':debut', $date_debut);
            $stmt->bindValue(':fin', $date_fin);
            $stmt->bindValue(':desc', $description);
            // PDO::PARAM_BOOL should work, but PARAM_INT(0/1) is often safer across DBs/versions
            $stmt->bindValue(':active', $is_active ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':matricule', $matricule);
            $stmt->bindValue(':id', $campagne_id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['message'] = "Campagne modifiée avec succès.";
            $_SESSION['message_type'] = 'success';
             header("Location: " . $redirectBaseUrl); // Redirige vers la liste avec filtre/tri/page
            exit();
        }
    }

    // --- 2. TRAITEMENT DES REQUÊTES GET (Actions et Affichage Préparation) ---

    // --- Action GET : Générer des campagnes de test ---
    elseif ($action == 'generer_campagnes') {
        $nombre_campagnes = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 5;
        if ($nombre_campagnes < 1) $nombre_campagnes = 1;
        if ($nombre_campagnes > 50) $nombre_campagnes = 50; // Limit generation

        $faker = Factory::create('fr_FR');
        $count_success = 0;
        $matricule_creation = $_SESSION['matricule'];

        // Verify admin matricule exists (important!)
        $userCheckStmt = $pdo->prepare("SELECT 1 FROM users WHERE matricule = ?");
        $userCheckStmt->execute([$matricule_creation]);
        if ($userCheckStmt->fetchColumn() === false) {
             $_SESSION['message'] = "Erreur fatale: Le matricule de l'administrateur (".htmlspecialchars($matricule_creation).") est invalide. Impossible de générer des campagnes.";
             $_SESSION['message_type'] = 'danger';
             header("Location: " . $callingScript . "?action=liste" . (!empty($currentQueryString) ? '&' . $currentQueryString : ''));
             exit();
        }

        $sqlInsert = "INSERT INTO campagnes (nom_campagne, date_debut, date_fin, description, is_active, matricule_creation) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert); // Prepare once

        // Find max existing 'Campagne X' number to avoid collisions
        $maxNumStmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(nom_campagne FROM E'^Campagne\\\\s+(\\\\d+)$') AS INTEGER)) FROM campagnes WHERE nom_campagne ~ E'^Campagne\\\\s+\\\\d+$'");
        $startNum = ($maxNumStmt->fetchColumn() ?: 0) + 1;

        for ($i = 0; $i < $nombre_campagnes; $i++) {
            $currentNum = $startNum + $i;
            $nom_campagne = 'Campagne ' . $currentNum;
            $description = 'Description Campagne ' . $currentNum;
            try {
                $date_debut_obj = $faker->dateTimeBetween('-1 year', '+2 months');
                $date_debut = $date_debut_obj->format('Y-m-d');
                // Ensure end date is strictly after start date
                $min_end_date = (clone $date_debut_obj)->modify('+1 day');
                $max_end_date = (clone $date_debut_obj)->modify('+1 year');
                $date_fin_obj = $faker->dateTimeBetween($min_end_date, $max_end_date);
                $date_fin = $date_fin_obj->format('Y-m-d');
                $is_active = $faker->boolean(75); // 75% chance of being active

                // Execute the prepared statement with new values
                if ($stmtInsert->execute([$nom_campagne, $date_debut, $date_fin, $description, $is_active, $matricule_creation])) {
                    $count_success++;
                }
             } catch (PDOException $e) {
                 // Log specific error but continue generating others
                 error_log("[ERROR] Exception inserting fake campaign: " . $nom_campagne . " - Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
             } catch (\Exception $e) { // Catch potential Faker date errors
                 error_log("[ERROR] Exception generating fake data for campaign: " . $nom_campagne . " - Error: " . $e->getMessage());
             }
        }

        $_SESSION['message'] = "{$count_success}/{$nombre_campagnes} campagnes de test générées.";
        $_SESSION['message_type'] = ($count_success == $nombre_campagnes) ? 'success' : (($count_success > 0) ? 'warning' : 'danger');
        // Redirect back to the list, preserving sort/filter state
        header("Location: " . $callingScript . "?action=liste" . (!empty($currentQueryString) ? '&' . $currentQueryString : ''));
        exit();
    }

    // --- Action GET : Supprimer TOUTES les campagnes ---
    elseif ($action == 'supprimer_toutes_campagnes') {
        // IMPORTANT: Add extra confirmation/security if needed in a real app
         try {
            $pdo->beginTransaction();
            // Delete dependent records first (order matters!)
            $pdo->exec("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE campagne_id IN (SELECT campagne_id FROM campagnes))");
            $pdo->exec("DELETE FROM lots WHERE campagne_id IN (SELECT campagne_id FROM campagnes)");
            $pdo->exec("DELETE FROM vaccin_type_associations WHERE vaccin_id IN (SELECT vaccin_id FROM vaccins WHERE campagne_id IN (SELECT campagne_id FROM campagnes))");
            $pdo->exec("DELETE FROM vaccins WHERE campagne_id IN (SELECT campagne_id FROM campagnes)");
            // Finally, delete the campaigns
            $deletedRows = $pdo->exec("DELETE FROM campagnes");
            $pdo->commit();
            $_SESSION['message'] = "{$deletedRows} campagnes et toutes les données associées ont été supprimées.";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("[ERROR] supprimer_toutes_campagnes failed: " . $e->getMessage());
             $_SESSION['message'] = "Erreur lors de la suppression globale des campagnes. Voir les logs pour détails. Détails: " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
        // Redirect back to list (filters/sorts might be less relevant but preserved)
        header("Location: " . $callingScript . "?action=liste" . (!empty($currentQueryString) ? '&' . $currentQueryString : ''));
        exit();
    }

    // --- Action GET : Supprimer une campagne spécifique ---
    elseif ($action == 'supprimer' && isset($_GET['id'])) {
        $campagne_id = (int)$_GET['id'];
        try {
            $pdo->beginTransaction();
            // Delete dependencies first
            $stmtVr = $pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE campagne_id = ?)");
            $stmtVr->execute([$campagne_id]);
            $stmtL = $pdo->prepare("DELETE FROM lots WHERE campagne_id = ?");
            $stmtL->execute([$campagne_id]);
            $stmtVta = $pdo->prepare("DELETE FROM vaccin_type_associations WHERE vaccin_id IN (SELECT vaccin_id FROM vaccins WHERE campagne_id = ?)");
            $stmtVta->execute([$campagne_id]);
            $stmtV = $pdo->prepare("DELETE FROM vaccins WHERE campagne_id = ?");
            $stmtV->execute([$campagne_id]);
            // Delete the campaign itself
            $stmtC = $pdo->prepare("DELETE FROM campagnes WHERE campagne_id = ?");
            $stmtC->execute([$campagne_id]);
            $rowCount = $stmtC->rowCount();
            $pdo->commit();

            if ($rowCount > 0) {
                $_SESSION['message'] = "Campagne (ID: {$campagne_id}) et données associées supprimées avec succès.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Campagne (ID: {$campagne_id}) non trouvée ou déjà supprimée.";
                $_SESSION['message_type'] = 'warning';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("[ERROR] supprimer campagne ID {$campagne_id} failed: " . $e->getMessage());
            $_SESSION['message'] = "Erreur lors de la suppression de la campagne (ID: {$campagne_id}). Détails: " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
        // Redirect back to the list, preserving page, filter, and sort
        $redirectUrl = $callingScript . "?action=liste&page=" . $page;
        if (!empty($currentQueryString)) $redirectUrl .= "&" . $currentQueryString;
        header("Location: " . $redirectUrl);
        exit();
    }

    // --- Action GET : Activer ou Désactiver une campagne ---
    elseif (($action == 'activer' || $action == 'desactiver') && isset($_GET['id'])) {
        $campagne_id = (int)$_GET['id'];
        $newState = ($action == 'activer'); // true for activer, false for desactiver

         $sql = "UPDATE campagnes SET is_active = ? WHERE campagne_id = ?";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->bindValue(1, $newState, PDO::PARAM_BOOL); // Use PARAM_BOOL
            $stmt->bindValue(2, $campagne_id, PDO::PARAM_INT);
            $stmt->execute();
            $rowCount = $stmt->rowCount();

            if ($rowCount > 0) {
                $_SESSION['message'] = "Statut de la campagne (ID: {$campagne_id}) mis à jour avec succès (" . ($newState ? "Activée" : "Désactivée") . ").";
                $_SESSION['message_type'] = 'success';
            } else {
                // Check if it already had the desired state or didn't exist
                $checkStmt = $pdo->prepare("SELECT is_active FROM campagnes WHERE campagne_id = ?");
                $checkStmt->execute([$campagne_id]);
                $dbState = $checkStmt->fetchColumn();

                if ($dbState === false) {
                     $_SESSION['message'] = "Campagne (ID: {$campagne_id}) non trouvée.";
                     $_SESSION['message_type'] = 'warning';
                } elseif (filter_var($dbState, FILTER_VALIDATE_BOOLEAN) === $newState) {
                     $_SESSION['message'] = "La campagne (ID: {$campagne_id}) était déjà " . ($newState ? "active" : "inactive") . ".";
                     $_SESSION['message_type'] = 'info';
                } else {
                     // Should not happen if rowCount was 0 unless concurrent modification
                     $_SESSION['message'] = "Impossible de mettre à jour le statut pour la campagne (ID: {$campagne_id}).";
                     $_SESSION['message_type'] = 'warning';
                }
            }
        } catch (PDOException $e) {
             error_log("[ERROR " . ucfirst($action) . " ID {$campagne_id}] " . $e->getMessage());
             $_SESSION['message'] = "Erreur base de données lors de la mise à jour du statut (ID: {$campagne_id}).";
             $_SESSION['message_type'] = 'danger';
        }
         // Redirect back to the list, preserving page, filter, and sort
        $redirectUrl = $callingScript . "?action=liste&page=" . $page;
        if (!empty($currentQueryString)) $redirectUrl .= "&" . $currentQueryString;
        header("Location: " . $redirectUrl);
        exit();
    }

    // --- 3. PRÉPARATION DES DONNÉES POUR L'AFFICHAGE (Form or List) ---

    $totalPages = 1; // Initialize totalPages

    // --- Préparation pour le formulaire de modification (action=modifier GET) ---
    if ($action == 'modifier' && isset($_GET['id'])) {
        $campagne_id = (int)$_GET['id'];
        // Current page is needed for the 'Cancel' button link
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;

        $stmt = $pdo->prepare("SELECT * FROM campagnes WHERE campagne_id = ?");
        $stmt->execute([$campagne_id]);
        $campagne_edit = $stmt->fetch();

        if (!$campagne_edit) {
            $_SESSION['message'] = "Campagne (ID: {$campagne_id}) non trouvée pour modification.";
            $_SESSION['message_type'] = 'warning';
            // Redirect to list, preserving filter/sort
            $listUrl = $callingScript . "?action=liste";
            if (!empty($currentQueryString)) $listUrl .= "&" . $currentQueryString;
             header("Location: " . $listUrl);
            exit();
        }
         // Ensure boolean interpretation for checkbox
         if (isset($campagne_edit['is_active'])) {
             $campagne_edit['is_active'] = filter_var($campagne_edit['is_active'], FILTER_VALIDATE_BOOLEAN);
         }
    }

    // --- Préparation pour afficher la liste des campagnes (action=liste) ---
    elseif ($action == 'liste') {
        // Construction dynamique de la clause WHERE et des paramètres (uniquement statut)
        $whereConditions = [];
        $params = []; // Parameters for the WHERE clause

        if ($filtre_statut === '1' || $filtre_statut === '0') {
            $whereConditions[] = "is_active = ?";
            $params[] = (int)$filtre_statut; // Bind as integer 0 or 1
        }

        $sqlWhere = "";
        if (!empty($whereConditions)) {
            $sqlWhere = " WHERE " . implode(" AND ", $whereConditions);
        }

        // 1. Compter le nombre TOTAL de campagnes correspondant au filtre
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM campagnes" . $sqlWhere);
        $totalStmt->execute($params); // Execute with filter params only
        $totalCampagnes = $totalStmt->fetchColumn();

        // 2. Calculer le nombre total de pages basé sur les résultats filtrés
        $totalPages = $totalCampagnes > 0 ? ceil($totalCampagnes / $perPage) : 1;

        // 3. Ajuster la page actuelle si elle dépasse le nombre total de pages calculé
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        // Ensure page is at least 1
        if ($page < 1) {
            $page = 1;
        }

        // 4. Calculer l'offset basé sur la page (potentiellement ajustée)
        $offset = ($page - 1) * $perPage;

        // 5. Construire la clause ORDER BY (sécurisée)
        $orderByClause = " ORDER BY ";
        // Use LOWER() for case-insensitive sorting on text fields
        if ($sort_by === 'nom_campagne' || $sort_by === 'description') {
            $orderByClause .= "LOWER(" . $sort_by . ") " . $sort_dir;
        } else {
            // For dates, ID, is_active, boolean - direct sort is fine
             $orderByClause .= $sort_by . " " . $sort_dir;
        }
         // Add secondary sort for stability, unless already sorting by the primary key
         if ($sort_by !== 'campagne_id') {
             $orderByClause .= ", campagne_id ASC"; // Or DESC if primary sort is DESC
         }


        // 6. Construire et exécuter la requête principale pour récupérer les campagnes de la page
        $sqlList = "SELECT * FROM campagnes" . $sqlWhere . $orderByClause . " LIMIT ? OFFSET ?";
        $stmtList = $pdo->prepare($sqlList);

        // Lier les paramètres : d'abord ceux du WHERE, puis LIMIT, puis OFFSET
        $bindIndex = 1;
        foreach ($params as $param) { // $params contains filter values (e.g., status)
            // Determine param type if more filters are added later
            $stmtList->bindValue($bindIndex++, $param, PDO::PARAM_INT); // Status is INT (0/1)
        }
        // Bind LIMIT and OFFSET parameters
        $stmtList->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $stmtList->bindValue($bindIndex++, $offset, PDO::PARAM_INT);

        $stmtList->execute();
        $campagnes = $stmtList->fetchAll();

        // 7. Post-traitement (e.g., convertir is_active en booléen pour affichage)
         foreach ($campagnes as $key => $campagne) {
             if (isset($campagne['is_active'])) {
                 $campagnes[$key]['is_active'] = filter_var($campagne['is_active'], FILTER_VALIDATE_BOOLEAN);
             }
         }
        // error_log("[DEBUG] Fetched " . count($campagnes) . " campagnes for page {$page}/{$totalPages} (Total filtered: {$totalCampagnes}) - Sort: {$sort_by} {$sort_dir}, Filter: {$filtre_statut}");

    } // End action 'liste'

} catch (PDOException $e) {
    // --- GESTION GLOBALE DES ERREURS PDO ---
    error_log("[FATAL PDOException in system_gerer_campagnes.php] " . $e->getMessage() . " (Code: " . $e->getCode() . ")\nTrace: " . $e->getTraceAsString());

    // Set message for session or direct display
    $criticalErrorMessage = "Erreur critique de base de données. L'opération n'a pas pu être complétée. Veuillez contacter l'administrateur. Détails techniques loggés.";
    $_SESSION['message'] = $criticalErrorMessage;
    $_SESSION['message_type'] = 'danger';

    // Try to redirect to a safe page (like the list view) if the error didn't occur during list view preparation itself.
    // If the error happened while fetching the list, we can't redirect, so we set $message for direct display.
    if (isset($action) && $action !== 'liste') {
       $fallbackListUrl = $callingScript . "?action=liste";
       // Try to preserve filters/sorts in the fallback URL
       if (!empty($currentQueryString)) $fallbackListUrl .= "&" . $currentQueryString;
       // Prevent redirect loops if headers already sent (though unlikely with exit())
       if (!headers_sent()) {
           header("Location: " . $fallbackListUrl);
           exit();
       } else {
           // Fallback if redirection fails: display error directly on potentially broken page
           $message = $criticalErrorMessage;
           // Clear data to prevent further errors in the HTML part
           $campagnes = [];
           $campagne_edit = null;
           $totalPages = 1;
           $page = 1;
       }
    } else {
       // Error occurred during 'liste' action preparation. Set $message for direct display.
       $message = $criticalErrorMessage;
       // Reset data for safe rendering of the (likely empty) list view
       $campagnes = [];
       $totalPages = 1;
       $page = 1;
       // $currentQueryString, $sort_by, $sort_dir, $filtre_statut remain as they were for filter/sort UI state
    }
} catch (\Exception $e) {
    // --- Catch non-PDO exceptions (e.g., Faker errors) ---
    error_log("[FATAL Exception in system_gerer_campagnes.php] " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $generalErrorMessage = "Une erreur inattendue est survenue. Contactez l'administrateur. Détails loggés.";
    $_SESSION['message'] = $generalErrorMessage;
    $_SESSION['message_type'] = 'danger';

    if (isset($action) && $action !== 'liste' && !headers_sent()) {
       $fallbackListUrl = $callingScript . "?action=liste";
       if (!empty($currentQueryString)) $fallbackListUrl .= "&" . $currentQueryString;
       header("Location: " . $fallbackListUrl);
       exit();
    } else {
       $message = $generalErrorMessage;
       $campagnes = [];
       $campagne_edit = null;
       $totalPages = 1;
       $page = 1;
    }
}

// --- Variables available to the including file (gerer_campagnes.php):
// $action, $message (if critical list error), $campagnes, $campagne_edit,
// $page, $totalPages, $filtre_statut, $sort_by, $sort_dir, $currentQueryString,
// $pdo (if needed, though generally presentation shouldn't use it directly)
// The function renderSortLink() is also defined.
// Session messages ($_SESSION['message'], $_SESSION['message_type']) might also be set.

// No closing ?>