<?php
session_start(); // TOUJOURS EN PREMIER

require_once '../app/config/config.php'; // Besoin pour DB et autres actions

// --- AJAX Request Handlers ---

// NOUVEAU: Fetch Vaccines based on Campaign
if (isset($_GET['action']) && $_GET['action'] === 'fetch_vaccins') {
    header('Content-Type: application/json');
    $response = ['error' => null, 'vaccins' => []];
    $campagne_id = filter_input(INPUT_GET, 'campagne_id', FILTER_VALIDATE_INT);

    if (!$campagne_id || $campagne_id <= 0) {
        $response['error'] = 'ID de campagne invalide.';
        echo json_encode($response);
        exit;
    }

    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo_ajax = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Sélectionner les vaccins liés à cette campagne
        $stmt = $pdo_ajax->prepare("SELECT vaccin_id, nom_vaccin FROM vaccins WHERE campagne_id = :campagne_id ORDER BY nom_vaccin");
        $stmt->bindParam(':campagne_id', $campagne_id, PDO::PARAM_INT);
        $stmt->execute();
        $response['vaccins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($response['vaccins'])) {
            $response['error'] = 'Aucun vaccin trouvé pour cette campagne.';
        }
    } catch (PDOException $e) {
        error_log("AJAX Fetch Vaccins Error: " . $e->getMessage());
        $response['error'] = 'Erreur base de données lors de la récupération des vaccins.';
    } finally {
        $pdo_ajax = null;
    }
    echo json_encode($response);
    exit;
}

