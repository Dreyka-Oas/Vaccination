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
use DateTime;                     // Manipulation des dates (utilisé indirectement ou pour validation).

/**
 * Contrôleur pour la gestion des lots de vaccins (CRUD).
 *
 * Gère l'affichage (liste paginée, triée, filtrée), l'ajout, la modification,
 * la suppression, la génération de données de test et la suppression globale des lots.
 * Fournit également une méthode AJAX pour récupérer les vaccins associés à une campagne.
 * L'accès est réservé aux administrateurs.
 */
class LotController {

    /**
     * Instance de la connexion PDO.
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur du contrôleur LotController.
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
     * Récupère les vaccins associés à une campagne via AJAX.
     *
     * Utilisé par le formulaire (modale) d'ajout/modification de lot pour
     * remplir dynamiquement la liste déroulante des vaccins lorsqu'une
     * campagne est sélectionnée. Retourne les données au format JSON.
     *
     * @return void
     */
    public function getVaccinsAjax(): void {
        // Définit le type de contenu de la réponse comme JSON.
        header('Content-Type: application/json');
        // Récupère l'ID de la campagne depuis les paramètres GET.
        $campagneId = isset($_GET['campagne_id']) ? (int)$_GET['campagne_id'] : 0;
        $vaccins = []; // Initialise le tableau des vaccins.

        // Ne procède que si l'ID de campagne est valide (supérieur à 0).
        if ($campagneId > 0) {
            try {
                // Prépare et exécute la requête pour trouver les vaccins de cette campagne.
                $stmt = $this->pdo->prepare("SELECT vaccin_id, nom_vaccin FROM vaccins WHERE campagne_id = ? ORDER BY nom_vaccin");
                $stmt->execute([$campagneId]);
                // Récupère tous les vaccins trouvés.
                $vaccins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // En cas d'erreur, loggue l'erreur détaillée.
                error_log("Erreur AJAX get_vaccins pour campagne ID {$campagneId}: " . $e->getMessage());
                // Ne renvoie pas l'erreur détaillée au client pour des raisons de sécurité.
                // Le front-end gérera l'absence de données ou affichera une erreur générique.
            }
        }
        // Encode le tableau des vaccins (peut être vide) en JSON et l'affiche.
        echo json_encode($vaccins);
        // Termine l'exécution du script car c'est une réponse AJAX.
        exit;
    }

