<?php
// ==========================================================================
// Fichier: gestion/gerer_utilisateurs.php
// Description: Gestion CRUD, Filtre, Recherche, Génération test des utilisateurs.
//              Permet la modification du matricule (avec confirmation) et du mot de passe.
// WARNING: La modification du matricule (PK) est risquée sans ON UPDATE CASCADE sur les FKs.
// ==========================================================================

// Afficher les erreurs pour le débogage (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- Sécurité : Vérifier si l'utilisateur est admin ---
if (!isset($_SESSION['matricule']) || $_SESSION['role'] !== 'admin') {
    // Utilisation du chemin corrigé vers l'index (suppose 'gestion' est à la racine)
    header("Location: ../index.php");
    exit();
}

// --- Inclusions ---
// Chemins corrigés
require_once '../../app/config/config.php';
require_once '../../vendor/autoload.php'; // Nécessaire pour Faker

use Faker\Factory; // Alias pour Faker

// --- Initialisation ---
$action = $_GET['action'] ?? 'liste';
$message = '';
$message_type = 'info'; // Default message type
$users_list = [];
$roles_list = [];
$user_edit = null;
$logged_in_user_matricule = $_SESSION['matricule'];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalPages = 1;
$totalUsers = 0;

// Filtres et Recherche
$filter_role_id = filter_input(INPUT_GET, 'filter_role_id', FILTER_VALIDATE_INT);
$search_term = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '');

// Construire la chaîne de requête pour conserver les filtres/recherche
$queryStringParams = [];
if ($filter_role_id) { $queryStringParams['filter_role_id'] = $filter_role_id; }
if (!empty($search_term)) { $queryStringParams['search'] = $search_term; }
$currentQueryString = http_build_query($queryStringParams);
$queryStringPrefix = !empty($currentQueryString) ? '&' . $currentQueryString : '';

// --- Fonction pour générer le sel (compatible avec crypt() BLOWFISH) ---
function gen_salt($cost = 10) {
    $salt = strtr(base64_encode(random_bytes(16)), '+', '.');
    return sprintf('$2y$%02d$', $cost) . $salt;
}

