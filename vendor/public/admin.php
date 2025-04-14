<?php
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['matricule']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); // Redirection si non autorisé
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Projet Vaccination</title>

    <!-- Dépendances CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css"> <!-- Assurez-vous que ce chemin est correct -->
    
</head>
<body>

    <div class="dashboard-container">
        <div class="card">
            <div class="card-header header">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-1"><i class="fas fa-user-shield me-2"></i>Administration</h1>
                        <p class="mb-0 small text-white-50">Gestion des données et paramètres.</p>
                    </div>
                 </div>
            </div>

            <div class="card-body">
                 <div class="admin-button-list">
                    <a href="gestion/gerer_campagnes.php" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Campagnes Vaccinales
                    </a>
                    <a href="gestion/gerer_type_vaccins.php" class="btn btn-primary">
                        <i class="fas fa-tags me-2"></i>Type de Vaccins
                    </a>
                    <a href="gestion/gerer_vaccins.php" class="btn btn-primary">
                        <i class="fas fa-syringe me-2"></i>Vaccins
                    </a>
                    <a href="gestion/gerer_lots.php" class="btn btn-primary">
                        <i class="fas fa-box me-2"></i>Lots
                    </a>
                    <a href="gestion/gerer_utilisateurs.php" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i>Utilisateurs <!-- Bouton ajouté ici -->
                    </a>
                 </div>
            </div>
            <!-- Pied de carte optionnel -->
            <!--
            <div class="card-footer text-center">
                 <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Retour au Dashboard</a>
            </div>
            -->
        </div> <!-- Fin card -->

        <!-- Bouton Retour hors de la carte -->
        <div class="text-center my-4">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Retour au Dashboard
            </a>
        </div>

    </div> <!-- Fin dashboard-container -->

    <!-- Dépendances JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>