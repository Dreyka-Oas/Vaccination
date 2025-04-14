<?php

/**
 * Espace de noms pour les contrôleurs de l'application.
 */
namespace App\Controller;

// Importations des classes nécessaires.
use App\Core\Auth;        // Pour gérer l'authentification, la session, et les informations utilisateur.
use App\Core\Flasher;     // Pour afficher des messages flash (notifications) à l'utilisateur.
use App\Core\Functions;   // Potentiellement pour des fonctions utilitaires (non utilisé directement ici, mais pourrait l'être).
use App\Model\Database;   // Pour obtenir une instance de la connexion à la base de données.
use PDO;                  // Pour utiliser les fonctionnalités de PDO (types de paramètres, etc.).
use PDOException;         // Pour gérer les erreurs spécifiques à PDO (connexion, requêtes).

/**
 * Contrôleur gérant l'authentification des utilisateurs.
 *
 * Ce contrôleur s'occupe de l'affichage du formulaire de connexion,
 * du traitement des données soumises lors de la tentative de connexion,
 * et de la déconnexion de l'utilisateur.
 */
class AuthController {

    /**
     * Affiche le formulaire de connexion.
     *
     * Charge simplement la vue contenant le formulaire HTML permettant
     * à l'utilisateur de saisir son matricule et son mot de passe.
     *
     * @return void
     */
    public function loginForm(): void {
        // Inclut le fichier de la vue qui contient le code HTML du formulaire.
        require_once '../app/View/auth/login.php';
    }

    /**
     * Traite les données soumises via le formulaire de connexion.
     *
     * Vérifie les informations d'identification (matricule et mot de passe)
     * fournies par l'utilisateur, les compare avec les données stockées en
     * base de données et, si elles sont correctes, établit la session
     * utilisateur. Sinon, redirige vers le formulaire avec un message d'erreur.
     *
     * @return void
     */
    public function processLogin(): void {
        // S'assure que la session est démarrée pour pouvoir utiliser Flasher et Auth.
        Auth::startSession();

        // Vérifie si la requête HTTP utilise bien la méthode POST.
        // C'est une mesure de sécurité pour s'assurer que les données proviennent
        // du formulaire et non d'une requête GET directe.
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
             // Si ce n'est pas POST, redirige vers le formulaire avec un message d'erreur.
             header("Location: index.php?controller=Auth&action=loginForm&error=" . urlencode("Accès non autorisé"));
             exit(); // Arrête l'exécution du script.
        }

        // Récupère le matricule et le mot de passe depuis les données POST.
        // Utilise l'opérateur null coalescent (??) pour éviter les erreurs si les clés n'existent pas.
        $matricule = $_POST["matricule"] ?? '';
        $password = $_POST["password"] ?? '';

        // Valide que les deux champs ne sont pas vides.
        if (empty($matricule) || empty($password)) {
            // Si l'un ou l'autre est vide, définit un message flash d'erreur.
            Flasher::setFlash("Matricule et mot de passe requis", 'danger');
            // Redirige l'utilisateur vers le formulaire de connexion.
            header("Location: index.php?controller=Auth&action=loginForm");
            exit(); // Arrête l'exécution.
        }

        // Bloc try...catch pour gérer les erreurs potentielles lors de l'interaction avec la base de données.
        try {
            // Obtient une instance de la connexion PDO à la base de données.
            $pdo = Database::getInstance();

            // Prépare la requête SQL pour récupérer l'utilisateur par son matricule.
            // Jointure avec la table 'roles' pour obtenir le nom du rôle.
            $stmt = $pdo->prepare("
                SELECT u.matricule, u.username, u.password_hash, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.role_id
                WHERE u.matricule = :matricule
            ");
            // Lie la valeur de la variable $matricule au paramètre :matricule dans la requête préparée.
            // PDO::PARAM_STR indique que la valeur est une chaîne de caractères.
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            // Exécute la requête.
            $stmt->execute();
            // Récupère l'utilisateur trouvé (ou false si aucun utilisateur n'a ce matricule).
            // PDO::FETCH_ASSOC retourne un tableau associatif (nom_colonne => valeur).
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérifie si un utilisateur a été trouvé avec ce matricule.
            if ($user) {
                 // Compare le mot de passe fourni avec le hash stocké en base de données.
                 // IMPORTANT : Utilise crypt() avec le hash stocké comme sel, car le hash contient le sel et l'algorithme.
                 // Utilise hash_equals() pour une comparaison sécurisée contre les attaques temporelles (timing attacks).
                 $isPasswordCorrect = hash_equals($user['password_hash'], crypt($password, $user['password_hash']));

                // Si le mot de passe est correct.
                if ($isPasswordCorrect) {
                    // Authentifie l'utilisateur en enregistrant ses informations dans la session.
                    Auth::authenticate($user);
                    // Redirige vers le tableau de bord principal après une connexion réussie.
                    header("Location: index.php?controller=Dashboard&action=index");
                    exit(); // Arrête l'exécution.
                } else {
                    // Si le mot de passe est incorrect, définit un message flash d'erreur.
                    Flasher::setFlash("Mot de passe incorrect", 'danger');
                    // Redirige vers le formulaire de connexion.
                    header("Location: index.php?controller=Auth&action=loginForm");
                    exit(); // Arrête l'exécution.
                }
            } else {
                // Si aucun utilisateur n'est trouvé avec ce matricule, définit un message flash d'erreur.
                Flasher::setFlash("Matricule inconnu", 'danger');
                // Redirige vers le formulaire de connexion.
                header("Location: index.php?controller=Auth&action=loginForm");
                exit(); // Arrête l'exécution.
            }

        // Capture les exceptions PDO (erreurs liées à la base de données).
        } catch (PDOException $e) {
            // Enregistre l'erreur détaillée dans les logs du serveur (important pour le débogage).
            error_log("Erreur de connexion DB : " . $e->getMessage());
             // Définit un message flash générique pour l'utilisateur.
             Flasher::setFlash("Erreur de connexion à la base de données.", 'danger');
             // Redirige vers le formulaire de connexion.
            header("Location: index.php?controller=Auth&action=loginForm");
            exit(); // Arrête l'exécution.
        }
    }

    /**
     * Déconnecte l'utilisateur actuellement authentifié.
     *
     * Appelle la méthode de déconnexion de la classe Auth pour détruire
     * la session et redirige l'utilisateur vers le formulaire de connexion.
     *
     * @return void
     */
    public function logout(): void {
        // Appelle la méthode statique logout de la classe Auth pour effacer les données de session.
        Auth::logout();
        // Redirige l'utilisateur vers le formulaire de connexion après la déconnexion.
        header("Location: index.php?controller=Auth&action=loginForm");
        exit(); // Arrête l'exécution du script.
    }
}