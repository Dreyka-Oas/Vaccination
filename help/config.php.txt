<?php

/**
 * Fichier de configuration de la base de données.
 *
 * Ce fichier définit les constantes utilisées pour établir la connexion
 * à la base de données PostgreSQL de l'application.
 * Assurez-vous que ces informations correspondent à votre environnement local
 * ou de production.
 */

/**
 * @const DB_HOST
 * Définit l'adresse (hôte) du serveur de base de données.
 * Souvent 'localhost' si la base de données est hébergée sur la même machine
 * que le serveur web.
 */
define('DB_HOST', 'localhost');

/**
 * @const DB_PORT
 * Définit le port sur lequel le serveur PostgreSQL écoute les connexions.
 * Le port par défaut pour PostgreSQL est '5432'. Changez cette valeur si
 * votre serveur utilise un port différent.
 */
define('DB_PORT', '5432');

/**
 * @const DB_NAME
 * Définit le nom de la base de données spécifique à utiliser pour cette
 * application au sein du serveur PostgreSQL.
 */
define('DB_NAME', 'vaccination_db');

/**
 * @const DB_USER
 * Définit le nom d'utilisateur requis pour s'authentifier auprès du serveur
 * de base de données et accéder à la base 'DB_NAME'.
 * Cet utilisateur doit disposer des privilèges appropriés (SELECT, INSERT, UPDATE, DELETE, etc.).
 */
define('DB_USER', 'vaccination_user');

/**
 * @const DB_PASS
 * Définit le mot de passe associé à l'utilisateur 'DB_USER' pour
 * l'authentification auprès de la base de données.
 */
define('DB_PASS', 'vaccination_password');

?>