// MODIFIÉ: Fetch Lots based on Campaign AND Vaccine
if (isset($_GET['action']) && $_GET['action'] === 'fetch_lots') {
    header('Content-Type: application/json');
    $response = ['error' => null, 'lots' => []];
    $campagne_id = filter_input(INPUT_GET, 'campagne_id', FILTER_VALIDATE_INT);
    $vaccin_id = filter_input(INPUT_GET, 'vaccin_id', FILTER_VALIDATE_INT);

    if (!$campagne_id || $campagne_id <= 0 || !$vaccin_id || $vaccin_id <= 0) {
        $response['error'] = 'ID de campagne ou de vaccin invalide.';
        echo json_encode($response);
        exit;
    }

    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo_ajax = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Sélectionner les lots pour la campagne ET le vaccin spécifiés, qui ne sont PAS expirés
        // ATTENTION: Pas de colonne 'is_active' ou 'quantite_restante' dans le schema fourni pour lots.
        $stmt = $pdo_ajax->prepare("SELECT lot_id, nom_lot, date_expiration
                                    FROM lots
                                    WHERE campagne_id = :campagne_id
                                      AND vaccin_id = :vaccin_id
                                      AND date_expiration >= CURRENT_DATE -- Ne montrer que les lots non expirés
                                    ORDER BY nom_lot");
        $stmt->bindParam(':campagne_id', $campagne_id, PDO::PARAM_INT);
        $stmt->bindParam(':vaccin_id', $vaccin_id, PDO::PARAM_INT);
        $stmt->execute();
        $response['lots'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
         if (empty($response['lots'])) {
             $response['error'] = 'Aucun lot valide (non expiré) trouvé pour ce vaccin dans cette campagne.';
         }

    } catch (PDOException $e) {
        error_log("AJAX Fetch Lots Error: " . $e->getMessage());
        $response['error'] = 'Erreur base de données lors de la récupération des lots.';
    } finally {
        $pdo_ajax = null;
    }

    echo json_encode($response);
    exit; // Important
}
// --- Fin AJAX ---

// --- Infos Utilisateur ---
$user_matricule = $_SESSION['matricule'] ?? null;
$user_username = $_SESSION['username'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// --- Redirection si non connecté ---
if (!$user_matricule) {
    header('Location: login.php?error=auth_required');
    exit();
}

// --- Gestion du matricule patient recherché via Session ---
if (isset($_GET['matricule_patient'])) {
    $matricule_from_get = trim($_GET['matricule_patient']);
    if (!empty($matricule_from_get)) {
        $_SESSION['selected_matricule_patient'] = htmlspecialchars($matricule_from_get, ENT_QUOTES, 'UTF-8');
        // Redirection pour nettoyer l'URL
        $query_params = $_GET;
        unset($query_params['matricule_patient']);
        $redirect_url = basename($_SERVER['PHP_SELF']);
        if (!empty($query_params)) {
            $redirect_url .= '?' . http_build_query($query_params);
        }
        header('Location: ' . $redirect_url);
        exit;
    } else {
        unset($_SESSION['selected_matricule_patient']);
    }
}
$matricule_to_prefill_add = $_SESSION['selected_matricule_patient'] ?? null;

// --- POST Action Handling (Save/Delete) ---
$redirect_needed = false;
$form_action_url = basename($_SERVER['PHP_SELF']); // Utiliser basename pour l'URL de base

// --- Handle SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $record_id = filter_input(INPUT_POST, 'vaccination_record_id', FILTER_VALIDATE_INT);
    $matricule_patient = ($record_id > 0)
        ? filter_input(INPUT_POST, 'matricule_patient', FILTER_SANITIZE_SPECIAL_CHARS) // Edit: from form
        : $matricule_to_prefill_add; // Add: from session
    $campagne_id = filter_input(INPUT_POST, 'campagne_id', FILTER_VALIDATE_INT);
    // $vaccin_id = filter_input(INPUT_POST, 'vaccin_id', FILTER_VALIDATE_INT); // Needed for lot selection, but NOT saved in vaccination_records table per schema
    $lot_id = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
    $date_vaccination = filter_input(INPUT_POST, 'date_vaccination', FILTER_SANITIZE_SPECIAL_CHARS); // Valider format si besoin

    // Validation (Vaccin ID n'est pas requis ici car non sauvé dans la table finale)
    if (empty($matricule_patient) || !$campagne_id || !$lot_id || empty($date_vaccination)) {
        $_SESSION['form_message'] = ['type' => 'danger', 'text' => 'Erreur: Tous les champs requis (Matricule, Campagne, Lot, Date) ne sont pas remplis correctement.'];
    } else {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $pdo_save = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            // NOTE: Pas de gestion de quantité car la colonne n'existe pas dans le schema 'lots' fourni.

            if ($record_id > 0) { // UPDATE
                // Schema vaccination_records: pas de vaccin_id, pas de date_modification standard
                $sql = "UPDATE vaccination_records SET
                            date_vaccination = :date_vacc,
                            matricule_patient = :mat_p,
                            campagne_id = :camp_id,
                            lot_id = :lot_id
                            -- date_modification = CURRENT_TIMESTAMP -- Si vous ajoutez cette colonne
                        WHERE vaccination_record_id = :rec_id";
                $stmt = $pdo_save->prepare($sql);
                $stmt->bindParam(':rec_id', $record_id, PDO::PARAM_INT);
                $_SESSION['form_message'] = ['type' => 'success', 'text' => 'Vaccination modifiée avec succès.'];
            } else { // INSERT
                 // Schema vaccination_records: matricule_creation, date_creation existent
                $sql = "INSERT INTO vaccination_records
                            (date_vaccination, matricule_patient, campagne_id, lot_id, matricule_creation, date_creation)
                        VALUES
                            (:date_vacc, :mat_p, :camp_id, :lot_id, :creator, CURRENT_TIMESTAMP)";
                $stmt = $pdo_save->prepare($sql);
                $stmt->bindParam(':creator', $user_matricule, PDO::PARAM_STR);
                $_SESSION['form_message'] = ['type' => 'success', 'text' => 'Vaccination ajoutée avec succès.'];
            }
            $stmt->bindParam(':date_vacc', $date_vaccination);
            $stmt->bindParam(':mat_p', $matricule_patient);
            $stmt->bindParam(':camp_id', $campagne_id, PDO::PARAM_INT);
            $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
            $stmt->execute();

            // *** PAS DE MISE À JOUR DE QUANTITÉ DE LOT ICI (colonne absente du schema) ***

            $redirect_needed = true;

        } catch (PDOException $e) {
            error_log("Save Vaccination Error: " . $e->getMessage());
            // Vérifier les contraintes spécifiques (ex: lot invalide, etc.)
             if (strpos($e->getMessage(), 'vaccination_records_lot_id_fkey') !== false) {
                 $_SESSION['form_message'] = ['type' => 'danger', 'text' => 'Erreur: Le lot sélectionné n\'existe plus ou est invalide.'];
             } elseif (strpos($e->getMessage(), 'vaccination_records_campagne_id_fkey') !== false) {
                  $_SESSION['form_message'] = ['type' => 'danger', 'text' => 'Erreur: La campagne sélectionnée n\'existe plus ou est invalide.'];
             } else {
                 $_SESSION['form_message'] = ['type' => 'danger', 'text' => 'Erreur base de données lors de l\'enregistrement. Détail: ' . $e->getMessage()];
             }
        } finally {
            $pdo_save = null;
        }
    }
}

