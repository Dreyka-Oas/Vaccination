<!-- Début du corps de la carte (card-body), contient le formulaire -->
<div class="card-body">
    <!-- Titre du formulaire, change dynamiquement selon l'action (ajouter ou modifier) -->
    <h3 class="mb-4 border-bottom pb-3 fs-5">
        <?php
        // Expression ternaire pour afficher le titre approprié
        echo ($action == 'ajouter')
            // Si $action est 'ajouter'
            ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter une campagne'
            // Sinon (si $action est 'modifier')
            : '<i class="fas fa-edit me-2 text-primary"></i>Modifier : ' . htmlspecialchars($campagne_edit['nom_campagne'] ?? '');
        ?>
    </h3>

    <!-- Formulaire HTML -->
    <!-- method="post" : les données seront envoyées dans le corps de la requête HTTP -->
    <!-- action="..." : URL vers laquelle les données du formulaire seront envoyées.
         Elle pointe vers le contrôleur Campaign, action save.
         Inclut la query string actuelle ($currentQueryString, contenant les filtres/tris)
         pour pouvoir la conserver lors de la redirection après sauvegarde. -->
    <form method="post" action="index.php?controller=Campaign&action=save<?= (!empty($currentQueryString) ? '&' . $currentQueryString : '') ?>">

        <?php // Bloc PHP pour ajouter un champ caché si on est en mode modification ?>
        <?php if ($action == 'modifier'): ?>
            <!-- Champ caché 'campagne_id' : contient l'ID de la campagne à modifier.
                 N'est pas visible par l'utilisateur mais sera envoyé avec le formulaire.
                 Permet au contrôleur de savoir quelle campagne mettre à jour.
                 Utilise htmlspecialchars() pour la sécurité. -->
            <input type="hidden" name="campagne_id" value="<?= htmlspecialchars($campagne_edit['campagne_id']) ?>">
        <?php endif; ?>

        <!-- Champ caché 'page' : contient le numéro de la page actuelle de la liste.
             Sera envoyé avec le formulaire pour permettre au contrôleur de rediriger
             l'utilisateur vers la même page après la sauvegarde.
             Utilise htmlspecialchars(). -->
        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

        <!-- Grille Bootstrap pour organiser les champs (row avec gouttières g-3) -->
        <div class="row g-3 mb-3">
            <!-- Colonne pour le nom (prend 8/12 de la largeur sur md et plus) -->
            <div class="col-md-8">
                <!-- Label pour le champ 'nom_campagne'.
                     'required-field' est une classe CSS personnalisée pour indiquer les champs obligatoires (probablement ajoute un '*'). -->
                <label for="nom_campagne" class="form-label required-field">Nom</label>
                <!-- Champ de saisie pour le nom.
                     type="text", id="nom_campagne", name="nom_campagne".
                     value="..." : pré-remplit le champ avec la valeur existante si en modification, sinon vide.
                                   Utilise htmlspecialchars() et l'opérateur null coalescent (?? '') pour éviter les erreurs si $campagne_edit n'existe pas ou n'a pas cette clé.
                     required : attribut HTML5 pour rendre le champ obligatoire côté client.
                     maxlength="255" : limite la longueur de la saisie. -->
                <input type="text" class="form-control" id="nom_campagne" name="nom_campagne" value="<?= htmlspecialchars($campagne_edit['nom_campagne'] ?? '') ?>" required maxlength="255">
            </div>
            <!-- Colonne pour le switch 'Active' (prend 4/12) -->
            <!-- d-flex align-items-end pb-1 : classes Bootstrap pour aligner le switch verticalement en bas et ajouter un peu d'espace en bas -->
            <div class="col-md-4 d-flex align-items-end pb-1">
                <!-- Conteneur pour le switch Bootstrap -->
                <div class="form-check form-switch">
                    <?php
                        // Logique PHP pour déterminer si la case doit être cochée par défaut.
                        $isChecked = false; // Par défaut, non cochée.
                        if ($action === 'ajouter') {
                            $isChecked = true; // Cochée par défaut lors de l'ajout.
                        } elseif (isset($campagne_edit['is_active'])) {
                            // Si en modification, utilise la valeur existante ($campagne_edit['is_active'] est déjà un booléen PHP grâce au contrôleur).
                            $isChecked = $campagne_edit['is_active'];
                        }
                    ?>
                    <!-- Checkbox stylisée en switch.
                         role="switch" : pour l'accessibilité.
                         id="is_active", name="is_active".
                         value="1" : valeur envoyée si la case est cochée.
                         <?= $isChecked ? 'checked' : '' ?> : ajoute l'attribut 'checked' si $isChecked est true. -->
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= $isChecked ? 'checked' : '' ?>>
                    <!-- Label associé au switch -->
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
        </div>

        <!-- Nouvelle ligne Bootstrap pour les dates -->
        <div class="row g-3 mb-3">
            <!-- Colonne pour la date de début (6/12) -->
            <div class="col-md-6">
                <!-- Label indiquant que le champ est optionnel -->
                <label for="date_debut" class="form-label">Début <small class="text-muted">(Opt.)</small></label>
                <!-- Champ de saisie de type date.
                     id="date_debut", name="date_debut".
                     value="..." : pré-remplit avec la date existante (format AAAA-MM-JJ). -->
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($campagne_edit['date_debut'] ?? '') ?>">
            </div>
            <!-- Colonne pour la date de fin (6/12) -->
            <div class="col-md-6">
                <label for="date_fin" class="form-label">Fin <small class="text-muted">(Opt.)</small></label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($campagne_edit['date_fin'] ?? '') ?>">
            </div>
        </div>

        <!-- Champ pour la description (prend toute la largeur) -->
        <div class="mb-4">
            <label for="description" class="form-label">Description <small class="text-muted">(Opt.)</small></label>
            <!-- Zone de texte multiligne.
                 id="description", name="description", rows="3" : hauteur initiale.
                 Le contenu entre les balises est pré-rempli avec la description existante.
                 Utilise htmlspecialchars() pour afficher correctement les caractères spéciaux et éviter XSS. -->
            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($campagne_edit['description'] ?? '') ?></textarea>
        </div>

        <!-- Section des boutons d'action -->
        <!-- d-flex justify-content-end : aligne les boutons à droite.
             border-top pt-3 mt-4 : ajoute une ligne de séparation et de l'espacement.
             gap-2 : ajoute un espace entre les boutons. -->
        <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
            <!-- Bouton Annuler : un lien qui redirige vers la liste (page actuelle avec filtres/tris). -->
            <a href="index.php?controller=Campaign&action=list&page=<?= htmlspecialchars($page) ?><?= (!empty($currentQueryString) ? '&' . $currentQueryString : '') ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
            <!-- Bouton de soumission du formulaire -->
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>
                <!-- Texte du bouton change selon l'action -->
                <?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?>
            </button>
        </div>
    </form> <!-- Fin du formulaire -->
</div> <!-- Fin du card-body -->