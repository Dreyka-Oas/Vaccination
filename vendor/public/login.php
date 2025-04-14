<?php
// Vous pourriez vouloir démarrer la session ici si vous affichez
// des messages flash après une tentative échouée, par exemple.
// session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Projet Vaccination</title>
    <!-- CSS Bootstrap via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS Font Awesome via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- CSS Personnalisé -->
    <link rel="stylesheet" href="css/main.css"> <!-- Assurez-vous que ce chemin est correct -->
    <style>
        /* Optionnel : Assurer que le login container prend toute la hauteur */
        /* body, html { height: 100%; } */
        /* .login-container { min-height: 100%; } */
        /* Note: ces styles sont déjà dans _login.css, donc pas forcément nécessaires ici */
    </style>
</head>
<body>
    <!-- Le conteneur .login-container est défini dans _login.css pour centrer -->
    <div class="login-container">
        <!-- Utilisation de .card pour le style de base et .login-card pour la largeur max -->
        <div class="card login-card">
            <!-- Utilisation de .card-body pour le padding intérieur -->
            <div class="card-body p-lg-5"> <!-- Padding plus grand sur larges écrans -->

                <!-- En-tête spécifique au login (défini dans _login.css) -->
                <div class="login-header">
                    <i class="fas fa-shield-virus fa-3x mb-3 text-primary"></i> <!-- Icône illustrative -->
                    <h2>Connexion Sécurisée</h2>
                    <p class="text-muted">Accédez à votre espace Projet Vaccination.</p>
                </div>

                <!-- Formulaire de connexion -->
                <form action="process_login.php" method="post" class="login-form">
                    <?php
                    // Afficher un message d'erreur (style Bootstrap) s'il y en a un dans l'URL
                    if (isset($_GET['error'])) {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                        echo '<i class="fas fa-exclamation-triangle me-2"></i>'; // Icône d'erreur
                        echo htmlspecialchars(urldecode($_GET['error'])); // Décoder l'URL si nécessaire
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                        echo '</div>';
                    }
                    // Vous pouvez aussi utiliser des messages flash via $_SESSION ici
                    // if (isset($_SESSION['error_message'])) { ... unset($_SESSION['error_message']); }
                    ?>

                    <!-- Champ Matricule -->
                    <div class="mb-3"> <!-- Marge en bas -->
                        <label for="matricule" class="form-label">Matricule</label>
                        <!-- Utilisation de Input Group Bootstrap pour l'icône -->
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span> <!-- Icône -->
                            <input type="text" class="form-control form-control-lg" id="matricule" name="matricule" placeholder="Votre matricule" required>
                        </div>
                    </div>

                    <!-- Champ Mot de passe -->
                    <div class="mb-4"> <!-- Marge un peu plus grande avant le bouton -->
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock fa-fw"></i></span> <!-- Icône -->
                            <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Votre mot de passe" required>
                        </div>
                        <!-- Optionnel: Lien Mot de passe oublié -->
                        <!-- <div class="text-end mt-1">
                            <a href="#" class="small forgot-password">Mot de passe oublié ?</a>
                        </div> -->
                    </div>

                    <!-- Bouton de connexion -->
                    <div class="d-grid"> <!-- d-grid pour bouton pleine largeur -->
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                        </button>
                    </div>

                     <!-- Optionnel: Lien d'inscription -->
                     <!-- <p class="text-center small mt-4">Pas encore de compte ? <a href="register.php">Inscrivez-vous</a></p> -->

                </form>
            </div> <!-- Fin card-body -->
        </div> <!-- Fin card -->
    </div> <!-- Fin login-container -->

    <!-- JavaScript Bootstrap Bundle (inclus Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activer la fermeture des alertes (si utilisées)
        var alertList = document.querySelectorAll('.alert');
        alertList.forEach(function (alert) {
             new bootstrap.Alert(alert);
        });
    </script>
</body>
</html>