<!-- Ce fichier représente le contenu de la modale d'ajout/modification de type de vaccin -->
<!-- Il est inclus dans la vue principale list.php du VaccineTypeController -->

<div class="card-body"> <!-- Enveloppe standard, souvent dans un <div class="modal-body"> -->
    <!-- Titre dynamique : change selon si on ajoute ou modifie -->
    <h3 class="mb-4 border-bottom pb-3 fs-5">
        <?= ($action == 'ajouter') /* Variable $action fournie par le contrôleur */
            ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter un type' /* Titre si ajout */
            : '<i class="fas fa-edit me-2 text-primary"></i>Modifier le type : ' . htmlspecialchars($type_vaccin_edit['nom_type'] ?? '') /* Titre si modif */
        ?>
    </h3>

    <!-- Formulaire HTML pour ajouter ou modifier un type de vaccin -->
    <!-- method="post", action pointe vers VaccineTypeController::save -->
    <!-- L'URL inclut la query string actuelle ($currentQueryString, contenant le tri) pour redirection -->
    <form method="post" action="index.php?controller=VaccineType&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>">

        <?php // Si on est en mode modification... ?>
        <?php if ($action == 'modifier'): ?>
            <!-- ...ajoute un champ caché pour l'ID du type à modifier. -->
            <!-- Permet au contrôleur de savoir quelle ligne mettre à jour. -->
            <input type="hidden" name="type_vaccin_id" value="<?= htmlspecialchars($type_vaccin_edit['type_vaccin_id']) ?>">
        <?php endif; ?>

        <!-- Champ caché pour la page actuelle (pour redirection vers la même page de la liste) -->
        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

        <!-- Champ pour le Nom du type -->
        <div class="mb-3">
            <!-- Label avec classe 'required-field' pour l'astérisque visuel -->
            <label for="nom_type" class="form-label required-field">Nom du type</label>
            <!-- Champ texte pour le nom. -->
            <!-- value="..." pré-remplit avec la valeur existante si modification. -->
            <!-- required pour validation HTML5 côté client. -->
            <input type="text" class="form-control" id="nom_type" name="nom_type" value="<?= htmlspecialchars($type_vaccin_edit['nom_type'] ?? '') ?>" required maxlength="255">
        </div>

        <!-- Champ pour la Description -->
        <div class="mb-4">
            <!-- Label indiquant que c'est optionnel -->
            <label for="description" class="form-label">Description <small class="text-muted">(Optionnel)</small></label>
            <!-- Zone de texte multiligne. -->
            <!-- Le contenu est pré-rempli si modification. -->
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($type_vaccin_edit['description'] ?? '') ?></textarea>
        </div>

        <!-- Section des boutons d'action du formulaire -->
        <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
            <!-- Bouton Annuler : lien qui retourne à la liste (page actuelle, tri conservé) -->
            <a href="index.php?controller=VaccineType&action=list&page=<?= htmlspecialchars($page) ?><?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
            <!-- Bouton de soumission du formulaire -->
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>
                <?= ($action == 'ajouter') ? 'Ajouter le type' : 'Enregistrer' ?> <!-- Texte dynamique -->
            </button>
        </div>
    </form> <!-- Fin du formulaire -->
</div> <!-- Fin du card-body / modal-body -->