<?php
session_start(); // Démarre ou reprend une session existante

// Inclusion du fichier de configuration pour les informations de connexion à la DB
// Assurez-vous que le chemin relatif '../app/config/config.php' est correct
// par rapport à l'emplacement de ce fichier (process_login.php)
require_once '../app/config/config.php';

// Vérifier si la requête HTTP utilise la méthode POST (formulaire soumis)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Récupérer le matricule et le mot de passe depuis les données POST
    // Il est recommandé d'ajouter une validation/nettoyage ici si nécessaire
    $matricule = $_POST["matricule"];
    $password = $_POST["password"];

    // Vérifier si les champs ne sont pas vides (validation minimale)
    if (empty($matricule) || empty($password)) {
        // Rediriger vers login.php avec un message d'erreur si un champ est vide
        header("Location: login.php?error=" . urlencode("Matricule et mot de passe requis"));
        exit(); // Arrêter l'exécution du script
    }

    try {
        // Tentative de connexion à la base de données PostgreSQL en utilisant PDO
        // Les constantes (DB_HOST, DB_PORT, etc.) viennent de config.php
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);

        // Configurer PDO pour qu'il lance des exceptions en cas d'erreur SQL
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Préparer la requête SQL pour sélectionner l'utilisateur par son matricule
        // Il est crucial de sélectionner la colonne password_hash pour la vérification
        $stmt = $pdo->prepare("
            SELECT u.matricule, u.username, u.password_hash, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.role_id 
            WHERE u.matricule = :matricule
        ");


        // Lier la valeur du matricule au paramètre :matricule dans la requête préparée
        // Cela prévient les injections SQL
        $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);

        // Exécuter la requête
        $stmt->execute();

        // Récupérer le résultat sous forme de tableau associatif
        // fetch() retourne l'utilisateur ou `false` s'il n'est pas trouvé
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérifier si un utilisateur a été trouvé avec ce matricule
        if ($user) {
            // Utilisateur trouvé, maintenant vérifier le mot de passe

            // --- VÉRIFICATION DU MOT DE PASSE (POINT IMPORTANT) ---
            // Compare le hash stocké dans la base de données ($user['password_hash'])
            // avec le hash du mot de passe fourni ($password).
            // La fonction crypt() est utilisée ici car vous avez inséré le mot de passe
            // avec crypt() et gen_salt().
            // IMPORTANT : On passe le HASH COMPLET stocké ($user['password_hash'])
            // comme deuxième argument à crypt(). La fonction extrait automatiquement
            // le sel et l'algorithme de ce hash pour effectuer la comparaison.
            // hash_equals() est utilisé pour une comparaison sécurisée contre les attaques temporelles.
            $isPasswordCorrect = hash_equals($user['password_hash'], crypt($password, $user['password_hash']));

            if ($isPasswordCorrect) {
                // Le mot de passe est correct : Authentification réussie !

                // Régénérer l'ID de session pour prévenir la fixation de session
                session_regenerate_id(true);

                // Stocker les informations essentielles de l'utilisateur dans la session
                $_SESSION['user_id'] = $user['matricule']; // Utiliser matricule comme ID unique
                $_SESSION['matricule'] = $user['matricule'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['authenticated'] = true; // Marqueur d'authentification

                // Rediriger l'utilisateur vers le tableau de bord ou la page principale après connexion
                header("Location: dashboard.php"); // Assurez-vous que dashboard.php existe
                exit(); // Arrêter l'exécution du script après la redirection
            } else {
                // Le mot de passe est incorrect
                // Rediriger vers login.php avec un message d'erreur
                header("Location: login.php?error=" . urlencode("Mot de passe incorrect"));
                exit(); // Arrêter l'exécution du script
            }
        } else {
            // Aucun utilisateur trouvé avec ce matricule
            // Rediriger vers login.php avec un message d'erreur
            header("Location: login.php?error=" . urlencode("Matricule inconnu"));
            exit(); // Arrêter l'exécution du script
        }

    } catch (PDOException $e) {
        // Une erreur s'est produite lors de la connexion ou de l'exécution de la requête SQL
        // En production, il est préférable de loguer cette erreur plutôt que de l'afficher.
        // error_log("Erreur de connexion DB : " . $e->getMessage());

        // Rediriger vers login.php avec un message d'erreur générique (ou détaillé pour le debug)
         header("Location: login.php?error=" . urlencode("Erreur de connexion à la base de données"));
        // Pour le débogage, vous pourriez inclure le message d'erreur :
        // header("Location: login.php?error=" . urlencode("Erreur DB: " . $e->getMessage()));
        exit(); // Arrêter l'exécution du script
    } finally {
        // Assurer que la connexion est fermée (même si PHP le fait souvent automatiquement à la fin du script)
        $pdo = null;
    }

} else {
    // La requête n'est pas POST (accès direct à l'URL process_login.php, méthode GET, etc.)
    // Rediriger vers la page de connexion avec un message d'erreur
    header("Location: login.php?error=" . urlencode("Accès non autorisé"));
    exit(); // Arrêter l'exécution du script
}
?>