// --- Handle DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $record_id = filter_input(INPUT_POST, 'vaccination_record_id', FILTER_VALIDATE_INT);
    if ($record_id > 0) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $pdo_delete = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // *** PAS DE RECUPERATION/INCREMENTATION DE QUANTITE DE LOT ICI (colonne absente) ***

            $stmt = $pdo_delete->prepare("DELETE FROM vaccination_records WHERE vaccination_record_id = :rec_id");
            $stmt->bindParam(':rec_id', $record_id, PDO::PARAM_INT);
            $deleted = $stmt->execute();

            if ($deleted) {
                $_SESSION['form_message'] = ['type' => 'success', 'text' => 'Vaccination supprimée avec succès.'];
                $redirect_needed = true;
            } else {
                 $_SESSION['form_message'] = ['type' => 'warning', 'text' => 'La suppression a échoué ou l\'enregistrement n\'existait pas.'];
            }

        } catch (PDOException $e) {
            error_log("Delete Vaccination Error: " . $e->getMessage());
            $_SESSION['form_message'] = ['type' => 'danger', 'text' => 'Erreur base de données lors de la suppression.'];
        } finally {
             $pdo_delete = null;
        }
    } else {
        $_SESSION['form_message'] = ['type' => 'warning', 'text' => 'ID d\'enregistrement invalide pour la suppression.'];
    }
}

// --- Gérer la redirection POST-Action ---
$filter_campagne_id = filter_input(INPUT_GET, 'filter_campagne_id', FILTER_VALIDATE_INT);
if ($filter_campagne_id !== false && $filter_campagne_id <= 0) { $filter_campagne_id = null; }

if ($redirect_needed) {
    $redirect_url = $form_action_url;
    $query_params_redirect = [];
    if ($filter_campagne_id) {
        $query_params_redirect['filter_campagne_id'] = $filter_campagne_id;
    }
    if (!empty($query_params_redirect)) {
         $redirect_url .= '?' . http_build_query($query_params_redirect);
    }
    header('Location: ' . $redirect_url);
    exit();
}

// --- Data Fetching for Page Display ---
$active_campaigns_for_modal = [];
$all_campaigns_for_filter = [];
$vaccinations_list = [];
$page_error_message = null;
$pdo = null;

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Campagnes actives pour le modal
    $stmt_modal_campagnes = $pdo->query("SELECT campagne_id, nom_campagne FROM campagnes WHERE is_active = true ORDER BY nom_campagne");
    $active_campaigns_for_modal = $stmt_modal_campagnes->fetchAll(PDO::FETCH_ASSOC);

    // Toutes les campagnes pour le filtre
    $stmt_filter_campagnes = $pdo->query("SELECT campagne_id, nom_campagne FROM campagnes ORDER BY nom_campagne");
    $all_campaigns_for_filter = $stmt_filter_campagnes->fetchAll(PDO::FETCH_ASSOC);

    // Liste des vaccinations (JOINDRE LOTS et VACCINS pour affichage/edit data)
    $sql_list = "SELECT
                    vr.vaccination_record_id, vr.date_vaccination, vr.matricule_patient,
                    vr.campagne_id, c.nom_campagne,
                    vr.lot_id, l.nom_lot, l.vaccin_id, -- Besoin de l.vaccin_id pour le bouton edit
                    v.nom_vaccin,                      -- Besoin de v.nom_vaccin pour affichage table
                    p.username AS patient_username
                 FROM vaccination_records vr
                 JOIN campagnes c ON vr.campagne_id = c.campagne_id
                 JOIN lots l ON vr.lot_id = l.lot_id
                 JOIN vaccins v ON l.vaccin_id = v.vaccin_id -- Jointure ajoutée
                 LEFT JOIN users p ON vr.matricule_patient = p.matricule
                 WHERE 1=1";
    $params = [];
    if ($filter_campagne_id !== null && $filter_campagne_id !== false) {
        $sql_list .= " AND vr.campagne_id = :campagne_id";
        $params[':campagne_id'] = $filter_campagne_id;
    }
    // Filtrer par matricule session si besoin (décommenter si nécessaire)
    // if ($matricule_to_prefill_add) {
    //    $sql_list .= " AND vr.matricule_patient = :matricule_session";
    //    $params[':matricule_session'] = $matricule_to_prefill_add;
    // }
    $sql_list .= " ORDER BY vr.date_vaccination DESC, vr.date_creation DESC";
    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute($params);
    $vaccinations_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Page Load Data Fetch Error: " . $e->getMessage());
    $page_error_message = "Erreur critique lors de la récupération des données. Contactez l'administrateur.";
} finally {
    $pdo = null;
}

