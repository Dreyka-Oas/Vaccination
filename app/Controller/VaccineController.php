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
 * Contrôleur pour la gestion des vaccins (CRUD).
 *
 * Gère l'affichage (liste paginée, triée, filtrée par campagne), l'ajout,
 * la modification (y compris la gestion des types associés via une table pivot),
 * la suppression (avec suppression en cascade des données liées),
 * la génération de données de test et la suppression globale des vaccins.
 * L'accès est réservé aux administrateurs.
 */
class VaccineController {

    /**
     * Instance de la connexion PDO.
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur du contrôleur VaccineController.
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
     * Affiche la liste paginée, triée et filtrable des vaccins.
     *
     * Récupère les vaccins en joignant les tables campagnes et (via une table pivot)
     * les types de vaccins. Gère la pagination, le tri, le filtrage par campagne.
     * Agrège les noms des types associés pour l'affichage et récupère les IDs des types
     * pour le pré-remplissage du formulaire de modification.
     *
     * @return void
     */
    public function list(): void {
        // Récupération et validation des paramètres de pagination, filtre et tri.
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 10; // Nombre de vaccins par page.

        // Filtre par campagne (validation entière).
        $filtre_campagne_id = isset($_GET['filtre_campagne_id']) ? filter_var($_GET['filtre_campagne_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : '';
        // Colonnes autorisées pour le tri (inclut la colonne agrégée 'type_names').
        $allowed_sort_columns = ['nom_vaccin', 'nom_campagne', 'type_names', 'description', 'vaccin_id'];
        $sort_by = $_GET['sort_by'] ?? 'vaccin_id';
        $sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC';

        // Validation des paramètres de tri.
        if (!in_array($sort_by, $allowed_sort_columns)) $sort_by = 'vaccin_id';
        if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') $sort_dir = 'DESC';

        // Construction de la clause WHERE pour le filtre par campagne.
        $whereConditions = [];
        $params = []; // Paramètres pour la requête préparée.
        if (!empty($filtre_campagne_id)) {
            $whereConditions[] = "v.campagne_id = ?"; // Alias 'v' pour vaccins.
            $params[] = $filtre_campagne_id;
        }
        $sqlWhere = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

        // Initialisation des variables pour la vue.
        $totalVaccins = 0;
        $vaccins_liste = [];
        $totalPages = 1;
        $paginationHtml = '';
        $available_campaigns = []; // Pour filtre et modale.
        $available_types = [];     // Pour modale.
        $action = 'liste'; // Pour la vue.

        // Construction de la query string et de l'URL de base pour pagination/tri.
        $currentQueryString = http_build_query(array_filter(['filtre_campagne_id' => $filtre_campagne_id, 'sort_by' => $sort_by, 'sort_dir' => $sort_dir]));
        $baseUrl = "index.php?controller=Vaccine&action=list" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        try {
            // Récupère les campagnes actives pour les listes déroulantes.
            $stmtCampagnes = $this->pdo->query("SELECT campagne_id, nom_campagne FROM campagnes WHERE is_active = TRUE ORDER BY nom_campagne");
            $available_campaigns = $stmtCampagnes->fetchAll(PDO::FETCH_ASSOC);

            // Récupère tous les types de vaccins pour les checkboxes de la modale.
            $stmtTypes = $this->pdo->query("SELECT type_vaccin_id, nom_type FROM type_vaccins ORDER BY nom_type");
            $available_types = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

            // Compte le nombre total de vaccins correspondant au filtre.
            // Note: Pas besoin de jointure ici car on filtre sur v.campagne_id.
            $sqlCount = "SELECT COUNT(v.vaccin_id) FROM vaccins v" . $sqlWhere;
            $totalStmt = $this->pdo->prepare($sqlCount);
            $totalStmt->execute($params);
            $totalVaccins = $totalStmt->fetchColumn();

            // Calcule les données de pagination.
            $paginationData = Paginator::getPaginationData($totalVaccins, $perPage, $page);
            $page = $paginationData['current_page'];
            $totalPages = $paginationData['total_pages'];
            $offset = $paginationData['offset'];

            // Construction de la clause ORDER BY.
             $orderByClause = " ORDER BY ";
             $colToSortBy = '';
             // Détermine la colonne ou l'alias réel pour le tri.
             switch ($sort_by) {
                 case 'nom_vaccin':   $colToSortBy = 'v.nom_vaccin'; break;   // Alias 'v'
                 case 'nom_campagne': $colToSortBy = 'c.nom_campagne'; break; // Alias 'c'
                 case 'description':  $colToSortBy = 'v.description'; break;  // Alias 'v'
                 case 'type_names':   $colToSortBy = 'type_names'; break;   // Alias de la colonne agrégée
                 case 'vaccin_id': default: $colToSortBy = 'v.vaccin_id'; break; // Alias 'v'
             }
             // Tri insensible à la casse pour les chaînes.
             if (in_array($sort_by, ['nom_vaccin', 'nom_campagne', 'description'])) {
                 $orderByClause .= "LOWER(" . $colToSortBy . ") " . $sort_dir;
             } else {
                 // Tri direct pour les autres colonnes (ID, ou l'agrégat déjà en chaîne).
                 $orderByClause .= $colToSortBy . " " . $sort_dir;
             }
             // Tri secondaire pour la stabilité.
              if ($sort_by !== 'vaccin_id') {
                  $orderByClause .= ", v.vaccin_id ASC";
              }

            // Requête SQL pour récupérer la liste des vaccins paginée et triée.
            // Utilise LEFT JOIN pour inclure les vaccins même s'ils n'ont pas de type associé.
            // Utilise string_agg (PostgreSQL) pour agréger les noms et les IDs des types associés.
            $sqlList = "
                SELECT
                    v.vaccin_id, v.nom_vaccin, v.description, v.campagne_id,
                    c.nom_campagne,
                    /* Agrège les IDs des types associés en une chaîne séparée par des virgules, triée par ID. Utile pour le JS de la modale. */
                    COALESCE(string_agg(tv.type_vaccin_id::text, ',' ORDER BY tv.type_vaccin_id), '') AS associated_type_ids_str,
                    /* Agrège les noms des types associés en une chaîne lisible, triée par nom. Affiche 'Aucun' si pas de type. */
                    COALESCE(string_agg(tv.nom_type, ', ' ORDER BY tv.nom_type), 'Aucun') AS type_names
                FROM vaccins v
                JOIN campagnes c ON v.campagne_id = c.campagne_id
                LEFT JOIN vaccin_type_associations vta ON v.vaccin_id = vta.vaccin_id
                LEFT JOIN type_vaccins tv ON vta.type_vaccin_id = tv.type_vaccin_id
                {$sqlWhere} /* Applique le filtre par campagne ici */
                GROUP BY v.vaccin_id, c.nom_campagne /* Groupement nécessaire à cause de string_agg */
                {$orderByClause} /* Applique le tri */
                LIMIT ? OFFSET ? /* Applique la pagination */
            ";
            $stmtList = $this->pdo->prepare($sqlList);

            // Lie les paramètres (filtre + pagination).
            $bindIndex = 1;
            foreach ($params as $param) { $stmtList->bindValue($bindIndex++, $param, PDO::PARAM_INT); }
            $stmtList->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
            $stmtList->bindValue($bindIndex++, $offset, PDO::PARAM_INT);

            // Exécute et récupère les vaccins.
            $stmtList->execute();
            $vaccins_liste = $stmtList->fetchAll(PDO::FETCH_ASSOC);

            // Génère le HTML de la pagination.
            if ($totalVaccins > 0 && $totalPages > 1) {
                 $paginationHtml = Paginator::render($baseUrl, $page, $totalPages);
            }

        } catch (PDOException $e) {
            // Gestion des erreurs PDO.
            error_log("Erreur lister vaccins: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la récupération des vaccins.", 'danger');
        }

        // Charge la vue de la liste des vaccins.
        require_once '../app/View/admin/vaccines/list.php';
    }

    /**
     * Sauvegarde un vaccin (ajout ou modification).
     *
     * Traite les données POST du formulaire modal. Gère l'insertion/mise à jour
     * du vaccin lui-même ET la gestion des associations avec les types de vaccins
     * dans la table pivot `vaccin_type_associations` (suppression puis réinsertion
     * des types sélectionnés lors de la modification). Le tout dans une transaction.
     *
     * @return void
     */
    public function save(): void {
        // Vérifie la méthode HTTP.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?controller=Vaccine&action=list"); exit();
        }

        // Récupération des données POST, détermination de l'action, page et matricule admin.
        $id = isset($_POST['vaccin_id']) ? (int)$_POST['vaccin_id'] : null;
        $action = $id ? 'modifier' : 'ajouter';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $matricule = Auth::getMatricule();

        $nom_vaccin = $_POST['nom_vaccin'] ?? '';
        $description = $_POST['description'] ?? '';
        $campagne_id = $_POST['campagne_id'] ?? null;
        // Récupère les IDs des types cochés sous forme de tableau.
        $selected_type_ids = $_POST['type_ids'] ?? [];

        // Construction de l'URL de redirection.
        $filtre_campagne_id_get = $_GET['filtre_campagne_id'] ?? '';
        $sort_by_get = $_GET['sort_by'] ?? 'vaccin_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['filtre_campagne_id' => $filtre_campagne_id_get, 'sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        $listUrl = "index.php?controller=Vaccine&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Validation des champs obligatoires (nom, campagne, au moins un type).
        if (empty($nom_vaccin) || empty($campagne_id) || empty($selected_type_ids)) {
             $errorMsg = '';
             if (empty($nom_vaccin)) $errorMsg = "Le nom du vaccin est requis.";
             elseif (empty($campagne_id)) $errorMsg = "Veuillez sélectionner une campagne.";
             else $errorMsg = "Veuillez sélectionner au moins un type."; // Si les types sont vides
             Flasher::setFlash("Erreur : " . $errorMsg, 'danger');
             header("Location: " . $listUrl); exit();
         }
         // Vérification de l'authentification.
         if (empty($matricule)) { Flasher::setFlash("Erreur d'authentification.", 'danger'); header("Location: index.php?controller=Auth&action=loginForm"); exit(); }

        // Début de la transaction pour assurer l'atomicité des opérations (vaccin + associations).
        $this->pdo->beginTransaction();
        try {
             // Si c'est un ajout :
             if ($action === 'ajouter') {
                 // Insère le vaccin et récupère l'ID généré (RETURNING vaccin_id).
                 $sqlVaccin = "INSERT INTO vaccins (nom_vaccin, description, campagne_id, matricule_creation) VALUES (?, ?, ?, ?) RETURNING vaccin_id";
                 $stmtVaccin = $this->pdo->prepare($sqlVaccin);
                 $stmtVaccin->execute([$nom_vaccin, $description, (int)$campagne_id, $matricule]);
                 $new_vaccin_id = $stmtVaccin->fetchColumn(); // Récupère l'ID inséré.
                 // Vérifie si l'ID a bien été récupéré.
                 if (!$new_vaccin_id) throw new \Exception("Impossible de récupérer l'ID du nouveau vaccin.");
                 $vaccin_id_to_use = $new_vaccin_id; // ID à utiliser pour les associations.
             } else { // Si c'est une modification :
                 // Met à jour le vaccin existant.
                 $sqlVaccin = "UPDATE vaccins SET nom_vaccin = ?, description = ?, campagne_id = ?, matricule_creation = ? WHERE vaccin_id = ?";
                 $stmtVaccin = $this->pdo->prepare($sqlVaccin);
                 $stmtVaccin->execute([$nom_vaccin, $description, (int)$campagne_id, $matricule, $id]);
                 $vaccin_id_to_use = $id; // ID existant à utiliser pour les associations.

                 // Supprime TOUTES les anciennes associations pour ce vaccin avant de réinsérer les nouvelles.
                 // C'est plus simple que de comparer les anciennes et nouvelles sélections.
                 $this->pdo->prepare("DELETE FROM vaccin_type_associations WHERE vaccin_id = ?")->execute([$vaccin_id_to_use]);
             }

             // Insère les nouvelles associations (pour ajout et modification).
             $sqlInsertAssoc = "INSERT INTO vaccin_type_associations (vaccin_id, type_vaccin_id) VALUES (?, ?)";
             $stmtInsertAssoc = $this->pdo->prepare($sqlInsertAssoc);
             // Boucle sur les IDs de types sélectionnés dans le formulaire.
             foreach ($selected_type_ids as $type_id) {
                 // Vérifie que l'ID est bien un entier avant de l'insérer.
                 if (filter_var($type_id, FILTER_VALIDATE_INT)) {
                     $stmtInsertAssoc->execute([$vaccin_id_to_use, (int)$type_id]);
                 }
             }

             // Valide la transaction si tout s'est bien passé.
             $this->pdo->commit();
             // Message de succès et redirection.
             Flasher::setFlash("Vaccin " . ($action === 'ajouter' ? 'ajouté' : 'modifié') . " avec succès.", 'success');
             header("Location: " . $listUrl); exit();

        // Capture les erreurs (PDO ou autre Exception).
        } catch (\Exception $e) { // Capture PDOException et Exception générique (comme celle lancée pour l'ID).
             // Annule la transaction.
             $this->pdo->rollBack();
             // Log l'erreur.
             error_log("Erreur sauvegarde vaccin: " . $e->getMessage());
             // Message flash spécifique pour les doublons.
              if (strpos($e->getMessage(), 'duplicate key value violates unique constraint') !== false) { // Probablement contrainte unique sur nom_vaccin?
                   Flasher::setFlash("Erreur : Un vaccin avec ce nom existe déjà.", 'danger');
              } else { // Autres erreurs.
                  Flasher::setFlash("Erreur lors de la sauvegarde du vaccin: " . $e->getMessage(), 'danger');
              }
             // Redirection vers la liste.
             header("Location: " . $listUrl); exit();
        }
    }

    /**
     * Supprime un vaccin et ses données associées.
     *
     * Supprime en cascade (manuellement) les enregistrements de vaccination (via les lots),
     * les lots, les associations vaccin-type, puis le vaccin lui-même.
     * Le tout dans une transaction.
     *
     * @return void
     */
    public function delete(): void {
        // Récupération de l'ID et des paramètres GET pour la redirection.
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $filtre_campagne_id_get = $_GET['filtre_campagne_id'] ?? '';
        $sort_by_get = $_GET['sort_by'] ?? 'vaccin_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['filtre_campagne_id' => $filtre_campagne_id_get, 'sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        $listUrl = "index.php?controller=Vaccine&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Validation de l'ID.
        if (!$id) {
            Flasher::setFlash("ID de vaccin invalide.", 'warning');
            header("Location: " . $listUrl); exit();
        }

         try {
            // Début de la transaction.
            $this->pdo->beginTransaction();
            // 1. Supprimer les enregistrements de vaccination liés aux lots de CE vaccin.
            $stmtRecs = $this->pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id = ?)");
            $stmtRecs->execute([$id]);
            // 2. Supprimer les lots liés à CE vaccin.
            $stmtLots = $this->pdo->prepare("DELETE FROM lots WHERE vaccin_id = ?");
            $stmtLots->execute([$id]);
            // 3. Supprimer les associations de type pour CE vaccin.
            $stmtAssoc = $this->pdo->prepare("DELETE FROM vaccin_type_associations WHERE vaccin_id = ?");
            $stmtAssoc->execute([$id]);
            // 4. Supprimer le vaccin lui-même.
            $stmt = $this->pdo->prepare("DELETE FROM vaccins WHERE vaccin_id = ?");
            $stmt->execute([$id]);
            $rowCount = $stmt->rowCount(); // Vérifie si le vaccin a été supprimé.
            // Valide la transaction.
            $this->pdo->commit();

            // Message flash.
            if ($rowCount > 0) { Flasher::setFlash("Vaccin et données associées supprimés.", 'success'); }
            else { Flasher::setFlash("Vaccin non trouvé ou déjà supprimé.", 'warning'); }
        } catch (PDOException $e) {
            // Annule la transaction en cas d'erreur.
            $this->pdo->rollBack();
            // Log et message flash.
            error_log("Erreur suppression vaccin ID {$id}: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la suppression.", 'danger');
        }
        // Redirection.
        header("Location: " . $listUrl); exit();
    }

