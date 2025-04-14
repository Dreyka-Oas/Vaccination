<?php

/**
 * Espace de noms pour les classes Core de l'application.
 */
namespace App\Core;

/**
 * Classe Auth pour la gestion de l'authentification et des sessions.
 *
 * Fournit des méthodes statiques pour démarrer les sessions, vérifier si un utilisateur
 * est connecté, récupérer les informations de l'utilisateur (ID, matricule, nom, rôle),
 * vérifier les droits d'administrateur, authentifier un utilisateur après connexion,
 * déconnecter un utilisateur, et vérifier l'accès aux pages protégées.
 */
class Auth {

    /**
     * Démarre une nouvelle session ou reprend une session existante.
     *
     * Vérifie si une session est déjà active avant d'appeler session_start()
     * pour éviter les avertissements PHP. Cette méthode est appelée au début
     * de la plupart des autres méthodes de cette classe pour garantir
     * que la session est disponible.
     *
     * @return void
     */
    public static function startSession(): void {
        // Vérifie l'état actuel de la session.
        if (session_status() === PHP_SESSION_NONE) {
            // Si aucune session n'est active, démarre une nouvelle session.
            session_start();
        }
    }

    /**
     * Vérifie si un utilisateur est actuellement connecté.
     *
     * Vérifie la présence et la valeur de la clé 'authenticated' dans la session.
     *
     * @return bool True si l'utilisateur est connecté, false sinon.
     */
    public static function isLoggedIn(): bool {
        // S'assure que la session est démarrée.
        self::startSession();
        // Retourne true si la clé 'authenticated' existe et est égale à true.
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    /**
     * Récupère le rôle de l'utilisateur connecté.
     *
     * Lit la clé 'role' dans la session.
     *
     * @return string|null Le nom du rôle (ex: 'admin') ou null si non connecté ou rôle non défini.
     */
    public static function getUserRole(): ?string {
        self::startSession();
        // Retourne la valeur de $_SESSION['role'] si elle existe, sinon null.
        return $_SESSION['role'] ?? null;
    }

     /**
      * Récupère l'identifiant utilisateur (ici, le matricule) de l'utilisateur connecté.
      *
      * Lit la clé 'user_id' dans la session.
      * Note : Dans ce projet, 'user_id' semble être redondant avec 'matricule'.
      *
      * @return mixed|null La valeur de l'ID utilisateur ou null si non défini.
      */
     public static function getUserId() {
         self::startSession();
         return $_SESSION['user_id'] ?? null;
     }

     /**
      * Récupère le matricule de l'utilisateur connecté.
      *
      * Lit la clé 'matricule' dans la session.
      *
      * @return string|null Le matricule ou null si non défini.
      */
     public static function getMatricule() {
         self::startSession();
         return $_SESSION['matricule'] ?? null;
     }

      /**
       * Récupère le nom d'utilisateur de l'utilisateur connecté.
       *
       * Lit la clé 'username' dans la session.
       *
       * @return string|null Le nom d'utilisateur ou null si non défini.
       */
      public static function getUsername() {
         self::startSession();
         return $_SESSION['username'] ?? null;
     }

    /**
     * Vérifie si l'utilisateur connecté est un administrateur.
     *
     * Combine la vérification de connexion (`isLoggedIn`) et la vérification
     * que le rôle est 'admin'.
     *
     * @return bool True si l'utilisateur est connecté ET est admin, false sinon.
     */
    public static function isAdmin(): bool {
        // Vérifie si l'utilisateur est connecté ET si son rôle est exactement 'admin'.
        return self::isLoggedIn() && self::getUserRole() === 'admin';
    }

    /**
     * Authentifie un utilisateur après une connexion réussie.
     *
     * Régénère l'ID de session pour des raisons de sécurité (prévention de fixation de session),
     * puis stocke les informations essentielles de l'utilisateur (fournies dans le tableau $user)
     * dans la variable de session $_SESSION. Marque également la session comme authentifiée.
     *
     * @param array $user Tableau associatif contenant les informations de l'utilisateur
     *                    provenant de la base de données (doit inclure au moins
     *                    'matricule', 'username', 'role_name').
     * @return void
     */
    public static function authenticate(array $user): void {
        self::startSession();
        // Régénère l'ID de session pour prévenir les attaques de fixation de session.
        // Le paramètre true détruit l'ancienne session associée à l'ancien ID.
        session_regenerate_id(true);
        // Stocke les informations utilisateur dans la session.
        $_SESSION['user_id'] = $user['matricule']; // ID utilisateur (semble être le matricule).
        $_SESSION['matricule'] = $user['matricule']; // Matricule.
        $_SESSION['username'] = $user['username'];   // Nom d'utilisateur.
        $_SESSION['role'] = $user['role_name'];      // Rôle (ex: 'admin').
        // Définit le flag d'authentification.
        $_SESSION['authenticated'] = true;
    }

    /**
     * Déconnecte l'utilisateur actuel.
     *
     * Efface toutes les variables de session, détruit le cookie de session
     * s'il existe, et détruit la session côté serveur.
     *
     * @return void
     */
    public static function logout(): void {
        self::startSession();

        // Réinitialise le tableau $_SESSION.
        $_SESSION = array();

        // Si les sessions utilisent des cookies (cas le plus courant),
        // supprime également le cookie de session côté client.
        if (ini_get("session.use_cookies")) {
            // Récupère les paramètres du cookie de session actuel.
            $params = session_get_cookie_params();
            // Définit un nouveau cookie avec le même nom, mais une date d'expiration passée
            // et les mêmes paramètres (chemin, domaine, etc.) pour assurer sa suppression.
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Finalement, détruit la session côté serveur.
        session_destroy();
    }

    /**
     * Vérifie si l'utilisateur est authentifié. Si non, redirige vers une URL.
     *
     * Utilisé au début des actions de contrôleur qui nécessitent que l'utilisateur
     * soit simplement connecté (quel que soit son rôle).
     *
     * @param string $redirectUrl L'URL vers laquelle rediriger si l'utilisateur n'est pas connecté.
     *                            Par défaut, le formulaire de connexion.
     * @return void
     */
    public static function checkAuthentication(string $redirectUrl = 'index.php?controller=Auth&action=loginForm'): void {
        // Si l'utilisateur n'est PAS connecté.
        if (!self::isLoggedIn()) {
            // Définit un message flash pour informer l'utilisateur.
            Flasher::setFlash('Vous devez être connecté pour accéder à cette page.', 'warning');
            // Effectue la redirection.
            header("Location: " . $redirectUrl);
            // Arrête l'exécution du script pour éviter que le reste de l'action ne s'exécute.
            exit();
        }
    }

    /**
     * Vérifie si l'utilisateur est un administrateur. Si non, redirige vers une URL.
     *
     * Utilisé au début des actions de contrôleur qui nécessitent des privilèges
     * d'administrateur. Appelle d'abord checkAuthentication pour s'assurer
     * que l'utilisateur est connecté.
     *
     * @param string $redirectUrl L'URL vers laquelle rediriger si l'utilisateur n'est pas
     *                            connecté ou n'est pas administrateur. Par défaut, le tableau de bord.
     * @return void
     */
    public static function checkAdminAccess(string $redirectUrl = 'index.php?controller=Dashboard&action=index'): void {
        // D'abord, vérifie si l'utilisateur est connecté (redirige si besoin).
        self::checkAuthentication();
        // Ensuite, vérifie si l'utilisateur connecté a bien le rôle 'admin'.
        if (!self::isAdmin()) {
            // Si non admin, message flash d'erreur.
            Flasher::setFlash('Accès non autorisé. Vous devez être administrateur.', 'danger');
            // Redirection vers l'URL spécifiée (par défaut, le dashboard).
            header("Location: " . $redirectUrl);
            // Arrête l'exécution.
            exit();
        }
    }
}