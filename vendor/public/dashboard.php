<?php
session_start(); // INDISPENSABLE pour accéder à $_SESSION

// Les variables pour stocker les infos utilisateur (ou null si non connecté)
$user_matricule = $_SESSION['matricule'] ?? null;
$user_username = $_SESSION['username'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// Pas besoin de config.php ou Faker ici
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - Projet Vaccination</title>

    <!-- CSS: Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS: Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- CSS: Votre fichier de style personnalisé -->
    <link rel="stylesheet" href="css/modern-style.css"> <!-- !! Assurez-vous que ce chemin est correct !! -->

</head>
<body>

    <!-- Conteneur principal -->
    <div class="dashboard-container">

        <!-- Carte Principale pour l'en-tête et potentiellement d'autres infos -->
        <div class="card mb-4"> <!-- mb-4 pour espacer de la carte de recherche en dessous -->
            <!-- En-tête de la carte avec Titre et Infos Utilisateur/Actions -->
            <div class="card-header header">
                 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <!-- Partie Gauche: Titre -->
                    <div>
                        <h1 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>Tableau de Bord</h1>
                        <p class="mb-0 small text-white-50">Gestion des Vaccinations</p>
                    </div>
                    <!-- Partie Droite: Infos Utilisateur & Déconnexion/Connexion -->
                    <div class="text-end user-info-header">
                        <?php if ($user_matricule): ?>
                             <span class="small text-white-50 me-3">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($user_username) ?>
                                (<span class="badge bg-light text-dark"><?= htmlspecialchars($user_role) ?></span>)
                             </span>
                            <a href="logout.php" class="btn btn-danger btn-sm btn-logout" title="Se déconnecter">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-light">
                                <i class="fas fa-sign-in-alt me-1"></i> Se connecter
                            </a>
                        <?php endif; ?>
                    </div>
                 </div>
            </div>
            <!-- Corps optionnel de la carte principale (peut contenir des stats rapides, etc.) -->
            <!-- <div class="card-body"> ... Contenu supplémentaire si besoin ... </div> -->
        </div>

        <!-- Carte pour la Section de Recherche -->
        <div class="card search-card">
            <div class="card-body">
                <h2 class="card-title h4 mb-2 text-center">Rechercher une Vaccination</h2>
                <p class="card-text text-muted text-center mb-4">Consultez l'historique par matricule patient.</p>

                <form action="form_vaccination.php" method="get" class="row g-3 justify-content-center">
                    <div class="col-md-6 col-lg-5">
                        <label for="matricule_recherche" class="form-label visually-hidden">Matricule patient</label> <!-- visually-hidden si le placeholder est clair -->
                        <div class="input-group">
                             <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="matricule_recherche" name="matricule_patient" placeholder="Matricule du patient" required>
                        </div>
                    </div>
                    <div class="col-auto"> <!-- Bouton prend sa largeur naturelle -->
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Section Pied de page (Liens & Copyright) - Hors des cartes -->
        <div class="text-center my-4">
            <?php // Afficher le lien Admin seulement si l'utilisateur est connecté ET a le rôle 'admin' ?>
            <?php if ($user_role === 'admin'): ?>
                <a href="admin.php" class="btn btn-secondary mb-3">
                    <i class="fas fa-user-shield me-1"></i> Espace Administration
                </a>
            <?php endif; ?>
            <p class="small text-muted">© <?= date('Y') ?> Projet Vaccination</p>
        </div>

    </div> <!-- Fin du dashboard-container -->

    <!-- JS: Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JS spécifique (si besoin pour tooltips, etc.) -->
    <script>
        // Initialiser les tooltips Bootstrap si vous en ajoutez
         document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
               if(!bootstrap.Tooltip.getInstance(tooltipTriggerEl)){ // Prevent multiple initializations
                 return new bootstrap.Tooltip(tooltipTriggerEl);
               }
            });
         });
    </script>

</body>
</html>