    /**
     * Génère un nombre spécifié de vaccins de test avec Faker.
     *
     * Associe aléatoirement les vaccins générés à des campagnes actives
     * et à un ou plusieurs types de vaccins existants.
     *
     * @return void
     */
    public function generate(): void {
        // Récupération et validation du nombre à générer.
        $nombre = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 5;
        if ($nombre < 1) $nombre = 1; if ($nombre > 50) $nombre = 50; // Limite.
        $matricule_creation = Auth::getMatricule();

        // Vérification de la session admin.
        if (empty($matricule_creation)) { Flasher::setFlash("Erreur session admin.", 'danger'); header("Location: index.php?controller=Vaccine&action=list"); exit(); }

        try {
            // Récupère les IDs des campagnes actives.
            $stmtCampagnes = $this->pdo->query("SELECT campagne_id FROM campagnes WHERE is_active = TRUE");
            $available_campaigns_ids = $stmtCampagnes->fetchAll(PDO::FETCH_COLUMN);
            // Récupère les IDs de tous les types de vaccins existants.
            $stmtTypes = $this->pdo->query("SELECT type_vaccin_id FROM type_vaccins");
            $available_types_ids = $stmtTypes->fetchAll(PDO::FETCH_COLUMN);

            // Vérifie s'il existe des campagnes actives ET des types pour pouvoir générer.
            if (empty($available_campaigns_ids) || empty($available_types_ids)) {
                $msg = "Impossible de générer: ";
                if (empty($available_campaigns_ids)) $msg .= "aucune campagne active trouvée. ";
                if (empty($available_types_ids)) $msg .= "aucun type de vaccin trouvé.";
                Flasher::setFlash(trim($msg), 'warning');
                header("Location: index.php?controller=Vaccine&action=list"); exit();
            }

            // Initialisation de Faker, compteur et requêtes préparées (une pour vaccin, une pour association).
            $faker = Factory::create('fr_FR'); $count_success = 0;
            $sqlVaccin = "INSERT INTO vaccins (nom_vaccin, description, campagne_id, matricule_creation) VALUES (?, ?, ?, ?) RETURNING vaccin_id";
            $sqlAssoc = "INSERT INTO vaccin_type_associations (vaccin_id, type_vaccin_id) VALUES (?, ?)";
            $stmtVaccin = $this->pdo->prepare($sqlVaccin);
            $stmtAssoc = $this->pdo->prepare($sqlAssoc);

            // Boucle de génération.
            for ($i = 0; $i < $nombre; $i++) {
                // Génération de données aléatoires pour le vaccin.
                $nom_vaccin_gen = 'Vaccin ' . $faker->unique()->company . ' ' . $faker->randomElement(['Alpha', 'Beta', 'Gamma']) . '-' . $faker->randomNumber(3);
                $description_gen = $faker->catchPhrase;
                // Sélection aléatoire d'une campagne active.
                $random_campagne_id = $faker->randomElement($available_campaigns_ids);
                // Sélection aléatoire d'un nombre de types à associer (entre 1 et 3, ou moins si moins de types existent).
                $numTypesToAssociate = $faker->numberBetween(1, min(count($available_types_ids), 3));
                // Sélection aléatoire des IDs de types.
                $random_type_ids = $faker->randomElements($available_types_ids, $numTypesToAssociate);

                // Transaction pour insérer le vaccin ET ses associations.
                try {
                    $this->pdo->beginTransaction();
                    // Insère le vaccin et récupère son ID.
                    $stmtVaccin->execute([$nom_vaccin_gen, $description_gen, $random_campagne_id, $matricule_creation]);
                    $new_vaccin_id = $stmtVaccin->fetchColumn();
                    if (!$new_vaccin_id) throw new \Exception("ID vaccin non récupéré pour {$nom_vaccin_gen}");
                    // Insère les associations pour chaque type sélectionné.
                    foreach ($random_type_ids as $type_id) { $stmtAssoc->execute([$new_vaccin_id, $type_id]); }
                    // Valide la transaction.
                    $this->pdo->commit(); $count_success++;
                } catch (\Throwable $e) { // Capture PDOException et autres erreurs potentielles.
                    // Annule la transaction.
                    $this->pdo->rollBack();
                    // Log l'erreur et réinitialise Faker pour l'unicité.
                    error_log("[FAKER] Erreur génération vaccin '{$nom_vaccin_gen}': " . $e->getMessage());
                    $faker->unique(true); // Important pour la prochaine itération.
                }
            }
             $faker->unique(true); // Réinitialise après la boucle.
             // Message flash récapitulatif.
             Flasher::setFlash("{$count_success}/{$nombre} vaccins générés.", $count_success > 0 ? 'success' : 'danger');
        } catch (PDOException $e) {
             // Gestion des erreurs PDO globales.
             error_log("Erreur DB pendant génération vaccins: " . $e->getMessage());
             Flasher::setFlash("Erreur base de données lors de la génération.", 'danger');
        }
         // Redirection vers la liste.
         header("Location: index.php?controller=Vaccine&action=list"); exit();
    }

