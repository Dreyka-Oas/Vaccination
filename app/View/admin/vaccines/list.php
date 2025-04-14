<?php
// Importation des classes Core nécessaires
use App\Core\Flasher;          // Pour afficher les messages flash
use App\Core\SortLinkGenerator; // Pour générer les liens de tri
use App\Core\Paginator;         // Pour générer la pagination
// Les variables $vaccins_liste, $page, $totalPages, $paginationHtml, $sort_by, $sort_dir,
// $filtre_campagne_id, $available_campaigns, $available_types, $currentQueryString
// sont fournies par VaccineController::list()
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Vaccins</title>
    <!-- CSS Bootstrap, Font Awesome et personnalisé -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <!-- Styles spécifiques à cette page -->
    <style>
        /* Liens de tri dans les en-têtes */
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #0d6efd; text-decoration: underline; }
        th .sort-icon { margin-left: 5px; color: #adb5bd; }
        th.sorted .sort-icon { color: #000; }
        /* Style pour description et types tronqués dans le tableau */
        .table-description, .table-types { display: inline-block; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        /* Astérisque rouge pour champs requis dans la modale */
        .required-field::after { content: ' *'; color: red; }
        /* Style pour les petits boutons d'action */
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
        /* Style pour le formulaire de filtre */
        .filter-form { background-color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem; border: 1px solid #dee2e6; }
        /* Style pour le conteneur des checkboxes de type (avec scroll si besoin) */
        .type-checkbox-group { max-height: 150px; overflow-y: auto; border: 1px solid #dee2e6; padding: 0.5rem; }
    </style>
</head>
<body>
    <!-- Conteneur principal -->
    <div class="dashboard-container">
        <!-- Carte principale -->
        <div class="card">
             <!-- En-tête de la carte -->
             <div class="card-header header">
                 <!-- Flexbox pour aligner titre et boutons -->
                 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <!-- Titre et sous-titre -->
                    <div>
                        <h1 class="mb-1"><i class="fas fa-syringe me-2"></i>Gestion des Vaccins</h1>
                        <p class="mb-0 small text-white-50">Ajouter, modifier, filtrer et trier les vaccins</p>
                    </div>
                    <!-- Groupe de boutons d'actions générales -->
                    <div class="btn-group" role="group">
                        <!-- Bouton "Nouveau" : ouvre la modale d'ajout/modif -->
                        <button type="button" id="addVaccineBtn" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#vaccineFormModal" title="Nouveau Vaccin"><i class="fas fa-plus me-1 text-success"></i>Nouveau</button>
                        <!-- Bouton "Générer" : ouvre la modale de génération via JS -->
                        <button type="button" class="btn btn-sm btn-light" onclick="openGenerateVaccinsModal()" title="Générer données test"><i class="fas fa-magic me-1 text-info"></i>Générer</button>
                        <!-- Bouton "Tout Supprimer" : ouvre la modale de suppression globale via JS -->
                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllVaccinsModal()" title="Supprimer Tous!"><i class="fas fa-trash-alt me-1"></i>Suppr. Tout</button>
                    </div>
                </div>
            </div>

            <?php // Affiche les messages flash ?>
            <?php Flasher::displayFlash(); ?>

                 <!-- Formulaire de filtre par campagne -->
                 <div class="filter-form mx-3 mt-3">
                    <form method="GET" action="index.php">
                        <!-- Champs cachés pour contrôleur et action -->
                        <input type="hidden" name="controller" value="Vaccine">
                        <input type="hidden" name="action" value="list">
                        <!-- Champs cachés pour conserver le tri lors du filtrage -->
                        <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                        <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sort_dir) ?>">
                        <!-- Grille pour aligner les éléments -->
                        <div class="row g-2 align-items-end">
                            <!-- Colonne pour le select de campagne -->
                            <div class="col-md-6">
                                <label for="filtre_campagne_id" class="form-label small">Filtrer par Campagne Active</label>
                                <!-- Liste déroulante des campagnes actives -->
                                <select class="form-select form-select-sm" id="filtre_campagne_id" name="filtre_campagne_id">
                                    <option value="" <?= ($filtre_campagne_id === '') ? 'selected' : '' ?>>-- Toutes --</option>
                                    <?php // Boucle sur les campagnes actives disponibles ?>
                                    <?php foreach ($available_campaigns as $camp): ?>
                                        <!-- Option sélectionnée si elle correspond au filtre actuel -->
                                        <option value="<?= $camp['campagne_id'] ?>" <?= ($filtre_campagne_id == $camp['campagne_id']) ? 'selected' : '' ?>> <?= htmlspecialchars($camp['nom_campagne']) ?> </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Colonne pour les boutons Filtrer et Reset -->
                            <div class="col-md-6 d-flex gap-2">
                                <!-- Bouton pour soumettre le filtre -->
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button>
                                <!-- Bouton pour réinitialiser le filtre et le tri -->
                                <a href="index.php?controller=Vaccine&action=list" class="btn btn-secondary btn-sm flex-grow-1" title="Réinitialiser filtre et tri"><i class="fas fa-times me-1"></i>Reset</a>
                            </div>
                        </div>
                    </form>
                 </div>

                 <!-- Conteneur pour le tableau responsive -->
                 <div class="table-responsive">
                    <?php // Vérifie s'il y a des vaccins à afficher ?>
                    <?php if (!empty($vaccins_liste)): ?>
                        <!-- Tableau Bootstrap -->
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php
                                        // URL de base pour les liens de tri (inclut le filtre campagne)
                                        $baseUrlForSort = "index.php?controller=Vaccine&action=list" . (!empty($filtre_campagne_id) ? '&filtre_campagne_id=' . $filtre_campagne_id : '');
                                        // Génère les en-têtes triables
                                        echo SortLinkGenerator::render($baseUrlForSort, 'nom_vaccin', 'Nom Vaccin', $sort_by, $sort_dir);
                                        echo SortLinkGenerator::render($baseUrlForSort, 'nom_campagne', 'Campagne', $sort_by, $sort_dir);
                                        echo SortLinkGenerator::render($baseUrlForSort, 'type_names', 'Type(s)', $sort_by, $sort_dir); // Tri sur la colonne agrégée
                                        echo SortLinkGenerator::render($baseUrlForSort, 'description', 'Description', $sort_by, $sort_dir);
                                    ?>
                                    <!-- En-tête pour la colonne Actions -->
                                    <th class="text-center" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php // Boucle sur chaque vaccin ?>
                                <?php foreach ($vaccins_liste as $vaccin): ?>
                                    <tr>
                                        <!-- Affiche les données du vaccin -->
                                        <td><?= htmlspecialchars($vaccin['nom_vaccin']) ?></td>
                                        <td><?= htmlspecialchars($vaccin['nom_campagne']) ?></td>
                                        <!-- Affiche la liste des types (tronquée) avec tooltip complet -->
                                        <td> <span class="table-types" data-bs-toggle="tooltip" title="<?= htmlspecialchars($vaccin['type_names']) ?>"> <?= htmlspecialchars($vaccin['type_names']) ?> </span> </td>
                                         <!-- Affiche la description (tronquée) avec tooltip complet -->
                                         <td> <span class="table-description" data-bs-toggle="tooltip" title="<?= htmlspecialchars($vaccin['description'] ?: 'Aucune description') ?>"> <?= htmlspecialchars($vaccin['description'] ?: '-') ?> </span> </td>
                                        <!-- Colonne Actions -->
                                        <td class="text-center">
                                            <?php
                                                 // Prépare les paramètres pour les URLs d'action (id, page)
                                                 $actionParams = ['controller' => 'Vaccine', 'id' => $vaccin['vaccin_id'], 'page' => $page];
                                                 // Ajoute les filtres/tris actuels à l'URL
                                                 if (!empty($currentQueryString)) { parse_str($currentQueryString, $existingParams); $actionParams = array_merge($existingParams, $actionParams); }
                                                 // Construit l'URL de suppression
                                                 $deleteUrl = 'index.php?' . http_build_query(array_merge($actionParams, ['action' => 'delete']));
                                             ?>
                                            <!-- Bouton Modifier : ouvre la modale et passe les données via data-* -->
                                            <!-- data-type-ids contient la chaîne d'IDs séparés par des virgules, fournie par le contrôleur -->
                                            <button type="button" class="btn btn-primary btn-action edit-btn"
                                                    data-bs-toggle="modal" data-bs-target="#vaccineFormModal"
                                                    data-id="<?= $vaccin['vaccin_id'] ?>"
                                                    data-nom="<?= htmlspecialchars($vaccin['nom_vaccin']) ?>"
                                                    data-campagne-id="<?= $vaccin['campagne_id'] ?>"
                                                    data-description="<?= htmlspecialchars($vaccin['description'] ?? '') ?>"
                                                    data-type-ids="<?= htmlspecialchars($vaccin['associated_type_ids_str']) ?>"
                                                    data-bs-toggle="tooltip" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- Bouton Supprimer : lien direct avec confirmation JS -->
                                            <a href="<?= $deleteUrl ?>" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer" onclick="return confirm('Confirmer la suppression du vaccin \'<?= htmlspecialchars(addslashes($vaccin['nom_vaccin']), ENT_QUOTES) ?>\' et de ses données associées (lots, enregistrements...) ?')"><i class="fas fa-trash-alt"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; // Fin boucle vaccins ?>
                            </tbody>
                        </table>
                    <?php else: // Si aucun vaccin n'est trouvé ?>
                        <div class="card-body text-center p-4">
                            <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                             <!-- Message indiquant pourquoi aucun vaccin n'est affiché -->
                             <p class="text-muted mb-3"> <?php if (!empty($filtre_campagne_id)): ?> Aucun vaccin trouvé pour la campagne sélectionnée. <?php else: ?> Aucun vaccin enregistré. <?php endif; ?> </p>
                             <?php
                                // Vérifie si des campagnes actives et des types existent (nécessaire pour ajouter un vaccin)
                                // CORRECTION : Utilisation de empty() sur les variables fournies par le contrôleur
                                $hasPrerequisites = !empty($available_campaigns) && !empty($available_types);
                              ?>
                             <?php // Si des prérequis manquent, affiche un message d'avertissement ?>
                             <?php if (!$hasPrerequisites): ?>
                                 <p class="text-warning small mb-3">
                                     Vérifiez qu'il existe des
                                     <?php if (empty($available_campaigns)): ?><a href="index.php?controller=Campaign&action=list">campagnes actives</a><?php endif; ?>
                                     <?php if (empty($available_campaigns) && empty($available_types)): ?> et des <?php endif; ?>
                                     <?php if (empty($available_types)): ?><a href="index.php?controller=VaccineType&action=list">types de vaccins</a><?php endif; ?>.
                                 </p>
                             <?php endif; ?>
                             <?php // Si filtre/tri actif, affiche bouton Reset ?>
                             <?php if (!empty($filtre_campagne_id) || !empty($currentQueryString)): ?> <a href="index.php?controller=Vaccine&action=list" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser filtre/tri</a><br> <?php endif; ?>
                            <!-- Bouton pour ouvrir la modale d'ajout -->
                            <!-- Désactivé si les prérequis (campagnes actives ET types) ne sont pas remplis -->
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#vaccineFormModal" id="addVaccineBtnDirect" <?= !$hasPrerequisites ? 'disabled' : '' ?>>
                                <i class="fas fa-plus me-1"></i>Créer un vaccin
                            </button>
                        </div>
                    <?php endif; // Fin condition !empty($vaccins_liste) ?>
                </div>

                <?php // Affiche la pagination si nécessaire ?>
                <?php if (!empty($vaccins_liste) && $totalPages > 1): ?>
                    <div class="card-footer">
                         <?= $paginationHtml // Affiche le HTML généré par Paginator ?>
                    </div>
                <?php endif; ?>
        </div> <!-- Fin carte principale -->

        <!-- Lien de retour vers le panneau Admin -->
        <div class="text-center my-4"><a href="index.php?controller=Admin&action=index" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour Admin</a></div>
    </div> <!-- Fin conteneur principal -->

    <!-- Modale Bootstrap pour le formulaire d'ajout/modification de vaccin -->
    <div class="modal fade" id="vaccineFormModal" tabindex="-1" aria-labelledby="vaccineFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> <!-- Modale large -->
            <div class="modal-content">
                <!-- Le formulaire est inclus ici (contenu de form.php) -->
                <form method="post" action="index.php?controller=Vaccine&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" id="modalVaccineForm">
                    <div class="modal-header">
                        <!-- Titre mis à jour par JS -->
                        <h5 class="modal-title" id="vaccineFormModalLabel">Ajouter un Vaccin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Champs cachés ID et page -->
                        <input type="hidden" name="vaccin_id" id="modal_vaccin_id" value="">
                        <input type="hidden" name="page" id="modal_page" value="<?= htmlspecialchars($page) ?>">

                        <!-- Champs du formulaire (Nom, Campagne, Types, Description) -->
                        <div class="mb-3">
                            <label for="modal_nom_vaccin" class="form-label required-field">Nom du Vaccin</label>
                            <input type="text" class="form-control" id="modal_nom_vaccin" name="nom_vaccin" required maxlength="255">
                         </div>
                         <div class="row g-3 mb-3">
                             <div class="col-md-6">
                                <label for="modal_campagne_id" class="form-label required-field">Campagne Associée</label>
                                <select class="form-select" id="modal_campagne_id" name="campagne_id" required>
                                    <option value="">-- Sélectionner une campagne active --</option>
                                    <?php foreach ($available_campaigns as $camp): ?>
                                        <option value="<?= $camp['campagne_id'] ?>"><?= htmlspecialchars($camp['nom_campagne']) ?></option>
                                    <?php endforeach; ?>
                                 </select>
                                <div class="form-text">Seules les campagnes actives sont listées.</div>
                             </div>
                             <div class="col-md-6">
                                <label class="form-label required-field">Type(s) de Vaccin</label>
                                <div class="border p-3 rounded type-checkbox-group">
                                    <?php // Affiche les types disponibles sous forme de checkboxes ?>
                                    <?php if (empty($available_types)): ?>
                                        <span class="text-muted">Aucun type disponible.</span>
                                    <?php else: ?>
                                        <?php foreach ($available_types as $type): ?>
                                            <div class="form-check">
                                                <!-- name="type_ids[]" pour recevoir un tableau PHP -->
                                                <input class="form-check-input" type="checkbox" name="type_ids[]" id="modal_type_<?= $type['type_vaccin_id'] ?>" value="<?= $type['type_vaccin_id'] ?>">
                                                <label class="form-check-label" for="modal_type_<?= $type['type_vaccin_id'] ?>"><?= htmlspecialchars($type['nom_type']) ?></label>
                                             </div>
                                         <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text">Cochez un ou plusieurs types.</div>
                             </div>
                         </div>
                         <div class="mb-3">
                            <label for="modal_description" class="form-label">Description <small class="text-muted">(Opt.)</small></label>
                            <textarea class="form-control" id="modal_description" name="description" rows="3"></textarea>
                         </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Annuler</button>
                        <!-- Texte bouton mis à jour par JS -->
                        <button type="submit" class="btn btn-primary" id="modalSubmitButton"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modale pour générer des vaccins test -->
    <div class="modal fade" id="generateVaccinsModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form id="generateVaccinsForm" action="index.php" method="get">
                     <input type="hidden" name="controller" value="Vaccine">
                     <input type="hidden" name="action" value="generate">
                     <div class="modal-header">
                         <h6 class="modal-title"><i class="fas fa-magic me-2 text-info"></i>Générer Vaccins Test</h6>
                         <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                     </div>
                     <div class="modal-body">
                         <div class="mb-3">
                             <label for="nombreVaccins" class="form-label">Nombre (1-50):</label>
                             <input type="number" class="form-control form-control-sm" id="nombreVaccins" name="nombre" value="5" min="1" max="50" required>
                         </div>
                         <?php // Affiche avertissement si prérequis manquants (utilise les variables $available_*) ?>
                         <?php // CORRECTION: Utilisation de empty() sur les variables existantes ?>
                         <?php if (empty($available_campaigns) || empty($available_types)): ?>
                             <small class="text-danger d-block">Requiert campagnes actives et types.</small>
                         <?php endif; ?>
                     </div>
                     <div class="modal-footer">
                         <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
                         <?php // CORRECTION: Désactive le bouton si prérequis manquants ?>
                         <button type="submit" class="btn btn-sm btn-primary" <?= (empty($available_campaigns) || empty($available_types)) ? 'disabled' : '' ?>>Générer</button>
                     </div>
                 </form>
            </div>
        </div>
    </div>

    <!-- Modale pour supprimer tous les vaccins -->
    <div class="modal fade" id="deleteAllVaccinsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="deleteAllVaccinsForm" action="index.php" method="GET">
                    <input type="hidden" name="controller" value="Vaccine">
                    <input type="hidden" name="action" value="deleteAll">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong class="text-danger">Attention : Irréversible !</strong></p>
                        <p>Suppression de <strong>TOUS</strong> les vaccins, associations, lots et enregistrements liés.</p>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="confirmDeleteAllVaccinsCheckbox" required>
                            <label class="form-check-label" for="confirmDeleteAllVaccinsCheckbox">Je confirme vouloir tout supprimer.</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <!-- Bouton désactivé, géré par JS -->
                        <button type="submit" class="btn btn-danger" id="confirmDeleteAllVaccinsBtn" disabled><i class="fas fa-trash-alt me-1"></i>Supprimer Tout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts JS spécifiques -->
    <script>
        // Fonctions pour ouvrir les modales de génération et suppression globale
        function openGenerateVaccinsModal() { var myModal = new bootstrap.Modal(document.getElementById('generateVaccinsModal')); myModal.show(); }
        const deleteAllVaccinsModalEl = document.getElementById('deleteAllVaccinsModal');
        if (deleteAllVaccinsModalEl) {
            const c = deleteAllVaccinsModalEl.querySelector('#confirmDeleteAllVaccinsCheckbox'), b = deleteAllVaccinsModalEl.querySelector('#confirmDeleteAllVaccinsBtn'), i = new bootstrap.Modal(deleteAllVaccinsModalEl);
            function o(){ c.checked = false; b.disabled = true; i.show(); } // Fonction pour ouvrir
            c.addEventListener('change', function() { b.disabled = !this.checked; }); // Active/désactive bouton
            window.openDeleteAllVaccinsModal = o; // Rend la fonction globale
        }

        // Attend que le DOM soit chargé
        document.addEventListener('DOMContentLoaded', function () {
            // Initialise les tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (t) { if (!bootstrap.Tooltip.getInstance(t)) { return new bootstrap.Tooltip(t, { delay: { "show": 300, "hide": 100 }, html: true }); } });

             // ---- Gestion de la modale d'ajout/modification Vaccin ----
             const vaccineModal = document.getElementById('vaccineFormModal');
             const modalForm = document.getElementById('modalVaccineForm');
             const modalTitle = document.getElementById('vaccineFormModalLabel');
             const modalVaccineId = document.getElementById('modal_vaccin_id');
             const modalNomVaccin = document.getElementById('modal_nom_vaccin');
             const modalCampagneId = document.getElementById('modal_campagne_id');
             const modalDescription = document.getElementById('modal_description');
             // Récupère TOUTES les checkboxes de type DANS la modale
             const modalCheckboxes = vaccineModal.querySelectorAll('.type-checkbox-group input[type="checkbox"]');
             const modalPageInput = document.getElementById('modal_page');
             const modalSubmitButton = document.getElementById('modalSubmitButton');

             // Fonction pour préparer la modale en mode AJOUT
             function prepareModalForAdd() {
                 modalForm.reset(); // Vide les champs
                 modalTitle.textContent = 'Ajouter un Vaccin'; // Titre
                 modalVaccineId.value = ''; // Vide ID
                 modalCheckboxes.forEach(cb => cb.checked = false); // Décoche toutes les cases
                 modalSubmitButton.textContent = 'Ajouter'; // Texte bouton
                 modalPageInput.value = <?= json_encode($page) ?>; // Page actuelle
             }

             // Fonction pour préparer la modale en mode MODIFICATION
             function prepareModalForEdit(button) {
                 modalForm.reset(); // Vide les champs
                 const data = button.dataset; // Récupère data-*
                 modalTitle.textContent = 'Modifier le vaccin : ' + data.nom; // Titre
                 // Pré-remplit les champs
                 modalVaccineId.value = data.id;
                 modalNomVaccin.value = data.nom;
                 modalCampagneId.value = data.campagneId;
                 modalDescription.value = data.description;
                 modalSubmitButton.textContent = 'Enregistrer'; // Texte bouton
                 modalPageInput.value = <?= json_encode($page) ?>; // Page actuelle

                 // Gérer les checkboxes de type pour la modification
                 modalCheckboxes.forEach(cb => cb.checked = false); // D'abord tout décocher
                 // Si des IDs de type sont fournis dans data-type-ids (chaîne séparée par ',')
                 if (data.typeIds && data.typeIds.length > 0) {
                     const typeIdsArray = data.typeIds.split(','); // Convertit la chaîne en tableau
                     // Boucle sur les IDs du vaccin à modifier
                     typeIdsArray.forEach(id => {
                         // Trouve la checkbox correspondante dans la modale par son ID
                         const checkbox = document.getElementById('modal_type_' + id);
                         if (checkbox) {
                             checkbox.checked = true; // Coche la case
                         }
                     });
                 }
             }

             // Attache les écouteurs aux boutons "Nouveau"
             const addBtn = document.getElementById('addVaccineBtn');
             if (addBtn) {
                 addBtn.addEventListener('click', prepareModalForAdd);
             }
             const addBtnDirect = document.getElementById('addVaccineBtnDirect');
             if (addBtnDirect) {
                  addBtnDirect.addEventListener('click', prepareModalForAdd);
             }

             // Délégation d'événement pour les boutons "Modifier" du tableau
             const tableBody = document.querySelector('.table tbody');
             if (tableBody) {
                 tableBody.addEventListener('click', function(event) {
                     const editButton = event.target.closest('.edit-btn'); // Trouve le bouton
                     if (editButton) {
                         prepareModalForEdit(editButton); // Prépare la modale
                     }
                 });
             }

             // Réinitialise la modale quand elle est fermée
             vaccineModal.addEventListener('hidden.bs.modal', function () {
                 modalForm.reset();
                 modalVaccineId.value = ''; // Vide ID
                 modalCheckboxes.forEach(cb => cb.checked = false); // Décoche tout
             });
        }); // Fin DOMContentLoaded
    </script>
</body>
</html>