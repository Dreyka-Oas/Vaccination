<?php
/**
 * Point d'entrée principal de l'application (Front Controller).
 *
 * Ce script est responsable de :
 * - L'initialisation de l'environnement (erreurs, autoloading, config).
 * - Le démarrage de la session.
 * - Le routage basique des requêtes vers le contrôleur et l'action appropriés.
 * - L'instanciation du contrôleur et l'appel de la méthode d'action.
 * - La gestion des erreurs de base (fichier/classe/méthode non trouvé, exceptions).
 */

// ---- START: Activation du rapport d'erreurs détaillé pour le développement ----
// Affiche les erreurs à l'écran. Utile en développement, à désactiver en production.
ini_set('display_errors', 1);
// Affiche les erreurs qui se produisent au démarrage de PHP.
ini_set('display_startup_errors', 1);
// Définit le niveau de rapport d'erreurs pour afficher toutes les erreurs, avertissements, et notices.
error_reporting(E_ALL);
// ---- END: Activation du rapport d'erreurs ----

// Inclusion de l'autoloader de Composer.
// Charge automatiquement les classes des dépendances (ex: Faker) et de l'application (si configuré dans composer.json).
require_once '../vendor/autoload.php';
// Inclusion du fichier de configuration principal de l'application (contient les constantes DB, etc.).
require_once '../app/config/config.php';

// Importation des classes du Core nécessaires.
use App\Core\Auth;    // Pour la gestion des sessions.
use App\Core\Flasher; // Pour afficher des messages d'erreur à l'utilisateur en cas de problème de routage.

// Démarre la session PHP. Essentiel pour Auth et Flasher.
Auth::startSession();

// --- Détermination du Contrôleur et de l'Action ---

// Récupère le nom du contrôleur depuis le paramètre GET 'controller'.
// Met la première lettre en majuscule et ajoute 'Controller' (convention).
// Si le paramètre n'existe pas, utilise 'DashboardController' par défaut.
$controllerName = isset($_GET['controller']) ? ucfirst($_GET['controller']) . 'Controller' : 'DashboardController';

// Récupère le nom de l'action depuis le paramètre GET 'action'.
// Si le paramètre n'existe pas, utilise 'index' par défaut.
$actionName = isset($_GET['action']) ? $_GET['action'] : 'index';

// --- Chargement et Exécution du Contrôleur/Action ---

// Construit le chemin vers le fichier du contrôleur attendu.
$controllerFile = '../app/Controller/' . $controllerName . '.php';

// Vérifie si le fichier du contrôleur existe physiquement.
if (file_exists($controllerFile)) {
    // Si oui, inclut le fichier pour rendre la classe disponible.
    require_once $controllerFile;

    // Construit le nom complet de la classe avec son namespace.
    $fullControllerName = 'App\\Controller\\' . $controllerName; // Notez le double backslash pour l'échappement dans une chaîne.

    // Vérifie si la classe du contrôleur existe bien (après inclusion du fichier).
    if (class_exists($fullControllerName)) {
        // Si oui, instancie le contrôleur.
        $controller = new $fullControllerName();

        // Vérifie si la méthode correspondant à l'action demandée existe dans l'objet contrôleur.
        if (method_exists($controller, $actionName)) {
            // Si oui, tente d'exécuter la méthode d'action dans un bloc try...catch.
            try {
                // Appelle la méthode sur l'instance du contrôleur.
                // C'est ici que la logique principale de la requête est exécutée.
                $controller->$actionName();
            } catch (\Exception $e) { // Capture toute exception non gérée qui pourrait survenir dans le contrôleur/action.
                // Enregistre l'erreur détaillée dans les logs du serveur.
                error_log("Erreur dans le contrôleur/action: " . $e->getMessage() . "\n" . $e->getTraceAsString()); // Ajout de la trace pour débogage
                // Définit un message flash générique pour l'utilisateur.
                Flasher::setFlash("Une erreur interne est survenue. Veuillez réessayer ou contacter l'administrateur.", 'danger');
                // Redirige vers la page d'accueil (ou une page d'erreur dédiée).
                header("Location: index.php");
                exit(); // Arrête l'exécution.
            }
        } else {
            // Si la méthode d'action n'existe pas dans le contrôleur.
            // Définit un message flash d'erreur.
            Flasher::setFlash("Action '{$actionName}' non trouvée dans le contrôleur '{$controllerName}'.", 'danger');
            // Redirige vers la page d'accueil.
            header("Location: index.php");
            exit(); // Arrête l'exécution.
        }
    } else {
        // Si la classe du contrôleur n'existe pas (même si le fichier existe).
        Flasher::setFlash("Classe contrôleur '{$fullControllerName}' non trouvée.", 'danger');
        header("Location: index.php");
        exit(); // Arrête l'exécution.
    }
} else {
    // Si le fichier du contrôleur n'existe pas.
    Flasher::setFlash("Fichier contrôleur '{$controllerFile}' introuvable.", 'danger');
    header("Location: index.php");
    exit(); // Arrête l'exécution.
}