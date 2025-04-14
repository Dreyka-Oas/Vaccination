<?php
// Importation des classes Core
use App\Core\Flasher;  // Pour afficher les messages flash
use App\Core\Paginator; // Pour afficher la pagination
// Les variables $users_list, $roles_list, $totalUsers, $totalPages, $page,
// $paginationHtml, $logged_in_user_matricule, $filter_role_id, $search_term,
// $currentQueryString, $queryStringParams, $highlight_matricule
// sont fournies par UserController::list()
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <!-- CSS Bootstrap, Font Awesome et personnalisé -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <!-- Styles spécifiques -->
    <style>
        /* Barre de filtre/recherche */
        .filter-search-bar { margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: 0.375rem; border: 1px solid #dee2e6; }
        /* Alignement vertical dans le tableau */
        .table th, .table td { vertical-align: middle; }
        /* Style pour les boutons d'action dans le tableau */
        .action-buttons form { display: inline-block; margin: 0 2px;} /* Pas utilisé ici mais gardé */
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
        /* Astérisque rouge pour champs requis dans la modale */
        .required-field::after { content: ' *'; color: red; }
        /* Groupe de confirmation de changement de matricule (caché par défaut) */
        #confirmMatriculeChangeGroup { display: none; }
        /* Classe pour surligner une ligne de tableau (après modification) */
        .table-info { --bs-table-bg-state: #cfe2ff; }
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
                     <div><h1 class="mb-1 h3"><i class="fas fa-users-cog me-2"></i>Gestion Utilisateurs</h1><p class="mb-0 small text-white-50">Ajouter, modifier, filtrer...</p></div>
                     <!-- Groupe de boutons d'action généraux -->
                     <div class="btn-group" role="group">
                        <!-- Bouton "Nouveau" : ouvre la modale d'ajout/modif utilisateur -->
                        <button type="button" id="addUserBtn" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#userFormModal" title="Nouveau"><i class="fas fa-user-plus me-1 text-success"></i>Nouveau</button>
                        <!-- Bouton "Générer" : ouvre la modale de génération d'utilisateurs via JS -->
                        <button type="button" class="btn btn-sm btn-light" onclick="openGenerateUsersModal()" title="Générer Test"><i class="fas fa-flask me-1 text-info"></i>Générer</button>
                     </div>
                 </div>
             </div>

            <?php // Affiche les messages flash ?>
            <?php Flasher::displayFlash(); ?>

                 <!-- Barre de filtre et de recherche -->
                 <div class="filter-search-bar m-3">
                     <form action="index.php" method="get" class="row g-3 align-items-end">
                        <!-- Champs cachés pour contrôleur et action -->
                        <input type="hidden" name="controller" value="User">
                        <input type="hidden" name="action" value="list">
                        <!-- Colonne pour le filtre par rôle -->
                        <div class="col-md-4">
                            <label for="filter_role_id" class="form-label small">Filtrer :</label>
                            <!-- Liste déroulante des rôles -->
                            <select class="form-select form-select-sm" id="filter_role_id" name="filter_role_id">
                                <option value="">-- Tous Rôles --</option>
                                <?php // Boucle sur les rôles disponibles ($roles_list vient du contrôleur) ?>
                                <?php foreach ($roles_list as $role): ?>
                                    <!-- Option sélectionnée si elle correspond au filtre actuel -->
                                    <option value="<?= htmlspecialchars($role['role_id']) ?>" <?= (isset($filter_role_id) && $filter_role_id == $role['role_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst($role['role_name'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Colonne pour la recherche par nom -->
                        <div class="col-md-5">
                            <label for="search" class="form-label small">Rechercher Nom :</label>
                            <!-- Champ de recherche texte -->
                            <input type="search" class="form-control form-control-sm" id="search" name="search" value="<?= htmlspecialchars($search_term ?? '') ?>" placeholder="Entrez un nom...">
                        </div>
                        <!-- Colonne pour les boutons Filtrer et Reset -->
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <!-- Bouton pour soumettre le formulaire de filtre/recherche -->
                            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button>
                            <?php // Affiche le bouton Reset seulement si un filtre ou une recherche est actif ?>
                            <?php if (isset($filter_role_id) && $filter_role_id || !empty($search_term)): ?>
                                <a href="index.php?controller=User&action=list" class="btn btn-outline-secondary btn-sm" title="Effacer"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                 </div>
                 <!-- Conteneur pour le tableau responsive -->
                 <div class="table-responsive">
                    <?php // Vérifie s'il y a des utilisateurs à afficher ?>
                    <?php if (!empty($users_list)): ?>
                        <!-- Tableau Bootstrap -->
                        <!-- caption-top place la légende au-dessus -->
                        <table class="table table-striped table-hover mb-0 caption-top">
                            <!-- Légende affichant le nombre total d'utilisateurs et la page actuelle -->
                            <caption class="px-3 small text-muted"><?= $totalUsers ?> utilisateur(s). Page <?= $page ?>/<?= $totalPages ?>.</caption>
                            <thead class="table-light">
                                <tr>
                                    <!-- En-têtes de colonnes (non triables dans cette version) -->
                                    <th>Matricule</th>
                                    <th>Nom d'utilisateur</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <!-- En-tête pour la colonne Actions -->
                                    <th class="text-center" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php // Boucle sur chaque utilisateur ?>
                            <?php foreach ($users_list as $user): ?>
                                <!-- Ajoute la classe 'table-info' pour surligner la ligne si $highlight_matricule correspond -->
                                <tr <?= ($highlight_matricule === $user['matricule']) ? 'class="table-info"' : '' ?>>
                                    <!-- Affiche les données de l'utilisateur -->
                                    <td><?= htmlspecialchars($user['matricule']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?: '-') /* Affiche '-' si l'email est null */ ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['role_name'])) /* Met la première lettre du rôle en majuscule */ ?></td>
                                    <!-- Colonne Actions -->
                                    <td class="text-center action-buttons">
                                        <!-- Bouton Modifier : ouvre la modale et passe les données via data-* -->
                                        <button type="button" class="btn btn-primary btn-action edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#userFormModal"
                                                data-matricule="<?= htmlspecialchars($user['matricule']) ?>"
                                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                                data-email="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                                data-role-id="<?= $user['role_id'] ?>"
                                                data-bs-toggle="tooltip" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php // Condition pour le bouton Supprimer ?>
                                        <?php // Empêche l'utilisateur connecté de se supprimer lui-même ?>
                                        <?php if ($user['matricule'] !== $logged_in_user_matricule): ?>
                                            <!-- Bouton Supprimer : ouvre la modale de confirmation via JS -->
                                            <!-- Passe le matricule, le nom, la page et la query string à la fonction JS -->
                                            <button type="button" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer"
                                                    onclick="openDeleteUserModal('<?= htmlspecialchars(addslashes($user['matricule']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($user['username']), ENT_QUOTES) ?>', <?= $page ?>, '<?= addslashes($currentQueryString) ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php else: // Si c'est l'utilisateur connecté ?>
                                            <!-- Bouton Supprimer désactivé -->
                                            <button type="button" class="btn btn-secondary btn-action" disabled data-bs-toggle="tooltip" title="Soi-même"><i class="fas fa-trash-alt"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; // Fin de la boucle utilisateurs ?>
                            </tbody>
                        </table>
                    <?php else: // Si aucun utilisateur n'est trouvé ?>
                        <div class="card-body text-center p-4">
                            <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                            <!-- Message différent si filtre/recherche actif -->
                            <p class="text-muted mb-3"><?= ($filter_role_id || !empty($search_term)) ? 'Aucun utilisateur trouvé.' : 'Aucun utilisateur enregistré.' ?></p>
                            <?php // Affiche le bouton Reset si filtre/recherche actif ?>
                            <?php if ($filter_role_id || !empty($search_term)): ?>
                                <a href="index.php?controller=User&action=list" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-times me-1"></i>Réinitialiser</a><br>
                            <?php endif; ?>
                             <!-- Bouton pour ouvrir la modale d'ajout -->
                             <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userFormModal" id="addUserBtnDirect">
                                <i class="fas fa-user-plus me-1"></i>Créer un utilisateur
                            </button>
                        </div>
                    <?php endif; // Fin de la condition !empty($users_list) ?>
                </div>
                <?php // Affiche la pagination si nécessaire ?>
                <?php if ($totalUsers > 0 && $totalPages > 1): ?>
                    <div class="card-footer">
                        <?= $paginationHtml // Affiche le HTML généré par Paginator ?>
                    </div>
                <?php endif; ?>

        </div> <!-- Fin de la carte principale -->
        <!-- Lien de retour vers le panneau Admin -->
        <div class="text-center my-4"><a href="index.php?controller=Admin&action=index" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour Admin</a></div>
    </div> <!-- Fin du conteneur principal -->

    <!-- Modale Bootstrap pour le formulaire d'ajout/modification utilisateur -->
    <!-- Le contenu du formulaire (form.php) est inclus ici -->
    <div class="modal fade" id="userFormModal" tabindex="-1" aria-labelledby="userFormModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-lg"> <!-- Modale large -->
            <div class="modal-content">
                <!-- Le formulaire avec son action et son ID pour le ciblage JS -->
                <form method="post" action="index.php?controller=User&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" id="modalUserForm">
                    <div class="modal-header">
                        <!-- Titre mis à jour par JS -->
                        <h5 class="modal-title" id="userFormModalLabel">Ajouter un utilisateur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Champs cachés pour matricule original et page -->
                        <input type="hidden" name="original_matricule" id="modal_original_matricule" value="">
                        <input type="hidden" name="page" id="modal_page" value="<?= htmlspecialchars($page) ?>">

                         <!-- Champs du formulaire (identiques à form.php mais avec ID préfixés par 'modal_') -->
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_matricule" class="form-label required-field">Matricule</label>
                                <input type="text" class="form-control" id="modal_matricule" name="matricule" required maxlength="50">
                                <small class="text-muted" id="matriculeHelp">Identifiant unique.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal_username" class="form-label required-field">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="modal_username" name="username" required maxlength="50">
                            </div>
                        </div>

                         <!-- Groupe de confirmation pour changement de matricule (caché par défaut) -->
                         <div class="mb-3 alert alert-warning p-2" id="confirmMatriculeChangeGroup">
                             <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="confirm_matricule_change" name="confirm_matricule_change">
                                <label class="form-check-label small" for="confirm_matricule_change">
                                    <i class="fas fa-exclamation-triangle text-danger me-1"></i> Je confirme vouloir modifier le matricule. <strong>Attention :</strong> Risqué sans `ON UPDATE CASCADE`.
                                </label>
                             </div>
                        </div>

                        <!-- Ligne Email et Rôle -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_email" class="form-label">Email <small class="text-muted">(Optionnel)</small></label>
                                <input type="email" class="form-control" id="modal_email" name="email" maxlength="100" placeholder="nom@exemple.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal_role_id" class="form-label required-field">Rôle</label>
                                <select class="form-select" id="modal_role_id" name="role_id" required>
                                    <option value="" selected disabled>-- Sélectionner --</option>
                                    <?php foreach ($roles_list as $role): ?>
                                        <option value="<?= htmlspecialchars($role['role_id']) ?>">
                                            <?= htmlspecialchars(ucfirst($role['role_name'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Fieldset pour les champs Mot de passe -->
                        <fieldset class="border p-3 pt-2 mt-3 mb-3" id="passwordFieldset">
                            <!-- Légende et labels mis à jour par JS -->
                            <legend class="small w-auto px-2" id="passwordLegend">Mot de passe <span class="required-field"></span></legend>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="modal_password" class="form-label small" id="passwordLabel">Mot de passe</label>
                                    <input type="password" class="form-control form-control-sm" id="modal_password" name="password" required minlength="6">
                                    <small class="text-muted" id="passwordHelp">Min 6 caractères.</small>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="modal_password_confirm" class="form-label small" id="passwordConfirmLabel">Confirmer Mot de passe</label>
                                    <input type="password" class="form-control form-control-sm" id="modal_password_confirm" name="password_confirm" required>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Annuler</button>
                        <!-- Texte du bouton mis à jour par JS -->
                        <button type="submit" class="btn btn-primary" id="modalSubmitButton"><i class="fas fa-save me-1"></i>Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modale pour générer des utilisateurs test -->
    <div class="modal fade" id="generateUsersModal" tabindex="-1" aria-labelledby="generateUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form id="generateUsersForm" action="index.php" method="get">
                     <input type="hidden" name="controller" value="User">
                     <input type="hidden" name="action" value="generate">
                     <div class="modal-header">
                         <h6 class="modal-title" id="generateUsersModalLabel"><i class="fas fa-flask text-info me-2"></i>Générer Utilisateurs</h6>
                         <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                     </div>
                     <div class="modal-body">
                         <?php // Inclut les paramètres de filtre/recherche actuels pour les conserver après génération ?>
                         <?php foreach ($queryStringParams as $key => $value): ?>
                             <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                         <?php endforeach; ?>
                         <div class="mb-3">
                             <label for="nombreUsers" class="form-label">Nombre (1-50):</label>
                             <input type="number" class="form-control form-control-sm" id="nombreUsers" name="nombre" value="5" min="1" max="50" required>
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

    <!-- Modale de confirmation pour supprimer un utilisateur -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteUserModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong class="text-danger">Attention !</strong></p>
                    <!-- Le nom et matricule seront insérés ici par JS -->
                    <p>Supprimer "<strong id="deleteUsername"></strong>" (Matricule: <span id="deleteUserMatricule"></span>) ?</p>
                    <p class="small text-muted">Action irréversible.</p>
                    <!-- Checkbox de confirmation -->
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="" id="confirmDeleteUserCheckbox" required>
                        <label class="form-check-label" for="confirmDeleteUserCheckbox">Je confirme vouloir supprimer.</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <!-- Bouton désactivé par défaut, activé et actionné par JS -->
                    <button type="button" class="btn btn-danger" id="confirmDeleteUserBtn" disabled><i class="fas fa-trash-alt me-1"></i>Supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script JS spécifique -->
    <script>
        // Attend que le DOM soit chargé
        document.addEventListener('DOMContentLoaded', function () {
            // Initialise les tooltips Bootstrap
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) { if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) { return new bootstrap.Tooltip(tooltipTriggerEl); } });

            // Fonction globale pour ouvrir la modale de génération
            window.openGenerateUsersModal = function() { var m=document.getElementById('generateUsersModal'); if(m){new bootstrap.Modal(m).show();} }

            // Gestion de la modale de suppression d'utilisateur
            const deleteUserModalEl = document.getElementById('deleteUserModal');
            if(deleteUserModalEl) {
                const confirmCheckboxUser = deleteUserModalEl.querySelector('#confirmDeleteUserCheckbox');
                const confirmBtnUser = deleteUserModalEl.querySelector('#confirmDeleteUserBtn');
                const usernameSpan = deleteUserModalEl.querySelector('#deleteUsername');
                const userMatriculeSpan = deleteUserModalEl.querySelector('#deleteUserMatricule');
                const modalInstanceUser = new bootstrap.Modal(deleteUserModalEl);
                let deleteUserUrl = '#'; // Variable pour stocker l'URL de suppression

                // Fonction globale pour ouvrir la modale de suppression
                window.openDeleteUserModal = function(matricule, username, page, queryString) { // Changé queryStringPrefix en queryString
                    usernameSpan.textContent = username; // Met à jour le nom dans la modale
                    userMatriculeSpan.textContent = matricule; // Met à jour le matricule
                    // Construit l'URL de suppression en ajoutant matricule, page et la query string existante
                     let prefix = queryString && queryString.length > 0 && queryString.charAt(0) !== '&' ? '&' : ''; // Ajoute '&' si nécessaire
                     deleteUserUrl = `index.php?controller=User&action=delete&matricule=${encodeURIComponent(matricule)}&page=${page}${prefix}${queryString}`;
                    confirmCheckboxUser.checked = false; // Décoche la case
                    confirmBtnUser.disabled = true; // Désactive le bouton
                    modalInstanceUser.show(); // Affiche la modale
                };
                // Active/désactive le bouton quand la case change
                confirmCheckboxUser.addEventListener('change', function() { confirmBtnUser.disabled = !this.checked; });
                // Action du bouton Confirmer : redirige vers l'URL de suppression
                confirmBtnUser.addEventListener('click', function() { if (!confirmCheckboxUser.checked) return; window.location.href = deleteUserUrl; });
            }

            // ---- Gestion de la modale d'ajout/modification utilisateur ----
             const userModal = document.getElementById('userFormModal');
             const modalForm = document.getElementById('modalUserForm');
             const modalTitle = document.getElementById('userFormModalLabel');
             const modalOriginalMatriculeInput = document.getElementById('modal_original_matricule');
             const modalMatriculeInput = document.getElementById('modal_matricule');
             const modalUsernameInput = document.getElementById('modal_username');
             const modalEmailInput = document.getElementById('modal_email');
             const modalRoleIdSelect = document.getElementById('modal_role_id');
             const modalPasswordInput = document.getElementById('modal_password');
             const modalPasswordConfirmInput = document.getElementById('modal_password_confirm');
             const modalPageInput = document.getElementById('modal_page');
             const modalSubmitButton = document.getElementById('modalSubmitButton');
             // Éléments spécifiques à la modale user
             const confirmMatriculeGroup = document.getElementById('confirmMatriculeChangeGroup');
             const confirmMatriculeCheckbox = document.getElementById('confirm_matricule_change');
             const passwordFieldset = document.getElementById('passwordFieldset');
             const passwordLegend = document.getElementById('passwordLegend');
             const passwordLabel = document.getElementById('passwordLabel');
             const passwordConfirmLabel = document.getElementById('passwordConfirmLabel');
             const passwordHelp = document.getElementById('passwordHelp');
             const matriculeHelp = document.getElementById('matriculeHelp');

             // Fonction pour préparer la modale en mode AJOUT
             function prepareModalForAdd() {
                 modalForm.reset(); // Vide tous les champs
                 modalTitle.textContent = 'Ajouter un utilisateur'; // Titre
                 modalOriginalMatriculeInput.value = ''; // Vide matricule original
                 modalMatriculeInput.readOnly = false; // Matricule éditable
                 modalSubmitButton.textContent = 'Ajouter'; // Texte bouton
                 modalPageInput.value = <?= json_encode($page) ?>; // Page actuelle
                 // Cache confirmation changement matricule
                 confirmMatriculeGroup.style.display = 'none';
                 confirmMatriculeCheckbox.checked = false;
                 confirmMatriculeCheckbox.required = false;
                 matriculeHelp.textContent = 'Identifiant unique.'; // Aide matricule
                 // Configure les champs mot de passe pour l'ajout (obligatoire)
                 passwordFieldset.disabled = false;
                 passwordLegend.innerHTML = 'Mot de passe <span class="required-field"></span>';
                 passwordLabel.textContent = 'Mot de passe';
                 passwordConfirmLabel.textContent = 'Confirmer Mot de passe';
                 modalPasswordInput.required = true;
                 modalPasswordInput.name = 'password';
                 modalPasswordConfirmInput.required = true;
                 modalPasswordConfirmInput.name = 'password_confirm';
                 passwordHelp.textContent = 'Min 6 caractères.';
             }

             // Fonction pour préparer la modale en mode MODIFICATION
             function prepareModalForEdit(button) {
                 modalForm.reset(); // Vide les champs
                 const data = button.dataset; // Récupère data-* du bouton
                 modalTitle.textContent = 'Modifier : ' + data.username; // Titre
                 // Pré-remplit les champs
                 modalOriginalMatriculeInput.value = data.matricule; // Stocke l'original
                 modalMatriculeInput.value = data.matricule;
                 modalUsernameInput.value = data.username;
                 modalEmailInput.value = data.email;
                 modalRoleIdSelect.value = data.roleId;
                 modalMatriculeInput.readOnly = false; // Matricule éditable initialement
                 modalSubmitButton.textContent = 'Enregistrer'; // Texte bouton
                 modalPageInput.value = <?= json_encode($page) ?>; // Page actuelle
                 // Cache confirmation changement (sera affiché par checkMatriculeChange si besoin)
                 confirmMatriculeGroup.style.display = 'none';
                 confirmMatriculeCheckbox.checked = false;
                 confirmMatriculeCheckbox.required = false;
                 matriculeHelp.textContent = 'Peut être modifié (Attention Risqué !)'; // Aide matricule
                 // Configure les champs mot de passe pour la modif (optionnel)
                 passwordFieldset.disabled = false; // Reste éditable
                 passwordLegend.innerHTML = 'Changer le mot de passe <small class="text-muted">(Optionnel)</small>';
                 passwordLabel.textContent = 'Nouveau mot de passe';
                 passwordConfirmLabel.textContent = 'Confirmer Nouveau mot de passe';
                 modalPasswordInput.required = false; // Non requis
                 modalPasswordInput.name = 'new_password';
                 modalPasswordConfirmInput.required = false; // Non requis
                 modalPasswordConfirmInput.name = 'new_password_confirm';
                 passwordHelp.textContent = 'Min 6 caractères. Laisser vide pour ne pas changer.';
                 // Vérifie l'état initial du matricule (au cas où il serait déjà différent si rechargement après erreur)
                 checkMatriculeChange();
             }

             /**
              * Vérifie si le matricule a changé et gère l'affichage/état requis de la checkbox de confirmation.
              * Fonction spécifique à la modale utilisateur.
              */
             function checkMatriculeChange() {
                 const isEditMode = modalOriginalMatriculeInput.value !== '';
                 if (!isEditMode) return; // Ne fait rien en mode ajout
                 const currentMatricule = modalMatriculeInput.value;
                 const originalMatricule = modalOriginalMatriculeInput.value;

                 // Si le matricule a changé
                 if (currentMatricule !== originalMatricule) {
                     confirmMatriculeGroup.style.display = 'block'; // Affiche confirmation
                     confirmMatriculeCheckbox.required = true; // Rend la coche requise
                 } else { // Si le matricule est revenu à l'original ou n'a pas changé
                     confirmMatriculeGroup.style.display = 'none'; // Cache confirmation
                     confirmMatriculeCheckbox.required = false; // Coche non requise
                     confirmMatriculeCheckbox.checked = false; // Décoche
                 }
             }

             // Écouteur sur la saisie dans le champ matricule pour vérifier les changements
             modalMatriculeInput.addEventListener('input', checkMatriculeChange);

             // Attache les écouteurs aux boutons "Nouveau"
             const addBtn = document.getElementById('addUserBtn');
             if (addBtn) { addBtn.addEventListener('click', prepareModalForAdd); }
             const addBtnDirect = document.getElementById('addUserBtnDirect');
             if (addBtnDirect) { addBtnDirect.addEventListener('click', prepareModalForAdd); }

             // Délégation d'événement pour les boutons "Modifier" du tableau
             const tableBody = document.querySelector('.table tbody');
             if (tableBody) {
                 tableBody.addEventListener('click', function(event) {
                     const editButton = event.target.closest('.edit-btn');
                     if (editButton) {
                         prepareModalForEdit(editButton); // Prépare la modale pour l'édition
                     }
                 });
             }

             // Réinitialise la modale quand elle est fermée
             userModal.addEventListener('hidden.bs.modal', function () {
                 modalForm.reset();
                 modalOriginalMatriculeInput.value = '';
                 confirmMatriculeGroup.style.display = 'none';
                 confirmMatriculeCheckbox.checked = false;
                 confirmMatriculeCheckbox.required = false;
                 modalMatriculeInput.readOnly = false;
                 // Réinitialise l'apparence des champs mot de passe comme pour l'ajout
                 passwordFieldset.disabled = false;
                 passwordLegend.innerHTML = 'Mot de passe <span class="required-field"></span>';
                 passwordLabel.textContent = 'Mot de passe';
                 passwordConfirmLabel.textContent = 'Confirmer Mot de passe';
                 modalPasswordInput.required = true;
                 modalPasswordInput.name = 'password';
                 modalPasswordConfirmInput.required = true;
                 modalPasswordConfirmInput.name = 'password_confirm';
                 passwordHelp.textContent = 'Min 6 caractères.';
             });

             // Validation JS supplémentaire à la soumission du formulaire de la modale
             modalForm.addEventListener('submit', function(event) {
                    const isEditMode = modalOriginalMatriculeInput.value !== '';
                    const pwd1 = modalPasswordInput.value;
                    const pwd2 = modalPasswordConfirmInput.value;
                    const pwdMinLength = 6;

                    // Validation mot de passe (longueur et correspondance)
                    if (isEditMode) { // Mode Modification
                        if (pwd1.length > 0 || pwd2.length > 0) { // Si l'utilisateur essaie de changer le mdp
                           if (pwd1.length < pwdMinLength) { alert(`Le nouveau mot de passe doit faire au moins ${pwdMinLength} caractères.`); event.preventDefault(); modalPasswordInput.focus(); return; }
                           if (pwd1 !== pwd2) { alert('Les nouveaux mots de passe ne correspondent pas.'); event.preventDefault(); modalPasswordConfirmInput.focus(); return; }
                        }
                    } else { // Mode Ajout (mdp obligatoire)
                       if (pwd1.length < pwdMinLength) { alert(`Le mot de passe doit faire au moins ${pwdMinLength} caractères.`); event.preventDefault(); modalPasswordInput.focus(); return; }
                       if (pwd1 !== pwd2) { alert('Les mots de passe ne correspondent pas.'); event.preventDefault(); modalPasswordConfirmInput.focus(); return; }
                    }

                    // Validation confirmation changement matricule (seulement en mode modif)
                    if (isEditMode && modalMatriculeInput.value !== modalOriginalMatriculeInput.value && !confirmMatriculeCheckbox.checked) {
                        alert('Veuillez cocher la case pour confirmer la modification du matricule.');
                        event.preventDefault(); confirmMatriculeCheckbox.focus(); return;
                    }
             }); // Fin écouteur submit

        }); // Fin DOMContentLoaded global
    </script>
</body>
</html>