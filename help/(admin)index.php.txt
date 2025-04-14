<?php
// Importation de la classe Auth pour potentiellement vérifier le statut de l'utilisateur,
// bien qu'elle ne soit pas explicitement utilisée dans le code HTML de cette vue spécifique.
// La vérification d'accès est gérée par le contrôleur (AdminController) avant d'inclure cette vue.
use App\Core\Auth;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Projet Vaccination</title>
    <!-- Inclusion des CSS Bootstrap, Font Awesome et personnalisée -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <!-- Aucun style CSS spécifique à cette page n'est défini ici -->
</head>
<body>
    <!-- Conteneur principal pour la page d'administration -->
    <div class="dashboard-container">
        <!-- Carte Bootstrap principale pour le contenu -->
        <div class="card">
            <!-- En-tête de la carte (avec style 'header' personnalisé) -->
            <div class="card-header header">
                 <!-- Utilisation de Flexbox pour aligner le contenu de l'en-tête -->
                 <div class="d-flex justify-content-between align-items-center">
                    <!-- Titre et sous-titre de la page d'administration -->
                    <div>
                        <h1 class="mb-1"><i class="fas fa-user-shield me-2"></i>Administration</h1>
                        <p class="mb-0 small text-white-50">Gestion des données et paramètres.</p>
                    </div>
                    <!-- Potentiellement un espace pour des infos utilisateur ou bouton déconnexion, vide ici -->
                 </div>
            </div>

            <!-- Corps de la carte -->
            <div class="card-body">
                 <!-- Conteneur pour la liste des boutons de navigation administratifs -->
                 <!-- La classe 'admin-button-list' est probablement définie dans main.css pour styliser la disposition -->
                 <div class="admin-button-list">
                    <!-- Lien vers la gestion des Campagnes -->
                    <a href="index.php?controller=Campaign&action=list" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Campagnes Vaccinales
                    </a>
                    <!-- Lien vers la gestion des Types de Vaccins -->
                    <a href="index.php?controller=VaccineType&action=list" class="btn btn-primary">
                        <i class="fas fa-tags me-2"></i>Type de Vaccins
                    </a>
                    <!-- Lien vers la gestion des Vaccins -->
                    <a href="index.php?controller=Vaccine&action=list" class="btn btn-primary">
                        <i class="fas fa-syringe me-2"></i>Vaccins
                    </a>
                    <!-- Lien vers la gestion des Lots -->
                    <a href="index.php?controller=Lot&action=list" class="btn btn-primary">
                        <i class="fas fa-box me-2"></i>Lots
                    </a>
                    <!-- Lien vers la gestion des Utilisateurs -->
                    <a href="index.php?controller=User&action=list" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i>Utilisateurs
                    </a>
                    <!-- Note: Il manque peut-être un lien vers la gestion des enregistrements (VaccinationRecord) ici -->
                 </div>
            </div>
        </div> <!-- Fin de la carte -->

        <!-- Bouton centré pour retourner au tableau de bord principal -->
        <div class="text-center my-4">
            <a href="index.php?controller=Dashboard&action=index" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Retour au Dashboard
            </a>
        </div>
    </div> <!-- Fin du conteneur principal -->

    <!-- Inclusion du JavaScript de Bootstrap (Bundle inclut Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Aucun script JS spécifique à cette page -->
</body>
</html>