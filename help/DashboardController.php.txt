<?php

/**
 * Espace de noms pour les contrôleurs de l'application.
 */
namespace App\Controller;

// Importations des classes nécessaires depuis le Core de l'application.
use App\Core\Auth;    // Pour la gestion de l'authentification et la récupération des infos utilisateur.
use App\Core\Flasher; // Bien qu'il ne soit pas utilisé directement dans cette méthode,
                     // il est souvent utile dans les contrôleurs pour les messages flash.

/**
 * Contrôleur pour le tableau de bord principal de l'application.
 *
 * Ce contrôleur gère l'affichage de la page d'accueil principale que les utilisateurs
 * voient après s'être connectés. Il sert de point d'entrée pour les
 * fonctionnalités courantes comme la recherche de vaccinations.
 */
class DashboardController {

    /**
     * Affiche la page du tableau de bord principal.
     *
     * Cette méthode est typiquement appelée après une connexion réussie ou
     * lorsque l'utilisateur accède à la racine de l'application (si déjà connecté).
     * Elle vérifie d'abord que l'utilisateur est bien authentifié.
     * Si oui, elle récupère quelques informations sur l'utilisateur connecté
     * (matricule, nom, rôle) pour potentiellement les afficher dans la vue,
     * puis charge la vue du tableau de bord.
     *
     * @return void
     */
    public function index(): void {
        // Étape 1: Vérifier si l'utilisateur est connecté.
        // Si l'utilisateur n'est pas connecté, la méthode `checkAuthentication()`
        // le redirigera automatiquement vers la page de connexion (définie dans Auth.php)
        // et arrêtera l'exécution du script ici.
        Auth::checkAuthentication();

        // Étape 2: Récupérer les informations de l'utilisateur actuellement connecté
        // depuis la session via les méthodes de la classe Auth.
        // Ces variables seront disponibles dans la vue incluse ci-dessous.
        $user_matricule = Auth::getMatricule();  // Récupère le matricule unique de l'utilisateur.
        $user_username = Auth::getUsername();    // Récupère le nom d'utilisateur.
        $user_role = Auth::getUserRole();        // Récupère le rôle de l'utilisateur (ex: 'admin', 'standard').

        // Étape 3: Charger et afficher le fichier de la vue correspondante.
        // C'est ce fichier qui contient le code HTML de la page du tableau de bord,
        // y compris le formulaire de recherche de vaccination et les éventuels liens
        // spécifiques au rôle (comme le lien vers l'administration pour les admins).
        require_once '../app/View/dashboard/index.php';
    }
}