<?php
// Importation des classes Core
use App\Core\Flasher; // Pour afficher les messages flash
use App\Core\Auth;    // Pour récupérer les infos de l'utilisateur connecté (nom, rôle, admin status)

// Les variables utilisées ici sont fournies par VaccinationRecordController::form() :
// $matricule_to_prefill_add : Matricule patient éventuellement pré-rempli via session (après recherche dashboard)
// $active_campaigns_for_modal : Campagnes actives pour la modale add/edit
// $all_campaigns_for_filter : Toutes les campagnes pour le filtre de la liste
// $vaccinations_list : La liste des enregistrements à afficher (peut être vide)
// $page_error_message : Message d'erreur général si la récupération a échoué
// $filter_campagne_id : ID de la campagne utilisée pour filtrer la liste
// $user_matricule, $user_username, $user_role, $isAdmin : Infos de l'utilisateur connecté
// $form_action_url, $delete_action_url, $filter_action_url, $ajax_base_url : URLs construites par le contrôleur
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Titre dynamique incluant le matricule patient si pré-rempli -->
    <title>Gestion des Vaccinations <?= ($matricule_to_prefill_add ? ' pour ' . htmlspecialchars($matricule_to_prefill_add) : '') ?> - Projet Vaccination</title>
    <!-- CSS Bootstrap, Font Awesome et personnalisé -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <!-- Styles spécifiques -->
    <style>
        /* Styles pour l'en-tête personnalisé */
        .header { background-color: #0d6efd; color: white; }
        .header h1, .header h3, .header p { color: white; }
        .header .text-white-50 { color: rgba(255, 255, 255, 0.7) !important; }
        .header .btn-outline-light { border-color: rgba(255, 255, 255, 0.5); color: rgba(255, 255, 255, 0.8); }
        .header .btn-outline-light:hover { background-color: rgba(255, 255, 255, 0.1); color: white; }
        /* Style infos utilisateur dans l'en-tête */
        .user-info-header .badge { font-size: 0.8em; vertical-align: middle; }
        .user-info-header .btn-logout { padding: 0.3rem 0.7rem; font-size: 0.9em; }
        /* Marge pour le tableau */
        .table-responsive { margin-top: 1rem; }
        /* Styles pour les formulaires dans les actions du tableau */
        .action-buttons form { display: inline-block; margin: 0 2px;}
        /* Styles pour les labels et le pied de page de la modale */
        .modal-body .form-label { margin-bottom: 0.5rem; }
        .modal-footer { border-top: 1px solid #dee2e6; }
        /* Style pour les champs désactivés/lecture seule */
        select:disabled, input[readonly] { background-color: #e9ecef; cursor: not-allowed; opacity: 1;}
        /* Style pour le formulaire de filtre */
        .filter-form { margin-bottom: 1rem; padding: 1rem; background-color: #f8f9fa; border-radius: 0.375rem; border: 1px solid #dee2e6;}
        /* Classe pour ajouter une icône de chargement (via background-image) aux selects */
        .loading {
           background-image: url('data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAkKAAAALAAAAAAQABAAAAMyCLrc/jDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAAA==');
           background-repeat: no-repeat; background-position: right center; padding-right: 25px;
        }
    </style>
</head>
<body>
    <!-- Conteneur principal -->
    <div class="container mt-4">

        <!-- Carte pour l'en-tête de page -->
        <div class="card mb-4">
             <div class="card-header header">
                 <!-- Flexbox pour aligner titre, retour et infos utilisateur -->
                 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                     <!-- Titre principal et lien retour Dashboard -->
                     <div>
                         <!-- Titre dynamique incluant le matricule si pré-rempli -->
                         <h1 class="mb-1 h3"><i class="fas fa-list-alt me-2"></i>Gestion Vaccinations <?= ($matricule_to_prefill_add ? ' pour ' . htmlspecialchars($matricule_to_prefill_add) : '') ?></h1>
                         <a href="index.php?controller=Dashboard&action=index" class="btn btn-sm btn-outline-light mt-1"><i class="fas fa-arrow-left me-1"></i> Retour Dashboard</a>
                     </div>
                     <!-- Infos utilisateur connecté et bouton déconnexion -->
                     <div class="text-end user-info-header">
                         <?php // Affiche si l'utilisateur est connecté ?>
                         <?php if ($user_matricule): ?>
                              <span class="small text-white-50 me-3">
                                 <i class="fas fa-user me-1"></i><?= htmlspecialchars($user_username) ?> <!-- Nom utilisateur -->
                                 (<span class="badge bg-light text-dark"><?= htmlspecialchars($user_role) ?></span>) <!-- Rôle -->
                              </span>
                             <!-- Bouton de déconnexion -->
                             <a href="index.php?controller=Auth&action=logout" class="btn btn-danger btn-sm btn-logout" title="Se déconnecter"> <i class="fas fa-sign-out-alt"></i> </a>
                         <?php endif; ?>
                     </div>
                 </div>
             </div>
        </div>

        <?php // Affiche les messages flash ?>
        <?php Flasher::displayFlash(); ?>
        <?php // Affiche un message d'erreur général si défini par le contrôleur ?>
        <?php if ($page_error_message): ?>
            <div class="alert alert-danger" role="alert"> <?= htmlspecialchars($page_error_message) ?> </div>
        <?php endif; ?>

        <!-- Ligne contenant le filtre et les boutons d'action principaux -->
        <div class="row align-items-center mb-3">
            <!-- Colonne pour le formulaire de filtre -->
            <div class="col-md-8">
                <div class="filter-form">
                    <!-- Formulaire GET pour filtrer la liste par campagne -->
                    <form action="<?= htmlspecialchars($filter_action_url) /* URL fournie par le contrôleur */ ?>" method="get" class="row g-2 align-items-end">
                        <!-- Champs cachés pour contrôleur et action -->
                        <input type="hidden" name="controller" value="VaccinationRecord">
                        <input type="hidden" name="action" value="form">
                         <div class="col-auto"><label for="filter_campagne_id" class="form-label mb-0">Filtrer:</label></div>
                         <!-- Colonne pour la liste déroulante des campagnes -->
                         <div class="col-sm">
                            <select class="form-select form-select-sm" id="filter_campagne_id" name="filter_campagne_id">
                                <option value="">-- Toutes Campagnes --</option>
                                <?php // Boucle sur toutes les campagnes disponibles pour le filtre ?>
                                <?php foreach ($all_campaigns_for_filter as $campagne): ?>
                                <option value="<?= htmlspecialchars($campagne['campagne_id']) ?>" <?= ($filter_campagne_id == $campagne['campagne_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($campagne['nom_campagne']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                         </div>
                         <!-- Colonne pour les boutons Filtrer et Reset -->
                         <div class="col-auto">
                            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter me-1"></i> Filtrer</button>
                            <?php // Affiche le bouton Reset seulement si un filtre est actif ?>
                            <?php if ($filter_campagne_id): ?>
                                <a href="<?= htmlspecialchars($filter_action_url) ?>" class="btn btn-outline-secondary btn-sm ms-1" title="Effacer le filtre"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                         </div>
                    </form>
                </div>
            </div>
            <!-- Colonne pour les boutons Ajouter et Générer -->
            <div class="col-md-4 text-md-end mt-2 mt-md-0">
                 <div class="btn-group" role="group">
                     <!-- Bouton Ajouter : ouvre la modale d'ajout/modification -->
                     <!-- Désactivé si aucun matricule patient n'est pré-rempli (sélectionné via dashboard) -->
                     <!-- Le title change pour expliquer pourquoi il est désactivé -->
                     <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vaccinationModal" id="addVaccinationBtn"
                             <?= !$matricule_to_prefill_add ? 'disabled' : '' ?>
                             title="<?= !$matricule_to_prefill_add ? 'Recherchez d\'abord un patient via le Dashboard' : 'Ajouter une vaccination pour '.htmlspecialchars($matricule_to_prefill_add) ?>">
                        <i class="fas fa-plus me-1"></i> Ajouter
                     </button>
                     <?php // Affiche le bouton Générer seulement si l'utilisateur est admin ?>
                     <?php if ($isAdmin): ?>
                     <button type="button" class="btn btn-info" onclick="openGenerateRecordsModal()" title="Générer données test">
                        <i class="fas fa-flask me-1"></i> Générer
                     </button>
                    <?php endif; ?>
                 </div>
            </div>
        </div>

        <!-- Carte contenant la liste des enregistrements -->
        <div class="card">
            <!-- En-tête de la carte indiquant si la liste est filtrée -->
            <div class="card-header">Liste des Vaccinations <?= ($filter_campagne_id ? '(Filtrée par campagne)' : '') ?></div>
            <div class="card-body">
                <?php // Vérifie qu'il n'y a pas d'erreur et que la liste n'est pas vide ?>
                <?php if (!$page_error_message && !empty($vaccinations_list)): ?>
                    <!-- Tableau responsive -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered caption-top">
                            <!-- Légende indiquant le nombre d'enregistrements trouvés -->
                            <caption class="small text-muted"><?= count($vaccinations_list) ?> enregistrement(s) trouvé(s).</caption>
                            <thead class="table-light">
                                <tr>
                                    <!-- En-têtes de colonnes -->
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
                            <?php // Boucle sur chaque enregistrement ?>
                            <?php foreach ($vaccinations_list as $record): ?>
                                <tr>
                                    <!-- Affiche les données de l'enregistrement -->
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($record['date_vaccination']))) /* Format date */ ?></td>
                                    <td><?= htmlspecialchars($record['matricule_patient']) ?></td>
                                    <td><?= htmlspecialchars($record['patient_username'] ?? 'N/A') /* Affiche N/A si le nom du patient n'est pas trouvé */ ?></td>
                                    <td><?= htmlspecialchars($record['nom_campagne']) ?></td>
                                    <td><?= htmlspecialchars($record['nom_vaccin']) ?></td>
                                    <td><?= htmlspecialchars($record['nom_lot']) ?></td>
                                    <!-- Colonne Actions -->
                                    <td class="action-buttons">
                                        <!-- Bouton Modifier : ouvre la modale et passe les données via data-* -->
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
                                        <!-- Bouton Supprimer : formulaire POST avec confirmation JS -->
                                        <form action="<?= htmlspecialchars($delete_action_url) /* URL fournie par contrôleur */ ?>" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet enregistrement ?');">
                                            <input type="hidden" name="vaccination_record_id" value="<?= htmlspecialchars($record['vaccination_record_id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"> <i class="fas fa-trash-alt"></i> </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; // Fin boucle ?>
                            </tbody>
                        </table>
                    </div>
                <?php // Messages si la liste est vide ?>
                <?php elseif (!$page_error_message && $filter_campagne_id): ?> <p class="text-center text-muted fst-italic mt-3">Aucun enregistrement trouvé pour cette campagne.</p>
                <?php elseif (!$page_error_message): ?> <p class="text-center text-muted fst-italic mt-3">Aucun enregistrement de vaccination trouvé.</p>
                <?php endif; // Fin condition liste vide ?>
            </div>
        </div>

        <!-- Pied de page simple -->
        <div class="text-center my-4"><p class="small text-muted">© <?= date('Y') ?> Projet Vaccination</p></div>
    </div> <!-- Fin conteneur principal -->

    <!-- Modale Bootstrap pour Ajouter/Modifier un enregistrement -->
    <div class="modal fade" id="vaccinationModal" tabindex="-1" aria-labelledby="vaccinationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> <!-- Modale large -->
            <div class="modal-content">
                <!-- Formulaire POST pointant vers l'action 'save' -->
                <form action="<?= htmlspecialchars($form_action_url) ?>" method="post" id="vaccinationFormModal">
                    <div class="modal-header">
                        <!-- Titre (sera mis à jour par JS) -->
                        <h5 class="modal-title" id="vaccinationModalLabel">Ajouter/Modifier Vaccination</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Champ caché pour l'ID de l'enregistrement (en mode modification) -->
                        <input type="hidden" name="vaccination_record_id" id="modal_record_id" value="">

                        <?php // Vérifie s'il y a des campagnes actives disponibles pour la modale ?>
                        <?php if (!empty($active_campaigns_for_modal)): ?>
                            <!-- Champ Matricule Patient -->
                            <!-- 'readonly' car il est soit pré-rempli, soit récupéré en mode modification -->
                            <div class="mb-3" id="modal_matricule_group">
                                <label for="modal_matricule_patient" class="form-label"><i class="fas fa-id-card me-1"></i> Matricule Patient</label>
                                <input type="text" class="form-control" id="modal_matricule_patient" name="matricule_patient" placeholder="Matricule" required readonly>
                            </div>
                            <!-- Champ Campagne (liste déroulante) -->
                            <div class="mb-3">
                                <label for="modal_campagne_id" class="form-label"><i class="fas fa-calendar-alt me-1"></i> Campagne</label>
                                <select class="form-select" id="modal_campagne_id" name="campagne_id" required>
                                     <option value="" selected disabled>-- Sélectionner une campagne --</option>
                                     <?php foreach ($active_campaigns_for_modal as $campagne): ?>
                                         <option value="<?= htmlspecialchars($campagne['campagne_id']) ?>"><?= htmlspecialchars($campagne['nom_campagne']) ?></option>
                                     <?php endforeach; ?>
                                </select>
                                <!-- Zone pour afficher des messages d'aide/erreur/chargement pour ce select (géré par JS) -->
                                <div id="modal_campagneHelp" class="form-text text-muted small" style="min-height: 1.2em;"></div>
                            </div>
                             <!-- Champ Vaccin (liste déroulante, désactivée, remplie par AJAX) -->
                             <div class="mb-3">
                                <label for="modal_vaccin_id" class="form-label"><i class="fas fa-syringe me-1"></i> Vaccin</label>
                                <select class="form-select" id="modal_vaccin_id" name="vaccin_id" required disabled>
                                    <option value="" selected disabled>-- Sélectionner d'abord une campagne --</option>
                                </select>
                                <!-- Zone d'aide/erreur/chargement pour ce select -->
                                <div id="modal_vaccinHelp" class="form-text text-muted small" style="min-height: 1.2em;"></div>
                            </div>
                            <!-- Champ Lot (liste déroulante, désactivée, remplie par AJAX) -->
                            <div class="mb-3">
                                <label for="modal_lot_id" class="form-label"><i class="fas fa-box me-1"></i> Lot</label>
                                <select class="form-select" id="modal_lot_id" name="lot_id" required disabled>
                                    <option value="" selected disabled>-- Sélectionner d'abord un vaccin --</option>
                                </select>
                                <!-- Zone d'aide/erreur/chargement pour ce select -->
                                <div id="modal_lotHelp" class="form-text text-muted small" style="min-height: 1.2em;"></div>
                            </div>
                            <!-- Champ Date de Vaccination -->
                            <div class="mb-3">
                                <label for="modal_date_vaccination" class="form-label"><i class="fas fa-clock me-1"></i> Date de Vaccination</label>
                                <input type="date" class="form-control" id="modal_date_vaccination" name="date_vaccination" required>
                            </div>
                         <?php else: // Si aucune campagne active n'est disponible ?>
                             <div class="alert alert-warning">Aucune campagne active n'a été trouvée. Impossible d'ajouter ou de modifier des vaccinations.</div>
                         <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <!-- Bouton de soumission, désactivé par défaut, activé par JS -->
                        <button type="submit" id="modal_submitButton" class="btn btn-primary" disabled>
                            <i class="fas fa-save me-1"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modale pour générer des enregistrements test (affichée si admin) -->
    <div class="modal fade" id="generateRecordsModal" tabindex="-1" aria-labelledby="generateRecordsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm"> <!-- Petite modale -->
            <div class="modal-content">
                <!-- Formulaire GET pointant vers l'action 'generate' -->
                <form action="index.php" method="get">
                     <input type="hidden" name="controller" value="VaccinationRecord">
                    <input type="hidden" name="action" value="generate">
                    <div class="modal-header">
                        <h6 class="modal-title" id="generateRecordsModalLabel"><i class="fas fa-flask me-2 text-info"></i>Générer Enregistrements Test</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombreRecords" class="form-label">Nombre (2-15):</label>
                            <!-- Champ pour choisir le nombre à générer -->
                            <input type="number" class="form-control form-control-sm" id="nombreRecords" name="nombre" value="2" min="2" max="15" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-sm btn-primary">Générer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script JS spécifique à cette page -->
    <script>
        // Récupère le matricule pré-rempli depuis PHP (peut être null)
        const matriculeSessionPrefill = <?= json_encode($matricule_to_prefill_add) ?>;
        // Récupère l'URL de base pour les appels AJAX depuis PHP
        const AJAX_BASE_URL = '<?= $ajax_base_url ?>';

        // Attend que le DOM soit chargé
        document.addEventListener('DOMContentLoaded', function() {
            // Récupère les éléments DOM de la modale principale
            const vaccinationModalEl = document.getElementById('vaccinationModal');
            const vaccinationModal = new bootstrap.Modal(vaccinationModalEl); // Instance de la modale Bootstrap
            const modalForm = document.getElementById('vaccinationFormModal');
            const modalTitle = document.getElementById('vaccinationModalLabel');
            const modalRecordIdInput = document.getElementById('modal_record_id');
            const modalMatriculeGroup = document.getElementById('modal_matricule_group'); // Groupe contenant le champ matricule
            const modalMatriculeInput = document.getElementById('modal_matricule_patient');
            const modalCampagneSelect = document.getElementById('modal_campagne_id');
            const modalVaccinSelect = document.getElementById('modal_vaccin_id');
            const modalLotSelect = document.getElementById('modal_lot_id');
            const modalDateInput = document.getElementById('modal_date_vaccination');
            const modalSubmitButton = document.getElementById('modal_submitButton');
            // Zones d'aide/feedback sous les selects
            const modalCampagneHelp = document.getElementById('modal_campagneHelp');
            const modalVaccinHelp = document.getElementById('modal_vaccinHelp');
            const modalLotHelp = document.getElementById('modal_lotHelp');
            const addVaccinationButton = document.getElementById('addVaccinationBtn'); // Bouton "Ajouter" principal

            // Options HTML standard pour les selects lors des appels AJAX
            const loadingOption = '<option value="" selected disabled>Chargement...</option>';
            const errorOption = '<option value="" disabled>Erreur chargement</option>';
            const campaignRequiredOption = '<option value="" selected disabled>-- Sélectionner d\'abord une campagne --</option>';
            const vaccineRequiredOption = '<option value="" selected disabled>-- Sélectionner d\'abord un vaccin --</option>';
            const noVaccineOption = '<option value="" disabled>Aucun vaccin trouvé</option>';
            const noLotOption = '<option value="" disabled>Aucun lot valide trouvé</option>';
            const selectVaccineOption = '<option value="" selected disabled>-- Sélectionner un vaccin --</option>';
            const selectLotOption = '<option value="" selected disabled>-- Sélectionner un lot --</option>';

            /**
             * Met à jour l'état visuel et fonctionnel d'un select et de sa zone d'aide.
             * @param {HTMLSelectElement} selectElement - L'élément select à modifier.
             * @param {HTMLElement} helpElement - L'élément où afficher le message d'aide/erreur.
             * @param {'enabled'|'disabled'|'loading'|'error'|'no_data'|'warning'} state - L'état souhaité.
             * @param {string} [message=''] - Le message à afficher dans la zone d'aide.
             * @param {string|null} [optionsHtml=null] - Le contenu HTML des options à insérer dans le select.
             */
            function setSelectState(selectElement, helpElement, state, message = '', optionsHtml = null) {
                // Active ou désactive le select selon l'état
                selectElement.disabled = (state === 'disabled' || state === 'loading' || state === 'error' || state === 'no_data');
                // Ajoute/enlève la classe 'loading' pour l'icône CSS
                selectElement.classList.toggle('loading', state === 'loading');
                // Met à jour le message d'aide
                helpElement.textContent = message;
                // Définit la classe CSS du message d'aide
                helpElement.className = 'form-text small '; // Classe de base
                if (state === 'error') helpElement.classList.add('text-danger');
                else if (state === 'no_data' || state === 'warning') helpElement.classList.add('text-warning');
                else helpElement.classList.add('text-muted');

                // Met à jour les options du select si fourni
                if (optionsHtml !== null) {
                    selectElement.innerHTML = optionsHtml;
                }
                // Vérifie si le bouton de soumission doit être activé/désactivé
                checkSubmitButtonState();
             }

             /**
              * Vérifie si tous les champs requis du formulaire sont valides et active/désactive le bouton 'Enregistrer'.
              */
             function checkSubmitButtonState() {
                 // Vérifie que chaque select requis a une valeur et n'est pas désactivé
                 const campagneOk = modalCampagneSelect.value && !modalCampagneSelect.disabled;
                 const vaccinOk = modalVaccinSelect.value && !modalVaccinSelect.disabled;
                 const lotOk = modalLotSelect.value && !modalLotSelect.disabled;
                 // Vérifie que les champs input requis ne sont pas vides
                 const matriculeOk = modalMatriculeInput.value.trim() !== '';
                 const dateOk = modalDateInput.value !== '';

                 // Le bouton est activé seulement si toutes les conditions sont vraies
                 modalSubmitButton.disabled = !(campagneOk && vaccinOk && lotOk && matriculeOk && dateOk);
             }

            /**
             * Charge les vaccins pour la campagne sélectionnée via AJAX et met à jour le select des vaccins.
             * @param {string} selectedCampagneId - L'ID de la campagne sélectionnée.
             * @param {string|null} [vaccinToSelect=null] - L'ID du vaccin à présélectionner (mode modif).
             */
            async function updateModalVaccins(selectedCampagneId, vaccinToSelect = null) {
                 // Met les selects vaccin et lot en état de chargement/désactivé
                 setSelectState(modalVaccinSelect, modalVaccinHelp, 'loading', 'Chargement des vaccins...', loadingOption);
                 setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption); // Lot dépend du vaccin

                 // Si aucune campagne sélectionnée, réinitialise et sort
                 if (!selectedCampagneId) {
                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                     return;
                 }

                 try {
                     // Appel AJAX pour récupérer les vaccins
                     const response = await fetch(`${AJAX_BASE_URL}&action=fetchVaccinsAjax&campagne_id=${selectedCampagneId}`);
                     if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                     const data = await response.json(); // Parse la réponse JSON

                     // Gère la réponse
                     if (data.error && data.vaccins.length === 0) { // Erreur signalée par le backend et pas de vaccins
                         setSelectState(modalVaccinSelect, modalVaccinHelp, 'error', data.error, errorOption);
                     } else if (data.vaccins && data.vaccins.length > 0) { // Vaccins trouvés
                         let optionsHtml = selectVaccineOption; // Option par défaut
                         // Construit le HTML des options
                         data.vaccins.forEach(vaccin => {
                             optionsHtml += `<option value="${vaccin.vaccin_id}">${vaccin.nom_vaccin}</option>`;
                         });
                         // Met à jour le select en état 'enabled' avec les options
                         setSelectState(modalVaccinSelect, modalVaccinHelp, 'enabled', 'Veuillez sélectionner un vaccin.', optionsHtml);

                         // Si un vaccin doit être présélectionné (mode modification)
                         if (vaccinToSelect) {
                             modalVaccinSelect.value = vaccinToSelect; // Tente la sélection
                             if (modalVaccinSelect.value == vaccinToSelect) { // Si la sélection a réussi
                                 // Déclenche manuellement l'événement 'change' pour charger les lots correspondants
                                 modalVaccinSelect.dispatchEvent(new Event('change'));
                             } else { // Si le vaccin original n'est plus dans la liste
                                 setSelectState(modalVaccinSelect, modalVaccinHelp, 'warning', 'Vaccin original non trouvé. Sélectionnez un autre vaccin.', optionsHtml);
                             }
                         }
                     } else { // Aucun vaccin trouvé (data.vaccins est vide)
                         setSelectState(modalVaccinSelect, modalVaccinHelp, 'no_data', data.error || 'Aucun vaccin trouvé pour cette campagne.', noVaccineOption);
                     }
                 } catch (error) { // Erreur réseau ou de parsing JSON
                     console.error('Erreur AJAX récupération vaccins:', error);
                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'error', 'Erreur réseau/serveur chargement vaccins.', errorOption);
                 }
            }

            /**
             * Charge les lots valides pour la campagne et le vaccin sélectionnés via AJAX.
             * @param {string} selectedCampagneId - L'ID de la campagne.
             * @param {string} selectedVaccinId - L'ID du vaccin.
             * @param {string|null} [lotToSelect=null] - L'ID du lot à présélectionner (mode modif).
             */
            async function updateModalLots(selectedCampagneId, selectedVaccinId, lotToSelect = null) {
                 // Met le select lot en état de chargement
                 setSelectState(modalLotSelect, modalLotHelp, 'loading', 'Chargement des lots...', loadingOption);

                 // Si campagne ou vaccin non sélectionné, réinitialise et sort
                 if (!selectedCampagneId || !selectedVaccinId) {
                     setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                     return;
                 }

                try {
                    // Appel AJAX pour récupérer les lots (seuls les lots valides/non expirés sont retournés par le backend)
                    const response = await fetch(`${AJAX_BASE_URL}&action=fetchLotsAjax&campagne_id=${selectedCampagneId}&vaccin_id=${selectedVaccinId}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json(); // Parse JSON

                    // Gère la réponse
                    if (data.error && data.lots.length === 0) { // Erreur signalée et pas de lots
                        setSelectState(modalLotSelect, modalLotHelp, 'error', data.error, errorOption);
                    } else if (data.lots && data.lots.length > 0) { // Lots trouvés
                        let optionsHtml = selectLotOption; // Option par défaut
                        // Construit le HTML des options, incluant la date d'expiration formatée
                        data.lots.forEach(lot => {
                            const expirationDate = new Date(lot.date_expiration).toLocaleDateString('fr-FR'); // Format date fr
                            optionsHtml += `<option value="${lot.lot_id}">${lot.nom_lot} (Exp: ${expirationDate})</option>`;
                        });
                        // Met à jour le select en état 'enabled'
                        setSelectState(modalLotSelect, modalLotHelp, 'enabled', 'Veuillez sélectionner un lot.', optionsHtml);

                        // Si un lot doit être présélectionné (mode modification)
                        if (lotToSelect) {
                            modalLotSelect.value = lotToSelect; // Tente la sélection
                             // Si la sélection échoue (lot original expiré ou plus associé ?)
                             if (modalLotSelect.value != lotToSelect) {
                                setSelectState(modalLotSelect, modalLotHelp, 'warning', 'Lot original expiré ou non trouvé. Sélectionnez un autre lot.', optionsHtml);
                             }
                        }
                    } else { // Aucun lot trouvé
                        setSelectState(modalLotSelect, modalLotHelp, 'no_data', data.error || 'Aucun lot valide trouvé.', noLotOption);
                    }
                } catch (error) { // Erreur réseau/parsing
                    console.error('Erreur AJAX récupération lots:', error);
                    setSelectState(modalLotSelect, modalLotHelp, 'error', 'Erreur réseau/serveur chargement lots.', errorOption);
                }
            }

             // Écouteur : Quand la campagne change...
             modalCampagneSelect.addEventListener('change', function() {
                 updateModalVaccins(this.value, null); // ...charge les vaccins (sans présélection)
                 setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption); // ...réinitialise les lots
             });

             // Écouteur : Quand le vaccin change...
             modalVaccinSelect.addEventListener('change', function() {
                 const campId = modalCampagneSelect.value;
                 updateModalLots(campId, this.value, null); // ...charge les lots correspondants (sans présélection)
             });

             // Écouteurs pour vérifier l'état du bouton Submit quand ces champs changent
             modalLotSelect.addEventListener('change', checkSubmitButtonState);
             modalDateInput.addEventListener('change', checkSubmitButtonState);
             modalMatriculeInput.addEventListener('input', checkSubmitButtonState);

            // Écouteur sur le bouton "Ajouter" principal
            if (addVaccinationButton) {
                 addVaccinationButton.addEventListener('click', function() {
                     // Ne fait rien si le bouton est désactivé ou si aucun matricule n'est pré-rempli
                     if (this.disabled || !matriculeSessionPrefill) return;

                     // Prépare la modale pour l'ajout
                     modalForm.reset(); // Vide les champs
                     modalTitle.textContent = 'Ajouter une Vaccination pour ' + matriculeSessionPrefill; // Titre
                     modalRecordIdInput.value = ''; // Vide l'ID
                     modalDateInput.value = ''; // Vide la date
                     modalCampagneSelect.value = ""; // Déselectionne campagne
                     modalMatriculeInput.value = matriculeSessionPrefill; // Pré-remplit matricule
                     modalMatriculeInput.readOnly = true; // Rend matricule non modifiable
                     modalMatriculeGroup.style.display = 'block'; // Assure que le groupe matricule est visible

                     // Réinitialise les selects vaccin et lot
                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                     setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                     modalSubmitButton.disabled = true; // Désactive le bouton Submit initialement
                 });
            }

            // Délégation d'événement sur le corps du tableau pour les boutons "Modifier"
            document.querySelector('.table tbody')?.addEventListener('click', async function(event) {
                 const editButton = event.target.closest('.edit-btn'); // Trouve le bouton cliqué ou son parent .edit-btn
                 if (editButton) {
                     // Prépare la modale pour la modification
                     modalForm.reset();
                     modalTitle.textContent = 'Modifier la Vaccination';

                     // Récupère les données depuis les attributs data-* du bouton
                     const recordId = editButton.dataset.recordId;
                     const matricule = editButton.dataset.matriculePatient;
                     const campagneId = editButton.dataset.campagneId;
                     const vaccinId = editButton.dataset.vaccinId;
                     const lotId = editButton.dataset.lotId;
                     const dateVacc = editButton.dataset.dateVaccination;

                     // Pré-remplit les champs de la modale
                     modalRecordIdInput.value = recordId;
                     modalMatriculeInput.value = matricule;
                     modalMatriculeInput.readOnly = true; // Rend le matricule non modifiable en mode édition
                     modalMatriculeGroup.style.display = 'block';
                     modalDateInput.value = dateVacc;
                     modalCampagneSelect.value = campagneId; // Sélectionne la campagne

                     // Réinitialise les selects vaccin/lot et désactive Submit avant chargement AJAX
                     setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                     setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                     modalSubmitButton.disabled = true;

                     // Charge les vaccins pour cette campagne, en tentant de présélectionner le vaccin actuel
                     // 'await' est utilisé car updateModalVaccins est asynchrone
                     await updateModalVaccins(campagneId, vaccinId);

                     // Si le chargement des vaccins a réussi ET que le vaccin présélectionné est toujours valide
                     if (modalVaccinSelect.value == vaccinId && !modalVaccinSelect.disabled) {
                        // Charge les lots pour ce vaccin, en tentant de présélectionner le lot actuel
                        // 'await' car updateModalLots est aussi asynchrone
                        await updateModalLots(campagneId, vaccinId, lotId);
                     }
                     // Le bouton Submit sera activé par checkSubmitButtonState appelé dans setSelectState
                 }
             });

            // Écouteur : Réinitialise la modale quand elle est fermée
            vaccinationModalEl.addEventListener('hidden.bs.modal', function () {
                 modalForm.reset();
                 modalRecordIdInput.value = '';
                 modalMatriculeInput.readOnly = false; // Redevient éditable (sera géré au prochain clic)
                 modalMatriculeGroup.style.display = 'block';
                 modalCampagneSelect.value = "";
                 modalDateInput.value = "";
                 // Réinitialise les selects dépendants
                 setSelectState(modalVaccinSelect, modalVaccinHelp, 'disabled', '', campaignRequiredOption);
                 setSelectState(modalLotSelect, modalLotHelp, 'disabled', '', vaccineRequiredOption);
                 modalSubmitButton.disabled = true; // Désactive le bouton
            });

             // Fonction globale pour ouvrir la modale de génération
             window.openGenerateRecordsModal = function() {
                 var generateModal = new bootstrap.Modal(document.getElementById('generateRecordsModal'));
                 generateModal.show();
             }

            // Initialise les tooltips Bootstrap restants
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                }
                return bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            });
        }); // Fin DOMContentLoaded
    </script>
</body>
</html>