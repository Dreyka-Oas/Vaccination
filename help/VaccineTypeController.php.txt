<?php

/**
 * Espace de noms pour les contrôleurs de l'application.
 */
namespace App\Controller;

// Importations des classes nécessaires.
use App\Core\Auth;                // Gestion de l'authentification et des accès.
use App\Core\Flasher;             // Messages flash pour l'utilisateur.
use App\Core\Paginator;           // Gestion de la pagination.
use App\Core\SortLinkGenerator;   // Génération des liens de tri.
use App\Model\Database;           // Connexion à la base de données.
use PDO;                          // Utilisation de PDO.
use PDOException;                 // Gestion des erreurs PDO.
use Faker\Factory;                // Génération de données de test.

/**
 * Contrôleur pour la gestion des types de vaccins (CRUD).
 *
 * Gère l'affichage (liste paginée, triée), l'ajout, la modification,
 * la suppression (avec suppression en cascade des données liées via les vaccins),
 * la génération de données de test et la suppression globale des types de vaccins.
 * L'accès est réservé aux administrateurs.
 */
class VaccineTypeController {

    /**
     * Instance de la connexion PDO.
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur du contrôleur VaccineTypeController.
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
     * Affiche la liste paginée et triable des types de vaccins.
     *
     * Récupère les types depuis la base de données en tenant compte
     * de la pagination et du tri. Prépare les données pour la vue.
     *
     * @return void
     */
    public function list(): void {
        // Récupération et validation des paramètres de pagination et de tri.
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 10; // Nombre de types par page.

        // Colonnes autorisées pour le tri.
        $allowed_sort_columns = ['nom_type', 'description', 'type_vaccin_id'];
        $sort_by = $_GET['sort_by'] ?? 'type_vaccin_id';
        $sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC';

        // Validation des paramètres de tri.
        if (!in_array($sort_by, $allowed_sort_columns)) $sort_by = 'type_vaccin_id';
        if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') $sort_dir = 'DESC';

        // Initialisation des variables pour la vue.
        $totalTypesVaccins = 0;
        $type_vaccins = []; // Liste des types à afficher.
        $totalPages = 1;
        $paginationHtml = '';
        // Construction de la query string pour les liens (pagination, tri).
        $currentQueryString = http_build_query(array_filter(['sort_by' => $sort_by, 'sort_dir' => $sort_dir]));
        $baseUrl = "index.php?controller=VaccineType&action=list" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');
        $action = 'liste'; // Pour la vue.

        try {
            // Compte le nombre total de types de vaccins.
            $totalStmt = $this->pdo->query("SELECT COUNT(*) FROM type_vaccins");
            $totalTypesVaccins = $totalStmt->fetchColumn();

            // Calcule les données de pagination.
            $paginationData = Paginator::getPaginationData($totalTypesVaccins, $perPage, $page);
            $page = $paginationData['current_page'];
            $totalPages = $paginationData['total_pages'];
            $offset = $paginationData['offset'];

            // Construction de la clause ORDER BY.
            $orderByClause = " ORDER BY ";
            // Tri insensible à la casse pour nom et description.
            if ($sort_by === 'nom_type' || $sort_by === 'description') {
                $orderByClause .= "LOWER(" . $sort_by . ") " . $sort_dir;
            } else {
                $orderByClause .= $sort_by . " " . $sort_dir;
            }
            // Tri secondaire pour la stabilité.
            if ($sort_by !== 'type_vaccin_id') {
                $orderByClause .= ", type_vaccin_id ASC";
            }

            // Requête pour récupérer la liste paginée et triée des types.
            $sqlList = "SELECT * FROM type_vaccins" . $orderByClause . " LIMIT ? OFFSET ?";
            $stmt = $this->pdo->prepare($sqlList);
            // Lie les paramètres de pagination (LIMIT et OFFSET).
            $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $type_vaccins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Génère le HTML de la pagination si nécessaire.
            if ($totalTypesVaccins > 0 && $totalPages > 1) {
                 $paginationHtml = Paginator::render($baseUrl, $page, $totalPages);
            }

        } catch (PDOException $e) {
            // Gestion des erreurs PDO.
            error_log("Erreur lister types vaccins: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la récupération des types de vaccins.", 'danger');
        }

        // Charge la vue de la liste des types de vaccins.
        require_once '../app/View/admin/vaccine_types/list.php';
    }

