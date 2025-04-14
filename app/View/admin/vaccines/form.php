<!-- Ce fichier représente le contenu de la modale d'ajout/modification de vaccin -->
<!-- Il est inclus dans la vue principale list.php du VaccineController -->

<div class="card-body"> <!-- Enveloppe standard, souvent dans un <div class="modal-body"> -->
    <!-- Titre dynamique : Ajouter ou Modifier -->
    <h3 class="mb-4 border-bottom pb-3 fs-5">
        <?= ($action == 'ajouter') /* Variable $action fournie par le contrôleur */
            ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter un vaccin' /* Titre si ajout */
            : '<i class="fas fa-edit me-2 text-primary"></i>Modifier le vaccin : '.htmlspecialchars($vaccin_edit['nom_vaccin'] ?? '') /* Titre si modif */
        ?>
    </h3>

    <?php // Condition préalable : Vérifie s'il existe des campagnes actives ET des types de vaccins disponibles. ?>
    <?php // Si l'un ou l'autre manque, on ne peut pas créer/modifier de vaccin. ?>
    <?php if (empty($available_campaigns) || empty($available_types)): ?>
        <!-- Affiche un message d'alerte expliquant pourquoi le formulaire n'est pas affiché -->
        <div class="alert alert-warning">
            Impossible d'ajouter/modifier un vaccin :
            <ul class="mb-0">
                <?php if (empty($available_campaigns)): ?>
                    <li>Aucune campagne active trouvée. <a href="index.php?controller=Campaign&action=list">Gérer les campagnes</a>.</li>
                <?php endif; ?>
                <?php if (empty($available_types)): ?>
                    <li>Aucun type de vaccin trouvé. <a href="index.php?controller=VaccineType&action=list">Gérer les types</a>.</li>
                <?php endif; ?>
            </ul>
             <!-- Bouton pour retourner à la liste des vaccins -->
             <div class="mt-3">
                 <a href="index.php?controller=Vaccine&action=list<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Retour</a>
             </div>
        </div>
    <?php else: // Si les conditions sont remplies, affiche le formulaire ?>
        <!-- Formulaire d'ajout/modification de vaccin -->
        <!-- method="post", action pointe vers VaccineController::save -->
        <!-- L'URL inclut la query string (filtre/tri) pour la redirection -->
        <form method="post" action="index.php?controller=Vaccine&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>">
            <?php // Si modification, ajoute le champ caché pour l'ID du vaccin ?>
            <?php if ($action == 'modifier'): ?>
                <input type="hidden" name="vaccin_id" value="<?= htmlspecialchars($vaccin_edit['vaccin_id']) ?>">
            <?php endif; ?>
            <!-- Champ caché pour la page actuelle (pour redirection) -->
            <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

            <!-- Champ Nom du Vaccin -->
            <div class="mb-3">
                <label for="nom_vaccin" class="form-label required-field">Nom du Vaccin</label>
                <!-- Pré-rempli si modification. Requis. -->
                <input type="text" class="form-control" id="nom_vaccin" name="nom_vaccin" value="<?= htmlspecialchars($vaccin_edit['nom_vaccin'] ?? '') ?>" required maxlength="255">
            </div>

            <!-- Ligne pour Campagne et Type(s) -->
            <div class="row g-3 mb-3">
                <!-- Colonne Campagne Associée -->
                <div class="col-md-6">
                    <label for="campagne_id" class="form-label required-field">Campagne Associée</label>
                    <!-- Liste déroulante des campagnes actives -->
                    <select class="form-select" id="campagne_id" name="campagne_id" required>
                        <option value="">-- Sélectionner une campagne active --</option>
                        <?php // Boucle sur les campagnes actives disponibles ($available_campaigns vient du contrôleur) ?>
                        <?php foreach ($available_campaigns as $camp): ?>
                            <!-- Option sélectionnée si elle correspond à la campagne du vaccin en cours de modification -->
                            <option value="<?= $camp['campagne_id'] ?>" <?= (isset($vaccin_edit['campagne_id']) && $vaccin_edit['campagne_id'] == $camp['campagne_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($camp['nom_campagne']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Seules les campagnes actives sont listées.</div> <!-- Aide textuelle -->
                </div>
                <!-- Colonne Type(s) de Vaccin -->
                <div class="col-md-6">
                    <label class="form-label required-field">Type(s) de Vaccin</label>
                    <!-- Conteneur pour les cases à cocher des types -->
                    <!-- 'type-checkbox-group' peut être utilisé pour styler (ex: scroll si trop long) -->
                    <div class="border p-3 rounded type-checkbox-group">
                        <?php // Vérifie s'il y a des types disponibles ?>
                        <?php if (empty($available_types)): ?>
                            <span class="text-muted">Aucun type disponible.</span>
                        <?php else: ?>
                            <?php // Boucle sur les types disponibles ($available_types vient du contrôleur) ?>
                            <?php foreach ($available_types as $type): ?>
                                <div class="form-check">
                                    <!-- Case à cocher pour chaque type -->
                                    <!-- name="type_ids[]" : important -> envoie un tableau des IDs cochés au serveur -->
                                    <!-- id unique pour chaque checkbox -->
                                    <!-- checked : condition complexe pour cocher si on est en mode modification ET que $associated_type_ids (fourni par le contrôleur) est un tableau ET que l'ID actuel est dans ce tableau -->
                                    <input class="form-check-input" type="checkbox" name="type_ids[]" id="type_<?= $type['type_vaccin_id'] ?>" value="<?= $type['type_vaccin_id'] ?>"
                                           <?= ($action == 'modifier' && isset($associated_type_ids) && is_array($associated_type_ids) && in_array($type['type_vaccin_id'], $associated_type_ids)) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="type_<?= $type['type_vaccin_id'] ?>">
                                        <?= htmlspecialchars($type['nom_type']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-text">Cochez un ou plusieurs types.</div> <!-- Aide textuelle -->
                </div>
            </div>

            <!-- Champ Description -->
            <div class="mb-4">
                <label for="description" class="form-label">Description <small class="text-muted">(Opt.)</small></label>
                <!-- Zone de texte multiligne, pré-remplie si modification -->
                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($vaccin_edit['description'] ?? '') ?></textarea>
            </div>

            <!-- Section des boutons d'action du formulaire -->
            <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
                <!-- Bouton Annuler : lien vers la liste (conserve page et filtres/tris) -->
                <a href="index.php?controller=Vaccine&action=list&page=<?= htmlspecialchars($page) ?><?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
                <!-- Bouton de soumission -->
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>
                    <?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?> <!-- Texte dynamique -->
                </button>
            </div>
        </form> <!-- Fin du formulaire -->
     <?php endif; // Fin du else (formulaire affiché) ?>
</div> <!-- Fin du card-body / modal-body -->