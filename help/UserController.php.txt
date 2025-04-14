<?php

/**
 * Espace de noms pour les contrôleurs de l'application.
 */
namespace App\Controller;

// Importations des classes nécessaires.
use App\Core\Auth;        // Gestion de l'authentification et des accès.
use App\Core\Flasher;     // Messages flash pour l'utilisateur.
use App\Core\Paginator;   // Gestion de la pagination.
use App\Core\Functions;   // Fonctions utilitaires (comme gen_salt).
use App\Model\Database;   // Connexion à la base de données.
use PDO;                  // Utilisation de PDO.
use PDOException;         // Gestion des erreurs PDO.
use Faker\Factory;        // Génération de données de test.

/**
 * Contrôleur pour la gestion des utilisateurs (CRUD).
 *
 * Gère l'affichage (liste paginée, filtrée par rôle, recherche par nom),
 * l'ajout, la modification (y compris le changement potentiellement risqué de matricule
 * et la mise à jour du mot de passe), la suppression et la génération
 * de données de test pour les utilisateurs.
 * L'accès est réservé aux administrateurs.
 */
class UserController {

    /**
     * Instance de la connexion PDO.
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur du contrôleur UserController.
     *
     * Vérifie les droits d'administrateur et initialise la connexion PDO.
     */
    public function __construct() {
        // Vérifie que l'utilisateur est un administrateur.
        Auth::checkAdminAccess();
        // Récupère l'instance PDO.
        $this->pdo = Database::getInstance();
    }

    /**
     * Affiche la liste paginée et filtrable des utilisateurs.
     *
     * Récupère les utilisateurs depuis la base de données en tenant compte
     * de la pagination, du filtrage par rôle et de la recherche par nom d'utilisateur.
     * Prépare les données pour la vue (liste des utilisateurs, rôles, pagination, etc.).
     * Gère également la mise en surbrillance d'un utilisateur après modification.
     *
     * @return void
     */
    public function list(): void {
        // Récupération et validation des paramètres de pagination et de filtre/recherche.
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 10; // Nombre d'utilisateurs par page.

        // Filtre par rôle (validation entière).
        $filter_role_id = filter_input(INPUT_GET, 'filter_role_id', FILTER_VALIDATE_INT);
        // Recherche par nom (nettoyage des caractères spéciaux).
        $search_term = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '');

        // Initialisation des variables pour la vue.
        $users_list = [];       // Liste des utilisateurs à afficher.
        $roles_list = [];       // Liste des rôles disponibles (pour le filtre et la modale).
        $totalUsers = 0;        // Nombre total d'utilisateurs (pour la pagination).
        $totalPages = 1;        // Nombre total de pages.
        $paginationHtml = '';   // HTML de la pagination.
        $logged_in_user_matricule = Auth::getMatricule(); // Pour désactiver le bouton de suppression de soi-même.
        $action = 'liste'; // Pour la vue.

        // Construction de la query string pour conserver les filtres/recherche dans les liens.
        $queryStringParams = [];
        if ($filter_role_id) { $queryStringParams['filter_role_id'] = $filter_role_id; }
        if (!empty($search_term)) { $queryStringParams['search'] = $search_term; }
        $currentQueryString = http_build_query($queryStringParams);
        // URL de base pour la pagination.
        $baseUrl = "index.php?controller=User&action=list" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Vérifie s'il faut mettre en surbrillance un utilisateur (après une modification).
        // Utilise une variable de session temporaire.
        $highlight_matricule = null;
        Auth::startSession(); // Assure que la session est démarrée.
        if (isset($_SESSION['highlight_matricule'])) {
            $highlight_matricule = $_SESSION['highlight_matricule'];
            unset($_SESSION['highlight_matricule']); // Supprime la variable de session après lecture.
        }