try {
    // --- Connexion DB ---
    $pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // --- Pré-charger les rôles ---
    $stmt_roles = $pdo->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
    $roles_list = $stmt_roles->fetchAll();
    $available_role_ids = array_column($roles_list, 'role_id');

    // --- 1. TRAITEMENT POST (Ajout / Modification Formulaire) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $currentPage = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $redirectBaseUrl = basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix;

        // --- Action Ajouter ---
        if ($action == 'ajouter') {
            // Récupération et validation des données... (comme avant)
            $matricule = trim(filter_input(INPUT_POST, 'matricule', FILTER_SANITIZE_SPECIAL_CHARS));
            $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
            $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            // Validations...
            if (empty($matricule) || empty($username) || !$role_id) { $_SESSION['message'] = "Erreur : Matricule, Nom d'utilisateur et Rôle sont obligatoires."; $_SESSION['message_type'] = 'danger'; }
            elseif ($email === false && !empty($_POST['email'])) { $_SESSION['message'] = "Erreur : Format d'email invalide."; $_SESSION['message_type'] = 'danger'; }
            elseif (empty($password) || strlen($password) < 6) { $_SESSION['message'] = "Erreur : Le mot de passe est obligatoire (minimum 6 caractères)."; $_SESSION['message_type'] = 'danger'; }
            elseif ($password !== $password_confirm) { $_SESSION['message'] = "Erreur : Les mots de passe ne correspondent pas."; $_SESSION['message_type'] = 'danger'; }
            else {
                // Hashage et Insertion... (comme avant)
                $salt = gen_salt();
                $password_hash = crypt($password, $salt);
                try {
                    $sql = "INSERT INTO users (matricule, username, password_hash, email, role_id, matricule_creation) VALUES (:mat, :uname, :pwhash, :email, :role_id, :creator)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':mat' => $matricule, ':uname' => $username, ':pwhash' => $password_hash, ':email' => $email, ':role_id' => $role_id, ':creator' => $logged_in_user_matricule]);
                    $_SESSION['message'] = "Utilisateur '$username' ajouté avec succès."; $_SESSION['message_type'] = 'success';
                    header("Location: " . $redirectBaseUrl . "&page=1"); exit();
                } catch (PDOException $e) { /* Gestion erreurs unique... */
                    if (strpos($e->getMessage(), 'users_pkey') !== false) { $_SESSION['message'] = "Erreur : Ce matricule existe déjà."; }
                    elseif (strpos($e->getMessage(), 'users_username_key') !== false) { $_SESSION['message'] = "Erreur : Ce nom d'utilisateur existe déjà."; }
                    elseif (strpos($e->getMessage(), 'users_email_key') !== false) { $_SESSION['message'] = "Erreur : Cet email est déjà utilisé."; }
                    else { $_SESSION['message'] = "Erreur base de données lors de l'ajout."; error_log("[ERROR] User Add PDOException: " . $e->getMessage()); }
                    $_SESSION['message_type'] = 'danger';
                }
            }
            // Redirection erreur ajout
            header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=ajouter" . $queryStringPrefix); exit();

        // --- Action Modifier ---
        } elseif ($action == 'modifier' && isset($_POST['original_matricule'])) {
            $original_matricule = trim($_POST['original_matricule']);
            $new_matricule = trim(filter_input(INPUT_POST, 'matricule', FILTER_SANITIZE_SPECIAL_CHARS)); // Nouveau matricule potentiel
            $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
            $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
            $confirm_matricule_change = isset($_POST['confirm_matricule_change']); // Checkbox confirmation matricule
            $new_password = $_POST['new_password'] ?? ''; // Nouveau mot de passe potentiel
            $new_password_confirm = $_POST['new_password_confirm'] ?? '';

            $matricule_changed = ($new_matricule !== $original_matricule);
            $password_changed = !empty($new_password);
            $validation_ok = true;
            $error_message = '';

            // Validations standard
            if (empty($new_matricule) || empty($username) || !$role_id) { $validation_ok = false; $error_message = "Matricule, Nom d'utilisateur et Rôle sont obligatoires."; }
            elseif ($email === false && !empty($_POST['email'])) { $validation_ok = false; $error_message = "Format d'email invalide."; }

            // Validation changement matricule
            if ($validation_ok && $matricule_changed && !$confirm_matricule_change) {
                 $validation_ok = false;
                 $error_message = "Vous devez cocher la case pour confirmer la modification du matricule.";
            }

            // Validation changement mot de passe (si tenté)
            if ($validation_ok && $password_changed) {
                if (strlen($new_password) < 6) {
                    $validation_ok = false; $error_message = "Le nouveau mot de passe doit faire au moins 6 caractères.";
                } elseif ($new_password !== $new_password_confirm) {
                    $validation_ok = false; $error_message = "Les nouveaux mots de passe ne correspondent pas.";
                }
            }

            // Si toutes les validations sont OK, procéder à l'UPDATE
            if ($validation_ok) {
                try {
                    // Construire la requête UPDATE dynamiquement
                    $sql_parts = [];
                    $params_update = [];

                    // Toujours mettre à jour ces champs
                    $sql_parts[] = "username = :uname"; $params_update[':uname'] = $username;
                    $sql_parts[] = "email = :email"; $params_update[':email'] = $email;
                    $sql_parts[] = "role_id = :role_id"; $params_update[':role_id'] = $role_id;

                    // Ajouter la modification du matricule si nécessaire
                    if ($matricule_changed) {
                        $sql_parts[] = "matricule = :new_mat"; $params_update[':new_mat'] = $new_matricule;
                    }

                    // Ajouter la modification du mot de passe si nécessaire
                    if ($password_changed) {
                        $salt = gen_salt();
                        $new_password_hash = crypt($new_password, $salt);
                        $sql_parts[] = "password_hash = :new_pwhash"; $params_update[':new_pwhash'] = $new_password_hash;
                    }

                    // Clause WHERE indispensable
                    $params_update[':orig_mat'] = $original_matricule;

                    if (!empty($sql_parts)) {
                        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE matricule = :orig_mat";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params_update);

                        $_SESSION['message'] = "Utilisateur '$username' modifié avec succès.";
                        // Si le matricule a été changé, il faut utiliser le *nouveau* pour retrouver l'utilisateur dans la liste après redirection
                        $_SESSION['highlight_matricule'] = $new_matricule;
                        $_SESSION['message_type'] = 'success';
                         // Redirection vers la page où l'élément *devrait* être après modif (potentiellement différent si tri/filtre affecté)
                        header("Location: " . $redirectBaseUrl . "&page=" . $currentPage);
                        exit();
                    } else {
                         // Cas où rien n'a été modifié (ne devrait pas arriver si on valide au moins username/role)
                         $_SESSION['message'] = "Aucune modification détectée.";
                         $_SESSION['message_type'] = 'info';
                    }

                } catch (PDOException $e) {
                     // Gérer les erreurs potentielles (PK existante, FK violation si pas de CASCADE)
                    if (strpos($e->getMessage(), 'users_pkey') !== false || strpos($e->getMessage(), 'duplicate key value violates unique constraint "users_pkey"') !== false ) {
                        $_SESSION['message'] = "Erreur : Le nouveau matricule '$new_matricule' existe déjà.";
                    } elseif (strpos($e->getMessage(), 'users_username_key') !== false) {
                        $_SESSION['message'] = "Erreur : Ce nom d'utilisateur existe déjà.";
                    } elseif (strpos($e->getMessage(), 'users_email_key') !== false) {
                        $_SESSION['message'] = "Erreur : Cet email est déjà utilisé.";
                    } elseif (strpos($e->getMessage(), 'violates foreign key constraint') !== false) {
                         $_SESSION['message'] = "Erreur critique : Impossible de modifier le matricule car il est utilisé ailleurs (FK sans ON UPDATE CASCADE ?). ";
                         error_log("[CRITICAL FK ERROR] Matricule change failed for {$original_matricule} to {$new_matricule}. Check ON UPDATE CASCADE. Details: " . $e->getMessage());
                    } else {
                        $_SESSION['message'] = "Erreur base de données lors de la modification.";
                        error_log("[ERROR] User Edit PDOException: " . $e->getMessage());
                    }
                    $_SESSION['message_type'] = 'danger';
                }
            } else {
                // Erreur de validation
                $_SESSION['message'] = "Erreur de validation : " . $error_message;
                $_SESSION['message_type'] = 'danger';
            }
            // Redirection en cas d'erreur (validation ou DB)
            // On utilise l'original_matricule pour retrouver le formulaire
            header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=modifier&matricule=" . urlencode($original_matricule) . "&page=" . $currentPage . $queryStringPrefix);
            exit();
        }
    }

    // --- 2. TRAITEMENT GET (Actions qui modifient et redirigent) ---

    // --- Action Générer Utilisateurs Test ---
    elseif ($action == 'generer_utilisateurs') {
        // ... (logique de génération inchangée, utilise crypt() pour pwd) ...
        $nombre_a_generer = isset($_GET['nombre']) ? max(1, min(50, (int)$_GET['nombre'])) : 5;
        $faker = Factory::create('fr_FR'); $count_success = 0;
        if (empty($available_role_ids)) { $_SESSION['message'] = "Erreur : Rôles non trouvés pour génération."; $_SESSION['message_type'] = 'danger'; }
        else {
            $sqlInsert = "INSERT INTO users (matricule, username, password_hash, email, role_id, matricule_creation) VALUES (:mat, :uname, :pwhash, :email, :role_id, :creator)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            for ($i = 0; $i < $nombre_a_generer; $i++) {
                $fake_matricule = $faker->unique()->bothify('FAKE####??'); $fake_username = $faker->unique()->userName; $fake_email = $faker->unique()->safeEmail;
                $fake_password = $faker->password(8, 12); $fake_role_id = $faker->randomElement($available_role_ids);
                $salt = gen_salt(); $password_hash = crypt($fake_password, $salt);
                try { $stmtInsert->execute([':mat' => $fake_matricule, ':uname' => $fake_username, ':pwhash' => $password_hash, ':email' => $fake_email, ':role_id' => $fake_role_id, ':creator' => $logged_in_user_matricule]); $count_success++; }
                catch (PDOException $e) { /* Log + reset unique si besoin */ error_log("[FAKER ERROR] Insert failed: {$e->getMessage()}"); /* Reset unique logic ...*/ }
            }
            $faker->unique(true);
            /* Définir message succès/warning/erreur */
            if ($count_success == $nombre_a_generer) { $_SESSION['message'] = "{$count_success} utilisateurs test générés."; $_SESSION['message_type'] = 'success'; }
            elseif ($count_success > 0) { $_SESSION['message'] = "{$count_success}/{$nombre_a_generer} utilisateurs test générés (voir logs)."; $_SESSION['message_type'] = 'warning'; }
            else { $_SESSION['message'] = "Échec génération utilisateurs test (voir logs)."; $_SESSION['message_type'] = 'danger'; }
        }
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix . "&page=1"); exit();
    }

    // --- Action Supprimer Individuel ---
    elseif ($action == 'supprimer' && isset($_GET['matricule'])) {
        // ... (logique de suppression inchangée) ...
        $matricule_to_delete = trim($_GET['matricule']); $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        if ($matricule_to_delete === $logged_in_user_matricule) { $_SESSION['message'] = "Erreur : Vous ne pouvez pas vous supprimer."; $_SESSION['message_type'] = 'warning'; }
        else {
            try { /* Vérifications dépendances optionnelles... */
                $sql = "DELETE FROM users WHERE matricule = :mat"; $stmt = $pdo->prepare($sql); $stmt->execute([':mat' => $matricule_to_delete]); $rowCount = $stmt->rowCount();
                if ($rowCount > 0) { $_SESSION['message'] = "Utilisateur (matricule: {$matricule_to_delete}) supprimé."; $_SESSION['message_type'] = 'success'; }
                else { $_SESSION['message'] = "Utilisateur non trouvé ou déjà supprimé."; $_SESSION['message_type'] = 'warning'; }
            } catch (PDOException $e) { /* Gestion erreurs FK... */
                 if (strpos($e->getMessage(), 'violates foreign key constraint') !== false) { $_SESSION['message'] = "Erreur : Utilisateur référencé ailleurs."; } else { $_SESSION['message'] = "Erreur DB suppression."; }
                 $_SESSION['message_type'] = 'danger'; error_log("[ERROR] User Delete PDOException for {$matricule_to_delete}: " . $e->getMessage());
            } catch (Exception $e) { $_SESSION['message'] = $e->getMessage(); $_SESSION['message_type'] = 'danger'; }
        }
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste&page=" . $currentPage . $queryStringPrefix); exit();
    }

    // --- 3. PRÉPARATION AFFICHAGE (action=liste ou action=modifier GET) ---
    elseif ($action == 'modifier' && isset($_GET['matricule'])) {
        // Récupération user_edit ... (inchangée)
        $matricule_edit = trim($_GET['matricule']); $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $stmt = $pdo->prepare("SELECT matricule, username, email, role_id FROM users WHERE matricule = :mat"); $stmt->execute([':mat' => $matricule_edit]); $user_edit = $stmt->fetch();
        if (!$user_edit) { $_SESSION['message'] = "Utilisateur non trouvé (Matricule: {$matricule_edit})."; $_SESSION['message_type'] = 'warning'; header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix); exit(); }
    }
    elseif ($action == 'liste') {
        // Construction WHERE, Comptage Total, Récupération Liste... (inchangés)
        $whereClauses = []; $params = []; if ($filter_role_id) { $whereClauses[] = "u.role_id = :role_id"; $params[':role_id'] = $filter_role_id; } if (!empty($search_term)) { $whereClauses[] = "LOWER(u.username) LIKE LOWER(:search)"; $params[':search'] = '%' . $search_term . '%'; } $whereSql = count($whereClauses) > 0 ? " WHERE " . implode(" AND ", $whereClauses) : "";
        $sqlTotal = "SELECT COUNT(*) FROM users u" . $whereSql; $totalStmt = $pdo->prepare($sqlTotal); $totalStmt->execute($params); $totalUsers = $totalStmt->fetchColumn(); $totalPages = ceil($totalUsers / $perPage); if ($totalPages < 1) $totalPages = 1; if ($page > $totalPages) { $page = $totalPages; $offset = max(0, ($page - 1) * $perPage); }
        $sqlList = "SELECT u.matricule, u.username, u.email, r.role_name, u.role_id FROM users u JOIN roles r ON u.role_id = r.role_id" . $whereSql . " ORDER BY u.username ASC LIMIT :limit OFFSET :offset";
        $stmtList = $pdo->prepare($sqlList); foreach ($params as $key => $val) { $stmtList->bindValue($key, $val); } $stmtList->bindValue(':limit', $perPage, PDO::PARAM_INT); $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT); $stmtList->execute(); $users_list = $stmtList->fetchAll();
    }

} catch (PDOException $e) { /* Gestion Erreur Globale... */
    error_log("[FATAL PDOException in " . basename(__FILE__) . "] " . $e->getMessage()); $message = "Erreur critique BDD.";
    if ($action !== 'liste') { $_SESSION['message'] = $message; $_SESSION['message_type'] = 'danger'; header("Location: " . basename($_SERVER['PHP_SELF']) . "?action=liste" . $queryStringPrefix); exit(); }
    $users_list = []; $totalPages = 1; $page = 1; $totalUsers = 0; $message_type = 'danger';
}

