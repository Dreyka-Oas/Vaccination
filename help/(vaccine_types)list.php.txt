<?php
use App\Core\Flasher;
use App\Core\SortLinkGenerator;
use App\Core\Paginator; // Assurez-vous que Paginator est inclus ou autoloadé
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Types de Vaccins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        th a { color: inherit; text-decoration: none; }
        th a:hover { color: #0d6efd; text-decoration: underline; }
        th .sort-icon { margin-left: 5px; color: #adb5bd; }
        th.sorted .sort-icon { color: #000; }
        .table-description { display: inline-block; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        .required-field::after { content: ' *'; color: red; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0 2px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="card">
             <div class="card-header header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="mb-1"><i class="fas fa-tags me-2"></i>Gestion des Types de Vaccins</h1>
                        <p class="mb-0 small text-white-50">Ajouter, modifier ou supprimer des types</p>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" id="addVaccineTypeBtn" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#vaccineTypeFormModal" title="Nouveau Type"><i class="fas fa-plus me-1 text-success"></i>Nouveau</button>
                        <button type="button" class="btn btn-sm btn-light" onclick="openGenerateTypesVaccinsModal()" title="Générer données test"><i class="fas fa-flask me-1 text-info"></i>Générer Test</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAllTypesVaccinsModal()" title="Supprimer Tous!"><i class="fas fa-trash-alt me-1"></i>Tout Supprimer</button>
                    </div>
                </div>
            </div>

            <?php Flasher::displayFlash(); ?>

             <div class="table-responsive">
                <?php if (!empty($type_vaccins)): ?>
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <?php
                                    $baseUrlForSort = "index.php?controller=VaccineType&action=list";
                                    echo SortLinkGenerator::render($baseUrlForSort, 'nom_type', 'Nom', $sort_by, $sort_dir);
                                    echo SortLinkGenerator::render($baseUrlForSort, 'description', 'Description', $sort_by, $sort_dir);
                                ?>
                                <th class="text-center" style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($type_vaccins as $type_vaccin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($type_vaccin['nom_type']) ?></td>
                                    <td >
                                        <span class="table-description" data-bs-toggle="tooltip" title="<?= htmlspecialchars($type_vaccin['description'] ?: 'Aucune description') ?>">
                                            <?= htmlspecialchars($type_vaccin['description'] ?: '-') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-primary btn-action edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#vaccineTypeFormModal"
                                                data-id="<?= $type_vaccin['type_vaccin_id'] ?>"
                                                data-nom="<?= htmlspecialchars($type_vaccin['nom_type']) ?>"
                                                data-description="<?= htmlspecialchars($type_vaccin['description'] ?? '') ?>"
                                                data-bs-toggle="tooltip" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-action" data-bs-toggle="tooltip" title="Supprimer"
                                                onclick="openDeleteTypeModal(<?= $type_vaccin['type_vaccin_id'] ?>, '<?= htmlspecialchars(addslashes($type_vaccin['nom_type']), ENT_QUOTES) ?>', <?= $page ?>, '<?= addslashes($currentQueryString) ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="card-body text-center p-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                        <p class="text-muted mb-3">Aucun type de vaccin enregistré.</p>
                        <?php if (!empty($currentQueryString)): ?>
                            <a href="index.php?controller=VaccineType&action=list" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-times me-1"></i>Réinitialiser le tri</a><br>
                        <?php endif; ?>
                         <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#vaccineTypeFormModal" id="addVaccineTypeBtnDirect">
                            <i class="fas fa-plus me-1"></i>Créer le premier type
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($type_vaccins) && $totalPages > 1): ?>
                <div class="card-footer">
                   <?= $paginationHtml ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center my-4"><a href="index.php?controller=Admin&action=index" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Retour Admin</a></div>
    </div>

    <div class="modal fade" id="vaccineTypeFormModal" tabindex="-1" aria-labelledby="vaccineTypeFormModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="index.php?controller=VaccineType&action=save<?= !empty($currentQueryString) ? '&' . $currentQueryString : '' ?>" id="modalVaccineTypeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="vaccineTypeFormModalLabel">Ajouter un Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="type_vaccin_id" id="modal_type_vaccin_id" value="">
                        <input type="hidden" name="page" id="modal_page" value="<?= htmlspecialchars($page) ?>">

                        <div class="mb-3">
                            <label for="modal_nom_type" class="form-label required-field">Nom du type</label>
                            <input type="text" class="form-control" id="modal_nom_type" name="nom_type" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="modal_description" class="form-label">Description <small class="text-muted">(Optionnel)</small></label>
                            <textarea class="form-control" id="modal_description" name="description" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Annuler</button>
                        <button type="submit" class="btn btn-primary" id="modalSubmitButton"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div class="modal fade" id="generateTypesVaccinsModal" tabindex="-1" aria-labelledby="generateTypesVaccinsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm"> <div class="modal-content"> <form id="generateTypesVaccinsForm" action="index.php" method="get"> <input type="hidden" name="controller" value="VaccineType"><input type="hidden" name="action" value="generate"> <div class="modal-header"> <h6 class="modal-title" id="generateTypesVaccinsModalLabel"><i class="fas fa-flask me-2 text-info"></i>Générer Types Test</h6> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <div class="mb-3"> <label for="nombreTypesVaccins" class="form-label">Nombre (1-50):</label> <input type="number" class="form-control form-control-sm" id="nombreTypesVaccins" name="nombre" value="5" min="1" max="50" required> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-sm btn-primary">Générer</button> </div> </form> </div> </div>
    </div>

    <div class="modal fade" id="deleteAllTypesVaccinsModal" tabindex="-1" aria-labelledby="deleteAllTypesVaccinsModalLabel" aria-hidden="true">
        <div class="modal-dialog"> <div class="modal-content"> <form id="deleteAllTypesVaccinsForm" action="index.php" method="GET"> <input type="hidden" name="controller" value="VaccineType"><input type="hidden" name="action" value="deleteAll"> <div class="modal-header bg-danger text-white"> <h5 class="modal-title" id="deleteAllTypesVaccinsModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <p><strong class="text-danger">Attention : Action Irréversible !</strong></p> <p>Suppression définitive de :</p> <ul class="list-unstyled mb-3 small"> <li><i class="fas fa-tags text-danger me-2 fa-fw"></i><strong>TOUS</strong> les types de vaccins.</li> <li><i class="fas fa-link text-danger me-2 fa-fw"></i><strong>TOUTES</strong> les associations vaccin-type.</li> <li><i class="fas fa-boxes text-danger me-2 fa-fw"></i><strong>TOUS</strong> les lots liés (via vaccins).</li> <li><i class="fas fa-file-medical text-danger me-2 fa-fw"></i><strong>TOUS</strong> les enregistrements de vaccination liés (via lots).</li> </ul> <div class="form-check mt-3"> <input class="form-check-input" type="checkbox" value="" id="confirmDeleteAllTypesVaccinsCheckbox" required> <label class="form-check-label" for="confirmDeleteAllTypesVaccinsCheckbox">Je confirme vouloir tout supprimer.</label> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="submit" class="btn btn-danger" id="confirmDeleteAllTypesVaccinsBtn" disabled> <i class="fas fa-trash-alt me-1"></i>Supprimer Tout </button> </div> </form> </div> </div>
    </div>

    <div class="modal fade" id="deleteTypeVaccinModal" tabindex="-1" aria-labelledby="deleteTypeVaccinModalLabel" aria-hidden="true">
        <div class="modal-dialog"> <div class="modal-content"> <div class="modal-header bg-danger text-white"> <h5 class="modal-title" id="deleteTypeVaccinModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirmation Requise</h5> <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <p><strong class="text-danger">Attention : Action Irréversible !</strong></p> <p>Vous allez supprimer le type de vaccin "<strong id="deleteTypeName"></strong>".</p> <p>Cela supprimera aussi définitivement les données associées (associations, lots liés, enregistrements liés).</p> <div class="form-check mt-3"> <input class="form-check-input" type="checkbox" value="" id="confirmDeleteTypeCheckbox" required> <label class="form-check-label" for="confirmDeleteTypeCheckbox">Je confirme vouloir supprimer ce type et ses données associées.</label> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button> <button type="button" class="btn btn-danger" id="confirmDeleteTypeBtn" disabled> <i class="fas fa-trash-alt me-1"></i>Supprimer ce Type </button> </div> </div> </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openGenerateTypesVaccinsModal() { var m=document.getElementById('generateTypesVaccinsModal'); if(m){new bootstrap.Modal(m).show();} }
        const deleteAllTypesVaccinsModalEl = document.getElementById('deleteAllTypesVaccinsModal');
        if (deleteAllTypesVaccinsModalEl) {
            const c=deleteAllTypesVaccinsModalEl.querySelector('#confirmDeleteAllTypesVaccinsCheckbox'), b=deleteAllTypesVaccinsModalEl.querySelector('#confirmDeleteAllTypesVaccinsBtn'), i=new bootstrap.Modal(deleteAllTypesVaccinsModalEl);
            window.openDeleteAllTypesVaccinsModal=function(){c.checked=false; b.disabled=true; i.show();};
            c.addEventListener('change', function(){b.disabled = !this.checked;});
        }
        const deleteTypeModalEl = document.getElementById('deleteTypeVaccinModal');
        if(deleteTypeModalEl) {
            const confirmCheckboxType = deleteTypeModalEl.querySelector('#confirmDeleteTypeCheckbox');
            const confirmBtnType = deleteTypeModalEl.querySelector('#confirmDeleteTypeBtn');
            const typeNameSpan = deleteTypeModalEl.querySelector('#deleteTypeName');
            const modalInstanceType = new bootstrap.Modal(deleteTypeModalEl);
            let deleteUrl = '#';
            window.openDeleteTypeModal = function(id, name, page, sortQueryString) { // Changed sortPrefix to sortQueryString
                typeNameSpan.textContent = name;
                // Ensure sortQueryString starts with '&' if not empty
                 let prefix = sortQueryString && sortQueryString.length > 0 && sortQueryString.charAt(0) !== '&' ? '&' : '';
                 deleteUrl = `index.php?controller=VaccineType&action=delete&id=${id}&page=${page}${prefix}${sortQueryString}`;
                confirmCheckboxType.checked = false;
                confirmBtnType.disabled = true;
                modalInstanceType.show();
            }
            confirmCheckboxType.addEventListener('change', function() {
                confirmBtnType.disabled = !this.checked;
            });
            confirmBtnType.addEventListener('click', function() {
                if (!confirmCheckboxType.checked) return;
                window.location.href = deleteUrl;
            });
        }
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, { delay: { "show": 300, "hide": 100 }, html: true });
                }
            });

             // JS pour la modale Ajout/Modif Type Vaccin
             const vaccineTypeModal = document.getElementById('vaccineTypeFormModal');
             const modalForm = document.getElementById('modalVaccineTypeForm');
             const modalTitle = document.getElementById('vaccineTypeFormModalLabel');
             const modalTypeId = document.getElementById('modal_type_vaccin_id');
             const modalNomType = document.getElementById('modal_nom_type');
             const modalDescription = document.getElementById('modal_description');
             const modalPageInput = document.getElementById('modal_page');
             const modalSubmitButton = document.getElementById('modalSubmitButton');

             function prepareModalForAdd() {
                 modalForm.reset();
                 modalTitle.textContent = 'Ajouter un Type';
                 modalTypeId.value = '';
                 modalSubmitButton.textContent = 'Ajouter le type';
                 modalPageInput.value = <?= json_encode($page) ?>;
             }

             function prepareModalForEdit(button) {
                 modalForm.reset();
                 const data = button.dataset;
                 modalTitle.textContent = 'Modifier le type : ' + data.nom;
                 modalTypeId.value = data.id;
                 modalNomType.value = data.nom;
                 modalDescription.value = data.description;
                 modalSubmitButton.textContent = 'Enregistrer';
                 modalPageInput.value = <?= json_encode($page) ?>;
             }

             const addBtn = document.getElementById('addVaccineTypeBtn');
             if (addBtn) {
                 addBtn.addEventListener('click', prepareModalForAdd);
             }
             const addBtnDirect = document.getElementById('addVaccineTypeBtnDirect');
              if (addBtnDirect) {
                 addBtnDirect.addEventListener('click', prepareModalForAdd);
             }

             const tableBody = document.querySelector('.table tbody');
             if (tableBody) {
                 tableBody.addEventListener('click', function(event) {
                     const editButton = event.target.closest('.edit-btn');
                     if (editButton) {
                         prepareModalForEdit(editButton);
                     }
                 });
             }

             vaccineTypeModal.addEventListener('hidden.bs.modal', function () {
                 modalForm.reset();
                 modalTypeId.value = '';
             });

        });
    </script>
</body>
</html>