        try {
            // Récupère la liste de tous les rôles pour les menus déroulants.
            $stmt_roles = $this->pdo->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
            $roles_list = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

            // Construction de la clause WHERE pour le filtrage et la recherche.
            $whereClauses = []; $params = []; // Utilisation de paramètres nommés pour PDO.
            if ($filter_role_id) { $whereClauses[] = "u.role_id = :role_id"; $params[':role_id'] = $filter_role_id; }
            if (!empty($search_term)) {
                // Recherche insensible à la casse sur le nom d'utilisateur.
                $whereClauses[] = "LOWER(u.username) LIKE LOWER(:search)";
                $params[':search'] = '%' . $search_term . '%'; // Ajoute les wildcards pour LIKE.
            }
            $whereSql = count($whereClauses) > 0 ? " WHERE " . implode(" AND ", $whereClauses) : "";

            // Compte le nombre total d'utilisateurs correspondant aux critères.
            $sqlTotal = "SELECT COUNT(*) FROM users u" . $whereSql;
            $totalStmt = $this->pdo->prepare($sqlTotal);
            $totalStmt->execute($params); // Exécute avec les paramètres de filtre/recherche.
            $totalUsers = $totalStmt->fetchColumn();

            // Calcule les données de pagination.
            $paginationData = Paginator::getPaginationData($totalUsers, $perPage, $page);
            $page = $paginationData['current_page'];
            $totalPages = $paginationData['total_pages'];
            $offset = $paginationData['offset'];

            // Requête SQL pour récupérer la liste paginée des utilisateurs avec leur rôle.
            $sqlList = "SELECT u.matricule, u.username, u.email, r.role_name, u.role_id
                        FROM users u
                        JOIN roles r ON u.role_id = r.role_id" . $whereSql .
                       " ORDER BY u.username ASC LIMIT :limit OFFSET :offset"; // Tri par nom d'utilisateur.
            $stmtList = $this->pdo->prepare($sqlList);
            // Lie les paramètres de filtre/recherche.
            foreach ($params as $key => $val) { $stmtList->bindValue($key, $val); }
            // Lie les paramètres de pagination (LIMIT et OFFSET).
            $stmtList->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtList->execute();
            $users_list = $stmtList->fetchAll(PDO::FETCH_ASSOC);

            // Génère le HTML de la pagination si nécessaire.
            if ($totalUsers > 0 && $totalPages > 1) {
                $paginationHtml = Paginator::render($baseUrl, $page, $totalPages);
            }

        } catch (PDOException $e) {
            // Gestion des erreurs PDO.
            error_log("Erreur lister utilisateurs: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la récupération des utilisateurs.", 'danger');
        }

