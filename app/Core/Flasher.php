<?php

/**
 * Espace de noms pour les classes Core de l'application.
 */
namespace App\Core;

/**
 * Classe Flasher pour la gestion des messages flash et des données de session temporaires.
 *
 * Un message "flash" est un message stocké en session qui est destiné à être affiché
 * une seule fois à l'utilisateur, généralement sur la page suivante après une redirection
 * (par exemple, pour confirmer une action réussie ou signaler une erreur).
 * Cette classe fournit également des méthodes génériques pour stocker et récupérer
 * des données de session qui ne doivent persister que pour une seule requête suivante.
 */
class Flasher {

    /**
     * Définit un message flash à afficher lors de la prochaine requête.
     *
     * Stocke le message et son type (ex: 'success', 'danger', 'warning', 'info')
     * dans une clé spécifique de la session ('flash_message').
     * S'assure que la session est démarrée avant d'accéder à $_SESSION.
     *
     * @param string $message Le contenu du message à afficher.
     * @param string $type    Le type de message (utilisé pour la classe CSS de l'alerte Bootstrap,
     *                        par défaut 'info').
     * @return void
     */
    public static function setFlash(string $message, string $type = 'info'): void {
        // S'assure que la session est démarrée.
        Auth::startSession();
        // Stocke le message et le type dans un tableau sous la clé 'flash_message'.
        // Écrase tout message flash précédent qui n'aurait pas été affiché.
        $_SESSION['flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
    }

    /**
     * Affiche le message flash s'il en existe un et le supprime de la session.
     *
     * Vérifie si un message flash existe dans la session. Si oui, génère le code HTML
     * pour une alerte Bootstrap en utilisant le message et le type stockés.
     * Après avoir récupéré les informations, supprime le message de la session
     * pour qu'il ne soit pas affiché à nouveau.
     *
     * @return void
     */
    public static function displayFlash(): void {
        // S'assure que la session est démarrée.
        Auth::startSession();
        // Vérifie si la clé 'flash_message' existe dans la session.
        if (isset($_SESSION['flash_message'])) {
            // Récupère le message et le type.
            $message = $_SESSION['flash_message']['message'];
            $type = $_SESSION['flash_message']['type'];
            // Supprime immédiatement le message de la session pour éviter
            // qu'il soit réaffiché (même si le script plante plus tard).
            unset($_SESSION['flash_message']);

            // Génère le code HTML de l'alerte Bootstrap.
            // Utilise htmlspecialchars() pour prévenir les attaques XSS via le type ou le message.
            // Utilise nl2br() pour convertir les sauts de ligne du message en balises <br>.
            echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show m-3" role="alert">';
            echo nl2br(htmlspecialchars($message));
            // Ajoute un bouton de fermeture standard de Bootstrap.
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
    }

    /**
     * Définit une donnée de session temporaire sous une clé spécifique.
     *
     * Permet de stocker n'importe quelle donnée ($value) dans la session
     * sous une clé ($key) donnée. Cette donnée persistera jusqu'à ce qu'elle
     * soit explicitement supprimée ou consommée via `consumeSessionData`.
     * Utile pour passer des données spécifiques entre requêtes sans utiliser
     * le mécanisme des messages flash standard.
     *
     * @param string $key   La clé sous laquelle stocker la donnée dans $_SESSION.
     * @param mixed  $value La donnée à stocker (peut être de n'importe quel type sérialisable).
     * @return void
     */
    public static function setSessionData(string $key, $value): void {
        Auth::startSession();
        $_SESSION[$key] = $value;
    }

    /**
     * Récupère et supprime une donnée de session temporaire.
     *
     * Tente de récupérer la valeur stockée sous la clé $key dans la session.
     * Si la clé existe, sa valeur est retournée et la clé est supprimée de la session.
     * Si la clé n'existe pas, retourne null.
     * Permet de lire une donnée une seule fois ("consommation").
     *
     * @param string $key La clé de la donnée à récupérer et supprimer.
     * @return mixed|null La valeur de la donnée si elle existait, sinon null.
     */
    public static function consumeSessionData(string $key) {
        Auth::startSession();
        $value = null; // Valeur par défaut si la clé n'existe pas.
        // Vérifie si la clé existe dans la session.
        if (isset($_SESSION[$key])) {
            $value = $_SESSION[$key]; // Récupère la valeur.
            unset($_SESSION[$key]);   // Supprime la clé de la session.
        }
        return $value; // Retourne la valeur récupérée ou null.
    }
}