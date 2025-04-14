<?php
// 1. Démarrer la session (INDISPENSABLE pour la manipuler)
// Doit être appelé AVANT toute sortie HTML ou manipulation de session.
session_start();

// 2. Supprimer toutes les variables de session
// Vide le tableau $_SESSION.
$_SESSION = array();

// 3. Détruire la session côté serveur
// Supprime le fichier de session ou l'entrée de base de données.
session_destroy();

// 4. (Optionnel mais recommandé) Supprimer le cookie de session côté client
// Cela force le navigateur à oublier l'ID de session.
// Il faut s'assurer que les paramètres (path, domain) correspondent à ceux
// utilisés lors de la création du cookie (souvent le path '/' par défaut).
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Expire dans le passé
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Rediriger l'utilisateur vers la page d'accueil (ou de connexion)
// Après la déconnexion, l'utilisateur n'a plus rien à faire sur logout.php.
header("Location: login.php"); // Redirige vers votre page d'accueil
exit; // TRÈS IMPORTANT: Arrête l'exécution du script immédiatement après la redirection.
       // Empêche l'exécution de code supplémentaire ou l'affichage de contenu indésirable.
?>