    /**
     * Supprime TOUS les vaccins et leurs données associées.
     *
     * Fonction de nettoyage dangereuse. Utilise une transaction.
     * Supprime enregistrements (via lots), lots, associations, puis vaccins.
     *
     * @return void
     */
    public function deleteAll(): void {
        try {
            // Début de la transaction.
            $this->pdo->beginTransaction();
            // 1. Supprime les enregistrements liés aux lots de N'IMPORTE QUEL vaccin.
            $this->pdo->exec("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE vaccin_id IS NOT NULL)");
            // 2. Supprime tous les lots qui ont un vaccin_id non null.
            $this->pdo->exec("DELETE FROM lots WHERE vaccin_id IS NOT NULL");
            // 3. Supprime toutes les associations vaccin-type.
            $this->pdo->exec("DELETE FROM vaccin_type_associations");
            // 4. Supprime tous les vaccins.
            $deletedRows = $this->pdo->exec("DELETE FROM vaccins");
            // Valide la transaction.
            $this->pdo->commit();
            // Message de succès.
            Flasher::setFlash("{$deletedRows} vaccins (et données associées) supprimés.", 'success');
        } catch (PDOException $e) {
             // Annule la transaction.
             $this->pdo->rollBack();
             // Log et message flash.
             error_log("Erreur suppression globale vaccins: " . $e->getMessage());
             Flasher::setFlash("Erreur lors de la suppression totale.", 'danger');
        }
        // Redirection vers la liste.
        header("Location: index.php?controller=Vaccine&action=list"); exit();
    }
}