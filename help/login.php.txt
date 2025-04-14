<?php
// Importation de la classe Flasher pour afficher les messages d'erreur/succès
use App\Core\Flasher;
// Note: Cette vue est affichée par AuthController::loginForm()
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Projet Vaccination</title>
    <!-- Inclusion CSS Bootstrap, Font Awesome et personnalisé -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <!-- Aucun style spécifique ici, probablement définis dans main.css (.login-container, .login-card, etc.) -->
</head>
<body>

    <!-- Conteneur principal pour centrer le formulaire de connexion -->
    <div class="login-container">

        <!-- Carte Bootstrap contenant le formulaire -->
        <div class="card login-card">

            <!-- Corps de la carte avec padding large sur grand écran (p-lg-5) -->
            <div class="card-body p-lg-5">

                <!-- En-tête du formulaire de connexion -->
                <div class="login-header text-center"> <!-- Ajout de text-center pour centrer le contenu -->
                    <!-- Icône thématique -->
                    <i class="fas fa-shield-virus fa-3x mb-3 text-primary"></i>
                    <!-- Titre principal -->
                    <h2>Connexion Sécurisée</h2>
                    <!-- Sous-titre -->
                    <p class="text-muted">Accédez à votre espace Projet Vaccination.</p>
                </div>

                <!-- Formulaire de connexion -->
                <!-- method="post" : envoie les données de manière sécurisée -->
                <!-- action : URL de traitement (AuthController::processLogin) -->
                <form action="index.php?controller=Auth&action=processLogin" method="post" class="login-form">
                    <?php
                        // Affiche ici les messages flash (par exemple, "Matricule inconnu", "Mot de passe incorrect")
                        Flasher::displayFlash();
                    ?>

                    <!-- Champ Matricule -->
                    <div class="mb-3">
                        <label for="matricule" class="form-label">Matricule</label>
                        <!-- Utilisation d'un input-group Bootstrap pour ajouter une icône -->
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span>
                            <!-- Champ de saisie texte pour le matricule -->
                            <!-- required : validation HTML5 pour champ obligatoire -->
                            <!-- form-control-lg : champ plus grand -->
                            <input type="text" class="form-control form-control-lg" id="matricule" name="matricule" placeholder="Votre matricule" required>
                        </div>
                    </div>

                    <!-- Champ Mot de passe -->
                    <div class="mb-4">
                        <label for="password" class="form-label">Mot de passe</label>
                        <!-- Input-group avec icône -->
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock fa-fw"></i></span>
                            <!-- Champ de saisie de type password (masque la saisie) -->
                            <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Votre mot de passe" required>
                        </div>
                    </div>

                    <!-- Bouton de soumission -->
                    <!-- d-grid : force le bouton à prendre toute la largeur -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                        </button>
                    </div>

                </form> <!-- Fin du formulaire -->
            </div> <!-- Fin card-body -->
        </div> <!-- Fin card -->
    </div> <!-- Fin login-container -->

    <!-- Inclusion JS Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script simple pour initialiser les alertes Bootstrap (utilisé par Flasher::displayFlash) -->
    <!-- Permet aux alertes d'être fermables via le bouton 'x' -->
    <script>
        // Sélectionne toutes les alertes affichées sur la page
        var alertList = document.querySelectorAll('.alert');
        // Pour chaque alerte trouvée...
        alertList.forEach(function (alert) {
             // ...crée une nouvelle instance d'Alerte Bootstrap pour la rendre interactive
             new bootstrap.Alert(alert);
        });
    </script>
</body>
</html>