<?php

/**
 * Espace de noms pour les classes du Modèle de l'application.
 */
namespace App\Model;

// Importations des classes PDO et PDOException globales.
use PDO;
use PDOException;

/**
 * Classe Database pour gérer la connexion à la base de données.
 *
 * Implémente le pattern Singleton pour garantir qu'une seule instance
 * de la connexion PDO est créée et utilisée tout au long de l'exécution
 * de la requête. Cela optimise les ressources et assure la cohérence.
 * La connexion est configurée pour PostgreSQL en utilisant les constantes
 * définies dans le fichier de configuration (config.php).
 */
class Database {
    /**
     * Stocke l'instance unique de la classe Database (Singleton).
     * Initialisée à null.
     * @var Database|null
     */
    private static $instance = null;

    /**
     * Stocke l'objet de connexion PDO.
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur privé pour empêcher l'instanciation directe depuis l'extérieur (Singleton).
     *
     * Établit la connexion PDO à la base de données PostgreSQL en utilisant les
     * constantes globales (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS)
     * définies dans le fichier de configuration.
     * Configure les options PDO pour la gestion des erreurs, le mode de récupération
     * par défaut et la désactivation de l'émulation des requêtes préparées.
     * Gère les erreurs de connexion PDO.
     *
     * @throws PDOException Si la connexion à la base de données échoue.
     */
    private function __construct() {

        // Construit la chaîne DSN (Data Source Name) pour PostgreSQL.
        // Utilise les constantes globales définies dans config.php.
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;

        // Définit les options de configuration pour la connexion PDO.
        $options = [
            // Mode de gestion des erreurs : Lève des exceptions PDOException en cas d'erreur.
            // C'est le mode recommandé pour une gestion robuste des erreurs.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Mode de récupération par défaut : Récupère les lignes sous forme de tableaux associatifs
            // (nom_colonne => valeur) par défaut lors de l'utilisation de fetch() ou fetchAll().
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Désactive l'émulation des requêtes préparées par PDO.
            // Force l'utilisation des requêtes préparées natives du SGBD (PostgreSQL ici),
            // ce qui est généralement plus sûr et parfois plus performant.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Tente d'établir la connexion PDO.
        try {
            // Instancie PDO avec le DSN, l'utilisateur, le mot de passe et les options.
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        // Capture une éventuelle PDOException si la connexion échoue.
        } catch (PDOException $e) {

            // Enregistre l'erreur de connexion détaillée dans les logs du serveur.
            // Ne pas afficher l'erreur brute à l'utilisateur final pour des raisons de sécurité.
            error_log('Connection Error: ' . $e->getMessage());

            // Relance l'exception pour signaler l'échec de la connexion
            // à la partie de l'application qui a tenté d'obtenir l'instance.
            // Cela provoquera une erreur fatale si non capturée plus haut.
            throw new PDOException("Erreur de connexion à la base de données. Vérifiez les logs.", (int)$e->getCode());
             // Note: Le message d'erreur public est générique. L'erreur détaillée est dans les logs.
        }
    }

    /**
     * Méthode statique pour obtenir l'instance unique de la connexion PDO (Singleton).
     *
     * C'est le point d'accès public pour obtenir l'objet PDO. Si l'instance
     * de la classe Database n'a pas encore été créée, elle l'instancie
     * (ce qui établit la connexion via le constructeur privé). Ensuite,
     * retourne l'objet PDO stocké dans cette instance.
     *
     * @return PDO L'instance unique de l'objet PDO connectée à la base de données.
     */
    public static function getInstance(): PDO {
        // Si l'instance statique $instance n'a pas encore été initialisée.
        if (self::$instance === null) {
            // Crée une nouvelle instance de la classe Database.
            // Cela appelle le constructeur privé __construct() qui établit la connexion PDO.
            self::$instance = new self();
        }
        // Retourne l'objet PDO stocké dans la propriété $pdo de l'instance unique.
        return self::$instance->pdo;
    }

    /**
     * Empêche le clonage de l'instance (Singleton).
     * Le déclarer en privé avec une méthode vide empêche `clone $dbInstance;`.
     * @return void
     */
    private function __clone() {}

    /**
     * Empêche la désérialisation de l'instance (Singleton).
     * Le déclarer public (ou privé) avec une méthode vide empêche `unserialize($serializedDbInstance);`.
     * @return void
     */
    public function __wakeup() {}
}