    /**
     * Sauvegarde un type de vaccin (ajout ou modification).
     *
     * Traite les données POST du formulaire modal. Insère ou met à jour
     * le type dans la base de données. Gère les erreurs (doublons de nom).
     *
     * @return void
     */
    public function save(): void {
        // Vérifie la méthode HTTP.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?controller=VaccineType&action=list"); exit();
        }

        // Récupération des données POST, détermination action, page, matricule admin.
        $id = isset($_POST['type_vaccin_id']) ? (int)$_POST['type_vaccin_id'] : null;
        $action = $id ? 'modifier' : 'ajouter';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $matricule = Auth::getMatricule(); // Pour matricule_creation.

        $nom_type = $_POST['nom_type'] ?? null;
        $description = $_POST['description'] ?? '';

        // Construction de l'URL de redirection (conserve le tri).
        $sort_by_get = $_GET['sort_by'] ?? 'type_vaccin_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        $listUrl = "index.php?controller=VaccineType&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Validation simple du nom (obligatoire).
        if (empty($nom_type)) {
            Flasher::setFlash("Le nom du type est obligatoire.", 'danger');
            header("Location: " . $listUrl); exit(); // Redirige vers la liste
        }
        // Vérification de l'authentification.
        if (empty($matricule)) {
             Flasher::setFlash("Erreur d'authentification.", 'danger');
             header("Location: index.php?controller=Auth&action=loginForm"); exit();
        }

