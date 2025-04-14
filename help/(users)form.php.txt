<!-- Ce fichier représente le corps du formulaire d'ajout/modification utilisateur, -->
<!-- destiné à être affiché dans une modale Bootstrap. -->
<!-- Les variables $action, $user_edit, $roles_list, $page, $currentQueryString -->
<!-- sont fournies par le UserController lors de l'appel à la vue list.php. -->

<div class="card-body"> <!-- Enveloppe pour le contenu, typiquement dans un <div class="modal-body"> -->
    <!-- Titre dynamique (Ajouter ou Modifier) -->
    <h3 class="mb-4 border-bottom pb-3 fs-5">
        <?= ($action == 'ajouter')
            ? '<i class="fas fa-user-plus me-2 text-success"></i>Ajouter utilisateur' /* Titre Ajout */
            : '<i class="fas fa-user-edit me-2 text-primary"></i>Modifier : ' . htmlspecialchars($user_edit['username'] ?? '') /* Titre Modif */
        ?>
    </h3>
    <!-- Formulaire HTML -->
    <!-- method="post", action pointe vers UserController::save -->
    <!-- L'URL inclut la query string actuelle pour conserver les filtres/page lors de la redirection. -->
    <!-- L'ID du formulaire est important pour le ciblage par JavaScript. -->
    <form method="post" action="index.php?controller=User&action=save<?= (!empty($currentQueryString) ? '&' . $currentQueryString : '') ?>" id="userFormModal">

        <?php // Champ caché pour stocker le matricule original en mode modification ?>
        <?php if ($action == 'modifier'): ?>
            <!-- Ce champ est crucial pour que le contrôleur sache quel utilisateur mettre à jour -->
            <!-- et pour détecter si le matricule a été modifié. -->
            <input type="hidden" name="original_matricule" id="modal_original_matricule" value="<?= htmlspecialchars($user_edit['matricule']) ?>">
        <?php else: // En mode ajout, le champ existe mais est vide. ?>
            <input type="hidden" name="original_matricule" id="modal_original_matricule" value="">
        <?php endif; ?>
        <!-- Champ caché pour la page actuelle (pour redirection) -->
        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

        <!-- Ligne pour Matricule et Nom d'utilisateur -->
        <div class="row">
            <!-- Colonne Matricule -->
            <div class="col-md-6 mb-3">
                <label for="modal_matricule" class="form-label required-field">Matricule</label>
                <!-- Champ Matricule. Pré-rempli en mode modification. -->
                <input type="text" class="form-control" id="modal_matricule" name="matricule" value="<?= htmlspecialchars($user_edit['matricule'] ?? '') ?>" required maxlength="50">
                <!-- Aide textuelle dynamique (gérée aussi par JS) -->
                <small class="text-muted" id="matriculeHelp">
                    <?= ($action == 'modifier') ? 'Peut être modifié (Attention Risqué !)' : "Identifiant unique." ?>
                </small>
            </div>
            <!-- Colonne Nom d'utilisateur -->
            <div class="col-md-6 mb-3">
                <label for="modal_username" class="form-label required-field">Nom d'utilisateur</label>
                <!-- Champ Nom d'utilisateur. Pré-rempli en mode modification. -->
                <input type="text" class="form-control" id="modal_username" name="username" value="<?= htmlspecialchars($user_edit['username'] ?? '') ?>" required maxlength="50">
            </div>
        </div>

        <!-- Section Avertissement/Confirmation pour la modification du matricule -->
        <!-- Ce bloc est caché par défaut et affiché par JavaScript si le matricule est modifié. -->
        <div class="mb-3 alert alert-warning p-2" id="confirmMatriculeChangeGroup">
             <div class="form-check">
                <!-- Checkbox pour confirmer explicitement la modification du matricule. -->
                <!-- Rendue 'required' par JavaScript si le matricule change. -->
                <input class="form-check-input" type="checkbox" value="1" id="confirm_matricule_change" name="confirm_matricule_change">
                <label class="form-check-label small" for="confirm_matricule_change">
                    <i class="fas fa-exclamation-triangle text-danger me-1"></i> Je confirme vouloir modifier le matricule. <strong>Attention :</strong> Cela peut casser les liens si les tables liées n'utilisent pas `ON UPDATE CASCADE`.
                </label>
             </div>
        </div>

        <!-- Ligne pour Email et Rôle -->
        <div class="row">
            <!-- Colonne Email -->
            <div class="col-md-6 mb-3">
                <label for="modal_email" class="form-label">Email <small class="text-muted">(Optionnel)</small></label>
                <!-- Champ Email. type="email" pour validation HTML5 basique. -->
                <input type="email" class="form-control" id="modal_email" name="email" value="<?= htmlspecialchars($user_edit['email'] ?? '') ?>" maxlength="100" placeholder="nom@exemple.com">
            </div>
            <!-- Colonne Rôle -->
            <div class="col-md-6 mb-3">
                <label for="modal_role_id" class="form-label required-field">Rôle</label>
                <!-- Liste déroulante pour sélectionner le rôle -->
                <select class="form-select" id="modal_role_id" name="role_id" required>
                    <!-- Option par défaut, désactivée et sélectionnée seulement en mode ajout initialement. -->
                    <option value="" disabled <?= ($action == 'ajouter') ? 'selected' : '' ?>>-- Sélectionner --</option>
                    <?php // Boucle sur les rôles disponibles ($roles_list vient du contrôleur) ?>
                    <?php foreach ($roles_list as $role): ?>
                        <!-- Option pour chaque rôle. -->
                        <!-- 'selected' si l'ID du rôle correspond à celui de l'utilisateur en cours de modification. -->
                        <option value="<?= htmlspecialchars($role['role_id']) ?>" <?= (($user_edit['role_id'] ?? null) == $role['role_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($role['role_name'])) /* Met la première lettre en majuscule */ ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Section pour le Mot de passe (dans un fieldset pour le regroupement visuel) -->
        <fieldset class="border p-3 pt-2 mt-3 mb-3">
            <!-- Légende dynamique -->
            <legend class="small w-auto px-2">
                <?= ($action == 'ajouter')
                    ? 'Mot de passe <span class="required-field"></span>' /* Obligatoire si ajout */
                    : 'Changer le mot de passe <small class="text-muted">(Optionnel)</small>' /* Optionnel si modif */
                ?>
            </legend>
            <!-- Ligne pour les champs mot de passe -->
            <div class="row">
                <!-- Colonne Mot de passe -->
                <div class="col-md-6 mb-2">
                    <!-- Label dynamique -->
                    <label for="modal_password" class="form-label small"><?= ($action == 'ajouter') ? 'Mot de passe' : 'Nouveau mot de passe' ?></label>
                    <!-- Champ Mot de passe -->
                    <!-- type="password" masque la saisie. -->
                    <!-- name change : 'password' en ajout, 'new_password' en modif. -->
                    <!-- 'required' seulement en ajout. -->
                    <!-- minlength="6" pour validation HTML5. -->
                    <input type="password" class="form-control form-control-sm" id="modal_password" name="<?= ($action == 'ajouter') ? 'password' : 'new_password' ?>" <?= ($action == 'ajouter') ? 'required' : '' ?> minlength="6">
                    <!-- Aide textuelle dynamique -->
                    <small class="text-muted">Min 6 caractères. <?= ($action == 'modifier') ? 'Laisser vide pour ne pas changer.' : '' ?></small>
                </div>
                <!-- Colonne Confirmation Mot de passe -->
                <div class="col-md-6 mb-2">
                    <!-- Label dynamique -->
                    <label for="modal_password_confirm" class="form-label small">Confirmer <?= ($action == 'ajouter') ? 'Mot de passe' : 'Nouveau mot de passe' ?></label>
                    <!-- Champ Confirmation -->
                    <!-- name change : 'password_confirm' en ajout, 'new_password_confirm' en modif. -->
                    <!-- 'required' seulement en ajout. -->
                    <input type="password" class="form-control form-control-sm" id="modal_password_confirm" name="<?= ($action == 'ajouter') ? 'password_confirm' : 'new_password_confirm' ?>" <?= ($action == 'ajouter') ? 'required' : '' ?>>
                </div>
            </div>
        </fieldset>

        <!-- Section des boutons d'action du formulaire -->
        <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
            <!-- Bouton Annuler : lien vers la liste (conserve page et filtres) -->
            <a href="index.php?controller=User&action=list&page=<?= htmlspecialchars($page) ?><?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
            <!-- Bouton de soumission -->
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>
                <?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?> <!-- Texte dynamique -->
            </button>
        </div>
    </form> <!-- Fin du formulaire -->

    <!-- Script JavaScript pour la logique spécifique à ce formulaire -->
    <script>
         // Attend que le DOM soit prêt
         document.addEventListener('DOMContentLoaded', function () {
             // Récupère les éléments du formulaire
             const userForm = document.getElementById('userFormModal'); // Le formulaire lui-même
             if (userForm) { // Vérifie que le formulaire existe
                 const originalMatriculeInput = document.getElementById('modal_original_matricule'); // Champ caché matricule original
                 const matriculeInput = document.getElementById('modal_matricule'); // Champ de saisie matricule
                 const confirmMatriculeGroup = document.getElementById('confirmMatriculeChangeGroup'); // Groupe de la checkbox de confirmation
                 const confirmMatriculeCheckbox = document.getElementById('confirm_matricule_change'); // La checkbox elle-même
                 const passwordInput = document.getElementById('modal_password'); // Champ mot de passe
                 const passwordConfirmInput = document.getElementById('modal_password_confirm'); // Champ confirmation mot de passe

                 // Détermine si on est en mode modification (si original_matricule a une valeur)
                 const isEditMode = originalMatriculeInput && originalMatriculeInput.value !== '';
                 // Stocke la valeur initiale du matricule en mode modification
                 let originalMatriculeValue = isEditMode ? originalMatriculeInput.value : '';

                 /**
                  * Vérifie si le matricule a été modifié par rapport à sa valeur originale
                  * et affiche/masque la checkbox de confirmation en conséquence.
                  * Rend également la checkbox requise si elle est affichée.
                  */
                 function checkMatriculeChange() {
                     // Ne fait rien si on n'est pas en mode modification
                     if (!isEditMode) return;
                     const currentMatricule = matriculeInput.value;
                     // Si le matricule actuel est différent de l'original
                     if (currentMatricule !== originalMatriculeValue) {
                         confirmMatriculeGroup.style.display = 'block'; // Affiche le groupe de confirmation
                         confirmMatriculeCheckbox.required = true; // Rend la checkbox requise
                     } else { // Si le matricule est revenu à l'original ou n'a pas changé
                         confirmMatriculeGroup.style.display = 'none'; // Masque le groupe
                         confirmMatriculeCheckbox.required = false; // Ne la rend plus requise
                         confirmMatriculeCheckbox.checked = false; // Décoche la case (sécurité)
                     }
                 }

                 // En mode modification, attache un écouteur à la saisie dans le champ matricule
                 if (isEditMode) {
                     matriculeInput.addEventListener('input', checkMatriculeChange);
                     checkMatriculeChange(); // Vérifie l'état initial au cas où la page est rechargée avec des erreurs
                 } else { // En mode ajout
                      // Masque le groupe de confirmation et s'assure que la checkbox n'est pas requise
                      if(confirmMatriculeGroup) confirmMatriculeGroup.style.display = 'none';
                      if(confirmMatriculeCheckbox) confirmMatriculeCheckbox.required = false;
                 }


                 // Ajoute un écouteur sur l'événement 'submit' du formulaire pour validation JS supplémentaire
                 userForm.addEventListener('submit', function(event) {
                     const pwd1 = passwordInput.value;
                     const pwd2 = passwordConfirmInput.value;
                     const pwdMinLength = 6; // Longueur minimale du mot de passe

                     // Validation du mot de passe en mode MODIFICATION
                     if (isEditMode) {
                          // Si l'un des champs de mot de passe est rempli (l'utilisateur veut changer le mdp)
                          if (pwd1.length > 0 || pwd2.length > 0) {
                             // Vérifie la longueur minimale
                             if (pwd1.length < pwdMinLength) {
                                 alert(`Le nouveau mot de passe doit faire au moins ${pwdMinLength} caractères.`);
                                 event.preventDefault(); // Empêche la soumission
                                 passwordInput.focus(); // Met le focus sur le champ
                                 return; // Arrête la fonction
                             }
                             // Vérifie que les mots de passe correspondent
                             if (pwd1 !== pwd2) {
                                 alert('Les nouveaux mots de passe ne correspondent pas.');
                                 event.preventDefault();
                                 passwordConfirmInput.focus();
                                 return;
                             }
                          }
                          // Si les champs sont vides, aucune validation de mdp n'est nécessaire en mode modif

                     } else { // Validation du mot de passe en mode AJOUT (obligatoire)
                         // Vérifie la longueur minimale
                         if (pwd1.length < pwdMinLength) {
                              alert(`Le mot de passe doit faire au moins ${pwdMinLength} caractères.`);
                              event.preventDefault();
                              passwordInput.focus();
                              return;
                         }
                          // Vérifie que les mots de passe correspondent
                          if (pwd1 !== pwd2) {
                              alert('Les mots de passe ne correspondent pas.');
                              event.preventDefault();
                              passwordConfirmInput.focus();
                              return;
                          }
                     }


                     // Validation de la confirmation de changement de matricule (seulement en mode modif)
                     // Si on est en modif ET que le matricule a changé ET que la case n'est pas cochée
                     if (isEditMode && matriculeInput.value !== originalMatriculeValue && !confirmMatriculeCheckbox.checked) {
                         alert('Veuillez cocher la case pour confirmer la modification du matricule.');
                         event.preventDefault(); // Empêche la soumission
                         confirmMatriculeCheckbox.focus(); // Met le focus sur la checkbox
                         return; // Arrête la fonction
                     }
                 }); // Fin de l'écouteur 'submit'
             } // Fin de if (userForm)
         }); // Fin de DOMContentLoaded
     </script>
</div> <!-- Fin du card-body -->