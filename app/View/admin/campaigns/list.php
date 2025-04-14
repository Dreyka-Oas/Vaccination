<?php
// Importation des classes nécessaires du Core pour utiliser leurs méthodes statiques.
use App\Core\Flasher;          // Pour afficher les messages flash (succès, erreur...).
use App\Core\SortLinkGenerator; // Pour générer les liens de tri dans les en-têtes de tableau.
use App\Core\Paginator;         // Pour générer les liens de pagination.
// Note: Les variables utilisées dans cette vue ($campagnes, $page, $totalPages, $paginationHtml,
// $sort_by, $sort_dir, $filtre_statut, $currentQueryString, etc.) sont définies
// et passées par le contrôleur (CampaignController, méthode list).
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Campagnes Vaccinales</title>
    <!-- Inclusion des CSS de Bootstrap et Font Awesome via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Inclusion de votre CSS personnalisé -->
    <link rel="stylesheet" href="css/main.css">
    <!-- Styles CSS spécifiques à cette page -->
    
</head>
<body>
    <!-- Conteneur principal -->
    <div class="dashboard-container">
        <!-- Carte Bootstrap pour encapsuler le contenu -->
        <div class="card">
             <!-- En-tête de la carte -->
             <div class="card-header header">
                <!-- Utilisation de Flexbox pour aligner titre et boutons -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <!-- Section titre et sous-titre -->
                    <div>
                        <h1 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Gestion des Campagnes</h1>
                        <p class="mb-0 small text-white-50">Administration et suivi des campagnes</p>
                    </div>
                    <!-- Groupe de boutons d'actions générales -->
                    <div class="btn-group" role="group">
                        <!-- Bouton "Nouveau" : ouvre la modale d'ajout via les attributs data-bs-* -->
                        <button type="button" id="addCampaignBtn" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#campaignFormModal" title="Ajouter"><i class="fas fa-plus me-1 text-success"></i>Nouveau</button>
                        <!-- Bouton "Générer" : appelle une fonction JS pour ouvrir la modale de génération -->
                        <button type="button" class="btn btn-sm btn-light" onclick="openCampaignGenerationModal()" title="Générer Tests"><i class="fas fa-rocket me-1 text-info"></i>Générer</button>
                        <!-- Bouton "Tout Supprimer" : appelle une fonction JS pour ouvrir la modale de confirmation -->
                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllCampaignsModal()" title="Tout Supprimer!"><i class="fas fa-trash-alt me-1"></i>Tout Suppr.</button>
                    </div>
                </div>
            </div>

            <?php // Affiche les messages flash (s'il y en a) ?>
            <?php Flasher::displayFlash(); ?>

             <!-- Formulaire de filtre -->
             <div class="filter-form mx-3 mt-3">
                <form method="GET" action="index.php">
                    <!-- Champs cachés pour indiquer le contrôleur et l'action -->
                    <input type="hidden" name="controller" value="Campaign">
                    <input type="hidden" name="action" value="list">
                    <!-- Champs cachés pour conserver le tri actuel lors de la soumission du filtre -->
                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                    <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sort_dir) ?>">
                    <!-- Grille Bootstrap pour aligner les champs de filtre -->
                    <div class="row g-2 align-items-end">
                        <!-- Colonne pour le filtre par statut -->
                        <div class="col-md-5">
                            <label for="filtre_statut" class="form-label small">Statut</label>
                            <!-- Liste déroulante pour choisir le statut -->
                            <select class="form-select form-select-sm" id="filtre_statut" name="filtre_statut">
                                <option value="" <?= $filtre_statut === '' ? 'selected' : '' ?>>-- Tous --</option>
                                <option value="1" <?= $filtre_statut === '1' ? 'selected' : '' ?>>Actives</option>
                                <option value="0" <?= $filtre_statut === '0' ? 'selected' : '' ?>>Inactives</option>
                            </select>
                        </div>
                        <!-- Colonne pour les boutons d'action du filtre -->
                        <div class="col-md-7 d-flex gap-2">
                            <!-- Bouton pour soumettre le formulaire de filtre -->
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button>
                            <!-- Bouton pour réinitialiser les filtres et le tri (lien vers l'action 'list' sans paramètres) -->
                            <a href="index.php?controller=Campaign&action=list" class="btn btn-secondary btn-sm flex-grow-1" title="Réinitialiser filtre et tri"><i class="fas fa-times me-1"></i>Reset</a>
                        </div>
                    </div>
                </form>
             </div>

             <!-- Conteneur pour rendre le tableau responsive -->
             <div class="table-responsive">
                <?php // Vérifie s'il y a des campagnes à afficher ?>
                <?php if (!empty($campagnes)): ?>
                    <!-- Tableau Bootstrap pour afficher les campagnes -->
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <?php
                                    // Construit l'URL de base pour les liens de tri, en incluant le filtre actuel.
                                    $baseUrlForSort = "index.php?controller=Campaign&action=list" . (!empty($filtre_statut) ? '&filtre_statut=' . $filtre_statut : '');
                                    // Génère les en-têtes de colonne cliquables pour le tri.
                                    echo SortLinkGenerator::render($baseUrlForSort, 'nom_campagne', 'Nom', $sort_by, $sort_dir);
                                    echo SortLinkGenerator::render($baseUrlForSort, 'date_debut', 'Début', $sort_by, $sort_dir);
                                    echo SortLinkGenerator::render($baseUrlForSort, 'date_fin', 'Fin', $sort_by, $sort_dir);
                                    echo SortLinkGenerator::render($baseUrlForSort, 'description', 'Description', $sort_by, $sort_dir);
                                    echo SortLinkGenerator::render($baseUrlForSort, 'is_active', 'Statut', $sort_by, $sort_dir);
                                ?>
                                <!-- En-tête pour la colonne Actions -->
                                <th class="text-center" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php // Boucle sur chaque campagne pour afficher une ligne ?>
                            <?php foreach ($campagnes as $campagne): ?>
                                <?php // Récupère l'état actuel (booléen) de la campagne ?>
                                <?php $isCurrentlyActive = $campagne['is_active']; ?>
                                <tr>
                                    <!-- Affiche les données de la campagne (protégées avec htmlspecialchars) -->
                                    <td><?= htmlspecialchars($campagne['nom_campagne']) ?></td>
                                    <!-- Formate les dates si elles existent, sinon affiche '-' -->
                                    <td><?= $campagne['date_debut'] ? date('d/m/Y', strtotime($campagne['date_debut'])) : '-' ?></td>
                                    <td><?= $campagne['date_fin'] ? date('d/m/Y', strtotime($campagne['date_fin'])) : '-' ?></td>
                                    <td>
                                        <!-- Affiche la description tronquée avec tooltip pour voir la version complète -->
                                        <span class="table-description" data-bs-toggle="tooltip" title="<?= htmlspecialchars($campagne['description'] ?: 'Aucune description') ?>">
                                            <?= htmlspecialchars($campagne['description'] ?: '-') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <!-- Affiche un badge coloré selon le statut -->
                                        <span class="status-badge <?= $isCurrentlyActive ? 'status-active' : 'status-inactive' ?>">
                                            <?= $isCurrentlyActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <!-- Colonne des boutons d'action spécifiques à la ligne -->
                                    <td class="text-center">
                                        <?php
                                            // Prépare les paramètres pour les URLs d'action (id, page actuelle).
                                            $actionParams = ['controller' => 'Campaign', 'id' => $campagne['campagne_id'], 'page' => $page];
                                            // Ajoute les paramètres de filtre/tri existants à ces URLs.
                                            if (!empty($currentQueryString)) {
                                                 parse_str($currentQueryString, $existingParams);
                                                 $actionParams = array_merge($existingParams, $actionParams);
                                             }
                                            // Construit les URLs pour activer, désactiver et supprimer.
                                            $activateUrl = 'index.php?' . http_build_query(array_merge($actionParams, ['action' => 'activate']));
                                            $deactivateUrl = 'index.php?' . http_build_query(array_merge($actionParams, ['action' => 'deactivate']));
                                            $deleteUrl = 'index.php?' . http_build_query(array_merge($actionParams, ['action' => 'delete']));
                                        ?>
                                        <?php // Affiche le bouton Activer ou Désactiver selon l'état actuel ?>
                                        <?php if ($isCurrentlyActive): ?>
                                            <a href="<?= $deactivateUrl ?>" class="btn btn-warning btn-action" data-bs-toggle="tooltip" title="Désactiver"><i class="fas fa-toggle-off"></i></a>
                                        <?php else: ?>
                                            <a href="<?= $activateUrl ?>" class="btn btn-success btn-action" data-bs-toggle="tooltip" title="Activer"><i class="fas fa-toggle-on"></i></a>
                                        <?php endif; ?>
                                        <!-- Bouton Modifier : ouvre la modale et passe les données via les attributs data-* -->
                                        <button type="button" class="btn btn-primary btn-action edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#campaignFormModal"
                                                data-id="<?= $campagne['campagne_id'] ?>"
                                                data-nom="<?= htmlspecialchars($campagne['nom_campagne']) ?>"
                                                data-debut="<?= htmlspecialchars($campagne['date_debut'] ?? '') ?>"
                                                data-fin="<?= htmlspecialchars($campagne['date_fin'] ?? '') ?>"
                                                data-description="<?= htmlspecialchars($campagne['description'] ?? '') ?>"
                                                data-active="<?= $campagne['is_active'] ? '1' : '0' ?>"
                                                data-bs-toggle="tooltip" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Bouton Supprimer : lien direct avec confirmation JS -->
                                        <a href="<?= $deleteUrl ?>" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer" onclick="return confirm('Supprimer \'<?= htmlspecialchars(addslashes($campagne['nom_campagne']), ENT_QUOTES) ?>\' et TOUTES les données associées ? IRREVERSIBLE !')"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; // Fin de la boucle sur les campagnes ?>
                        </tbody>
                    </table>
                <?php else: // S'il n'y a aucune campagne à afficher ?>
                    <div class="card-body text-center p-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                        <p class="text-muted mb-3">
                            <?php // Message différent si un filtre est appliqué ?>
                            <?php if ($filtre_statut !== ''): ?>
                                Aucune campagne ne correspond au statut sélectionné.
                            <?php else: ?>
                                Aucune campagne vaccinale n'a été trouvée.
                            <?php endif; ?>
                        </p>
                        <?php // Affiche le bouton Reset si un filtre ou un tri est actif ?>
                        <?php if ($filtre_statut !== '' || !empty($currentQueryString)): ?>
                            <a href="index.php?controller=Campaign&action=list" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser filtre et tri</a><br>
                        <?php endif; ?>
                         <!-- Bouton pour ouvrir la modale d'ajout directement depuis ce message "vide" -->
                         <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#campaignFormModal" id="addCampaignBtnDirect">
                            <i class="fas fa-plus me-1"></i>Créer une campagne
                        </button>
                    </div>
                <?php endif; // Fin de la condition !empty($campagnes) ?>
            </div>

            <?php // Affiche la pagination si nécessaire (plus d'une page) ?>
            <?php if (!empty($campagnes) && $totalPages > 1): ?>
                <div class="card-footer">
                   <?= $paginationHtml // Affiche le HTML généré par Paginator::render() ?>
                </div>
            <?php endif; ?>
        </div> <!-- Fin de la carte principale -->

        <!-- Lien de retour vers le panneau d'administration général -->
        <div class="text-center my-4">
            <a href="index.php?controller=Admin&action=index" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour Admin</a>
        </div>
    </div> <!-- Fin du conteneur principal -->

    <!-- Modale Bootstrap pour le formulaire d'ajout/modification -->
    <div class="modal fade" id="campaignFormModal" tabindex="-1" aria-labelledby="campaignFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> <!-- modal-lg pour une modale plus large -->
            <div class="modal-content">
                <!-- Le formulaire à l'intérieur de la modale. Il pointe vers la même action 'save' que le formulaire inline (qui n'est plus utilisé). -->
                <form method="post" action="index.php?controller=Campaign&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" id="modalCampaignForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="campaignFormModalLabel">Ajouter une Campagne</h5> <!-- Titre initial, sera modifié par JS si édition -->
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Champs cachés pour l'ID (en modification) et la page (pour redirection) -->
                        <input type="hidden" name="campagne_id" id="modal_campagne_id" value="">
                        <input type="hidden" name="page" id="modal_page" value="<?= htmlspecialchars($page) ?>">

                         <!-- Champs du formulaire (similaires à form.php, mais avec préfixe 'modal_' pour les ID) -->
                         <div class="row g-3 mb-3">
                             <div class="col-md-8">
                                 <label for="modal_nom_campagne" class="form-label required-field">Nom</label>
                                 <input type="text" class="form-control" id="modal_nom_campagne" name="nom_campagne" required maxlength="255">
                             </div>
                             <div class="col-md-4 d-flex align-items-end pb-1">
                                 <div class="form-check form-switch">
                                     <input class="form-check-input" type="checkbox" role="switch" id="modal_is_active" name="is_active" value="1" checked> <!-- Cochée par défaut -->
                                     <label class="form-check-label" for="modal_is_active">Active</label>
                                 </div>
                             </div>
                         </div>
                         <div class="row g-3 mb-3">
                             <div class="col-md-6">
                                 <label for="modal_date_debut" class="form-label">Début <small class="text-muted">(Opt.)</small></label>
                                 <input type="date" class="form-control" id="modal_date_debut" name="date_debut">
                             </div>
                             <div class="col-md-6">
                                 <label for="modal_date_fin" class="form-label">Fin <small class="text-muted">(Opt.)</small></label>
                                 <input type="date" class="form-control" id="modal_date_fin" name="date_fin">
                             </div>
                         </div>
                         <div class="mb-3">
                             <label for="modal_description" class="form-label">Description <small class="text-muted">(Opt.)</small></label>
                             <textarea class="form-control" id="modal_description" name="description" rows="3"></textarea>
                         </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Annuler</button>
                        <button type="submit" class="btn btn-primary" id="modalSubmitButton"><i class="fas fa-save me-1"></i>Enregistrer</button> <!-- Texte initial, modifié par JS -->
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modale pour la génération de données de test -->
    <div class="modal fade" id="campaignGenerationModal" tabindex="-1" aria-labelledby="campaignGenerationModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-sm"> <!-- Petite modale -->
            <div class="modal-content">
                <form id="generateCampaignsForm" action="index.php" method="get">
                     <input type="hidden" name="controller" value="Campaign">
                    <input type="hidden" name="action" value="generate">
                    <div class="modal-header">
                        <h6 class="modal-title" id="campaignGenerationModalLabel"><i class="fas fa-rocket me-2 text-info"></i>Générer Campagnes</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombreCampagnes" class="form-label">Nombre (1-50):</label>
                            <input type="number" class="form-control form-control-sm" id="nombreCampagnes" name="nombre" value="3" min="1" max="50" required>
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

    <!-- Modale de confirmation pour la suppression globale -->
    <div class="modal fade" id="deleteAllCampaignsModal" tabindex="-1" aria-labelledby="deleteAllCampaignsModalLabel" aria-hidden="true">
         <div class="modal-dialog">
            <div class="modal-content">
                <form id="deleteAllCampaignsForm" action="index.php" method="GET">
                    <input type="hidden" name="controller" value="Campaign">
                    <input type="hidden" name="action" value="deleteAll">
                    <div class="modal-header bg-danger text-white"> <!-- En-tête rouge pour danger -->
                        <h5 class="modal-title" id="deleteAllCampaignsModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                         <p><strong class="text-danger">Attention : Action Irréversible !</strong></p>
                         <p>Suppression définitive de :</p>
                         <!-- Liste des éléments qui seront supprimés -->
                         <ul class="list-unstyled mb-3 small">
                            <li><i class="fas fa-calendar-alt text-danger me-2 fa-fw"></i><strong>TOUTES</strong> les campagnes.</li>
                            <li><i class="fas fa-syringe text-danger me-2 fa-fw"></i><strong>TOUS</strong> les vaccins associés.</li>
                            <li><i class="fas fa-tags text-danger me-2 fa-fw"></i><strong>TOUTES</strong> les associations vaccin-type.</li>
                            <li><i class="fas fa-boxes text-danger me-2 fa-fw"></i><strong>TOUS</strong> les lots associés.</li>
                            <li><i class="fas fa-file-medical text-danger me-2 fa-fw"></i><strong>TOUS</strong> les enregistrements de vaccination.</li>
                        </ul>
                        <!-- Checkbox de confirmation obligatoire -->
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="" id="confirmDeleteCheckbox" required>
                            <label class="form-check-label" for="confirmDeleteCheckbox">Je confirme vouloir tout supprimer.</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <!-- Bouton de suppression désactivé par défaut, activé par JS quand la case est cochée -->
                        <button type="submit" class="btn btn-danger" id="confirmDeleteAllBtn" disabled><i class="fas fa-trash-alt me-1"></i>Supprimer Tout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Inclusion de Bootstrap JS (Bundle inclut Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script JavaScript spécifique à cette page -->
    <script>
        // Fonction pour ouvrir la modale de génération (appelée par le bouton 'Générer')
        function openCampaignGenerationModal() { var m=document.getElementById('campaignGenerationModal'); if(m){new bootstrap.Modal(m).show();} }
        // Initialisation et gestion de la modale de suppression globale
        const deleteAllModalEl = document.getElementById('deleteAllCampaignsModal');
        if (deleteAllModalEl) {
            const checkbox = deleteAllModalEl.querySelector('#confirmDeleteCheckbox');
            const submitBtn = deleteAllModalEl.querySelector('#confirmDeleteAllBtn');
            const modalInstance = new bootstrap.Modal(deleteAllModalEl);
            // Fonction pour ouvrir la modale (appelée par le bouton 'Tout Suppr.')
            window.openDeleteAllCampaignsModal=function(){
                checkbox.checked=false; // Décoche la case
                submitBtn.disabled=true; // Désactive le bouton
                modalInstance.show(); // Affiche la modale
            };
            // Active/désactive le bouton quand la case change d'état
            checkbox.addEventListener('change', function(){ submitBtn.disabled = !this.checked; });
        }

        // S'exécute quand le DOM est entièrement chargé
        document.addEventListener('DOMContentLoaded', function () {
            // Initialise tous les tooltips Bootstrap présents sur la page
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                 // Vérifie si un tooltip n'est pas déjà initialisé pour cet élément
                 if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                     // Crée un nouveau tooltip avec un délai et autorisant le HTML
                     return new bootstrap.Tooltip(tooltipTriggerEl, { delay: { "show": 300, "hide": 100 }, html: true });
                 }
                 // Sinon, retourne l'instance existante (bien que non utilisé ici)
             });

            // ---- Gestion de la modale d'ajout/modification ----
            // Récupération des éléments DOM de la modale
            const campaignModal = document.getElementById('campaignFormModal');
            const modalForm = document.getElementById('modalCampaignForm');
            const modalTitle = document.getElementById('campaignFormModalLabel');
            const modalCampagneId = document.getElementById('modal_campagne_id');
            const modalNomCampagne = document.getElementById('modal_nom_campagne');
            const modalDateDebut = document.getElementById('modal_date_debut');
            const modalDateFin = document.getElementById('modal_date_fin');
            const modalDescription = document.getElementById('modal_description');
            const modalIsActive = document.getElementById('modal_is_active');
            const modalPageInput = document.getElementById('modal_page');
            const modalSubmitButton = document.getElementById('modalSubmitButton');

            // Fonction pour préparer la modale en mode "Ajout"
            function prepareModalForAdd() {
                modalForm.reset(); // Réinitialise tous les champs du formulaire
                modalTitle.textContent = 'Ajouter une Campagne'; // Définit le titre
                modalCampagneId.value = ''; // Assure que l'ID caché est vide
                modalIsActive.checked = true; // Coche "Active" par défaut
                modalSubmitButton.textContent = 'Ajouter'; // Change le texte du bouton
                 // Met à jour le champ caché 'page' avec la page actuelle de la liste
                 modalPageInput.value = <?= json_encode($page) ?>;
            }

             // Fonction pour préparer la modale en mode "Modification"
             function prepareModalForEdit(button) {
                 modalForm.reset(); // Réinitialise les champs
                 const data = button.dataset; // Récupère les données depuis les attributs data-* du bouton cliqué
                 modalTitle.textContent = 'Modifier : ' + data.nom; // Définit le titre avec le nom actuel
                 // Pré-remplit les champs avec les données de la campagne
                 modalCampagneId.value = data.id;
                 modalNomCampagne.value = data.nom;
                 modalDateDebut.value = data.debut;
                 modalDateFin.value = data.fin;
                 modalDescription.value = data.description;
                 modalIsActive.checked = (data.active === '1'); // Coche si la data 'active' est '1'
                 modalSubmitButton.textContent = 'Enregistrer'; // Change le texte du bouton
                  // Met à jour le champ caché 'page'
                  modalPageInput.value = <?= json_encode($page) ?>;
             }

            // Attache l'événement 'click' au bouton "Nouveau" principal pour ouvrir la modale en mode ajout
            const addBtn = document.getElementById('addCampaignBtn');
            if (addBtn) {
                addBtn.addEventListener('click', prepareModalForAdd);
            }
             // Attache l'événement au bouton "Créer une campagne" (affiché si le tableau est vide)
             const addBtnDirect = document.getElementById('addCampaignBtnDirect');
             if (addBtnDirect) {
                 addBtnDirect.addEventListener('click', prepareModalForAdd);
             }

            // Utilise la délégation d'événement sur le corps du tableau pour gérer les clics sur les boutons "Modifier"
             const tableBody = document.querySelector('.table tbody');
             if (tableBody) {
                 tableBody.addEventListener('click', function(event) {
                     // Trouve le bouton .edit-btn le plus proche de l'élément cliqué (si c'est le bouton ou son icône)
                     const editButton = event.target.closest('.edit-btn');
                     // Si un bouton .edit-btn a été trouvé
                     if (editButton) {
                         // Prépare la modale pour la modification avec les données de ce bouton
                         prepareModalForEdit(editButton);
                     }
                 });
             }

             // Optionnel : Réinitialise le formulaire quand la modale est complètement fermée
             campaignModal.addEventListener('hidden.bs.modal', function () {
                 modalForm.reset();
                 modalCampagneId.value = ''; // Assure que l'ID est vidé
             });

        }); // Fin de DOMContentLoaded
    </script>
</body>
</html>