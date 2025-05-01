## 💉 Gestionnaire de Campagnes de Vaccination - Application PHP MVC ⚕️

Ce projet est une application web développée en **PHP** selon une architecture **MVC personnalisée**, utilisant une base de données **PostgreSQL** via PDO. Elle est conçue pour gérer les campagnes de vaccination, les vaccins, les lots, les utilisateurs (administrateurs, autres rôles) et les enregistrements de vaccination individuels.

---

### ✨ Fonctionnalités Clés :

*   **Architecture MVC Personnalisée 🏛️:** Structure claire avec un contrôleur frontal (`index.php`), des contrôleurs dédiés par entité (`CampaignController`, `UserController`, etc.), des vues (dans `app/View/`), et une classe modèle pour la base de données (`Database.php`).
*   **Authentification & Autorisation 🔑🛡️:**
    *   Système de connexion/déconnexion sécurisé (`AuthController`, `Auth.php`) basé sur matricule et mot de passe (hashé avec `crypt()`).
    *   Gestion de session (`Auth.php`) pour maintenir l'état connecté.
    *   Contrôle d'accès basé sur les rôles (ex: `Auth::checkAdminAccess()` pour les sections administration).
*   **Gestion CRUD Complète 📝:** Opérations Créer, Lire, Mettre à jour, Supprimer pour les entités principales :
    *   Campagnes (`CampaignController`) : Ajout, modification, activation/désactivation, suppression (avec cascade manuelle des données liées).
    *   Types de Vaccins (`VaccineTypeController`) : CRUD complet.
    *   Vaccins (`VaccineController`) : CRUD, gestion des associations avec les types (table pivot).
    *   Lots (`LotController`) : CRUD, avec validation que le vaccin appartient à la campagne.
    *   Utilisateurs (`UserController`) : CRUD, gestion des rôles, changement de mot de passe, gestion (risquée) du changement de matricule.
    *   Enregistrements de Vaccination (`VaccinationRecordController`) : CRUD, avec recherche par matricule patient.
*   **Interface Utilisateur Basée sur Bootstrap 🎨:**
    *   Utilisation de **Bootstrap 5** pour la mise en page et les composants (cartes, tableaux, formulaires, modales, alertes, pagination, badges).
    *   Structure CSS modulaire (`main.css` important `_variables.css`, `_button.css`, `_card.css`, etc.).
    *   Messages flash (`Flasher.php`) intégrés avec les alertes Bootstrap pour le feedback utilisateur.
*   **Fonctionnalités Avancées de Liste 📊:**
    *   **Pagination (`Paginator.php`)** pour toutes les listes principales.
    *   **Tri (`SortLinkGenerator.php`)** par colonnes cliquables dans les en-têtes de tableau.
    *   **Filtrage** des listes (par statut de campagne, par rôle utilisateur, etc.).
*   **Interactivité (JavaScript & AJAX) ⚡:**
    *   Utilisation de **modales Bootstrap** pour les formulaires d'ajout/modification.
    *   **AJAX (via `fetch`)** pour charger dynamiquement les listes déroulantes dépendantes dans les formulaires (ex: charger les vaccins quand une campagne est choisie, charger les lots quand un vaccin est choisi).
    *   JavaScript pour les confirmations de suppression et la gestion dynamique des formulaires dans les modales.
*   **Génération de Données de Test 🧪:**
    *   Utilisation de la librairie **FakerPHP** (`vendor/autoload.php`) pour peupler rapidement la base de données avec des données aléatoires (Campagnes, Utilisateurs, Vaccins, Lots, Enregistrements). Accessible via des boutons dédiés dans l'interface d'administration.
*   **Gestion d'Erreurs et Logs 🪵:** Utilisation de `try...catch` pour les opérations de base de données, et `error_log()` pour enregistrer les erreurs détaillées côté serveur.

---

### 🚀 Technologies Principales :

*   **Backend:** PHP (Architecture MVC personnalisée) 🐘
*   **Base de Données:** PostgreSQL (via PDO) 🐘
*   **Frontend:** HTML, CSS (Bootstrap 5), JavaScript (Vanilla) 🎨
*   **Dépendances (via Composer):** FakerPHP ✨

---

Ce projet offre une solution complète et structurée pour la gestion de données liées à la vaccination, en mettant en œuvre des pratiques web courantes comme l'architecture MVC, l'interaction BDD sécurisée, l'utilisation d'AJAX et la gestion des sessions/rôles.