        try {
            // Exécute INSERT ou UPDATE.
            if ($action === 'ajouter') {
                $sql = "INSERT INTO type_vaccins (nom_type, description, matricule_creation) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$nom_type, $description, $matricule]);
            } else { // Action 'modifier'
                $sql = "UPDATE type_vaccins SET nom_type = ?, description = ?, matricule_creation = ? WHERE type_vaccin_id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$nom_type, $description, $matricule, $id]);
            }

            // Message de succès et redirection.
            Flasher::setFlash("Type de vaccin " . ($action === 'ajouter' ? 'ajouté' : 'modifié') . " avec succès.", 'success');
            header("Location: " . $listUrl); exit();

        } catch (PDOException $e) {
            // Gestion des erreurs PDO.
            error_log("Erreur sauvegarde type vaccin: " . $e->getMessage());
            // Détection spécifique de l'erreur de contrainte unique sur le nom.
            if (strpos($e->getMessage(), 'duplicate key value violates unique constraint') !== false || $e->getCode() == '23505') {
                 Flasher::setFlash("Erreur : Le nom de type '{$nom_type}' existe déjà.", 'danger');
            } else {
                Flasher::setFlash("Erreur lors de la sauvegarde du type de vaccin.", 'danger');
            }
            // Redirection vers la liste en cas d'erreur.
            header("Location: " . $listUrl); exit();
        }
    }

     /**
      * Supprime un type de vaccin et ses données associées (en cascade).
      *
      * Identifie d'abord les vaccins associés à ce type, puis supprime les
      * enregistrements de vaccination liés (via les lots), les lots liés,
      * les associations vaccin-type, et enfin le type lui-même.
      * Le tout dans une transaction.
      *
      * @return void
      */
     public function delete(): void {
        // Récupération de l'ID et des paramètres GET pour la redirection.
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $sort_by_get = $_GET['sort_by'] ?? 'type_vaccin_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        $listUrl = "index.php?controller=VaccineType&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Validation de l'ID.
        if (!$id) {
            Flasher::setFlash("ID de type de vaccin invalide.", 'warning');
            header("Location: " . $listUrl); exit();
        }

         try {
            // Début de la transaction.
            $this->pdo->beginTransaction();

            // 1. Trouver les IDs des vaccins associés à ce type.
            $findVaccinsStmt = $this->pdo->prepare("SELECT vaccin_id FROM vaccin_type_associations WHERE type_vaccin_id = ?");
            $findVaccinsStmt->execute([$id]);
            $vaccin_ids = $findVaccinsStmt->fetchAll(PDO::FETCH_COLUMN); // Récupère les IDs dans un tableau simple.
            $deletedItemsMsg = ""; // Message pour indiquer ce qui a été supprimé en cascade.

            // Si des vaccins sont associés à ce type :
            if (!empty($vaccin_ids)) {
                // Crée une chaîne de placeholders (?,?,?) pour la clause IN.
                $placeholders = implode(',', array_fill(0, count($vaccin_ids), '?'));

                // 2. Supprimer les enregistrements de vaccination liés aux lots des vaccins trouvés.
                $stmtVr = $this->pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id IN ($placeholders))");
                $stmtVr->execute($vaccin_ids);
                $deletedItemsMsg .= $stmtVr->rowCount() . " enregistrement(s), ";

                // 3. Supprimer les lots liés aux vaccins trouvés.
                $stmtLots = $this->pdo->prepare("DELETE FROM lots WHERE vaccin_id IN ($placeholders)");
                $stmtLots->execute($vaccin_ids);
                $deletedItemsMsg .= $stmtLots->rowCount() . " lot(s), ";
            }

            // 4. Supprimer les associations de la table pivot pour ce type.
            $stmtAssoc = $this->pdo->prepare("DELETE FROM vaccin_type_associations WHERE type_vaccin_id = ?");
            $stmtAssoc->execute([$id]);
            $deletedItemsMsg .= $stmtAssoc->rowCount() . " association(s)";

            // 5. Supprimer le type de vaccin lui-même.
            $stmt = $this->pdo->prepare("DELETE FROM type_vaccins WHERE type_vaccin_id = ?");
            $stmt->execute([$id]);
            $rowCount = $stmt->rowCount(); // Vérifie si le type a été supprimé.

            // Valide la transaction.
            $this->pdo->commit();

            // Message flash.
            if ($rowCount > 0) { Flasher::setFlash("Type supprimé (et {$deletedItemsMsg}).", 'success'); }
            else { Flasher::setFlash("Type non trouvé ou déjà supprimé.", 'warning'); }

        } catch (PDOException $e) {
            // Annule la transaction en cas d'erreur.
            $this->pdo->rollBack();
            // Log et message flash.
            error_log("Erreur suppression type vaccin ID {$id}: " . $e->getMessage());
             Flasher::setFlash("Erreur suppression type: " . $e->getMessage(), 'danger');
        }
        // Redirection.
        header("Location: " . $listUrl); exit();
    }

    /**
     * Génère un nombre spécifié de types de vaccins de test avec Faker.
     *
     * Crée des types avec des noms et descriptions aléatoires.
     *
     * @return void
     */
    public function generate(): void {
        // Récupération et validation du nombre à générer.
         $nombre_types = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 5;
        if ($nombre_types < 1) $nombre_types = 1; if ($nombre_types > 50) $nombre_types = 50; // Limite.

        // Initialisation de Faker, compteur et récupération du matricule admin.
        $faker = Factory::create('fr_FR');
        $count_success = 0;
        $matricule_creation = Auth::getMatricule();
        // Vérification de la session admin.
        if (empty($matricule_creation)) {
            Flasher::setFlash("Erreur session admin.", 'danger');
            header("Location: index.php?controller=VaccineType&action=list"); exit();
        }

        try {
            // Requête d'insertion préparée.
            $sqlInsert = "INSERT INTO type_vaccins (nom_type, description, matricule_creation) VALUES (?, ?, ?)";
            $stmtInsert = $this->pdo->prepare($sqlInsert);

            // Trouve le plus grand numéro dans les noms existants pour commencer la numérotation.
            $maxNumStmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING(nom_type FROM E'^Type .*?(\\\\d+)$') AS INTEGER)) FROM type_vaccins WHERE nom_type ~ E'^Type .*?\\\\d+$'");
            $startNum = ($maxNumStmt->fetchColumn() ?: 0); // Commence à 0 si aucun trouvé.

            // Boucle de génération.
            for ($i = 0; $i < $nombre_types; $i++) {
                // Génère un nom unique et une description.
                $nom_type = 'Type ' . $faker->unique()->word . ' ' . ($startNum + $i + 1);
                $description = $faker->sentence(6);
                try {
                    // Exécute l'insertion.
                    if ($stmtInsert->execute([$nom_type, $description, $matricule_creation])) { $count_success++; }
                } catch (PDOException $e) {
                    // Gestion des erreurs (ex: doublon malgré unique()).
                    error_log("[FAKER] Erreur insertion type: " . $e->getMessage());
                     $faker->unique(true); // Réinitialise Faker pour la prochaine tentative.
                }
            }
             $faker->unique(true); // Réinitialise après la boucle.
             // Message flash récapitulatif.
             Flasher::setFlash("{$count_success}/{$nombre_types} types générés.", $count_success > 0 ? 'success' : 'danger');
        } catch (PDOException $e) {
            // Gestion des erreurs PDO globales.
            error_log("Erreur globale génération types: " . $e->getMessage());
            Flasher::setFlash("Erreur base de données lors de la génération.", 'danger');
        }
        // Redirection vers la liste.
        header("Location: index.php?controller=VaccineType&action=list"); exit();
    }

    /**
     * Supprime TOUS les types de vaccins et leurs données associées.
     *
     * Fonction de nettoyage dangereuse. Utilise une transaction.
     * Supprime enregistrements (via lots des vaccins associés), lots (via vaccins associés),
     * associations, puis les types eux-mêmes.
     *
     * @return void
     */
    public function deleteAll(): void {
        try {
            // Début de la transaction.
            $this->pdo->beginTransaction();
            // 1. Trouver tous les IDs de vaccins qui ont au moins une association de type (pour être sûr de cibler les bons lots/enregistrements).
            $vaccin_ids_all = $this->pdo->query("SELECT DISTINCT vaccin_id FROM vaccin_type_associations")->fetchAll(PDO::FETCH_COLUMN);

            // Si des vaccins étaient associés à des types:
            if(!empty($vaccin_ids_all)) {
                $placeholders_v_all = implode(',', array_fill(0, count($vaccin_ids_all), '?'));
                // 2. Supprimer les enregistrements liés aux lots de ces vaccins.
                $this->pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id IN ($placeholders_v_all))")->execute($vaccin_ids_all);
                // 3. Supprimer les lots liés à ces vaccins.
                $this->pdo->prepare("DELETE FROM lots WHERE vaccin_id IN ($placeholders_v_all)")->execute($vaccin_ids_all);
            }
            // 4. Supprimer toutes les associations vaccin-type.
            $this->pdo->exec("DELETE FROM vaccin_type_associations");
            // 5. Supprimer tous les types de vaccins.
            $deletedRows = $this->pdo->exec("DELETE FROM type_vaccins");
            // Valide la transaction.
            $this->pdo->commit();
            // Message de succès.
            Flasher::setFlash("{$deletedRows} types et données associées supprimés.", 'success');
        } catch (PDOException $e) {
            // Annule la transaction.
            $this->pdo->rollBack();
            // Log et message flash.
            error_log("Erreur suppression globale types: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la suppression totale.", 'danger');
        }
        // Redirection vers la liste.
        header("Location: index.php?controller=VaccineType&action=list"); exit();
    }
}