        // Charge la vue de la liste des utilisateurs.
        require_once '../app/View/admin/users/list.php';
    }

    /**
     * Sauvegarde un utilisateur (ajout ou modification).
     *
     * Gère les données POST du formulaire de la modale. Distingue l'ajout de la modification
     * en se basant sur la présence de 'original_matricule'.
     * Valide les données, gère le hashage du mot de passe (avec crypt),
     * et traite le cas spécifique (et risqué) de la modification du matricule.
     *
     * @return void
     */
    public function save(): void {
        // Vérifie la méthode HTTP.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?controller=User&action=list"); exit();
        }

        // Détermine l'action (ajout/modif) et récupère la page et le matricule de l'admin.
        $action = isset($_POST['original_matricule']) && !empty($_POST['original_matricule']) ? 'modifier' : 'ajouter';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $logged_in_user_matricule = Auth::getMatricule(); // Pour le champ 'matricule_creation'.

        // Récupère les paramètres GET pour l'URL de redirection.
        $filter_role_id_get = $_GET['filter_role_id'] ?? '';
        $search_term_get = $_GET['search'] ?? '';
        $queryStringParams = [];
        if ($filter_role_id_get) { $queryStringParams['filter_role_id'] = $filter_role_id_get; }
        if (!empty($search_term_get)) { $queryStringParams['search'] = $search_term_get; }
        $currentQueryString = http_build_query($queryStringParams);
        $listUrl = "index.php?controller=User&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // ---- CAS AJOUT ----
        if ($action === 'ajouter') {
             // Récupération et nettoyage des données du formulaire d'ajout.
             $matricule = trim(filter_input(INPUT_POST, 'matricule', FILTER_SANITIZE_SPECIAL_CHARS));
             $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
             $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null; // Valide email, sinon NULL.
             $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
             $password = $_POST['password'] ?? ''; // Mot de passe.
             $password_confirm = $_POST['password_confirm'] ?? ''; // Confirmation.

             // Validation des données pour l'ajout.
             if (empty($matricule) || empty($username) || !$role_id) { Flasher::setFlash("Erreur : Matricule, Nom d'utilisateur et Rôle sont obligatoires.", 'danger'); header("Location: " . $listUrl); exit(); }
             // Vérifie si l'email est fourni mais invalide.
             if ($email === false && !empty($_POST['email'])) { Flasher::setFlash("Erreur : Format d'email invalide.", 'danger'); header("Location: " . $listUrl); exit(); }
             // Vérifie le mot de passe (obligatoire et longueur minimale).
             if (empty($password) || strlen($password) < 6) { Flasher::setFlash("Erreur : Le mot de passe est obligatoire (minimum 6 caractères).", 'danger'); header("Location: " . $listUrl); exit(); }
             // Vérifie que les mots de passe correspondent.
             if ($password !== $password_confirm) { Flasher::setFlash("Erreur : Les mots de passe ne correspondent pas.", 'danger'); header("Location: " . $listUrl); exit(); }

             // Génère le sel et hashe le mot de passe avec crypt().
             $salt = \App\Core\gen_salt(); // Utilise la fonction du namespace App\Core.
             $password_hash = crypt($password, $salt); // Le hash contiendra le sel et l'algo.

             // Insertion en base de données.
             try {
                 $sql = "INSERT INTO users (matricule, username, password_hash, email, role_id, matricule_creation) VALUES (:mat, :uname, :pwhash, :email, :role_id, :creator)";
                 $stmt = $this->pdo->prepare($sql);
                 // Exécute avec les données validées et le hash du mot de passe.
                 $stmt->execute([
                     ':mat' => $matricule,
                     ':uname' => $username,
                     ':pwhash' => $password_hash,
                     ':email' => $email,
                     ':role_id' => $role_id,
                     ':creator' => $logged_in_user_matricule // Enregistre qui a créé l'utilisateur.
                 ]);
                 Flasher::setFlash("Utilisateur '$username' ajouté avec succès.", 'success');
                 // Redirection vers la liste (sans la page, pour aller à la première page où le nouvel user pourrait apparaître).
                 header("Location: index.php?controller=User&action=list" . (!empty($currentQueryString) ? '&' . $currentQueryString : '')); exit();
             } catch (PDOException $e) {
                 // Gestion des erreurs PDO spécifiques (contraintes uniques).
                 $errorMsg = "Erreur base de données lors de l'ajout.";
                 if (strpos($e->getMessage(), 'users_pkey') !== false) { $errorMsg = "Erreur : Ce matricule existe déjà."; } // Clé primaire (matricule).
                 elseif (strpos($e->getMessage(), 'users_username_key') !== false) { $errorMsg = "Erreur : Ce nom d'utilisateur existe déjà."; } // Contrainte unique username.
                 elseif (strpos($e->getMessage(), 'users_email_key') !== false) { $errorMsg = "Erreur : Cet email est déjà utilisé."; } // Contrainte unique email.
                 else { error_log("[ERROR] User Add PDOException: " . $e->getMessage()); } // Autres erreurs.
                 Flasher::setFlash($errorMsg, 'danger');
                 header("Location: " . $listUrl); exit(); // Redirige vers la liste (page courante).
             }

        // ---- CAS MODIFICATION ----
        } elseif ($action === 'modifier') {
             // Récupération des données du formulaire de modification.
             $original_matricule = trim($_POST['original_matricule']); // Matricule avant modif.
             $new_matricule = trim(filter_input(INPUT_POST, 'matricule', FILTER_SANITIZE_SPECIAL_CHARS)); // Nouveau matricule potentiel.
             $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
             $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
             $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
             $confirm_matricule_change = isset($_POST['confirm_matricule_change']); // Checkbox de confirmation.
             $new_password = $_POST['new_password'] ?? ''; // Nouveau mot de passe (optionnel).
             $new_password_confirm = $_POST['new_password_confirm'] ?? ''; // Confirmation nouveau mdp.

             // Détection des changements.
             $matricule_changed = ($new_matricule !== $original_matricule);
             $password_changed = !empty($new_password);
             $validation_ok = true;
             $error_message = '';

             // Validation des données pour la modification.
             if (empty($new_matricule) || empty($username) || !$role_id) { $validation_ok = false; $error_message = "Matricule, Nom d'utilisateur et Rôle sont obligatoires."; }
             elseif ($email === false && !empty($_POST['email'])) { $validation_ok = false; $error_message = "Format d'email invalide."; }
             // Si le matricule a changé, la confirmation est requise.
             elseif ($matricule_changed && !$confirm_matricule_change) { $validation_ok = false; $error_message = "Vous devez cocher la case pour confirmer la modification du matricule."; }
             // Si le mot de passe a changé, validation de longueur et correspondance.
             elseif ($password_changed) {
                 if (strlen($new_password) < 6) { $validation_ok = false; $error_message = "Le nouveau mot de passe doit faire au moins 6 caractères."; }
                 elseif ($new_password !== $new_password_confirm) { $validation_ok = false; $error_message = "Les nouveaux mots de passe ne correspondent pas."; }
             }

             // Si la validation est réussie.
             if ($validation_ok) {
                 try {
                     // Construction dynamique de la requête UPDATE.
                     $sql_parts = []; $params_update = []; // Parties SQL et paramètres.
                     // Ajoute les champs à mettre à jour (toujours username, email, role_id).
                     $sql_parts[] = "username = :uname"; $params_update[':uname'] = $username;
                     $sql_parts[] = "email = :email"; $params_update[':email'] = $email;
                     $sql_parts[] = "role_id = :role_id"; $params_update[':role_id'] = $role_id;

                     // Si le matricule a changé, l'ajoute à la mise à jour.
                     if ($matricule_changed) { $sql_parts[] = "matricule = :new_mat"; $params_update[':new_mat'] = $new_matricule; }
                     // Si le mot de passe a changé, l'ajoute (avec hashage).
                     if ($password_changed) {
                         $salt = \App\Core\gen_salt();
                         $new_password_hash = crypt($new_password, $salt);
                         $sql_parts[] = "password_hash = :new_pwhash"; $params_update[':new_pwhash'] = $new_password_hash;
                     }
                     // Ajoute le matricule original pour la clause WHERE.
                     $params_update[':orig_mat'] = $original_matricule;

                     // Exécute l'UPDATE seulement s'il y a des modifications détectées.
                     if (!empty($sql_parts)) {
                         $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE matricule = :orig_mat";
                         $stmt = $this->pdo->prepare($sql);
                         $stmt->execute($params_update);
                         Flasher::setFlash("Utilisateur '$username' modifié avec succès.", 'success');
                         // Met en session le matricule (potentiellement nouveau) pour le surlignage.
                         Auth::startSession();
                         $_SESSION['highlight_matricule'] = $new_matricule;
                         header("Location: " . $listUrl); exit();
                     } else {
                          // Aucune modification détectée.
                          Flasher::setFlash("Aucune modification détectée.", 'info');
                          header("Location: " . $listUrl); exit();
                     }
                 } catch (PDOException $e) {
                     // Gestion des erreurs PDO lors de la modification.
                     $errorMsg = "Erreur base de données lors de la modification.";
                     // Détection des erreurs de contrainte unique (nouveau matricule, username, email).
                     if (strpos($e->getMessage(), 'users_pkey') !== false || strpos($e->getMessage(), 'duplicate key value violates unique constraint "users_pkey"') !== false ) { $errorMsg = "Erreur : Le nouveau matricule '$new_matricule' existe déjà."; }
                     elseif (strpos($e->getMessage(), 'users_username_key') !== false) { $errorMsg = "Erreur : Ce nom d'utilisateur existe déjà."; }
                     elseif (strpos($e->getMessage(), 'users_email_key') !== false) { $errorMsg = "Erreur : Cet email est déjà utilisé."; }
                     // Détection d'erreur de clé étrangère (souvent si on change un matricule référencé ailleurs sans ON UPDATE CASCADE).
                     elseif (strpos($e->getMessage(), 'violates foreign key constraint') !== false) {
                        $errorMsg = "Erreur critique : Modification du matricule impossible car il est utilisé ailleurs (Clé étrangère sans 'ON UPDATE CASCADE' ?). Contactez l'administrateur.";
                        error_log("[CRITICAL FK ERROR] User matricule change failed for {$original_matricule} to {$new_matricule}. Check table foreign keys for ON UPDATE CASCADE. Details: " . $e->getMessage());
                     }
                     else { error_log("[ERROR] User Edit PDOException: " . $e->getMessage()); } // Autres erreurs.
                     Flasher::setFlash($errorMsg, 'danger');
                     header("Location: " . $listUrl); exit();
                 }
             } else {
                 // Si la validation échoue (avant la tentative DB).
                 Flasher::setFlash("Erreur de validation : " . $error_message, 'danger');
                 header("Location: " . $listUrl); exit();
             }
        } // Fin elseif ($action === 'modifier')
    } // Fin save()

    /**
     * Supprime un utilisateur.
     *
     * Gère la suppression d'un utilisateur spécifié par son matricule.
     * Empêche un utilisateur de se supprimer lui-même.
     * Gère les erreurs, notamment si l'utilisateur est référencé par des clés étrangères.
     *
     * @return void
     */
    public function delete(): void {
         // Récupération du matricule à supprimer et des infos pour la redirection.
         $matricule_to_delete = trim($_GET['matricule'] ?? '');
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
         $logged_in_user_matricule = Auth::getMatricule(); // Pour vérifier l'auto-suppression.

         // Construction de l'URL de redirection.
         $queryStringParams = [];
         if (isset($_GET['filter_role_id'])) { $queryStringParams['filter_role_id'] = $_GET['filter_role_id']; }
         if (!empty($_GET['search'])) { $queryStringParams['search'] = $_GET['search']; }
         $currentQueryString = http_build_query($queryStringParams);
         $listUrl = "index.php?controller=User&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

         // Validation : matricule non vide et différent de l'utilisateur connecté.
         if (empty($matricule_to_delete)) { Flasher::setFlash("Matricule invalide pour suppression.", 'warning'); header("Location: " . $listUrl); exit(); }
         if ($matricule_to_delete === $logged_in_user_matricule) { Flasher::setFlash("Vous ne pouvez pas vous supprimer.", 'warning'); header("Location: " . $listUrl); exit(); }

         try {
             // Exécute la requête DELETE.
             $sql = "DELETE FROM users WHERE matricule = :mat";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([':mat' => $matricule_to_delete]);
             $rowCount = $stmt->rowCount(); // Nombre de lignes supprimées.

             // Message de succès ou d'avertissement.
             if ($rowCount > 0) { Flasher::setFlash("Utilisateur (matricule: {$matricule_to_delete}) supprimé.", 'success'); }
             else { Flasher::setFlash("Utilisateur non trouvé ou déjà supprimé.", 'warning'); }
         } catch (PDOException $e) {
             // Gestion des erreurs PDO.
             $errorMsg = "Erreur DB suppression.";
              // Détection spécifique d'erreur de clé étrangère.
              if (strpos($e->getMessage(), 'violates foreign key constraint') !== false) {
                  $errorMsg = "Erreur : Impossible de supprimer cet utilisateur car il est référencé ailleurs (par exemple, dans les enregistrements de vaccination, les créations de campagnes/lots, etc.).";
              } else {
                  error_log("[ERROR] User Delete PDOException for {$matricule_to_delete}: " . $e->getMessage());
              }
              Flasher::setFlash($errorMsg, 'danger');
         } catch (\Exception $e) { // Capture d'autres exceptions potentielles.
              Flasher::setFlash("Erreur inattendue lors de la suppression: " . $e->getMessage(), 'danger');
         }
         // Redirection vers la liste.
         header("Location: " . $listUrl); exit();
    }

    /**
     * Génère un nombre spécifié d'utilisateurs de test avec Faker.
     *
     * Crée des utilisateurs avec des matricules, noms, emails, mots de passe
     * et rôles aléatoires.
     *
     * @return void
     */
    public function generate(): void {
        // Récupération et validation du nombre à générer.
         $nombre_a_generer = isset($_GET['nombre']) ? max(1, min(50, (int)$_GET['nombre'])) : 5;
         $logged_in_user_matricule = Auth::getMatricule(); // Pour 'matricule_creation'.

         // Construction de l'URL de redirection.
         $queryStringParams = [];
         if (isset($_GET['filter_role_id'])) { $queryStringParams['filter_role_id'] = $_GET['filter_role_id']; }
         if (!empty($_GET['search'])) { $queryStringParams['search'] = $_GET['search']; }
         $currentQueryString = http_build_query($queryStringParams);
         $listUrl = "index.php?controller=User&action=list" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

         // Vérification de la session admin.
         if (empty($logged_in_user_matricule)) { Flasher::setFlash("Erreur session admin.", 'danger'); header("Location: " . $listUrl); exit(); }

         try {
             // Récupère les ID de rôles disponibles pour les assigner aléatoirement.
             $stmt_roles = $this->pdo->query("SELECT role_id FROM roles");
             $available_role_ids = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
             if (empty($available_role_ids)) { Flasher::setFlash("Erreur : Aucun rôle trouvé pour la génération.", 'danger'); header("Location: " . $listUrl); exit(); }

             // Initialisation de Faker et compteur.
             $faker = Factory::create('fr_FR'); $count_success = 0;
             // Requête d'insertion préparée.
             $sqlInsert = "INSERT INTO users (matricule, username, password_hash, email, role_id, matricule_creation) VALUES (:mat, :uname, :pwhash, :email, :role_id, :creator)";
             $stmtInsert = $this->pdo->prepare($sqlInsert);

             // Boucle de génération.
             for ($i = 0; $i < $nombre_a_generer; $i++) {
                 // Génération de données aléatoires uniques.
                 $fake_matricule = $faker->unique()->bothify('FAKE####??'); // Matricule unique.
                 $fake_username = $faker->unique()->userName;             // Nom d'utilisateur unique.
                 $fake_email = $faker->unique()->safeEmail;               // Email unique.
                 $fake_password = $faker->password(8, 12);                // Mot de passe aléatoire.
                 $fake_role_id = $faker->randomElement($available_role_ids); // Rôle aléatoire parmi ceux existants.
                 // Hashage du mot de passe.
                 $salt = \App\Core\gen_salt();
                 $password_hash = crypt($fake_password, $salt);

                 try {
                     // Exécute l'insertion.
                     $stmtInsert->execute([
                         ':mat' => $fake_matricule,
                         ':uname' => $fake_username,
                         ':pwhash' => $password_hash,
                         ':email' => $fake_email,
                         ':role_id' => $fake_role_id,
                         ':creator' => $logged_in_user_matricule
                     ]);
                     $count_success++; // Incrémente si succès.
                 }
                 catch (PDOException $e) {
                     // En cas d'erreur (ex: doublon malgré unique(), très rare), loggue et réinitialise Faker.
                     error_log("[FAKER ERROR] Insert user failed: {$e->getMessage()}");
                      $faker->unique(true); // Important pour la prochaine itération.
                 }
             }
             $faker->unique(true); // Réinitialise après la boucle.

             // Message flash récapitulatif.
             if ($count_success == $nombre_a_generer) { Flasher::setFlash("{$count_success} utilisateurs test générés.", 'success'); }
             elseif ($count_success > 0) { Flasher::setFlash("{$count_success}/{$nombre_a_generer} utilisateurs test générés (voir logs).", 'warning'); }
             else { Flasher::setFlash("Échec génération utilisateurs test (voir logs).", 'danger'); }

         } catch (PDOException $e) {
             // Gestion des erreurs PDO globales.
             error_log("Erreur DB pendant génération users: " . $e->getMessage());
             Flasher::setFlash("Erreur base de données lors de la génération.", 'danger');
         }
         // Redirection vers la liste.
         header("Location: " . $listUrl); exit();
    }
}