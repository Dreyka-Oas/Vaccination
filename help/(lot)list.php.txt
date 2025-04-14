<?php
// Importation des classes Core nécessaires
use App\Core\Flasher;          // Pour afficher les messages flash
use App\Core\SortLinkGenerator; // Pour générer les liens de tri
use App\Core\Paginator;         // Pour générer la pagination
// Les variables $lots_liste, $page, $totalPages, $paginationHtml, $sort_by, $sort_dir,
// $filtre_campagne_id, $available_active_campaigns, $currentQueryString sont fournies par LotController::list()
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Lots de Vaccins</title>
    <!-- CSS Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css"> <!-- Votre CSS personnalisé -->
    <!-- Styles spécifiques à cette page -->
    <style>
        /* Style pour les liens de tri dans les en-têtes */
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #0d6efd; text-decoration: underline; }
        th .sort-icon { margin-left: 5px; color: #adb5bd; } /* Icône par défaut */
        th.sorted .sort-icon { color: #000; } /* Icône quand trié */
        /* Classes pour colorer les dates d'expiration */
        .expired-date { color: red; font-weight: bold; } /* Date passée */
        .soon-expired-date { color: orange; } /* Date proche (moins de 30 jours) */
        /* Astérisque rouge pour les champs requis dans la modale */
        .required-field::after { content: ' *'; color: red; }
        /* Style pour les petits boutons d'action */
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
        /* Style pour le formulaire de filtre */
        .filter-form { background-color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem; border: 1px solid #dee2e6; }
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
                        <h1 class="mb-1"><i class="fas fa-boxes me-2"></i>Gestion des Lots</h1>
                        <p class="mb-0 small text-white-50">Suivi, filtrage et tri des lots</p>
                    </div>
                    <!-- Groupe de boutons d'actions générales -->
                    <div class="btn-group" role="group">
                        <!-- Bouton "Nouveau" : ouvre la modale d'ajout -->
                        <button type="button" id="addLotBtn" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#lotFormModal" title="Ajouter lot"><i class="fas fa-plus me-1 text-success"></i>Nouveau</button>
                        <!-- Bouton "Générer" : ouvre la modale de génération via JS -->
                        <button type="button" class="btn btn-sm btn-light" onclick="openGenerateLotsModal()" title="Générer tests"><i class="fas fa-magic me-1 text-info"></i>Générer</button>
                        <!-- Bouton "Tout Supprimer" : ouvre la modale de suppression globale via JS -->
                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllLotsModal()" title="Supprimer tout!"><i class="fas fa-trash-alt me-1"></i>Tout Suppr.</button>
                    </div>
                </div>
            </div>

            <?php // Affiche les messages flash ?>
            <?php Flasher::displayFlash(); ?>

                 <!-- Formulaire de filtre -->
                 <div class="filter-form mx-3 mt-3">
                    <form method="GET" action="index.php">
                        <!-- Champs cachés pour contrôleur et action -->
                        <input type="hidden" name="controller" value="Lot">
                        <input type="hidden" name="action" value="list">
                        <!-- Champs cachés pour conserver le tri lors du filtrage -->
                        <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                        <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sort_dir) ?>">
                        <!-- Grille pour aligner les éléments du filtre -->
                        <div class="row g-2 align-items-end">
                            <!-- Colonne pour le filtre par campagne -->
                            <div class="col-md-6">
                                <label for="filtre_campagne_id" class="form-label small">Filtrer par Campagne (Active)</label>
                                <!-- Liste déroulante des campagnes actives -->
                                <select class="form-select form-select-sm" id="filtre_campagne_id" name="filtre_campagne_id">
                                    <option value="" <?= ($filtre_campagne_id === '') ? 'selected' : '' ?>>-- Toutes --</option>
                                    <?php // Boucle sur les campagnes actives disponibles ?>
                                    <?php foreach ($available_active_campaigns as $camp): ?>
                                        <!-- Option sélectionnée si elle correspond au filtre actuel -->
                                        <option value="<?= $camp['campagne_id'] ?>" <?= ($filtre_campagne_id == $camp['campagne_id']) ? 'selected' : '' ?>> <?= htmlspecialchars($camp['nom_campagne']) ?> </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Colonne pour les boutons du filtre -->
                            <div class="col-md-6 d-flex gap-2">
                                <!-- Bouton pour soumettre le filtre -->
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button>
                                <!-- Bouton pour réinitialiser filtre et tri -->
                                <a href="index.php?controller=Lot&action=list" class="btn btn-secondary btn-sm flex-grow-1" title="Réinitialiser filtre et tri"><i class="fas fa-times me-1"></i>Reset</a>
                            </div>
                        </div>
                    </form>
                 </div>

                 <!-- Conteneur pour le tableau responsive -->
                 <div class="table-responsive">
                    <?php // Vérifie s'il y a des lots à afficher ?>
                    <?php if (!empty($lots_liste)): ?>
                        <!-- Tableau Bootstrap -->
                        <table class="table table-striped table-hover mb-0">
                             <thead>
                                <tr>
                                    <?php
                                        // URL de base pour les liens de tri (inclut le filtre campagne)
                                        $baseUrlForSort = "index.php?controller=Lot&action=list" . (!empty($filtre_campagne_id) ? '&filtre_campagne_id=' . $filtre_campagne_id : '');
                                        // Génère les en-têtes de colonne triables
                                        echo SortLinkGenerator::render($baseUrlForSort, 'nom_lot', 'Nom Lot', $sort_by, $sort_dir);
                                        echo SortLinkGenerator::render($baseUrlForSort, 'nom_vaccin', 'Vaccin', $sort_by, $sort_dir);
                                        echo SortLinkGenerator::render($baseUrlForSort, 'date_expiration', 'Expire le', $sort_by, $sort_dir);
                                        echo SortLinkGenerator::render($baseUrlForSort, 'nom_campagne', 'Campagne', $sort_by, $sort_dir);
                                    ?>
                                    <!-- En-tête pour la colonne Actions -->
                                    <th class="text-center" style="width: 120px;">Actions</th>
                                </tr>
                             </thead>
                            <tbody>
                                <?php // Prépare les dates pour comparer l'expiration ?>
                                <?php $today = new DateTime(); $today->setTime(0,0,0); // Aujourd'hui à minuit
                                      $warning_limit = (new DateTime())->modify('+30 days')->setTime(0,0,0); // Date dans 30 jours à minuit ?>
                                <?php // Boucle sur chaque lot ?>
                                <?php foreach ($lots_liste as $lot): ?>
                                    <?php // Calcule la classe CSS et la date formatée pour l'expiration
                                          $date_class = ''; $formatted_date = '-';
                                          try { // Utilise try-catch au cas où la date serait invalide
                                              if ($lot['date_expiration']) {
                                                  $expiration_date = new DateTime($lot['date_expiration']);
                                                  $expiration_date->setTime(0,0,0); // Compare à minuit
                                                  if ($expiration_date < $today) $date_class = 'expired-date'; // Expiré
                                                  elseif ($expiration_date < $warning_limit) $date_class = 'soon-expired-date'; // Expire bientôt
                                                  $formatted_date = $expiration_date->format('d/m/Y'); // Format français
                                              }
                                          } catch (\Exception $ex) { // Si la date est invalide
                                              $date_class = 'text-danger'; $formatted_date = 'Date invalide';
                                          }
                                    ?>
                                    <tr>
                                        <!-- Affiche les données du lot -->
                                        <td><?= htmlspecialchars($lot['nom_lot']) ?></td>
                                        <td><?= htmlspecialchars($lot['nom_vaccin']) ?></td>
                                        <!-- Affiche la date formatée avec sa classe CSS éventuelle -->
                                        <td class="<?= $date_class ?>"><?= $formatted_date ?></td>
                                        <td><?= htmlspecialchars($lot['nom_campagne']) ?></td>
                                        <!-- Colonne Actions -->
                                        <td class="text-center">
                                             <?php
                                                 // Prépare les paramètres pour les URLs d'action (id, page)
                                                 $actionParams = ['controller' => 'Lot', 'id' => $lot['lot_id'], 'page' => $page];
                                                 // Ajoute les filtres/tris actuels à l'URL
                                                 if (!empty($currentQueryString)) { parse_str($currentQueryString, $existingParams); $actionParams = array_merge($existingParams, $actionParams); }
                                                 // Construit l'URL de suppression
                                                 $deleteUrl = 'index.php?' . http_build_query(array_merge($actionParams, ['action' => 'delete']));
                                             ?>
                                             <!-- Bouton Modifier : ouvre la modale et passe les données via data-* -->
                                             <button type="button" class="btn btn-primary btn-action edit-btn"
                                                     data-bs-toggle="modal" data-bs-target="#lotFormModal"
                                                     data-id="<?= $lot['lot_id'] ?>"
                                                     data-nom="<?= htmlspecialchars($lot['nom_lot']) ?>"
                                                     data-expiration="<?= htmlspecialchars($lot['date_expiration']) ?>"
                                                     data-campagne-id="<?= $lot['campagne_id'] ?>"
                                                     data-vaccin-id="<?= $lot['vaccin_id'] ?>"
                                                     data-bs-toggle="tooltip" title="Modifier">
                                                 <i class="fas fa-edit"></i>
                                             </button>
                                            <!-- Bouton Supprimer : lien direct avec confirmation JS -->
                                            <a href="<?= $deleteUrl ?>" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer" onclick="return confirm('Supprimer lot \'<?= htmlspecialchars(addslashes($lot['nom_lot']), ENT_QUOTES) ?>\' et enregistrements associés ?')"><i class="fas fa-trash-alt"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; // Fin de la boucle sur les lots ?>
                            </tbody>
                        </table>
                    <?php else: // S'il n'y a aucun lot à afficher ?>
                        <div class="card-body text-center p-4">
                             <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                             <!-- Message indiquant pourquoi aucun lot n'est affiché -->
                            <p class="text-muted mb-3"> <?php if (!empty($filtre_campagne_id)): ?> Aucun lot trouvé pour cette campagne. <?php else: ?> Aucun lot enregistré. <?php endif; ?> </p>
                            <?php // Vérifie s'il existe des campagnes actives (nécessaire pour ajouter un lot)
                               $hasActiveCampaigns = !empty($available_active_campaigns);
                            ?>
                            <?php // Si aucune campagne active n'existe, affiche un avertissement
                                if (!$hasActiveCampaigns): ?>
                                <p class="text-warning small mb-3">Vérifiez qu'il existe des <a href="index.php?controller=Campaign&action=list">campagnes actives</a> et des vaccins associés.</p>
                            <?php endif; ?>
                             <?php // Si un filtre ou un tri est actif, affiche le bouton Reset
                                if (!empty($filtre_campagne_id) || !empty($currentQueryString)): ?> <a href="index.php?controller=Lot&action=list" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser</a><br> <?php endif; ?>
                             <!-- Bouton pour ouvrir la modale d'ajout -->
                             <!-- Désactivé si aucune campagne active n'est disponible -->
                             <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#lotFormModal" id="addLotBtnDirect" <?= !$hasActiveCampaigns ? 'disabled' : '' ?>>
                                <i class="fas fa-plus me-1"></i>Ajouter un lot
                            </button>
                        </div>
                    <?php endif; // Fin de la condition !empty($lots_liste) ?>
                </div>

                 <?php // Affiche la pagination si nécessaire ?>
                 <?php if (!empty($lots_liste) && $totalPages > 1): ?>
                    <div class="card-footer">
                         <?= $paginationHtml // Affiche le HTML généré par Paginator ?>
                    </div>
                 <?php endif; ?>
        </div> <!-- Fin de la carte principale -->

        <!-- Lien de retour vers le panneau Admin -->
        <div class="text-center my-4"> <a href="index.php?controller=Admin&action=index" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour Admin</a> </div>
    </div> <!-- Fin du conteneur principal -->

    <!-- Modale Bootstrap pour le formulaire d'ajout/modification de lot -->
    <div class="modal fade" id="lotFormModal" tabindex="-1" aria-labelledby="lotFormModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-lg"> <!-- modal-lg pour une modale large -->
            <div class="modal-content">
                <!-- Le formulaire est inclus ici (le contenu de form.php) -->
                <!-- Le contrôleur doit passer les variables nécessaires ($action, $lot_edit, $page, $available_active_campaigns, $currentQueryString) -->
                <!-- Pour simplifier, on inclut le code du formulaire ici, mais en pratique, on pourrait utiliser include. -->
                <form method="post" action="index.php?controller=Lot&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" id="modalLotForm">
                    <div class="modal-header">
                        <!-- Le titre sera mis à jour par JS -->
                        <h5 class="modal-title" id="lotFormModalLabel">Ajouter un Lot</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Champs cachés pour ID et page -->
                        <input type="hidden" name="lot_id" id="modal_lot_id" value="">
                        <input type="hidden" name="page" id="modal_page" value="<?= htmlspecialchars($page) ?>">

                        <!-- Champs du formulaire (similaires à form.php) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"> <label for="modal_nom_lot" class="form-label required-field">Nom / N° Lot</label> <input type="text" class="form-control" id="modal_nom_lot" name="nom_lot" required maxlength="255"> <div class="form-text">Identifiant unique.</div> </div>
                            <div class="col-md-6"> <label for="modal_date_expiration" class="form-label required-field">Expiration</label> <input type="date" class="form-control" id="modal_date_expiration" name="date_expiration" required min="<?= date('Y-m-d') ?>"> </div>
                        </div>
                        <div class="row g-3 mb-4">
                             <div class="col-md-6"> <label for="modal_campagne_id" class="form-label required-field">Campagne (Active)</label> <select class="form-select" id="modal_campagne_id" name="campagne_id" required> <option value="">-- Sélectionner --</option> <?php foreach ($available_active_campaigns as $camp): ?> <option value="<?= $camp['campagne_id'] ?>"> <?= htmlspecialchars($camp['nom_campagne']) ?> </option> <?php endforeach; ?> </select> <div class="form-text">Seules les campagnes actives.</div> </div>
                             <div class="col-md-6"> <label for="modal_vaccin_id" class="form-label required-field">Vaccin</label> <select class="form-select" id="modal_vaccin_id" name="vaccin_id" required disabled> <option value="">-- Choisir campagne d'abord --</option> </select> <div class="form-text">Doit appartenir à la campagne.</div> <div id="modal_vaccin-loading" class="text-muted small mt-1" style="display: none;"><i class="fas fa-spinner fa-spin me-1"></i>Chargement...</div> <div id="modal_vaccin-error" class="text-danger small mt-1" style="display: none;"><i class="fas fa-exclamation-triangle me-1"></i>Erreur.</div> </div>
                        </div>
                    </div>
                     <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Annuler</button>
                        <!-- Le bouton Submit est désactivé initialement -->
                        <button type="submit" class="btn btn-primary" id="modalSubmitButton" disabled><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modale pour générer des lots de test -->
    <div class="modal fade" id="generateLotsModal" tabindex="-1"> <div class="modal-dialog modal-sm"> <div class="modal-content"> <form id="generateLotsForm" action="index.php" method="get"> <input type="hidden" name="controller" value="Lot"><input type="hidden" name="action" value="generate"> <div class="modal-header"> <h6 class="modal-title"><i class="fas fa-magic me-2 text-info"></i>Générer Lots Test</h6> <button type="button" class="btn-close" data-bs-dismiss="modal"></button> </div> <div class="modal-body"> <div class="mb-3"> <label for="nombreLots" class="form-label">Nombre (1-100):</label> <input type="number" class="form-control form-control-sm" id="nombreLots" name="nombre" value="10" min="1" max="100" required> </div> <?php $canGenerate = !empty($available_active_campaigns); if (!$canGenerate) { echo '<small class="text-danger d-block">Requiert campagnes actives et vaccins associés.</small>'; } /* Avertissement si impossible de générer */ ?> </div> <div class="modal-footer"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-sm btn-primary" <?= !$canGenerate ? 'disabled' : '' ?> /* Désactive si impossible */>Générer</button> </div> </form> </div> </div> </div>
    <!-- Modale pour supprimer tous les lots -->
    <div class="modal fade" id="deleteAllLotsModal" tabindex="-1"> <div class="modal-dialog"> <div class="modal-content"> <form id="deleteAllLotsForm" action="index.php" method="GET"> <input type="hidden" name="controller" value="Lot"><input type="hidden" name="action" value="deleteAll"> <div class="modal-header bg-danger text-white"> <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button> </div> <div class="modal-body"> <p><strong class="text-danger">Attention : Irréversible !</strong></p> <p>Suppression de <strong>TOUS</strong> les lots et <strong>TOUS</strong> les enregistrements associés.</p> <div class="form-check mt-3"> <input class="form-check-input" type="checkbox" id="confirmDeleteAllLotsCheckbox" required> <label class="form-check-label" for="confirmDeleteAllLotsCheckbox">Je confirme.</label> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-danger" id="confirmDeleteAllLotsBtn" disabled /* Activé par JS */><i class="fas fa-trash-alt me-1"></i>Supprimer Tout</button> </div> </form> </div> </div> </div>

    <!-- JS Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts JS spécifiques -->
    <script>
        // Fonction pour ouvrir la modale de génération
        function openGenerateLotsModal() { var m = new bootstrap.Modal(document.getElementById('generateLotsModal')); m.show(); }
        // Gestion de la modale de suppression globale
        const deleteAllLotsModalEl = document.getElementById('deleteAllLotsModal');
        if (deleteAllLotsModalEl) {
            const checkbox = deleteAllLotsModalEl.querySelector('#confirmDeleteAllLotsCheckbox');
            const submitBtn = deleteAllLotsModalEl.querySelector('#confirmDeleteAllLotsBtn');
            const modalInstance = new bootstrap.Modal(deleteAllLotsModalEl);
            // Fonction globale pour ouvrir la modale
            window.openDeleteAllLotsModal=function(){ checkbox.checked=false; submitBtn.disabled=true; modalInstance.show();} ;
            // Active/désactive le bouton à la coche/décoche
            checkbox.addEventListener('change', function(){submitBtn.disabled = !this.checked;});
        }
        // Initialisation des tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function () { var t=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')); t.map(function(e){if(!bootstrap.Tooltip.getInstance(e)){return new bootstrap.Tooltip(e,{delay:{"show":300,"hide":100}, html: true});}}); });

        // ---- Gestion de la modale d'ajout/modification ----
        document.addEventListener('DOMContentLoaded', function() {
            // Récupération des éléments DOM de la modale
            const lotModal = document.getElementById('lotFormModal');
            const modalForm = document.getElementById('modalLotForm');
            const modalTitle = document.getElementById('lotFormModalLabel');
            const modalLotId = document.getElementById('modal_lot_id');
            const modalNomLot = document.getElementById('modal_nom_lot');
            const modalDateExpiration = document.getElementById('modal_date_expiration');
            const modalCampagneSelect = document.getElementById('modal_campagne_id');
            const modalVaccinSelect = document.getElementById('modal_vaccin_id');
            const modalVaccinLoading = document.getElementById('modal_vaccin-loading');
            const modalVaccinError = document.getElementById('modal_vaccin-error');
            const modalPageInput = document.getElementById('modal_page');
            const modalSubmitButton = document.getElementById('modalSubmitButton');
            const ajaxVaccinUrl = 'index.php?controller=Lot&action=getVaccinsAjax'; // URL pour l'AJAX

            /**
             * Fonction asynchrone pour charger les vaccins dans la modale via AJAX.
             * @param {string} campagneId - L'ID de la campagne sélectionnée.
             * @param {string|null} vaccinToSelect - L'ID du vaccin à présélectionner (mode modif).
             */
            async function loadVaccinsForCampagneModal(campagneId, vaccinToSelect = null) {
                 // Réinitialisation de la liste vaccin et des indicateurs
                 modalVaccinSelect.innerHTML = '<option value="">-- Sélectionner --</option>';
                 modalVaccinSelect.disabled = true;
                 modalVaccinLoading.style.display = 'none';
                 modalVaccinError.style.display = 'none';
                 modalSubmitButton.disabled = true; // Désactive le bouton pendant le chargement

                 // Si aucune campagne sélectionnée, message et sortie
                 if (!campagneId) {
                     modalVaccinSelect.innerHTML = '<option value="">-- Choisir campagne d\'abord --</option>';
                     return; // Pas besoin de réactiver le bouton ici, car rien n'est sélectionnable
                 }
                 // Affiche l'indicateur de chargement
                 modalVaccinLoading.style.display = 'block';

                 try {
                     // Appel AJAX
                     const response = await fetch(`${ajaxVaccinUrl}&campagne_id=${campagneId}`);
                     if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                     const vaccins = await response.json(); // Parse JSON
                     modalVaccinLoading.style.display = 'none'; // Cache chargement

                     // Si des vaccins sont trouvés
                     if (vaccins && vaccins.length > 0) {
                          // Remplit la liste
                          modalVaccinSelect.innerHTML = '<option value="">-- Sélectionner un vaccin --</option>';
                         vaccins.forEach(vaccin => {
                             const o = document.createElement('option');
                             o.value = vaccin.vaccin_id;
                             o.textContent = vaccin.nom_vaccin;
                             modalVaccinSelect.appendChild(o);
                         });
                         // Active la liste
                         modalVaccinSelect.disabled = false;
                         // Note: Le bouton submit est activé seulement si un vaccin est choisi (voir event listener plus bas)

                         // Si un vaccin doit être présélectionné
                         if (vaccinToSelect) {
                             modalVaccinSelect.value = vaccinToSelect;
                             // Log si la sélection échoue
                             if (modalVaccinSelect.value !== vaccinToSelect.toString()) {
                                 console.warn(`Vaccin ID ${vaccinToSelect} non trouvé ou invalide pour la campagne ${campagneId} dans la modale.`);
                             }
                             // Active le bouton Submit si la présélection a réussi
                             modalSubmitButton.disabled = !modalVaccinSelect.value;
                         } else {
                              modalSubmitButton.disabled = true; // Garde désactivé si ajout (pas de vaccin sélectionné)
                         }
                     } else { // Aucun vaccin trouvé
                         modalVaccinSelect.innerHTML = '<option value="">-- Aucun vaccin trouvé --</option>';
                         // Laisse le bouton désactivé
                     }
                 } catch (error) { // Erreur AJAX
                     console.error('Erreur chargement vaccins (modale):', error);
                     modalVaccinLoading.style.display = 'none';
                     modalVaccinError.style.display = 'block'; // Affiche l'erreur
                     modalVaccinSelect.innerHTML = '<option value="">-- Erreur --</option>';
                     // Laisse le bouton désactivé
                 }
            }

             // Écouteur : Quand la campagne change dans la modale...
             modalCampagneSelect.addEventListener('change', function() {
                 // ...charge les vaccins correspondants (sans présélection)
                 loadVaccinsForCampagneModal(this.value);
             });
              // Écouteur : Quand le vaccin change dans la modale...
              modalVaccinSelect.addEventListener('change', function() {
                 // ...active ou désactive le bouton Submit selon si un vaccin est sélectionné
                 modalSubmitButton.disabled = !this.value;
             });

             // Fonction pour préparer la modale pour l'ajout
             function prepareModalForAdd() {
                 modalForm.reset(); // Vide les champs
                 modalTitle.textContent = 'Ajouter un Lot'; // Titre
                 modalLotId.value = ''; // Vide l'ID
                 modalSubmitButton.textContent = 'Ajouter'; // Texte bouton
                 modalPageInput.value = <?= json_encode($page) ?>; // Page actuelle
                 // Réinitialise et désactive la liste vaccin
                 modalVaccinSelect.innerHTML = '<option value="">-- Choisir campagne d\'abord --</option>';
                 modalVaccinSelect.disabled = true;
                 // Désactive le bouton Submit
                 modalSubmitButton.disabled = true;
             }

             // Fonction pour préparer la modale pour la modification
             function prepareModalForEdit(button) {
                 modalForm.reset(); // Vide les champs
                 const data = button.dataset; // Récupère les data-*
                 modalTitle.textContent = 'Modifier le Lot : ' + data.nom; // Titre
                 // Pré-remplit les champs
                 modalLotId.value = data.id;
                 modalNomLot.value = data.nom;
                 modalDateExpiration.value = data.expiration;
                 modalCampagneSelect.value = data.campagneId; // Sélectionne la campagne
                 modalSubmitButton.textContent = 'Enregistrer'; // Texte bouton
                 modalPageInput.value = <?= json_encode($page) ?>; // Page actuelle

                 // Charge les vaccins pour la campagne sélectionnée, en présélectionnant le vaccin actuel du lot
                 loadVaccinsForCampagneModal(data.campagneId, data.vaccinId);
                 // Note: Le bouton submit sera activé/désactivé par loadVaccinsForCampagneModal et l'event listener sur vaccinSelect
             }

             // Attache les écouteurs aux boutons "Nouveau"
             const addBtn = document.getElementById('addLotBtn');
             if (addBtn) { addBtn.addEventListener('click', prepareModalForAdd); }
             const addBtnDirect = document.getElementById('addLotBtnDirect');
             if (addBtnDirect) { addBtnDirect.addEventListener('click', prepareModalForAdd); }

             // Délégation d'événement pour les boutons "Modifier" du tableau
             const tableBody = document.querySelector('.table tbody');
             if (tableBody) {
                 tableBody.addEventListener('click', function(event) {
                     const editButton = event.target.closest('.edit-btn'); // Trouve le bouton parent .edit-btn
                     if (editButton) {
                         prepareModalForEdit(editButton); // Prépare la modale pour l'édition
                     }
                 });
             }

             // Réinitialise la modale quand elle est fermée
             lotModal.addEventListener('hidden.bs.modal', function () {
                 modalForm.reset();
                 modalLotId.value = '';
                 modalVaccinSelect.innerHTML = '<option value="">-- Choisir campagne d\'abord --</option>';
                 modalVaccinSelect.disabled = true;
                 modalSubmitButton.disabled = true;
                 modalVaccinLoading.style.display = 'none';
                 modalVaccinError.style.display = 'none';
             });
        }); // Fin DOMContentLoaded pour la modale add/edit
    </script>
</body>
</html>