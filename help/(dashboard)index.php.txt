<?php
// Importation des classes Core nécessaires
use App\Core\Auth;    // Pour vérifier le rôle et afficher les infos utilisateur
use App\Core\Flasher; // Pour afficher les messages flash
// Les variables $user_matricule, $user_username, $user_role sont fournies
// par le contrôleur (DashboardController::index) après vérification de l'authentification.
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - Projet Vaccination</title>
    <!-- Inclusion CSS Bootstrap, Font Awesome et personnalisé -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <!-- Aucun style spécifique ici, probablement dans main.css -->
</head>
<body>
    <!-- Conteneur principal du tableau de bord -->
    <div class="dashboard-container">
        <!-- Carte pour l'en-tête de page -->
        <div class="card mb-4">
            <div class="card-header header"> <!-- Style d'en-tête personnalisé -->
                 <!-- Flexbox pour aligner titre et infos utilisateur -->
                 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <!-- Titre principal et sous-titre -->
                    <div>
                        <h1 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>Tableau de Bord</h1>
                        <p class="mb-0 small text-white-50">Gestion des Vaccinations</p>
                    </div>
                    <!-- Section infos utilisateur et déconnexion -->
                    <div class="text-end user-info-header">
                        <?php // Vérifie si l'utilisateur est connecté (la variable $user_matricule est définie) ?>
                        <?php if ($user_matricule): ?>
                             <!-- Affiche le nom et le rôle de l'utilisateur -->
                             <span class="small text-white-50 me-3">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($user_username) ?>
                                (<span class="badge bg-light text-dark"><?= htmlspecialchars($user_role) ?></span>)
                             </span>
                            <!-- Bouton de déconnexion -->
                            <a href="index.php?controller=Auth&action=logout" class="btn btn-danger btn-sm btn-logout" title="Se déconnecter">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        <?php else: // Si l'utilisateur n'est pas connecté (ne devrait pas arriver si checkAuthentication est utilisé) ?>
                            <!-- Bouton pour aller à la page de connexion -->
                            <a href="index.php?controller=Auth&action=loginForm" class="btn btn-light">
                                <i class="fas fa-sign-in-alt me-1"></i> Se connecter
                            </a>
                        <?php endif; ?>
                    </div>
                 </div>
            </div>
        </div> <!-- Fin de la carte en-tête -->

        <!-- Carte pour la fonctionnalité de recherche de vaccination -->
        <div class="card search-card">
            <div class="card-body">
                <!-- Titre et description de la section de recherche -->
                <h2 class="card-title h4 mb-2 text-center">Rechercher une Vaccination</h2>
                <p class="card-text text-muted text-center mb-4">Consultez l'historique par matricule patient.</p>
                 <?php // Affiche les messages flash (peut être utile si une recherche échoue via redirection) ?>
                 <?php Flasher::displayFlash(); ?>

                <!-- Formulaire de recherche -->
                <!-- method="get" : les paramètres seront dans l'URL -->
                <!-- action : redirige vers la page de gestion des enregistrements (VaccinationRecordController::form) -->
                <form action="index.php" method="get" class="row g-3 justify-content-center">
                    <!-- Champs cachés pour spécifier le contrôleur et l'action cible -->
                    <input type="hidden" name="controller" value="VaccinationRecord">
                    <input type="hidden" name="action" value="form">
                    <!-- Colonne pour le champ de saisie du matricule -->
                    <div class="col-md-6 col-lg-5">
                        <!-- Label masqué visuellement mais utile pour l'accessibilité -->
                        <label for="matricule_recherche" class="form-label visually-hidden">Matricule patient</label>
                        <!-- Input group Bootstrap avec icône -->
                        <div class="input-group">
                             <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                             <!-- Champ de saisie pour le matricule -->
                             <!-- Le name="matricule_patient" sera utilisé par VaccinationRecordController::form -->
                            <input type="text" class="form-control" id="matricule_recherche" name="matricule_patient" placeholder="Matricule du patient" required>
                        </div>
                    </div>
                    <!-- Colonne pour le bouton de recherche -->
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Rechercher
                        </button>
                    </div>
                </form> <!-- Fin du formulaire de recherche -->
            </div>
        </div> <!-- Fin de la carte de recherche -->

        <!-- Section pied de page avec lien Administration (si admin) et copyright -->
        <div class="text-center my-4">
            <?php // Affiche le lien vers l'administration seulement si l'utilisateur a le rôle 'admin' ?>
            <?php if ($user_role === 'admin'): ?>
                <a href="index.php?controller=Admin&action=index" class="btn btn-secondary mb-3">
                    <i class="fas fa-user-shield me-1"></i> Espace Administration
                </a>
            <?php endif; ?>
            <!-- Copyright -->
            <p class="small text-muted">© <?= date('Y') ?> Projet Vaccination</p>
        </div>
    </div> <!-- Fin du conteneur principal -->

    <!-- JS Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script pour initialiser les tooltips Bootstrap (utilisé pour le bouton Déconnexion) -->
    <script>
         document.addEventListener('DOMContentLoaded', function () {
            // Sélectionne tous les éléments avec l'attribut data-bs-toggle="tooltip"
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            // Initialise un tooltip Bootstrap pour chaque élément trouvé
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
               // Vérifie si un tooltip n'est pas déjà initialisé pour éviter les doublons
               if(!bootstrap.Tooltip.getInstance(tooltipTriggerEl)){
                 return new bootstrap.Tooltip(tooltipTriggerEl);
               }
            });
         });
    </script>
</body>
</html>