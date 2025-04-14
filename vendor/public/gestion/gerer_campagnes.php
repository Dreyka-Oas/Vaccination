<?php
// ==========================================================================
// Fichier: gerer_campagnes.php
// Description: Affichage (HTML) pour la Gestion CRUD, Filtrage et Tri des campagnes.
//              Inclut system/system_gerer_campagnes.php pour la logique métier.
// Auteur: [Votre Nom/Équipe]
// Date: [Date de création/modification] // Mise à jour pour séparation logique/présentation
// ==========================================================================

// --- Inclusion de la Logique Métier ---
// Définit les variables nécessaires ($action, $campagnes, $page, $totalPages, etc.)
// et gère les actions POST/GET avant que le HTML ne soit rendu.
// Gère aussi la sécurité et la connexion DB.
require_once __DIR__ . '/system/system_gerer_campagnes.php';

// --- Variables disponibles après l'inclusion ---
// $action, $message, $campagnes, $campagne_edit, $page, $totalPages,
// $filtre_statut, $sort_by, $sort_dir, $currentQueryString
// La fonction renderSortLink() est également définie.
// $_SESSION['message'] / $_SESSION['message_type'] peuvent être définis.
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Campagnes Vaccinales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Assurez-vous que le chemin vers main.css est correct depuis gerer_campagnes.php -->
    <link rel="stylesheet" href="../css/main.css">
    <style>
        /* Styles spécifiques (identiques à l'original) */
        .table-description { display: inline-block; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        .status-badge { padding: 0.2em 0.6em; border-radius: 0.25rem; font-size: 0.8em; font-weight: bold; }
        .status-active { background-color: #d1e7dd; color: #0f5132; }
        .status-inactive { background-color: #f8d7da; color: #842029; }
        .required-field::after { content: ' *'; color: red; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
        .filter-form { background-color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem; border: 1px solid #dee2e6; }
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #0d6efd; text-decoration: underline; }
        th .sort-icon { margin-left: 5px; color: #adb5bd; /* Gris léger par défaut */}
        th.sorted .sort-icon { color: #000; /* Plus visible si trié */ }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="card">
             <div class="header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Gestion des Campagnes</h1>
                        <p class="mb-0 small text-white-50">Administration et suivi des campagnes</p>
                    </div>
                    <?php if ($action == 'liste'): ?>
                        <div class="btn-group" role="group">
                             <?php // Lien Ajouter : pas besoin de conserver état actuel ?>
                            <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=ajouter" class="btn btn-sm btn-light" title="Ajouter"><i class="fas fa-plus me-1 text-success"></i>Nouveau</a>
                            <button type="button" class="btn btn-sm btn-light" onclick="openCampaignGenerationModal()" title="Générer Tests"><i class="fas fa-rocket me-1 text-info"></i>Générer</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllCampaignsModal()" title="Tout Supprimer!"><i class="fas fa-trash-alt me-1"></i>Tout Suppr.</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php // Affichage des messages (Session ou direct si erreur critique liste) ?>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?> alert-dismissible fade show m-3" role="alert">
                    <?= nl2br(htmlspecialchars($_SESSION['message'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php elseif (!empty($message)): // Pour les erreurs critiques PDO/Exception lors de l'affichage liste ?>
                 <div class="alert alert-danger m-3" role="alert"><?= nl2br(htmlspecialchars($message)) ?></div>
            <?php endif; ?>

            <?php // --- Affichage conditionnel : Formulaire OU Liste --- ?>

            <?php // CAS 1 : Formulaire Ajout/Modification ?>
            <?php if ($action == 'ajouter' || ($action == 'modifier' && $campagne_edit)): ?>
                <div class="card-body">
                    <h3 class="mb-4 border-bottom pb-3 fs-5">
                        <?= ($action == 'ajouter') ? '<i class="fas fa-plus-circle me-2 text-success"></i>Ajouter une campagne' : '<i class="fas fa-edit me-2 text-primary"></i>Modifier : ' . htmlspecialchars($campagne_edit['nom_campagne']) ?>
                    </h3>
                    <?php // L'action du formulaire pointe vers le script actuel et inclut l'état (filtre/tri via $currentQueryString) pour la redirection POST ?>
                    <form method="post" action="<?= basename($_SERVER['PHP_SELF']) ?>?action=<?= $action ?><?= (!empty($currentQueryString) ? '&' . $currentQueryString : '') ?>">
                        <?php if ($action == 'modifier'): ?>
                            <input type="hidden" name="campagne_id" value="<?= htmlspecialchars($campagne_edit['campagne_id']) ?>">
                        <?php endif; ?>
                        <?php // Passer la page actuelle pour la redirection après succès POST ?>
                        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="nom_campagne" class="form-label required-field">Nom</label>
                                <input type="text" class="form-control" id="nom_campagne" name="nom_campagne" value="<?= htmlspecialchars($campagne_edit['nom_campagne'] ?? '') ?>" required maxlength="255">
                            </div>
                            <div class="col-md-4 d-flex align-items-end pb-1">
                                <div class="form-check form-switch">
                                    <?php
                                        // Déterminer l'état checked pour le switch
                                        $isChecked = false; // Default
                                        if ($action === 'ajouter') {
                                            $isChecked = true; // Default to active for new campaigns
                                        } elseif (isset($campagne_edit['is_active'])) {
                                            $isChecked = $campagne_edit['is_active']; // Use boolean value from logic file
                                        }
                                    ?>
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= $isChecked ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="date_debut" class="form-label">Début <small class="text-muted">(Opt.)</small></label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($campagne_edit['date_debut'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="date_fin" class="form-label">Fin <small class="text-muted">(Opt.)</small></label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($campagne_edit['date_fin'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="description" class="form-label">Description <small class="text-muted">(Opt.)</small></label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($campagne_edit['description'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end border-top pt-3 mt-4 gap-2">
                              <?php // Lien Annuler : retourne à la liste en conservant page + filtre + tri via $currentQueryString ?>
                              <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste&page=<?= $page ?><?= (!empty($currentQueryString) ? '&' . $currentQueryString : '') ?>" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annuler</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= ($action == 'ajouter') ? 'Ajouter' : 'Enregistrer' ?></button>
                        </div>
                    </form>
                </div>

            <?php // CAS 2 : Liste des campagnes avec filtre statut et tri ?>
            <?php elseif ($action == 'liste'): ?>

                 <!-- Formulaire de Filtre (Statut seulement) -->
                 <div class="filter-form mx-3 mt-3">
                    <form method="GET" action="<?= basename($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="action" value="liste">
                        <?php // Conserver les paramètres de tri actuels lors du filtrage via GET ?>
                        <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                        <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sort_dir) ?>">
                        <?php // La page sera réinitialisée à 1 par défaut lors d'un nouveau filtrage (pas besoin de champ caché 'page') ?>

                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="filtre_statut" class="form-label small">Statut</label>
                                <select class="form-select form-select-sm" id="filtre_statut" name="filtre_statut">
                                    <option value="" <?= $filtre_statut === '' ? 'selected' : '' ?>>-- Tous --</option>
                                    <option value="1" <?= $filtre_statut === '1' ? 'selected' : '' ?>>Actives</option>
                                    <option value="0" <?= $filtre_statut === '0' ? 'selected' : '' ?>>Inactives</option>
                                </select>
                            </div>
                            <div class="col-md-7 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button>
                                <?php // Reset réinitialise filtre ET tri (retourne à la vue par défaut) ?>
                                <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste" class="btn btn-secondary btn-sm flex-grow-1" title="Réinitialiser filtre et tri"><i class="fas fa-times me-1"></i>Reset</a>
                            </div>
                        </div>
                    </form>
                 </div>

                 <div class="table-responsive">
                    <?php if (!empty($campagnes)): ?>
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php
                                        // Appel de la fonction helper définie dans le fichier système
                                        renderSortLink('nom_campagne', 'Nom', $sort_by, $sort_dir, $filtre_statut);
                                        renderSortLink('date_debut', 'Début', $sort_by, $sort_dir, $filtre_statut);
                                        renderSortLink('date_fin', 'Fin', $sort_by, $sort_dir, $filtre_statut);
                                        renderSortLink('description', 'Description', $sort_by, $sort_dir, $filtre_statut);
                                        renderSortLink('is_active', 'Statut', $sort_by, $sort_dir, $filtre_statut);
                                    ?>
                                    <th class="text-center" style="width: 130px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campagnes as $campagne): ?>
                                    <?php $isCurrentlyActive = $campagne['is_active']; // Utilise la valeur booléenne préparée ?>
                                    <tr>
                                        <td><?= htmlspecialchars($campagne['nom_campagne']) ?></td>
                                        <td><?= $campagne['date_debut'] ? date('d/m/Y', strtotime($campagne['date_debut'])) : '-' ?></td>
                                        <td><?= $campagne['date_fin'] ? date('d/m/Y', strtotime($campagne['date_fin'])) : '-' ?></td>
                                        <td>
                                            <span class="table-description" data-bs-toggle="tooltip" title="<?= htmlspecialchars($campagne['description'] ?: 'Aucune description') ?>">
                                                <?= htmlspecialchars($campagne['description'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="status-badge <?= $isCurrentlyActive ? 'status-active' : 'status-inactive' ?>">
                                                <?= $isCurrentlyActive ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                // Construction de l'URL de base pour les actions (conserve page + filtre + tri)
                                                // Les paramètres sont déjà dans $currentQueryString, il faut juste ajouter l'ID et l'action spécifique
                                                $actionBaseParams = ['id' => $campagne['campagne_id'], 'page' => $page];
                                                if (!empty($currentQueryString)) {
                                                    parse_str($currentQueryString, $existingParams);
                                                    $actionBaseParams = array_merge($existingParams, $actionBaseParams);
                                                }
                                                $actionUrlBase = basename($_SERVER['PHP_SELF']) . "?" . http_build_query($actionBaseParams);
                                            ?>
                                            <?php if ($isCurrentlyActive): ?>
                                                <a href="<?= $actionUrlBase ?>&action=desactiver" class="btn btn-warning btn-action" data-bs-toggle="tooltip" title="Désactiver"><i class="fas fa-toggle-off"></i></a>
                                            <?php else: ?>
                                                <a href="<?= $actionUrlBase ?>&action=activer" class="btn btn-success btn-action" data-bs-toggle="tooltip" title="Activer"><i class="fas fa-toggle-on"></i></a>
                                            <?php endif; ?>
                                            <a href="<?= $actionUrlBase ?>&action=modifier" class="btn btn-primary btn-action" data-bs-toggle="tooltip" title="Modifier"><i class="fas fa-edit"></i></a>
                                            <a href="<?= $actionUrlBase ?>&action=supprimer" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer" onclick="return confirm('Supprimer \'<?= htmlspecialchars(addslashes($campagne['nom_campagne']), ENT_QUOTES) ?>\' et TOUTES les données associées ? IRREVERSIBLE !')"><i class="fas fa-trash-alt"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: // Aucune campagne trouvée (avec ou sans filtre) ?>
                        <div class="card-body text-center p-4">
                            <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-3">
                                <?php if ($filtre_statut !== ''): // Message si filtre actif ?>
                                    Aucune campagne ne correspond au statut sélectionné.
                                <?php else: // Message si aucun filtre et aucune campagne ?>
                                    Aucune campagne vaccinale n'a été trouvée.
                                <?php endif; ?>
                            </p>
                            <?php // Le bouton Reset réinitialise filtre ET tri ?>
                            <?php if ($filtre_statut !== '' || $sort_by !== 'campagne_id' || $sort_dir !== 'DESC'): ?>
                                <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=liste" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser filtre et tri</a><br>
                            <?php endif; ?>
                             <a href="<?= basename($_SERVER['PHP_SELF']) ?>?action=ajouter" class="btn btn-success"><i class="fas fa-plus me-1"></i>Créer une campagne</a>
                        </div>
                    <?php endif; ?>
                </div> <?php // Fin table-responsive ?>

                <?php // Afficher la pagination si nécessaire (utilise $page, $totalPages, $currentQueryString) ?>
                <?php if (!empty($campagnes) && $totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation Campagnes">
                            <ul class="pagination justify-content-center mb-0">
                                <?php
                                    // URL de base pour la pagination (conserve filtre ET tri via $currentQueryString)
                                    $pageUrlBase = basename($_SERVER['PHP_SELF']) . "?action=liste";
                                    if (!empty($currentQueryString)) $pageUrlBase .= "&" . $currentQueryString;
                                    $pageUrlBase .= "&page=";
                                ?>
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page - 1) ?>">«</a></li>
                                <?php
                                    // Logique pagination complexe (inchangée)
                                    $linksToShow = 5; $startPage = max(1, $page - floor($linksToShow / 2)); $endPage = min($totalPages, $page + floor($linksToShow / 2));
                                    if ($endPage - $startPage + 1 < $linksToShow) { if ($startPage === 1) { $endPage = min($totalPages, $startPage + $linksToShow - 1); } elseif ($endPage === $totalPages) { $startPage = max(1, $endPage - $linksToShow + 1); } }
                                ?>
                                <?php if ($startPage > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= $pageUrlBase ?>1">1</a></li>
                                    <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                <?php endif; ?>
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $page == $i ? 'active' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . $i ?>"><?= $i ?></a></li>
                                <?php endfor; ?>
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="<?= $pageUrlBase . $totalPages ?>"><?= $totalPages ?></a></li>
                                <?php endif; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $pageUrlBase . ($page + 1) ?>">»</a></li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php endif; // Fin conditionnel action=liste / action=ajouter|modifier ?>

        </div> <?php // Fin card ?>

        <div class="text-center my-4">
             <!-- Chemin vers admin.php est relatif à gerer_campagnes.php -->
            <a href="../admin.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour tableau de bord</a>
        </div>

    </div> <?php // Fin dashboard-container ?>

    <?php // --- Modales --- (Leurs formulaires pointent vers gerer_campagnes.php) ?>
    <!-- Modale Génération -->
    <div class="modal fade" id="campaignGenerationModal" tabindex="-1" aria-labelledby="campaignGenerationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                 <?php // Le formulaire de génération soumet à gerer_campagnes.php via GET ?>
                <form id="generateCampaignsForm" action="<?= basename($_SERVER['PHP_SELF']) ?>" method="get">
                    <div class="modal-header">
                        <h6 class="modal-title" id="campaignGenerationModalLabel"><i class="fas fa-rocket me-2 text-info"></i>Générer Campagnes</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generer_campagnes">
                        <?php // Conserver l'état actuel de tri/filtre lors de la redirection *après* génération? ?>
                        <?php if (!empty($currentQueryString)): ?>
                            <?php foreach (explode('&', $currentQueryString) as $param): ?>
                                <?php list($key, $value) = explode('=', $param, 2); ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="nombreCampagnes" class="form-label">Nombre (1-50):</label>
                            <input type="number" class="form-control form-control-sm" id="nombreCampagnes" name="nombre" value="5" min="1" max="50" required>
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
    <!-- Modale Suppression Globale -->
    <div class="modal fade" id="deleteAllCampaignsModal" tabindex="-1" aria-labelledby="deleteAllCampaignsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <?php // Le formulaire de suppression globale soumet à gerer_campagnes.php via GET ?>
                <form id="deleteAllCampaignsForm" action="<?= basename($_SERVER['PHP_SELF']) ?>" method="GET">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteAllCampaignsModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                         <p><strong class="text-danger">Attention : Action Irréversible !</strong></p>
                         <p>Suppression définitive de :</p>
                         <ul class="list-unstyled mb-3 small">
                            <li><i class="fas fa-calendar-alt text-danger me-2 fa-fw"></i><strong>TOUTES</strong> les campagnes.</li>
                            <li><i class="fas fa-syringe text-danger me-2 fa-fw"></i><strong>TOUS</strong> les vaccins associés.</li>
                            <li><i class="fas fa-tags text-danger me-2 fa-fw"></i><strong>TOUTES</strong> les associations vaccin-type.</li>
                            <li><i class="fas fa-boxes text-danger me-2 fa-fw"></i><strong>TOUS</strong> les lots associés.</li>
                            <li><i class="fas fa-file-medical text-danger me-2 fa-fw"></i><strong>TOUS</strong> les enregistrements de vaccination.</li>
                        </ul>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="" id="confirmDeleteCheckbox" required>
                            <label class="form-check-label" for="confirmDeleteCheckbox">Je confirme vouloir tout supprimer.</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="supprimer_toutes_campagnes">
                         <?php // Conserver l'état actuel de tri/filtre lors de la redirection *après* suppression? ?>
                        <?php if (!empty($currentQueryString)): ?>
                            <?php foreach (explode('&', $currentQueryString) as $param): ?>
                                <?php list($key, $value) = explode('=', $param, 2); ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger" id="confirmDeleteAllBtn" disabled><i class="fas fa-trash-alt me-1"></i>Supprimer Tout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS pour Modales et Tooltips (Inchangé, fonctionne car les ID sont dans le HTML)
        function openCampaignGenerationModal() { var m=document.getElementById('campaignGenerationModal'); if(m){new bootstrap.Modal(m).show();} }
        const deleteAllModalEl = document.getElementById('deleteAllCampaignsModal'); if (deleteAllModalEl) { const c=deleteAllModalEl.querySelector('#confirmDeleteCheckbox'), b=deleteAllModalEl.querySelector('#confirmDeleteAllBtn'), i=new bootstrap.Modal(deleteAllModalEl); window.openDeleteAllCampaignsModal=function(){c.checked=false; b.disabled=true; i.show();}; c.addEventListener('change', function(){b.disabled = !this.checked;}); }
        document.addEventListener('DOMContentLoaded', function () { var t=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')); var l=t.map(function(e){if(!bootstrap.Tooltip.getInstance(e)){return new bootstrap.Tooltip(e,{delay:{"show":300,"hide":100}, html: true});}}); });
    </script>
</body>
</html>