// --- Récupérer le message flash ---
$highlight_matricule = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']; $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
    // Récupérer le matricule à surligner (après modification du matricule)
    if (isset($_SESSION['highlight_matricule'])) {
        $highlight_matricule = $_SESSION['highlight_matricule'];
        unset($_SESSION['highlight_matricule']);
    }
} elseif (!empty($message) && !isset($message_type)) { $message_type = 'danger'; }

// --- 4. AFFICHAGE HTML ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Chemin CSS corrigé -->
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .filter-search-bar { margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: 0.375rem; border: 1px solid #dee2e6; }
        .table th, .table td { vertical-align: middle; }
        .action-buttons form { display: inline-block; margin: 0 2px;}
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
        .required-field::after { content: ' *'; color: red; }
        /* Masquer la confirmation de matricule par défaut */
        #confirmMatriculeChangeGroup { display: none; }
        /* Style pour la ligne surlignée */
        .table-info { --bs-table-bg-state: #cfe2ff; /* Couleur Bootstrap info */ }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="card">
             <div class="card-header header">
                 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                     <div><h1 class="mb-1 h3"><i class="fas fa-users-cog me-2"></i>Gestion Utilisateurs</h1><p class="mb-0 small text-white-50">Ajouter, modifier, filtrer...</p></div>
                     <?php if ($action == 'liste'): ?><div class="btn-group" role="group"><a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=ajouter" class="btn btn-sm btn-light" title="Nouveau"><i class="fas fa-user-plus me-1 text-success"></i>Nouveau</a><button type="button" class="btn btn-sm btn-light" onclick="openGenerateUsersModal()" title="Générer Test"><i class="fas fa-flask me-1 text-info"></i>Générer</button></div><?php endif; ?>
                 </div>
             </div>

            <?php if (!empty($message)): ?><div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show m-3" role="alert"><?= nl2br(htmlspecialchars($message)) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

            <?php if ($action == 'ajouter' || ($action == 'modifier' && $user_edit)): ?>
                <div class="card-body">
                    <h3 class="mb-4 border-bottom pb-3 fs-5"><?= ($action == 'ajouter') ? '<i class="fas fa-user-plus me-2 text-success"></i>Ajouter utilisateur' : '<i class="fas fa-user-edit me-2 text-primary"></i>Modifier : ' . htmlspecialchars($user_edit['username']) ?></h3>
                    <form method="post" action="<?= basename($_SERVER['PHP_SELF']) ?>?action=<?= $action ?><?= $queryStringPrefix ?>" id="userFormModal">
                        <?php if ($action == 'modifier'): ?><input type="hidden" name="original_matricule" id="modal_original_matricule" value="<?= htmlspecialchars($user_edit['matricule']) ?>"><?php else: ?><input type="hidden" name="original_matricule" id="modal_original_matricule" value=""><?php endif; ?>
                        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_matricule" class="form-label required-field">Matricule</label>
                                <!-- Matricule éditable en modification -->
                                <input type="text" class="form-control" id="modal_matricule" name="matricule" value="<?= htmlspecialchars($user_edit['matricule'] ?? '') ?>" required maxlength="50">
                                <small class="text-muted" id="matriculeHelp"><?= ($action == 'modifier') ? 'Peut être modifié (Attention Risqué !)' : "Identifiant unique." ?></small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal_username" class="form-label required-field">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="modal_username" name="username" value="<?= htmlspecialchars($user_edit['username'] ?? '') ?>" required maxlength="50">
                            </div>
                        </div>

                         <!-- Confirmation Changement Matricule (pour mode modif seulement) -->
                        <div class="mb-3 alert alert-warning p-2" id="confirmMatriculeChangeGroup">
                             <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="confirm_matricule_change" name="confirm_matricule_change">
                                <label class="form-check-label small" for="confirm_matricule_change">
                                    <i class="fas fa-exclamation-triangle text-danger me-1"></i> Je confirme vouloir modifier le matricule. <strong>Attention :</strong> Cela peut casser les liens si les tables liées n'utilisent pas `ON UPDATE CASCADE`.
                                </label>
                             </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_email" class="form-label">Email <small class="text-muted">(Optionnel)</small></label>
                                <input type="email" class="form-control" id="modal_email" name="email" value="<?= htmlspecialchars($user_edit['email'] ?? '') ?>" maxlength="100" placeholder="nom@exemple.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal_role_id" class="form-label required-field">Rôle</label>
                                <select class="form-select" id="modal_role_id" name="role_id" required>
                                    <option value="" disabled <?= ($action == 'ajouter') ? 'selected' : '' ?>>-- Sélectionner --</option>
                                    <?php foreach ($roles_list as $role): ?><option value="<?= htmlspecialchars($role['role_id']) ?>" <?= (($user_edit['role_id'] ?? null) == $role['role_id']) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($role['role_name'])) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Champs Mot de passe -->
                        <fieldset class="border p-3 pt-2 mt-3 mb-3">
                            <legend class="small w-auto px-2">
                                <?= ($action == 'ajouter') ? 'Mot de passe <span class="required-field"></span>' : 'Changer le mot de passe <small class="text-muted">(Optionnel)</small>' ?>
                            </legend>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="modal_password" class="form-label small"><?= ($action == 'ajouter') ? 'Mot de passe' : 'Nouveau mot de passe' ?></label>
                                    <input type="password" class="form-control form-control-sm" id="modal_password" name="<?= ($action == 'ajouter') ? 'password' : 'new_password' ?>" <?= ($action == 'ajouter') ? 'required' : '' ?> minlength="6">
                                    <small class="text-muted">Min 6 caractères. <?= ($action == 'modifier') ? 'Laisser vide pour ne pas changer.' : '' ?></small>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="modal_password_confirm" class="form-label small">Confirmer <?= ($action == 'ajouter') ? 'Mot de passe' : 'Nouveau mot de passe' ?></label>
                                    <input type="password" class="form-control form-control-sm" id="modal_password_confirm" name="<?= ($action == 'ajouter') ? 'password_confirm' : 'new_password_confirm' ?>" <?= ($action == 'ajouter') ? 'required' : '' ?>>
                                </div>
                            </div>
                        </fieldset>

                        <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
                            <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste&page=<?= htmlspecialchars($page) ?><?= $queryStringPrefix ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?></button>
                        </div>
                    </form>
                </div>
            <?php elseif ($action == 'liste'): ?>
                 <div class="filter-search-bar m-3">
                     <form action="<?= basename($_SERVER['PHP_SELF']) ?>" method="get" class="row g-3 align-items-end"><input type="hidden" name="action" value="liste"><div class="col-md-4"><label for="filter_role_id" class="form-label small">Filtrer :</label><select class="form-select form-select-sm" id="filter_role_id" name="filter_role_id"><option value="">-- Tous Rôles --</option><?php foreach ($roles_list as $role): ?><option value="<?= htmlspecialchars($role['role_id']) ?>" <?= ($filter_role_id == $role['role_id']) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($role['role_name'])) ?></option><?php endforeach; ?></select></div><div class="col-md-5"><label for="search" class="form-label small">Rechercher Nom :</label><input type="search" class="form-control form-control-sm" id="search" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Entrez un nom..."></div><div class="col-md-3 d-flex align-items-end gap-2"><button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button><?php if ($filter_role_id || !empty($search_term)): ?><a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste" class="btn btn-outline-secondary btn-sm" title="Effacer"><i class="fas fa-times"></i></a><?php endif; ?></div></form>
                 </div>
                 <div class="table-responsive">
                    <?php if (!empty($users_list)): ?>
                        <table class="table table-striped table-hover mb-0 caption-top">
                            <caption class="px-3 small text-muted"><?= $totalUsers ?> utilisateur(s). Page <?= $page ?>/<?= $totalPages ?>.</caption>
                            <thead class="table-light"><tr><th>Matricule</th><th>Nom d'utilisateur</th><th>Email</th><th>Rôle</th><th class="text-center" style="width: 120px;">Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($users_list as $user): ?>
                                <tr <?= ($highlight_matricule === $user['matricule']) ? 'class="table-info"' : '' // Surligner si modifié ?>>
                                    <td><?= htmlspecialchars($user['matricule']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['role_name'])) ?></td>
                                    <td class="text-center action-buttons">
                                        <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=modifier&matricule=<?= urlencode($user['matricule']) ?>&page=<?= $page ?><?= $queryStringPrefix ?>" class="btn btn-primary btn-action" data-bs-toggle="tooltip" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <?php if ($user['matricule'] !== $logged_in_user_matricule): ?><button type="button" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer" onclick="openDeleteUserModal('<?= htmlspecialchars(addslashes($user['matricule']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($user['username']), ENT_QUOTES) ?>', <?= $page ?>, '<?= addslashes($queryStringPrefix) ?>')"><i class="fas fa-trash-alt"></i></button><?php else: ?><button type="button" class="btn btn-secondary btn-action" disabled data-bs-toggle="tooltip" title="Soi-même"><i class="fas fa-trash-alt"></i></button><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?> <div class="card-body text-center p-4"><i class="fas fa-info-circle fa-2x text-muted mb-3"></i><p class="text-muted mb-3"><?= ($filter_role_id || !empty($search_term)) ? 'Aucun utilisateur trouvé.' : 'Aucun utilisateur enregistré.' ?></p><?php if ($filter_role_id || !empty($search_term)): ?><a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-times me-1"></i>Réinitialiser</a><br><?php endif; ?><a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=ajouter" class="btn btn-success"><i class="fas fa-user-plus me-1"></i>Créer un utilisateur</a></div>
                    <?php endif; ?>
                </div>
                <?php if ($totalUsers > 0 && $totalPages > 1): ?>
                    <div class="card-footer"><nav aria-label="Page navigation Utilisateurs"><ul class="pagination justify-content-center mb-0">
                    <?php /* Pagination HTML ... (inchangée) */ $pageUrlBase = "?action=liste"; if (!empty($currentQueryString)) $pageUrlBase .= "&" . $currentQueryString; $pageUrlBase .= "&page="; ?><li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page - 1) ?>">«</a></li> <?php $linksToShow = 5; $startPage = max(1, $page - floor($linksToShow / 2)); $endPage = min($totalPages, $page + floor($linksToShow / 2)); /* Adjust range logic ... */ if ($endPage - $startPage + 1 < $linksToShow) { if ($startPage === 1) { $endPage = min($totalPages, $startPage + $linksToShow - 1); } elseif ($endPage === $totalPages) { $startPage = max(1, $endPage - $linksToShow + 1); } } ?> <?php if ($startPage > 1): ?><li class="page-item"><a class="page-link" href="<?= $pageUrlBase ?>1">1</a></li><?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><?php endif; ?> <?php for ($i = $startPage; $i <= $endPage; $i++): ?><li class="page-item <?= $page == $i ? 'active' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . $i ?>"><?= $i ?></a></li><?php endfor; ?> <?php if ($endPage < $totalPages): ?><?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= $pageUrlBase . $totalPages ?>"><?= $totalPages ?></a></li><?php endif; ?> <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page + 1) ?>">»</a></li>
                    </ul></nav></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="text-center my-4"><a href="../admin.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour Admin</a></div>
    </div>

    <!-- Modals: Génération et Suppression -->
    <?php /* Modal Génération Test (HTML inchangé) */ ?>
    <div class="modal fade" id="generateUsersModal" tabindex="-1" aria-labelledby="generateUsersModalLabel" aria-hidden="true"><div class="modal-dialog modal-sm"><div class="modal-content"><form id="generateUsersForm" action="<?= basename($_SERVER['PHP_SELF']) ?>" method="get"><div class="modal-header"><h6 class="modal-title" id="generateUsersModalLabel"><i class="fas fa-flask text-info me-2"></i>Générer Utilisateurs</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="generer_utilisateurs"><?php foreach ($queryStringParams as $key => $value): ?><input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>"><?php endforeach; ?><div class="mb-3"><label for="nombreUsers" class="form-label">Nombre (1-50):</label><input type="number" class="form-control form-control-sm" id="nombreUsers" name="nombre" value="5" min="1" max="50" required></div></div><div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-sm btn-primary">Générer</button></div></form></div></div></div>
    <?php /* Modal Suppression Individuelle (HTML inchangé) */ ?>
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title" id="deleteUserModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirmation</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><strong class="text-danger">Attention !</strong></p><p>Supprimer "<strong id="deleteUsername"></strong>" (Matricule: <span id="deleteUserMatricule"></span>) ?</p><p class="small text-muted">Action irréversible.</p><div class="form-check mt-3"><input class="form-check-input" type="checkbox" value="" id="confirmDeleteUserCheckbox" required><label class="form-check-label" for="confirmDeleteUserCheckbox">Je confirme vouloir supprimer.</label></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="button" class="btn btn-danger" id="confirmDeleteUserBtn" disabled><i class="fas fa-trash-alt me-1"></i>Supprimer</button></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Init tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) { if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) { return new bootstrap.Tooltip(tooltipTriggerEl); } });

            // Gestion Formulaire Ajout/Modif
            const userForm = document.getElementById('userFormModal');
            if (userForm) {
                const originalMatriculeInput = document.getElementById('modal_original_matricule');
                const matriculeInput = document.getElementById('modal_matricule');
                const confirmMatriculeGroup = document.getElementById('confirmMatriculeChangeGroup');
                const confirmMatriculeCheckbox = document.getElementById('confirm_matricule_change');
                const passwordInput = document.getElementById('modal_password'); // Name varie selon add/edit
                const passwordConfirmInput = document.getElementById('modal_password_confirm'); // Name varie

                const isEditMode = originalMatriculeInput && originalMatriculeInput.value !== '';
                let originalMatriculeValue = isEditMode ? originalMatriculeInput.value : '';

                function checkMatriculeChange() {
                    if (!isEditMode) return; // Ne s'applique pas en mode ajout
                    const currentMatricule = matriculeInput.value;
                    if (currentMatricule !== originalMatriculeValue) {
                        confirmMatriculeGroup.style.display = 'block';
                        confirmMatriculeCheckbox.required = true; // Rendre la coche requise si matricule changé
                    } else {
                        confirmMatriculeGroup.style.display = 'none';
                        confirmMatriculeCheckbox.required = false;
                        confirmMatriculeCheckbox.checked = false; // Décocher si revenu à l'original
                    }
                }

                if (isEditMode) {
                    // Écouter les changements sur le champ matricule en mode édition
                    matriculeInput.addEventListener('input', checkMatriculeChange);
                    // Vérifier l'état initial au chargement (au cas où le formulaire est rechargé après erreur)
                    checkMatriculeChange();
                } else {
                     // Mode Ajout : s'assurer que la confirmation matricule est cachée
                     if(confirmMatriculeGroup) confirmMatriculeGroup.style.display = 'none';
                     if(confirmMatriculeCheckbox) confirmMatriculeCheckbox.required = false;
                }

                // Validation concordance mot de passe (côté client, basique)
                userForm.addEventListener('submit', function(event) {
                    const pwd1 = passwordInput.value;
                    const pwd2 = passwordConfirmInput.value;
                    const pwdMinLength = 6;

                    if (isEditMode) { // Mode MODIF: Mot de passe OPTIONNEL
                         if (pwd1.length > 0 || pwd2.length > 0) { // Si l'utilisateur tente de changer le mdp
                            if (pwd1.length < pwdMinLength) {
                                alert(`Le nouveau mot de passe doit faire au moins ${pwdMinLength} caractères.`);
                                event.preventDefault(); passwordInput.focus(); return;
                            }
                            if (pwd1 !== pwd2) {
                                alert('Les nouveaux mots de passe ne correspondent pas.');
                                event.preventDefault(); passwordConfirmInput.focus(); return;
                            }
                         }
                         // Si les champs sont vides, on laisse passer (le mot de passe n'est pas changé)
                    } else { // Mode AJOUT: Mot de passe REQUIS
                        if (pwd1.length < pwdMinLength) {
                             alert(`Le mot de passe doit faire au moins ${pwdMinLength} caractères.`);
                             event.preventDefault(); passwordInput.focus(); return;
                        }
                         if (pwd1 !== pwd2) {
                             alert('Les mots de passe ne correspondent pas.');
                             event.preventDefault(); passwordConfirmInput.focus(); return;
                         }
                    }

                    // Vérifier la confirmation de changement de matricule si nécessaire
                    if (isEditMode && matriculeInput.value !== originalMatriculeValue && !confirmMatriculeCheckbox.checked) {
                        alert('Veuillez cocher la case pour confirmer la modification du matricule.');
                        event.preventDefault();
                        confirmMatriculeCheckbox.focus();
                        return;
                    }
                });
            }

            // --- Modals ---
            // Génération Test
            window.openGenerateUsersModal = function() { var m=document.getElementById('generateUsersModal'); if(m){new bootstrap.Modal(m).show();} }
            // Suppression Individuelle
            const deleteUserModalEl = document.getElementById('deleteUserModal');
            if(deleteUserModalEl) { /* ... JS modal suppression inchangé ... */ const confirmCheckboxUser = deleteUserModalEl.querySelector('#confirmDeleteUserCheckbox'); const confirmBtnUser = deleteUserModalEl.querySelector('#confirmDeleteUserBtn'); const usernameSpan = deleteUserModalEl.querySelector('#deleteUsername'); const userMatriculeSpan = deleteUserModalEl.querySelector('#deleteUserMatricule'); const modalInstanceUser = new bootstrap.Modal(deleteUserModalEl); let deleteUserUrl = '#'; window.openDeleteUserModal = function(matricule, username, page, queryStringPrefix) { usernameSpan.textContent = username; userMatriculeSpan.textContent = matricule; deleteUserUrl = `<?= basename($_SERVER['PHP_SELF']) ?>?action=supprimer&matricule=${encodeURIComponent(matricule)}&page=${page}${queryStringPrefix}`; confirmCheckboxUser.checked = false; confirmBtnUser.disabled = true; modalInstanceUser.show(); }; confirmCheckboxUser.addEventListener('change', function() { confirmBtnUser.disabled = !this.checked; }); confirmBtnUser.addEventListener('click', function() { if (!confirmCheckboxUser.checked) return; window.location.href = deleteUserUrl; }); }

        });
    </script>
</body>
</html>