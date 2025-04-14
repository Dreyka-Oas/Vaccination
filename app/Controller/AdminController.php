<?php

/**
 * Espace de noms pour les contrôleurs de l'application.
 */
namespace App\Controller;

// Importations des classes nécessaires depuis le Core de l'application.
use App\Core\Auth;   // Pour la gestion de l'authentification et des accès.
use App\Core\Flasher; // Bien qu'il ne soit pas utilisé dans cette méthode spécifique,
                     // il est souvent utile dans les contrôleurs pour les messages flash.

/**
 * Contrôleur pour la section principale de l'administration.
 *
 * Gère l'affichage de la page d'accueil de la section administrative,
 * qui sert généralement de menu ou de point d'entrée vers les autres
 * fonctionnalités réservées aux administrateurs.
 */
class AdminController {

    /**
     * Affiche la page d'index de la section administration.
     *
     * Cette méthode est typiquement appelée lorsqu'un utilisateur accède à la
     * page principale de l'administration (par exemple via index.php?controller=Admin&action=index).
     * Elle vérifie d'abord si l'utilisateur a les droits d'administrateur.
     * Si oui, elle récupère quelques informations sur l'utilisateur connecté et
     * charge la vue correspondante.
     *
     * @return void
     */
    public function index(): void {
        // Étape 1: Vérifier si l'utilisateur est connecté ET est administrateur.
        // Si l'une de ces conditions n'est pas remplie, la méthode checkAdminAccess()
        // redirigera automatiquement l'utilisateur (par exemple, vers la page de connexion
        // ou le tableau de bord standard) et arrêtera l'exécution du script ici.
        Auth::checkAdminAccess();

        // Étape 2: Récupérer les informations de l'utilisateur connecté depuis la session.
        // Ces informations pourraient être utilisées dans la vue (par exemple, pour afficher
        // "Bonjour [username]" ou adapter le contenu selon le rôle, bien que dans ce cas,
        // le rôle soit déjà vérifié comme étant 'admin').
        $user_matricule = Auth::getMatricule();  // Récupère le matricule de l'admin connecté.
        $user_username = Auth::getUsername();    // Récupère le nom d'utilisateur de l'admin.
        $user_role = Auth::getUserRole();        // Récupère le rôle (devrait être 'admin' ici).

        // Étape 3: Charger et afficher la vue correspondante.
        // La vue contient le HTML de la page d'accueil de l'administration,
        // probablement avec des liens vers les différentes sections de gestion
        // (Campagnes, Utilisateurs, Vaccins, etc.).
        // Les variables $user_matricule, $user_username, $user_role sont disponibles
        // dans le scope de ce fichier vue s'il en a besoin.
        require_once '../app/View/admin/index.php';
    }
}