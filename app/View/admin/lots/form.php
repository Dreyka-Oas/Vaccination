<!-- Ce fichier représente le contenu de la modale d'ajout/modification de lot -->
<!-- Il est inclus dans la vue principale list.php -->

<div class="card-body"> <!-- Généralement, ce contenu est dans un <div class="modal-body"> -->
    <!-- Titre dynamique : Ajouter ou Modifier -->
    <h3 class="mb-4 border-bottom pb-3 fs-5">
        <?= ($action == 'ajouter') /* Vérifie la variable $action passée par le contrôleur */
            ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter un lot' /* Titre si ajout */
            : '<i class="fas fa-edit me-2 text-primary"></i>Modifier: '.htmlspecialchars($lot_edit['nom_lot'] ?? '') /* Titre si modif */
        ?>
    </h3>

    <?php // Condition spéciale : Si on est en mode ajout ET qu'il n'y a aucune campagne active disponible ?>
    <?php if (empty($available_active_campaigns) && $action == 'ajouter'): ?>
        <!-- Affiche un message d'alerte indiquant qu'on ne peut pas ajouter de lot -->
        <div class="alert alert-warning">
            Impossible d'ajouter un lot : Aucune campagne active trouvée.
            <a href="index.php?controller=Campaign&action=list">Gérer les campagnes</a>. <!-- Lien vers la gestion des campagnes -->
            <!-- Lien pour retourner à la liste des lots (conserve les filtres/tris) -->
            <a href="index.php?controller=Lot&action=list<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" class="btn btn-secondary btn-sm ms-2">Retour</a>
        </div>
    <?php else: // Sinon (soit on modifie, soit il y a des campagnes actives pour l'ajout) ?>
        <!-- Formulaire d'ajout/modification de lot -->
        <form method="post" action="index.php?controller=Lot&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" id="lotForm">
            <?php // Si on est en mode modification, ajoute un champ caché pour l'ID du lot ?>
            <?php if ($action == 'modifier'): ?>
                <input type="hidden" name="lot_id" value="<?= htmlspecialchars($lot_edit['lot_id']) ?>">
            <?php endif; ?>
            <!-- Champ caché pour conserver la page actuelle pour la redirection -->
            <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

            <!-- Ligne Bootstrap pour Nom du lot et Date d'expiration -->
            <div class="row g-3 mb-3">
                <!-- Colonne Nom du lot -->
                <div class="col-md-6">
                    <label for="nom_lot" class="form-label required-field">Nom / N° Lot</label>
                    <!-- Champ texte pour le nom/numéro du lot -->
                    <input type="text" class="form-control" id="nom_lot" name="nom_lot" value="<?= htmlspecialchars($lot_edit['nom_lot'] ?? '') ?>" required maxlength="255">
                    <div class="form-text">Identifiant unique.</div> <!-- Aide textuelle -->
                </div>
                <!-- Colonne Date d'expiration -->
                <div class="col-md-6">
                    <label for="date_expiration" class="form-label required-field">Expiration</label>
                    <!-- Champ date pour l'expiration -->
                    <!-- min="<?= date('Y-m-d') ?>" : Empêche de sélectionner une date passée (validation côté client) -->
                    <input type="date" class="form-control" id="date_expiration" name="date_expiration" value="<?= htmlspecialchars($lot_edit['date_expiration'] ?? '') ?>" required min="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <!-- Ligne Bootstrap pour Campagne et Vaccin -->
            <div class="row g-3 mb-4">
                <!-- Colonne Campagne -->
                 <div class="col-md-6">
                     <label for="campagne_id" class="form-label required-field">Campagne (Active)</label>
                     <!-- Liste déroulante pour sélectionner la campagne -->
                     <select class="form-select" id="campagne_id" name="campagne_id" required>
                         <option value="">-- Sélectionner --</option>
                         <?php // Boucle sur les campagnes actives disponibles (passées par le contrôleur) ?>
                         <?php foreach ($available_active_campaigns as $camp): ?>
                             <!-- Option pour chaque campagne -->
                             <!-- L'attribut 'selected' est ajouté si l'ID de la campagne correspond à celui du lot en cours de modification -->
                             <option value="<?= $camp['campagne_id'] ?>" <?= (isset($lot_edit['campagne_id']) && $lot_edit['campagne_id'] == $camp['campagne_id']) ? 'selected' : '' ?>>
                                 <?= htmlspecialchars($camp['nom_campagne']) ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
                     <div class="form-text">Seules les campagnes actives.</div> <!-- Aide textuelle -->
                 </div>
                 <!-- Colonne Vaccin -->
                 <div class="col-md-6">
                     <label for="vaccin_id" class="form-label required-field">Vaccin</label>
                     <!-- Liste déroulante pour sélectionner le vaccin -->
                     <!-- Elle est 'disabled' par défaut et sera remplie par JavaScript (AJAX) -->
                     <select class="form-select" id="vaccin_id" name="vaccin_id" required disabled>
                         <option value="">-- Choisir campagne d'abord --</option>
                     </select>
                     <div class="form-text">Doit appartenir à la campagne.</div> <!-- Aide textuelle -->
                     <!-- Indicateurs visuels pour le chargement AJAX (gérés par JS) -->
                     <div id="vaccin-loading" class="text-muted small mt-1" style="display: none;"><i class="fas fa-spinner fa-spin me-1"></i>Chargement...</div>
                     <div id="vaccin-error" class="text-danger small mt-1" style="display: none;"><i class="fas fa-exclamation-triangle me-1"></i>Erreur.</div>
                 </div>
            </div>

            <!-- Section des boutons d'action -->
            <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
                <!-- Bouton Annuler : lien vers la liste des lots (conserve page et filtres/tris) -->
                <a href="index.php?controller=Lot&action=list&page=<?= htmlspecialchars($page) ?><?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
                <!-- Bouton de soumission du formulaire -->
                <!-- Il est désactivé ('disabled') par défaut et sera activé par JS une fois les sélections valides -->
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-save me-1"></i>
                    <?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?> <!-- Texte dynamique -->
                </button>
            </div>
        </form> <!-- Fin du formulaire -->

         <!-- Script JavaScript pour gérer la liste déroulante dépendante (Vaccin en fonction de Campagne) -->
         <script>
             // Attend que le DOM soit complètement chargé
             document.addEventListener('DOMContentLoaded', function() {
                 // Récupère les éléments DOM nécessaires
                 const campagneSelect = document.getElementById('campagne_id');
                 const vaccinSelect = document.getElementById('vaccin_id');
                 const vaccinLoading = document.getElementById('vaccin-loading');
                 const vaccinError = document.getElementById('vaccin-error');
                 const submitBtn = document.getElementById('submitBtn');
                 const lotForm = document.getElementById('lotForm');
                 // Récupère les données du lot en cours de modification (si applicable), encodées en JSON par PHP
                 const lotEditData = <?php echo ($action == 'modifier' && isset($lot_edit)) ? json_encode($lot_edit) : 'null'; ?>;
                 // URL pour l'appel AJAX qui récupère les vaccins
                 const ajaxVaccinUrl = 'index.php?controller=Lot&action=getVaccinsAjax';

                 /**
                  * Fonction asynchrone pour charger les vaccins associés à une campagne via AJAX.
                  * @param {string|null} vaccinToSelect - L'ID du vaccin à présélectionner (en mode modification).
                  */
                 async function loadVaccinsForCampagne(vaccinToSelect = null) {
                     const selectedCampagneId = campagneSelect.value; // Récupère l'ID de la campagne sélectionnée

                     // Réinitialise la liste des vaccins et son état
                     vaccinSelect.innerHTML = '<option value="">-- Sélectionner --</option>'; // Option par défaut
                     vaccinSelect.disabled = true; // Désactive la liste
                     vaccinLoading.style.display = 'none'; // Cache l'indicateur de chargement
                     vaccinError.style.display = 'none'; // Cache l'indicateur d'erreur
                     if (submitBtn) submitBtn.disabled = true; // Désactive le bouton de soumission

                     // Si aucune campagne n'est sélectionnée, affiche un message et sort
                     if (!selectedCampagneId) {
                         vaccinSelect.innerHTML = '<option value="">-- Choisir campagne d\'abord --</option>';
                         if (submitBtn) submitBtn.disabled = false; // Réactive le bouton (sera bloqué par validation HTML5 si besoin)
                         return;
                     }

                     // Affiche l'indicateur de chargement
                     vaccinLoading.style.display = 'block';

                     try {
                         // Effectue l'appel AJAX vers l'URL définie
                         const response = await fetch(`${ajaxVaccinUrl}&campagne_id=${selectedCampagneId}`);
                         // Vérifie si la réponse HTTP est OK (status 2xx)
                         if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                         // Parse la réponse JSON
                         const vaccins = await response.json();
                         // Cache l'indicateur de chargement
                         vaccinLoading.style.display = 'none';

                         // Si des vaccins ont été trouvés
                         if (vaccins.length > 0) {
                              // Remplit la liste déroulante avec les vaccins reçus
                              vaccinSelect.innerHTML = '<option value="">-- Sélectionner un vaccin --</option>'; // Option initiale
                             vaccins.forEach(vaccin => {
                                 const optionElement = document.createElement('option');
                                 optionElement.value = vaccin.vaccin_id;
                                 optionElement.textContent = vaccin.nom_vaccin;
                                 vaccinSelect.appendChild(optionElement);
                             });
                             // Active la liste déroulante et le bouton de soumission
                             vaccinSelect.disabled = false;
                             if (submitBtn) submitBtn.disabled = false;

                             // Si un vaccin doit être présélectionné (mode modification)
                             if (vaccinToSelect) {
                                 vaccinSelect.value = vaccinToSelect; // Tente de sélectionner l'ID
                                 // Avertissement si la sélection échoue (vaccin plus valide pour cette campagne ?)
                                 if (vaccinSelect.value !== vaccinToSelect.toString()) {
                                     console.warn(`Vaccin ID ${vaccinToSelect} non trouvé pour campagne ${selectedCampagneId}.`);
                                     // Optionnel : afficher un message à l'utilisateur ici
                                 }
                             }
                         } else { // Si aucun vaccin n'a été trouvé pour cette campagne
                             vaccinSelect.innerHTML = '<option value="">-- Aucun vaccin trouvé --</option>';
                             if (submitBtn) submitBtn.disabled = true; // Garde le bouton désactivé
                         }
                     } catch (error) { // En cas d'erreur AJAX ou de parsing JSON
                         console.error('Erreur chargement vaccins:', error);
                         vaccinLoading.style.display = 'none'; // Cache chargement
                         vaccinError.style.display = 'block'; // Affiche erreur
                         vaccinSelect.innerHTML = '<option value="">-- Erreur --</option>'; // Option d'erreur
                         if (submitBtn) submitBtn.disabled = true; // Garde bouton désactivé
                     }
                 }

                 // S'assure que les éléments select existent
                 if (campagneSelect && vaccinSelect) {
                     // Ajoute un écouteur d'événement: quand la campagne change, recharge les vaccins
                     campagneSelect.addEventListener('change', () => { loadVaccinsForCampagne(); });

                     // Si on est en mode modification et qu'un lot est défini
                     if (lotEditData && lotEditData.campagne_id) {
                         // Utilise setTimeout pour s'assurer que le DOM est prêt et que la valeur est bien définie avant de charger
                         // Initialise la sélection de campagne et charge les vaccins avec le vaccin présélectionné
                         setTimeout(() => {
                              campagneSelect.value = lotEditData.campagne_id;
                              loadVaccinsForCampagne(lotEditData.vaccin_id);
                         }, 0); // Délai 0 pour exécuter après le thread actuel
                     } else {
                         // Si ajout, charge simplement les vaccins pour la campagne éventuellement sélectionnée par défaut
                         loadVaccinsForCampagne();
                     }
                 }

                 // Ajoute une validation simple côté client à la soumission
                 if (lotForm) {
                     lotForm.addEventListener('submit', function(event) {
                         // Si une campagne est sélectionnée mais pas de vaccin
                         if (campagneSelect.value && !vaccinSelect.value) {
                             alert("Veuillez sélectionner un vaccin.");
                             event.preventDefault(); // Empêche la soumission
                             vaccinSelect.focus(); // Met le focus sur le select vaccin
                         }
                         // Si la liste de vaccins est désactivée (erreur ou aucun vaccin) mais qu'une campagne est sélectionnée
                         else if (vaccinSelect.disabled && campagneSelect.value) {
                             alert("Impossible de soumettre : Aucun vaccin valide trouvé pour cette campagne ou une erreur est survenue.");
                             event.preventDefault(); // Empêche la soumission
                         }
                     });
                 }
             }); // Fin de DOMContentLoaded
         </script>
    <?php endif; // Fin du else (formulaire affiché) ?>
</div> <!-- Fin du card-body -->