// --- Préparer le message flash ---
$form_message = null;
if (isset($_SESSION['form_message'])) {
    $form_message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Vaccinations <?= ($matricule_to_prefill_add ? ' pour ' . htmlspecialchars($matricule_to_prefill_add) : '') ?> - Projet Vaccination</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .header { background-color: #0d6efd; color: white; }
        .header h1, .header h3, .header p { color: white; }
        .header .text-white-50 { color: rgba(255, 255, 255, 0.7) !important; }
        .header .btn-outline-light { border-color: rgba(255, 255, 255, 0.5); color: rgba(255, 255, 255, 0.8); }
        .header .btn-outline-light:hover { background-color: rgba(255, 255, 255, 0.1); color: white; }
        .user-info-header .badge { font-size: 0.8em; vertical-align: middle; }
        .user-info-header .btn-logout { padding: 0.3rem 0.7rem; font-size: 0.9em; }
        .table-responsive { margin-top: 1rem; }
        .action-buttons form { display: inline-block; margin: 0 2px;}
        .modal-body .form-label { margin-bottom: 0.5rem; }
        .modal-footer { border-top: 1px solid #dee2e6; }
        select:disabled, input[readonly] { background-color: #e9ecef; cursor: not-allowed; opacity: 1;}
        .filter-form { margin-bottom: 1rem; padding: 1rem; background-color: #f8f9fa; border-radius: 0.375rem; border: 1px solid #dee2e6;}
        .loading {
           background-image: url('data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAkKAAAALAAAAAAQABAAAAMyCLrc/jDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAAA==');
           background-repeat: no-repeat; background-position: right center; padding-right: 25px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">

        <div class="card mb-4">
             <div class="card-header header">
                 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                     <div>
                         <h1 class="mb-1 h3"><i class="fas fa-list-alt me-2"></i>Gestion Vaccinations <?= ($matricule_to_prefill_add ? ' pour ' . htmlspecialchars($matricule_to_prefill_add) : '') ?></h1>
                         <a href="dashboard.php" class="btn btn-sm btn-outline-light mt-1"><i class="fas fa-arrow-left me-1"></i> Retour Dashboard</a>
                     </div>
                     <div class="text-end user-info-header">
                         <?php if ($user_matricule): ?>
                              <span class="small text-white-50 me-3">
                                 <i class="fas fa-user me-1"></i><?= htmlspecialchars($user_username) ?>
                                 (<span class="badge bg-light text-dark"><?= htmlspecialchars($user_role) ?></span>)
                              </span>
                             <a href="logout.php" class="btn btn-danger btn-sm btn-logout" title="Se déconnecter"> <i class="fas fa-sign-out-alt"></i> </a>
                         <?php endif; ?>
                     </div>
                 </div>
             </div>
        </div>


        <?php if ($form_message): ?>
            <div class="alert alert-<?= htmlspecialchars($form_message['type']) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($form_message['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($page_error_message): ?>
            <div class="alert alert-danger" role="alert"> <?= htmlspecialchars($page_error_message) ?> </div>
        <?php endif; ?>


        <div class="row align-items-center mb-3">
            <div class="col-md-8">
                <div class="filter-form">
                    <form action="<?= basename($_SERVER['PHP_SELF']) ?>" method="get" class="row g-2 align-items-end">
                         <div class="col-auto"><label for="filter_campagne_id" class="form-label mb-0">Filtrer:</label></div>
                         <div class="col-sm">
                            <select class="form-select form-select-sm" id="filter_campagne_id" name="filter_campagne_id">
                                <option value="">-- Toutes Campagnes --</option>
                                <?php foreach ($all_campaigns_for_filter as $campagne): ?>
                                <option value="<?= htmlspecialchars($campagne['campagne_id']) ?>" <?= ($filter_campagne_id == $campagne['campagne_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($campagne['nom_campagne']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                         </div>
                         <div class="col-auto">
                            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter me-1"></i> Filtrer</button>
                            <?php if ($filter_campagne_id): ?>
                                <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary btn-sm ms-1" title="Effacer le filtre"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                         </div>
                    </form>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-2 mt-md-0">
                 <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vaccinationModal" id="addVaccinationBtn"
                         <?= !$matricule_to_prefill_add ? 'disabled' : '' ?>
                         title="<?= !$matricule_to_prefill_add ? 'Recherchez d\'abord un patient via le Dashboard' : 'Ajouter une vaccination pour '.htmlspecialchars($matricule_to_prefill_add) ?>">
                    <i class="fas fa-plus me-1"></i> Ajouter
                 </button>
            </div>
        </div>


        <div class="card">
            <div class="card-header">Liste des Vaccinations <?= ($filter_campagne_id ? '(Filtrée par campagne)' : '') ?></div>
            <div class="card-body">
                <?php if (!$page_error_message && !empty($vaccinations_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered caption-top">
                            <caption class="small text-muted"><?= count($vaccinations_list) ?> enregistrement(s) trouvé(s).</caption>
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Matricule</th>
                                    <th>Patient</th>
                                    <th>Campagne</th>
                                    <th>Vaccin</th>
                                    <th>Lot</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($vaccinations_list as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($record['date_vaccination']))) ?></td>
                                    <td><?= htmlspecialchars($record['matricule_patient']) ?></td>
                                    <td><?= htmlspecialchars($record['patient_username'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($record['nom_campagne']) ?></td>
                                    <td><?= htmlspecialchars($record['nom_vaccin']) ?></td>
                                    <td><?= htmlspecialchars($record['nom_lot']) ?></td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-info btn-sm edit-btn" title="Modifier"
                                            data-bs-toggle="modal" data-bs-target="#vaccinationModal"
                                            data-record-id="<?= htmlspecialchars($record['vaccination_record_id']) ?>"
                                            data-matricule-patient="<?= htmlspecialchars($record['matricule_patient']) ?>"
                                            data-campagne-id="<?= htmlspecialchars($record['campagne_id']) ?>"
                                            data-vaccin-id="<?= htmlspecialchars($record['vaccin_id']) ?>"
                                            data-lot-id="<?= htmlspecialchars($record['lot_id']) ?>"
                                            data-date-vaccination="<?= htmlspecialchars($record['date_vaccination']) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="<?= $form_action_url . ($filter_campagne_id ? '?filter_campagne_id='.$filter_campagne_id : '') ?>" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet enregistrement ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="vaccination_record_id" value="<?= htmlspecialchars($record['vaccination_record_id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"> <i class="fas fa-trash-alt"></i> </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                 <?php elseif (!$page_error_message && $filter_campagne_id): ?> <p class="text-center text-muted fst-italic mt-3">Aucun enregistrement trouvé pour cette campagne.</p>
                <?php elseif (!$page_error_message): ?> <p class="text-center text-muted fst-italic mt-3">Aucun enregistrement de vaccination trouvé.</p>
                <?php endif; ?>
            </div>
        </div>


        <div class="text-center my-4"><p class="small text-muted">© <?= date('Y') ?> Projet Vaccination</p></div>
    </div>


    <div class="modal fade" id="vaccinationModal" tabindex="-1" aria-labelledby="vaccinationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="<?= $form_action_url . ($filter_campagne_id ? '?filter_campagne_id='.$filter_campagne_id : '') ?>" method="post" id="vaccinationFormModal">
                    <div class="modal-header">
                        <h5 class="modal-title" id="vaccinationModalLabel">Ajouter/Modifier Vaccination</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="vaccination_record_id" id="modal_record_id" value="">

                        <?php if (!empty($active_campaigns_for_modal)): ?>
                            <div class="mb-3" id="modal_matricule_group">
                                <label for="modal_matricule_patient" class="form-label"><i class="fas fa-id-card me-1"></i> Matricule Patient</label>
                                <input type="text" class="form-control" id="modal_matricule_patient" name="matricule_patient" placeholder="Matricule" required>
                            </div>
                            <div class="mb-3">
                                <label for="modal_campagne_id" class="form-label"><i class="fas fa-calendar-alt me-1"></i> Campagne</label>
                                <select class="form-select" id="modal_campagne_id" name="campagne_id" required>
                                     <option value="" selected disabled>-- Sélectionner une campagne --</option>
                                     <?php foreach ($active_campaigns_for_modal as $campagne): ?>
                                         <option value="<?= htmlspecialchars($campagne['campagne_id']) ?>"><?= htmlspecialchars($campagne['nom_campagne']) ?></option>
                                     <?php endforeach; ?>
                                </select>
                                <div id="modal_campagneHelp" class="form-text text-muted small" style="min-height: 1.2em;"></div>
                            </div>
                             <div class="mb-3">
                                <label for="modal_vaccin_id" class="form-label"><i class="fas fa-syringe me-1"></i> Vaccin</label>
                                <select class="form-select" id="modal_vaccin_id" name="vaccin_id" required disabled>
                                    <option value="" selected disabled>-- Sélectionner d'abord une campagne --</option>
                                </select>
                                <div id="modal_vaccinHelp" class="form-text text-muted small" style="min-height: 1.2em;"></div>
                            </div>
                            <div class="mb-3">
                                <label for="modal_lot_id" class="form-label"><i class="fas fa-box me-1"></i> Lot</label>
                                <select class="form-select" id="modal_lot_id" name="lot_id" required disabled>
                                    <option value="" selected disabled>-- Sélectionner d'abord un vaccin --</option>
                                </select>
                                <div id="modal_lotHelp" class="form-text text-muted small" style="min-height: 1.2em;"></div>
                            </div>
                            <div class="mb-3">
                                <label for="modal_date_vaccination" class="form-label"><i class="fas fa-clock me-1"></i> Date de Vaccination</label>
                                <input type="date" class="form-control" id="modal_date_vaccination" name="date_vaccination" required max="<?= date('Y-m-d') ?>">
                            </div>
                         <?php else: ?>
                             <div class="alert alert-warning">Aucune campagne active n'a été trouvée. Impossible d'ajouter ou de modifier des vaccinations.</div>
                         <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" id="modal_submitButton" class="btn btn-primary" disabled>
                            <i class="fas fa-save me-1"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const matriculeSessionPrefill = <?= json_encode($matricule_to_prefill_add) ?>;
        const BASE_URL = '<?= basename($_SERVER['PHP_SELF']) ?>'; // Pour les appels AJAX

        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const vaccinationModalEl = document.getElementById('vaccinationModal');
            const vaccinationModal = new bootstrap.Modal(vaccinationModalEl);
            const modalForm = document.getElementById('vaccinationFormModal');
            const modalTitle = document.getElementById('vaccinationModalLabel');
            const modalRecordIdInput = document.getElementById('modal_record_id');
            const modalMatriculeGroup = document.getElementById('modal_matricule_group');
            const modalMatriculeInput = document.getElementById('modal_matricule_patient');
            const modalCampagneSelect = document.getElementById('modal_campagne_id');
            const modalVaccinSelect = document.getElementById('modal_vaccin_id');
            const modalLotSelect = document.getElementById('modal_lot_id');
            const modalDateInput = document.getElementById('modal_date_vaccination');
            const modalSubmitButton = document.getElementById('modal_submitButton');
            // Help texts
            const modalCampagneHelp = document.getElementById('modal_campagneHelp');
            const modalVaccinHelp = document.getElementById('modal_vaccinHelp');
            const modalLotHelp = document.getElementById('modal_lotHelp');
            // Add button
            const addVaccinationButton = document.getElementById('addVaccinationBtn');

            // Options for selects
            const loadingOption = '<option value="" selected disabled>Chargement...</option>';
            const errorOption = '<option value="" disabled>Erreur chargement</option>';
            const campaignRequiredOption = '<option value="" selected disabled>-- Sélectionner d\'abord une campagne --</option>';
            const vaccineRequiredOption = '<option value="" selected disabled>-- Sélectionner d\'abord un vaccin --</option>';
            const noVaccineOption = '<option value="" disabled>Aucun vaccin trouvé</option>';
            const noLotOption = '<option value="" disabled>Aucun lot valide trouvé</option>';
            const selectVaccineOption = '<option value="" selected disabled>-- Sélectionner un vaccin --</option>';
            const selectLotOption = '<option value="" selected disabled>-- Sélectionner un lot --</option>';

            // --- Helper to set select state ---
            function setSelectState(selectElement, helpElement, state, message = '', optionsHtml = null) {
                selectElement.disabled = (state === 'disabled' || state === 'loading' || state === 'error' || state === 'no_data');
                selectElement.classList.toggle('loading', state === 'loading');
                helpElement.textContent = message;
                helpElement.className = 'form-text small '; // Base classes

                if (state === 'error') helpElement.classList.add('text-danger');
                else if (state === 'no_data' || state === 'warning') helpElement.classList.add('text-warning');
                else helpElement.classList.add('text-muted');

                if (optionsHtml !== null) {
                    selectElement.innerHTML = optionsHtml;
                }
                checkSubmitButtonState(); // Vérifier état bouton après chaque changement
            }

            // --- Check if Submit Button should be enabled ---
             function checkSubmitButtonState() {
                 const campagneOk = modalCampagneSelect.value && !modalCampagneSelect.disabled;
                 const vaccinOk = modalVaccinSelect.value && !modalVaccinSelect.disabled;
                 const lotOk = modalLotSelect.value && !modalLotSelect.disabled;
                 const matriculeOk = modalMatriculeInput.value.trim() !== ''; // Vérifier si non vide
                 const dateOk = modalDateInput.value !== ''; // Vérifier si date sélectionnée

                 modalSubmitButton.disabled = !(campagneOk && vaccinOk && lotOk && matriculeOk && dateOk);
             }


            // --- Function to fetch VACCINS via AJAX ---
            async function updateModalVaccins(selectedCampagneId, vaccinToSelect = null) {
                 setSelectState(modalVaccinSelect, modalVaccinHelp, 'loading', 'Chargement des vaccins...', loadingOption);
                 setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption); // Reset lots

                 if (!selectedCampagneId) {
                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                     return;
                 }

                 try {
                     const response = await fetch(`${BASE_URL}?action=fetch_vaccins&campagne_id=${selectedCampagneId}`);
                     if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                     const data = await response.json();

                     if (data.error) {
                         setSelectState(modalVaccinSelect, modalVaccinHelp, 'error', data.error, errorOption);
                     } else if (data.vaccins && data.vaccins.length > 0) {
                         let optionsHtml = selectVaccineOption;
                         data.vaccins.forEach(vaccin => {
                             optionsHtml += `<option value="${vaccin.vaccin_id}">${vaccin.nom_vaccin}</option>`;
                         });
                         setSelectState(modalVaccinSelect, modalVaccinHelp, 'enabled', 'Veuillez sélectionner un vaccin.', optionsHtml);

                         if (vaccinToSelect) { // If editing, try to pre-select
                             modalVaccinSelect.value = vaccinToSelect;
                             // If pre-selection is successful, trigger lot loading automatically
                             if (modalVaccinSelect.value == vaccinToSelect) {
                                 modalVaccinSelect.dispatchEvent(new Event('change')); // Simulate change to load lots
                             } else {
                                 // vaccinToSelect might not exist anymore for this campaign
                                 setSelectState(modalVaccinSelect, modalVaccinHelp, 'warning', 'Vaccin original non trouvé pour cette campagne. Sélectionnez un autre vaccin.', optionsHtml); // Keep options available
                             }
                         }
                     } else {
                         setSelectState(modalVaccinSelect, modalVaccinHelp, 'no_data', 'Aucun vaccin trouvé pour cette campagne.', noVaccineOption);
                     }
                 } catch (error) {
                     console.error('Erreur AJAX récupération vaccins:', error);
                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'error', 'Erreur réseau/serveur chargement vaccins.', errorOption);
                 }
            }


            // --- Function to fetch LOTS via AJAX ---
            async function updateModalLots(selectedCampagneId, selectedVaccinId, lotToSelect = null) {
                 setSelectState(modalLotSelect, modalLotHelp, 'loading', 'Chargement des lots...', loadingOption);

                 if (!selectedCampagneId || !selectedVaccinId) {
                     setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                     return;
                 }

                try {
                    const response = await fetch(`${BASE_URL}?action=fetch_lots&campagne_id=${selectedCampagneId}&vaccin_id=${selectedVaccinId}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json();

                    if (data.error) {
                        setSelectState(modalLotSelect, modalLotHelp, 'error', data.error, errorOption);
                    } else if (data.lots && data.lots.length > 0) {
                        let optionsHtml = selectLotOption;
                        data.lots.forEach(lot => {
                            const expirationDate = new Date(lot.date_expiration).toLocaleDateString('fr-FR'); // Format date
                            optionsHtml += `<option value="${lot.lot_id}">${lot.nom_lot} (Exp: ${expirationDate})</option>`;
                        });
                        setSelectState(modalLotSelect, modalLotHelp, 'enabled', 'Veuillez sélectionner un lot.', optionsHtml);

                        if (lotToSelect) { // If editing, try to pre-select
                            modalLotSelect.value = lotToSelect;
                             if (modalLotSelect.value != lotToSelect) {
                                // Lot original might be expired or doesn't exist for this vaccin/campagne anymore
                                setSelectState(modalLotSelect, modalLotHelp, 'warning', 'Lot original expiré ou non trouvé. Sélectionnez un autre lot.', optionsHtml); // Keep options available
                             }
                        }
                    } else {
                        setSelectState(modalLotSelect, modalLotHelp, 'no_data', 'Aucun lot valide trouvé.', noLotOption);
                    }
                } catch (error) {
                    console.error('Erreur AJAX récupération lots:', error);
                    setSelectState(modalLotSelect, modalLotHelp, 'error', 'Erreur réseau/serveur chargement lots.', errorOption);
                }
            }

             // --- Event Listeners ---

             modalCampagneSelect.addEventListener('change', function() {
                 updateModalVaccins(this.value, null);
                 setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
             });

             modalVaccinSelect.addEventListener('change', function() {
                 const campId = modalCampagneSelect.value;
                 updateModalLots(campId, this.value, null);
             });

             modalLotSelect.addEventListener('change', checkSubmitButtonState);
             modalDateInput.addEventListener('change', checkSubmitButtonState);
             modalMatriculeInput.addEventListener('input', checkSubmitButtonState);


            // --- Prepare Modal for ADD ---
            if (addVaccinationButton) {
                 addVaccinationButton.addEventListener('click', function() {
                     if (this.disabled || !matriculeSessionPrefill) return;

                     modalForm.reset();
                     modalTitle.textContent = 'Ajouter une Vaccination pour ' + matriculeSessionPrefill;
                     modalRecordIdInput.value = '';
                     modalDateInput.valueAsDate = new Date();
                     modalCampagneSelect.value = "";
                     modalMatriculeInput.value = matriculeSessionPrefill;
                     modalMatriculeInput.readOnly = true;
                     modalMatriculeGroup.style.display = 'block';

                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                     setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                     modalSubmitButton.disabled = true;
                 });
            }

            // --- Prepare Modal for EDIT ---
            document.querySelector('.table tbody')?.addEventListener('click', async function(event) {
                 const editButton = event.target.closest('.edit-btn');
                 if (editButton) {
                     modalForm.reset();
                     modalTitle.textContent = 'Modifier la Vaccination';

                     const recordId = editButton.dataset.recordId;
                     const matricule = editButton.dataset.matriculePatient;
                     const campagneId = editButton.dataset.campagneId;
                     const vaccinId = editButton.dataset.vaccinId;
                     const lotId = editButton.dataset.lotId;
                     const dateVacc = editButton.dataset.dateVaccination;

                     modalRecordIdInput.value = recordId;
                     modalMatriculeInput.value = matricule;
                     modalMatriculeInput.readOnly = false;
                     modalMatriculeGroup.style.display = 'block';
                     modalDateInput.value = dateVacc;
                     modalCampagneSelect.value = campagneId;

                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                     setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                     modalSubmitButton.disabled = true;

                     await updateModalVaccins(campagneId, vaccinId);

                     if (modalVaccinSelect.value == vaccinId && !modalVaccinSelect.disabled) {
                        await updateModalLots(campagneId, vaccinId, lotId);
                     }
                 }
             });

            // --- Reset modal on hide ---
            vaccinationModalEl.addEventListener('hidden.bs.modal', function () {
                 modalForm.reset();
                 modalRecordIdInput.value = '';
                 modalMatriculeInput.readOnly = false;
                 modalMatriculeGroup.style.display = 'block';
                 modalCampagneSelect.value = "";
                 modalDateInput.value = "";
                 setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                 setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                 modalSubmitButton.disabled = true;
            });

            // --- Initialize tooltips ---
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                }
                return bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>