    /**
     * Affiche la liste paginée, triée et filtrable des lots.
     *
     * Récupère les lots en joignant les tables campagnes et vaccins pour
     * afficher leurs noms. Gère la pagination, le tri et le filtrage par campagne.
     * Prépare les données pour la vue.
     *
     * @return void
     */
    public function list(): void {
        // Récupération et validation des paramètres de pagination, tri et filtre.
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 10;
        // Utilise filter_var pour valider l'ID de campagne du filtre.
        $filtre_campagne_id = isset($_GET['filtre_campagne_id']) ? filter_var($_GET['filtre_campagne_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : '';
        $allowed_sort_columns = ['nom_lot', 'nom_vaccin', 'date_expiration', 'nom_campagne', 'lot_id'];
        $sort_by = $_GET['sort_by'] ?? 'lot_id';
        $sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC';

        // Validation des paramètres de tri.
        if (!in_array($sort_by, $allowed_sort_columns)) $sort_by = 'lot_id';
        if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') $sort_dir = 'DESC';

        // Construction de la clause WHERE pour le filtre par campagne.
        $whereConditions = [];
        $params = []; // Paramètres pour la requête préparée.
        if (!empty($filtre_campagne_id)) {
            $whereConditions[] = "l.campagne_id = ?"; // Utilise l'alias 'l' pour la table lots.
            $params[] = $filtre_campagne_id;
        }
        $sqlWhere = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

        // Initialisation des variables pour la vue.
        $totalLots = 0;
        $lots_liste = [];
        $totalPages = 1;
        $paginationHtml = '';
        $available_active_campaigns = []; // Pour remplir la liste déroulante du filtre et de la modale.
        $action = 'liste'; // Pour la vue.

        // Construction de la query string et de l'URL de base pour la pagination/tri.
        $currentQueryString = http_build_query(array_filter(['filtre_campagne_id' => $filtre_campagne_id, 'sort_by' => $sort_by, 'sort_dir' => $sort_dir]));
        $baseUrl = "index.php?controller=Lot&action=list" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        try {
            // Récupère les campagnes actives pour les listes déroulantes (filtre et modale).
            $stmtCampagnes = $this->pdo->query("SELECT campagne_id, nom_campagne FROM campagnes WHERE is_active = TRUE ORDER BY nom_campagne");
            $available_active_campaigns = $stmtCampagnes->fetchAll(PDO::FETCH_ASSOC);

            // Compte le nombre total de lots correspondant au filtre.
            // Jointure implicite nécessaire si on filtrait sur des colonnes jointes. Ici, ok.
            $sqlCount = "SELECT COUNT(l.lot_id) FROM lots l " . $sqlWhere;
            $totalStmt = $this->pdo->prepare($sqlCount);
            $totalStmt->execute($params);
            $totalLots = $totalStmt->fetchColumn();

            // Calcule les données de pagination.
            $paginationData = Paginator::getPaginationData($totalLots, $perPage, $page);
            $page = $paginationData['current_page'];
            $totalPages = $paginationData['total_pages'];
            $offset = $paginationData['offset'];

            // Construction de la clause ORDER BY, en gérant les alias des tables jointes.
             $orderByClause = " ORDER BY ";
             $colToSortBy = '';
             // Détermine la colonne réelle en fonction du paramètre 'sort_by'.
             switch ($sort_by) {
                 case 'nom_lot':        $colToSortBy = 'l.nom_lot'; break;       // Alias 'l' pour lots
                 case 'nom_vaccin':     $colToSortBy = 'v.nom_vaccin'; break;    // Alias 'v' pour vaccins
                 case 'date_expiration':$colToSortBy = 'l.date_expiration'; break;// Alias 'l'
                 case 'nom_campagne':   $colToSortBy = 'c.nom_campagne'; break;   // Alias 'c' pour campagnes
                 case 'lot_id': default: $colToSortBy = 'l.lot_id'; break;       // Alias 'l'
             }
             // Tri insensible à la casse pour les chaînes.
             if (in_array($sort_by, ['nom_lot', 'nom_vaccin', 'nom_campagne'])) {
                 $orderByClause .= "LOWER(" . $colToSortBy . ") " . $sort_dir;
             } else {
                 $orderByClause .= $colToSortBy . " " . $sort_dir;
             }
             // Tri secondaire par ID pour la stabilité.
              if ($sort_by !== 'lot_id') { $orderByClause .= ", l.lot_id ASC"; }

            // Requête SQL pour récupérer la liste des lots avec les noms de campagne et vaccin.
            // Utilise les alias définis dans la clause ORDER BY.
            $sqlList = "
                SELECT l.lot_id, l.nom_lot, l.date_expiration, c.nom_campagne, l.campagne_id, l.vaccin_id, v.nom_vaccin
                FROM lots l
                JOIN campagnes c ON l.campagne_id = c.campagne_id
                JOIN vaccins v ON l.vaccin_id = v.vaccin_id
                {$sqlWhere} {$orderByClause} LIMIT ? OFFSET ?"; // Inclut WHERE et ORDER BY.
            $stmtList = $this->pdo->prepare($sqlList);

            // Lie les paramètres (filtre + pagination).
            $bindIndex = 1;
            foreach ($params as $param) { $stmtList->bindValue($bindIndex++, $param, PDO::PARAM_INT); }
            $stmtList->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
            $stmtList->bindValue($bindIndex++, $offset, PDO::PARAM_INT);

            // Exécute et récupère les lots.
            $stmtList->execute();
            $lots_liste = $stmtList->fetchAll(PDO::FETCH_ASSOC);

            // Génère le HTML de la pagination si besoin.
             if ($totalLots > 0 && $totalPages > 1) {
                 $paginationHtml = Paginator::render($baseUrl, $page, $totalPages);
             }

        } catch (PDOException $e) {
            // Gestion des erreurs PDO.
            error_log("Erreur lister lots: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la récupération des lots.", 'danger');
        }

        // Charge la vue de la liste des lots.
        require_once '../app/View/admin/lots/list.php';
    }

    /**
     * Sauvegarde un lot (ajout ou modification).
     *
     * Traite les données POST du formulaire. Valide que le vaccin choisi
     * appartient bien à la campagne sélectionnée avant d'insérer ou de mettre à jour.
     * Gère les erreurs, notamment les doublons sur le nom du lot.
     *
     * @return void
     */
    public function save(): void {
        // Vérifie la méthode HTTP.
         if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             header("Location: index.php?controller=Lot&action=list"); exit();
         }

        // Récupération des données POST et détermination de l'action (ajout/modif).
        $id = isset($_POST['lot_id']) ? (int)$_POST['lot_id'] : null;
        $action = $id ? 'modifier' : 'ajouter';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $matricule = Auth::getMatricule(); // Matricule de l'admin créateur/modificateur.

        $nom_lot = $_POST['nom_lot'] ?? null;
        $date_expiration = $_POST['date_expiration'] ?? null;
        $campagne_id = $_POST['campagne_id'] ?? null;
        $vaccin_id = $_POST['vaccin_id'] ?? null;

        // Récupération des paramètres GET pour la redirection.
        $filtre_campagne_id_get = $_GET['filtre_campagne_id'] ?? '';
        $sort_by_get = $_GET['sort_by'] ?? 'lot_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['filtre_campagne_id' => $filtre_campagne_id_get, 'sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        $listUrl = "index.php?controller=Lot&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Validation des champs obligatoires.
        if (empty($nom_lot) || empty($date_expiration) || empty($campagne_id) || empty($vaccin_id)) {
             Flasher::setFlash("Tous les champs sont requis.", 'danger');
             header("Location: " . $listUrl); exit();
        }
        // Vérification de l'authentification.
        if (empty($matricule)) { Flasher::setFlash("Erreur d'authentification.", 'danger'); header("Location: index.php?controller=Auth&action=loginForm"); exit(); }

         try {
            // Vérification cruciale: le vaccin sélectionné appartient-il bien à la campagne choisie ?
            $vaccinCampagneCheckStmt = $this->pdo->prepare("SELECT 1 FROM vaccins WHERE vaccin_id = ? AND campagne_id = ?");
            $vaccinCampagneCheckStmt->execute([(int)$vaccin_id, (int)$campagne_id]);
            // Si fetchColumn() retourne false, l'association n'existe pas.
            if ($vaccinCampagneCheckStmt->fetchColumn() === false) {
                 Flasher::setFlash("Le vaccin sélectionné n'appartient pas à la campagne choisie.", 'danger');
                 header("Location: " . $listUrl); exit();
            }

            // Exécution de l'INSERT ou de l'UPDATE.
             if ($action === 'ajouter') {
                 // Utilise des placeholders '?' pour bind par position.
                 $sql = "INSERT INTO lots (nom_lot, date_expiration, campagne_id, vaccin_id, matricule_creation) VALUES (?, ?, ?, ?, ?)";
                 $stmt = $this->pdo->prepare($sql);
                 $stmt->execute([$nom_lot, $date_expiration, (int)$campagne_id, (int)$vaccin_id, $matricule]);
             } else { // Action 'modifier'
                 $sql = "UPDATE lots SET nom_lot = ?, date_expiration = ?, campagne_id = ?, vaccin_id = ?, matricule_creation = ? WHERE lot_id = ?";
                 $stmt = $this->pdo->prepare($sql);
                 // L'ID est le dernier paramètre pour la clause WHERE.
                 $stmt->execute([$nom_lot, $date_expiration, (int)$campagne_id, (int)$vaccin_id, $matricule, $id]);
             }

             // Message de succès et redirection.
             Flasher::setFlash("Lot " . ($action === 'ajouter' ? 'ajouté' : 'modifié') . " avec succès.", 'success');
             header("Location: " . $listUrl); exit();

        } catch (PDOException $e) {
             // Gestion des erreurs PDO lors de la sauvegarde.
             error_log("Erreur sauvegarde lot: " . $e->getMessage());
             // Détection spécifique de l'erreur de contrainte unique sur le nom du lot.
              if (strpos($e->getMessage(), 'duplicate key value violates unique constraint') !== false || $e->getCode() == '23505') { // Code 23505 = unique_violation
                  Flasher::setFlash("Erreur : Le nom de lot '{$nom_lot}' existe déjà.", 'danger');
              } else { // Autres erreurs PDO.
                 Flasher::setFlash("Erreur lors de la sauvegarde du lot: " . $e->getMessage(), 'danger');
              }
             // Redirection vers la liste en cas d'erreur.
             header("Location: " . $listUrl); exit();
        }
    }

    /**
     * Supprime un lot et ses enregistrements de vaccination associés.
     *
     * Utilise une transaction pour s'assurer que le lot et ses enregistrements
     * sont supprimés de manière atomique.
     *
     * @return void
     */
    public function delete(): void {
        // Récupération de l'ID et des paramètres GET pour la redirection.
         $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

         $filtre_campagne_id_get = $_GET['filtre_campagne_id'] ?? '';
         $sort_by_get = $_GET['sort_by'] ?? 'lot_id';
         $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
         $currentQueryString = http_build_query(array_filter(['filtre_campagne_id' => $filtre_campagne_id_get, 'sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
         $listUrl = "index.php?controller=Lot&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Validation de l'ID.
        if (!$id) {
            Flasher::setFlash("ID de lot invalide.", 'warning');
            header("Location: " . $listUrl); exit();
        }

        try {
             // Début de la transaction.
             $this->pdo->beginTransaction();

             // Supprime d'abord les enregistrements de vaccination liés à ce lot.
             $stmtDelRecs = $this->pdo->prepare("DELETE FROM vaccination_records WHERE lot_id = ?");
             $stmtDelRecs->execute([$id]);
             // Compte le nombre d'enregistrements supprimés (pour information).
             $deletedRecs = $stmtDelRecs->rowCount();

             // Ensuite, supprime le lot lui-même.
             $stmtDelLot = $this->pdo->prepare("DELETE FROM lots WHERE lot_id = ?");
             $stmtDelLot->execute([$id]);
             // Compte si le lot a été effectivement supprimé.
             $rowCount = $stmtDelLot->rowCount();

             // Valide la transaction.
             $this->pdo->commit();

             // Message de succès ou d'avertissement.
             if ($rowCount > 0) {
                 // Message incluant le nombre d'enregistrements liés supprimés.
                 Flasher::setFlash("Lot supprimé" . ($deletedRecs > 0 ? " (avec {$deletedRecs} enregistrement(s))" : "") . ".", 'success');
             } else {
                 Flasher::setFlash("Lot non trouvé.", 'warning');
             }
        } catch (PDOException $e) {
            // En cas d'erreur, annule la transaction.
            $this->pdo->rollBack();
            // Log l'erreur et message flash.
            error_log("Erreur suppression lot ID {$id}: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la suppression.", 'danger');
        }
        // Redirection vers la liste.
        header("Location: " . $listUrl); exit();
    }

    /**
     * Génère un nombre spécifié de lots de test aléatoires.
     *
     * Associe aléatoirement les lots générés à des vaccins appartenant
     * à des campagnes actives.
     *
     * @return void
     */
    public function generate(): void {
        // Récupération et validation du nombre à générer.
        $nombre = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 10;
        if ($nombre < 1) $nombre = 1; if ($nombre > 100) $nombre = 100; // Limite max.
        $matricule_creation = Auth::getMatricule();

        // Vérification de la session admin.
        if (empty($matricule_creation)) { Flasher::setFlash("Erreur session admin.", 'danger'); header("Location: index.php?controller=Lot&action=list"); exit(); }

        try {
             // Récupère les paires (vaccin_id, campagne_id) valides :
             // vaccin appartenant à une campagne active.
             $stmtVaccinsActifs = $this->pdo->query("SELECT v.vaccin_id, v.campagne_id FROM vaccins v JOIN campagnes c ON v.campagne_id = c.campagne_id WHERE c.is_active = TRUE");
             $validVaccinData = $stmtVaccinsActifs->fetchAll(PDO::FETCH_ASSOC);

             // Si aucune donnée valide n'est trouvée, impossible de générer des lots.
             if (empty($validVaccinData)) {
                 Flasher::setFlash("Impossible de générer: Aucune campagne active ou vaccin associé trouvé.", 'warning');
                  header("Location: index.php?controller=Lot&action=list"); exit();
             }

            // Initialisation de Faker et compteur de succès.
            $faker = Factory::create('fr_FR'); $count_success = 0;
            // Requête d'insertion préparée.
            $sqlInsert = "INSERT INTO lots (nom_lot, date_expiration, campagne_id, vaccin_id, matricule_creation) VALUES (?, ?, ?, ?, ?)";
            $stmtInsert = $this->pdo->prepare($sqlInsert);

            // Boucle pour générer les lots.
             for ($i = 0; $i < $nombre; $i++) {
                 try {
                     // Choisit aléatoirement une paire (vaccin, campagne) valide.
                     $randomVaccinInfo = $faker->randomElement($validVaccinData);
                     $selected_vaccin_id = $randomVaccinInfo['vaccin_id'];
                     $selected_campagne_id = $randomVaccinInfo['campagne_id'];
                     // Génère un nom de lot unique et une date d'expiration future.
                     $nom_lot_gen = 'LOT-' . $faker->unique()->bothify('??######??'); // Assure l'unicité.
                     $date_exp_gen = $faker->dateTimeBetween('+3 months', '+24 months')->format('Y-m-d');
                     // Exécute l'insertion.
                     if ($stmtInsert->execute([ $nom_lot_gen, $date_exp_gen, $selected_campagne_id, $selected_vaccin_id, $matricule_creation ])) {
                        $count_success++; // Incrémente si succès.
                     }
                  } catch (\PDOException $e) {
                      // En cas d'erreur PDO (ex: doublon malgré unique()), loggue et réinitialise Faker.
                      error_log("[FAKER] Erreur PDO insertion lot: " . $e->getMessage()); $faker->unique(true);
                  } catch (\Throwable $e) {
                      // Capture d'autres erreurs potentielles (ex: Faker).
                      error_log("[FAKER] Erreur General lot: " . $e->getMessage());
                  }
             }
              // Réinitialise l'unicité de Faker après la boucle.
              $faker->unique(true);
             // Message flash récapitulatif.
             Flasher::setFlash("{$count_success}/{$nombre} lots générés.", $count_success > 0 ? 'success' : 'danger');
        } catch (PDOException $e) {
             // Gestion des erreurs PDO globales pendant la génération.
             error_log("Erreur DB pendant génération lots: " . $e->getMessage());
             Flasher::setFlash("Erreur base de données lors de la génération.", 'danger');
        }
        // Redirection vers la liste.
        header("Location: index.php?controller=Lot&action=list"); exit();
    }

    /**
     * Supprime TOUS les lots et leurs enregistrements de vaccination associés.
     *
     * Fonction de nettoyage dangereuse. Utilise une transaction.
     *
     * @return void
     */
    public function deleteAll(): void {
        try {
            // Début de la transaction.
            $this->pdo->beginTransaction();
            // Supprime tous les enregistrements liés à n'importe quel lot.
            $deletedRecs = $this->pdo->exec("DELETE FROM vaccination_records WHERE lot_id IS NOT NULL");
            // Supprime tous les lots.
            $deletedLots = $this->pdo->exec("DELETE FROM lots");
            // Valide la transaction.
            $this->pdo->commit();
            // Message de succès.
            Flasher::setFlash("{$deletedLots} lots et {$deletedRecs} enregistrements supprimés.", 'success');
        } catch (PDOException $e) {
            // Annule la transaction en cas d'erreur.
            $this->pdo->rollBack();
            // Log l'erreur et message flash.
            error_log("Erreur suppression globale lots: " . $e->getMessage());
            Flasher::setFlash("Erreur lors de la suppression totale.", 'danger');
        }
        // Redirection vers la liste.
        header("Location: index.php?controller=Lot&action=list"); exit();
    }

}