<?php

/**
 * Espace de noms pour les contrôleurs de l'application.
 */
namespace App\Controller;

// Importations des classes nécessaires du Core, du Modèle et des bibliothèques externes (Faker).
use App\Core\Auth;                // Pour la gestion de l'authentification et des accès.
use App\Core\Flasher;             // Pour les messages flash (notifications).
use App\Core\Paginator;           // Pour la gestion de la pagination.
use App\Core\SortLinkGenerator;   // Pour générer les liens de tri dans les tableaux.
use App\Model\Database;           // Pour la connexion à la base de données.
use PDO;                          // Pour les interactions PDO (types, exceptions).
use PDOException;                 // Pour gérer les erreurs PDO.
use Faker\Factory;                // Pour générer des données de test aléatoires.
use DateTime;                     // Pour manipuler des dates (pas utilisé directement ici mais souvent utile).

/**
 * Contrôleur pour la gestion des campagnes de vaccination (CRUD).
 *
 * Ce contrôleur gère l'affichage, l'ajout, la modification, la suppression,
 * l'activation/désactivation et la génération de données de test pour les campagnes.
 * L'accès à ce contrôleur est réservé aux administrateurs.
 */
class CampaignController {

    /**
     * Instance de la connexion PDO à la base de données.
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur du contrôleur.
     *
     * Vérifie que l'utilisateur est un administrateur et initialise
     * la connexion à la base de données.
     */
    public function __construct() {
        // Vérifie si l'utilisateur a les droits d'administrateur.
        // Si non, redirige et arrête l'exécution.
        Auth::checkAdminAccess();
        // Récupère l'instance PDO pour les interactions avec la base de données.
        $this->pdo = Database::getInstance();
    }

    /**
     * Affiche la liste paginée et triable des campagnes.
     *
     * Récupère les campagnes depuis la base de données en tenant compte
     * de la pagination, du tri et des filtres (statut actif/inactif).
     * Prépare les données pour la vue (liste des campagnes, pagination HTML, etc.).
     *
     * @return void
     */
    public function list(): void {
        // Récupère le numéro de page actuel depuis l'URL (GET), défaut 1.
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        // Définit le nombre d'éléments par page.
        $perPage = 10;

        // Récupère le filtre de statut depuis l'URL (GET).
        $filtre_statut = $_GET['filtre_statut'] ?? '';
        // Définit les colonnes autorisées pour le tri.
        $allowed_sort_columns = ['nom_campagne', 'date_debut', 'date_fin', 'description', 'is_active', 'campagne_id'];
        // Récupère la colonne de tri et la direction depuis l'URL (GET), avec valeurs par défaut.
        $sort_by = $_GET['sort_by'] ?? 'campagne_id';
        $sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC';

        // Valide la colonne de tri et la direction.
        if (!in_array($sort_by, $allowed_sort_columns)) $sort_by = 'campagne_id';
        if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') $sort_dir = 'DESC';

        // Construit la clause WHERE pour le filtre de statut.
        $whereConditions = [];
        $params = []; // Paramètres pour la requête préparée.
        if ($filtre_statut === '1' || $filtre_statut === '0') {
            $whereConditions[] = "is_active = ?"; // Utilise un placeholder pour la sécurité.
            $params[] = (int)$filtre_statut;     // Ajoute la valeur au tableau de paramètres.
        }
        // Crée la chaîne SQL pour la clause WHERE.
        $sqlWhere = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

        // Initialisation des variables pour la vue.
        $totalCampagnes = 0;
        $campagnes = [];
        $totalPages = 1;
        $paginationHtml = '';
        // Construit la query string actuelle pour l'inclure dans les liens de pagination et de tri.
        $currentQueryString = http_build_query(array_filter(['filtre_statut' => $filtre_statut, 'sort_by' => $sort_by, 'sort_dir' => $sort_dir]));
        // Construit l'URL de base pour la pagination et le tri.
        $baseUrl = "index.php?controller=Campaign&action=list" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');
        // Variable pour indiquer l'action à la vue (utile si la vue gère plusieurs contextes).
        $action = 'liste';

        // Bloc try...catch pour gérer les erreurs PDO.
        try {
            // Compte le nombre total de campagnes correspondant aux filtres.
            $totalStmt = $this->pdo->prepare("SELECT COUNT(*) FROM campagnes" . $sqlWhere);
            $totalStmt->execute($params); // Exécute avec les paramètres de filtre.
            $totalCampagnes = $totalStmt->fetchColumn();

            // Calcule les données de pagination (page actuelle, total pages, offset).
            $paginationData = Paginator::getPaginationData($totalCampagnes, $perPage, $page);
            $page = $paginationData['current_page']; // Met à jour la page actuelle (peut être corrigée par Paginator).
            $totalPages = $paginationData['total_pages'];
            $offset = $paginationData['offset'];

            // Construit la clause ORDER BY pour le tri.
            $orderByClause = " ORDER BY ";
            // Utilise LOWER() pour un tri insensible à la casse sur les colonnes texte.
            if ($sort_by === 'nom_campagne' || $sort_by === 'description') {
                $orderByClause .= "LOWER(" . $sort_by . ") " . $sort_dir;
            } else {
                $orderByClause .= $sort_by . " " . $sort_dir;
            }
            // Ajoute un tri secondaire par ID pour assurer un ordre stable si les valeurs primaires sont égales.
            if ($sort_by !== 'campagne_id') {
                $orderByClause .= ", campagne_id ASC";
            }

            // Prépare la requête SQL pour récupérer la liste des campagnes paginée et triée.
            $sqlList = "SELECT * FROM campagnes" . $sqlWhere . $orderByClause . " LIMIT ? OFFSET ?";
            $stmtList = $this->pdo->prepare($sqlList);

            // Lie les paramètres du filtre (s'il y en a).
            $bindIndex = 1;
            foreach ($params as $param) {
                $stmtList->bindValue($bindIndex++, $param, PDO::PARAM_INT); // Type entier pour is_active.
            }
            // Lie les paramètres pour LIMIT et OFFSET.
            $stmtList->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
            $stmtList->bindValue($bindIndex++, $offset, PDO::PARAM_INT);

            // Exécute la requête.
            $stmtList->execute();
            // Récupère toutes les lignes résultantes sous forme de tableau associatif.
            $campagnes = $stmtList->fetchAll(PDO::FETCH_ASSOC);

             // Convertit la valeur 'is_active' (souvent une chaîne 't'/'f' ou 0/1 en DB) en booléen PHP.
             foreach ($campagnes as $key => $campagne) {
                 if (isset($campagne['is_active'])) {
                     $campagnes[$key]['is_active'] = filter_var($campagne['is_active'], FILTER_VALIDATE_BOOLEAN);
                 }
             }

             // Génère le HTML de la pagination si nécessaire.
             if ($totalCampagnes > 0 && $totalPages > 1){
                $paginationHtml = Paginator::render($baseUrl, $page, $totalPages);
             }

        // Capture les erreurs PDO.
        } catch (PDOException $e) {
            // Enregistre l'erreur détaillée dans les logs serveur.
            error_log("Erreur lister campagnes: " . $e->getMessage());
            // Définit un message flash pour l'utilisateur.
            Flasher::setFlash("Erreur lors de la récupération des campagnes.", 'danger');
            // Note: On ne redirige pas ici, la vue sera chargée et affichera le message d'erreur.
        }

        // Charge la vue pour afficher la liste des campagnes.
        // Les variables $campagnes, $paginationHtml, $page, $totalPages, $sort_by, $sort_dir, etc.
        // sont disponibles dans cette vue.
        require_once '../app/View/admin/campaigns/list.php';
    }

    /**
     * Sauvegarde une campagne (ajout ou modification).
     *
     * Traite les données soumises via le formulaire d'ajout/modification.
     * Valide les données, puis insère ou met à jour l'enregistrement
     * dans la base de données.
     *
     * @return void
     */
    public function save(): void {
        // Vérifie que la requête est bien POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?controller=Campaign&action=list"); exit();
        }

        // Récupère l'ID de la campagne depuis le formulaire (pour la modification).
        $id = isset($_POST['campagne_id']) ? (int)$_POST['campagne_id'] : null;
        // Détermine s'il s'agit d'un ajout ou d'une modification.
        $action = $id ? 'modifier' : 'ajouter';
        // Récupère la page actuelle pour la redirection.
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        // Récupère le matricule de l'administrateur connecté (qui effectue l'action).
        $matricule = Auth::getMatricule();

        // Récupère les données du formulaire.
        $nom_campagne = $_POST['nom_campagne'] ?? null;
        // Gère les dates vides comme NULL en base de données.
        $date_debut = empty($_POST['date_debut']) ? null : $_POST['date_debut'];
        $date_fin = empty($_POST['date_fin']) ? null : $_POST['date_fin'];
        $description = $_POST['description'] ?? '';
        // Gère la checkbox 'is_active'. Si cochée, la valeur est '1'.
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1';

        // Récupère les paramètres GET (filtre/tri) pour les conserver dans l'URL de redirection.
        $filtre_statut = $_GET['filtre_statut'] ?? '';
        $sort_by_get = $_GET['sort_by'] ?? 'campagne_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['filtre_statut' => $filtre_statut, 'sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        // Construit l'URL de redirection vers la liste, en conservant page, filtre et tri.
        $listUrl = "index.php?controller=Campaign&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Validation simple des champs obligatoires.
        if (empty($nom_campagne)) {
            Flasher::setFlash("Le nom de la campagne est obligatoire.", 'danger');
            header("Location: " . $listUrl); exit();
        }
        // Vérifie que l'administrateur est bien identifié.
        if (empty($matricule)) {
            Flasher::setFlash("Erreur d'authentification.", 'danger');
             // Redirige vers la page de connexion si problème d'authentification.
             header("Location: index.php?controller=Auth&action=loginForm"); exit();
        }

        // Bloc try...catch pour la sauvegarde en base de données.
        try {
             // Prépare la requête SQL appropriée (INSERT ou UPDATE).
             if ($action === 'ajouter') {
                 $sql = "INSERT INTO campagnes (nom_campagne, date_debut, date_fin, description, is_active, matricule_creation) VALUES (:nom, :debut, :fin, :desc, :active, :matricule)";
                 $stmt = $this->pdo->prepare($sql);
             } else { // Action 'modifier'
                 $sql = "UPDATE campagnes SET nom_campagne = :nom, date_debut = :debut, date_fin = :fin, description = :desc, is_active = :active, matricule_creation = :matricule WHERE campagne_id = :id";
                 $stmt = $this->pdo->prepare($sql);
                 // Lie l'ID uniquement pour la mise à jour.
                 $stmt->bindValue(':id', $id, PDO::PARAM_INT);
             }
             // Lie les valeurs communes aux deux types de requêtes.
             $stmt->bindValue(':nom', $nom_campagne);
             $stmt->bindValue(':debut', $date_debut); // PDO gère les NULL.
             $stmt->bindValue(':fin', $date_fin);     // PDO gère les NULL.
             $stmt->bindValue(':desc', $description);
             $stmt->bindValue(':active', $is_active, PDO::PARAM_BOOL); // Spécifie le type booléen.
             $stmt->bindValue(':matricule', $matricule);
             // Exécute la requête.
             $stmt->execute();

            // Définit un message de succès.
            Flasher::setFlash("Campagne " . ($action === 'ajouter' ? 'ajoutée' : 'modifiée') . " avec succès.", 'success');
            // Redirige vers la liste.
            header("Location: " . $listUrl); exit();

        // Capture les erreurs PDO.
        } catch (PDOException $e) {
             // Enregistre l'erreur détaillée.
             error_log("Erreur sauvegarde campagne: " . $e->getMessage());
             // Définit un message flash d'erreur pour l'utilisateur.
             Flasher::setFlash("Erreur lors de la sauvegarde de la campagne.", 'danger');
             // Redirige vers la liste.
             header("Location: " . $listUrl); exit();
        }
    }

    /**
     * Supprime une campagne et toutes ses données associées.
     *
     * Gère la suppression en cascade (manuellement via des requêtes DELETE)
     * des enregistrements de vaccination, lots, associations vaccin-type, vaccins,
     * puis la campagne elle-même, le tout dans une transaction pour assurer l'intégrité.
     *
     * @return void
     */
     public function delete(): void {
        // Récupère l'ID depuis l'URL (GET).
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        // Récupère la page pour la redirection.
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        // Récupère les paramètres GET (filtre/tri) pour la redirection.
        $filtre_statut = $_GET['filtre_statut'] ?? '';
        $sort_by_get = $_GET['sort_by'] ?? 'campagne_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['filtre_statut' => $filtre_statut, 'sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        $listUrl = "index.php?controller=Campaign&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Vérifie si l'ID est valide.
        if (!$id) {
            Flasher::setFlash("ID de campagne invalide.", 'warning');
            header("Location: " . $listUrl); exit();
        }

        // Bloc try...catch pour la suppression.
        try {
            // Démarre une transaction. Si une requête échoue, tout sera annulé (rollback).
            $this->pdo->beginTransaction();

            // Supprime les enregistrements de vaccination liés aux lots de cette campagne.
            $stmtVr = $this->pdo->prepare("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE campagne_id = ?)");
            $stmtVr->execute([$id]);

            // Supprime les lots de cette campagne.
            $stmtL = $this->pdo->prepare("DELETE FROM lots WHERE campagne_id = ?");
            $stmtL->execute([$id]);

            // Supprime les associations vaccin-type liées aux vaccins de cette campagne.
            $stmtVta = $this->pdo->prepare("DELETE FROM vaccin_type_associations WHERE vaccin_id IN (SELECT vaccin_id FROM vaccins WHERE campagne_id = ?)");
            $stmtVta->execute([$id]);

            // Supprime les vaccins de cette campagne.
            $stmtV = $this->pdo->prepare("DELETE FROM vaccins WHERE campagne_id = ?");
            $stmtV->execute([$id]);

            // Supprime la campagne elle-même.
            $stmtC = $this->pdo->prepare("DELETE FROM campagnes WHERE campagne_id = ?");
            $stmtC->execute([$id]);
            // Récupère le nombre de lignes affectées par la dernière requête (suppression de la campagne).
            $rowCount = $stmtC->rowCount();

            // Valide la transaction si tout s'est bien passé.
            $this->pdo->commit();

            // Affiche un message en fonction du résultat.
            if ($rowCount > 0) {
                Flasher::setFlash("Campagne (ID: {$id}) et données associées supprimées.", 'success');
            } else {
                Flasher::setFlash("Campagne (ID: {$id}) non trouvée ou déjà supprimée.", 'warning');
            }
        // Capture les erreurs PDO.
        } catch (PDOException $e) {
            // Annule la transaction en cas d'erreur.
            $this->pdo->rollBack();
            // Log l'erreur.
            error_log("Erreur suppression campagne ID {$id}: " . $e->getMessage());
            // Message flash pour l'utilisateur.
            Flasher::setFlash("Erreur lors de la suppression de la campagne (ID: {$id}).", 'danger');
        }
        // Redirige vers la liste dans tous les cas (succès ou échec).
        header("Location: " . $listUrl); exit();
    }

    /**
     * Active une campagne.
     * Raccourci pour appeler toggleActivation(true).
     * @return void
     */
    public function activate(): void {
         $this->toggleActivation(true);
    }

    /**
     * Désactive une campagne.
     * Raccourci pour appeler toggleActivation(false).
     * @return void
     */
    public function deactivate(): void {
         $this->toggleActivation(false);
    }

    /**
     * Méthode privée pour changer l'état (actif/inactif) d'une campagne.
     *
     * Met à jour le champ 'is_active' de la campagne spécifiée.
     *
     * @param bool $newState Le nouvel état souhaité (true pour actif, false pour inactif).
     * @return void
     */
    private function toggleActivation(bool $newState): void {
        // Récupère ID et page depuis GET.
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        // Construit l'URL de redirection (idem que dans list, save, delete).
        $filtre_statut = $_GET['filtre_statut'] ?? '';
        $sort_by_get = $_GET['sort_by'] ?? 'campagne_id';
        $sort_dir_get = $_GET['sort_dir'] ?? 'DESC';
        $currentQueryString = http_build_query(array_filter(['filtre_statut' => $filtre_statut, 'sort_by' => $sort_by_get, 'sort_dir' => $sort_dir_get]));
        $listUrl = "index.php?controller=Campaign&action=list&page=" . $page . (!empty($currentQueryString) ? '&' . $currentQueryString : '');

        // Valide l'ID.
        if (!$id) {
            Flasher::setFlash("ID de campagne invalide.", 'warning');
            header("Location: " . $listUrl); exit();
        }

        // Bloc try...catch pour la mise à jour.
        try {
             // Prépare la requête UPDATE.
             $sql = "UPDATE campagnes SET is_active = ? WHERE campagne_id = ?";
             $stmt = $this->pdo->prepare($sql);
             // Lie le nouvel état (booléen) et l'ID.
             $stmt->bindValue(1, $newState, PDO::PARAM_BOOL);
             $stmt->bindValue(2, $id, PDO::PARAM_INT);
             // Exécute.
             $stmt->execute();
             // Récupère le nombre de lignes modifiées.
             $rowCount = $stmt->rowCount();

             // Si au moins une ligne a été modifiée.
             if ($rowCount > 0) {
                 Flasher::setFlash("Statut de la campagne (ID: {$id}) mis à jour (" . ($newState ? "Activée" : "Désactivée") . ").", 'success');
             } else {
                 // Si aucune ligne modifiée, vérifie pourquoi :
                 // La campagne n'existe pas ? Ou était-elle déjà dans l'état souhaité ?
                 $checkStmt = $this->pdo->prepare("SELECT is_active FROM campagnes WHERE campagne_id = ?");
                 $checkStmt->execute([$id]);
                 $dbState = $checkStmt->fetchColumn(); // Récupère l'état actuel en base.

                 if ($dbState === false) { // fetchColumn retourne false si non trouvé.
                     Flasher::setFlash("Campagne (ID: {$id}) non trouvée.", 'warning');
                 } elseif (filter_var($dbState, FILTER_VALIDATE_BOOLEAN) === $newState) { // Compare l'état BD (converti en bool) avec l'état demandé.
                      Flasher::setFlash("La campagne (ID: {$id}) était déjà " . ($newState ? "active" : "inactive") . ".", 'info');
                 } else { // Autre cas (rare, pourrait indiquer un problème).
                      Flasher::setFlash("Impossible de mettre à jour le statut pour la campagne (ID: {$id}).", 'warning');
                 }
             }
        // Capture les erreurs PDO.
        } catch (PDOException $e) {
             // Log l'erreur.
             error_log("Erreur activation/desactivation campagne ID {$id}: " . $e->getMessage());
             // Message flash.
             Flasher::setFlash("Erreur base de données lors de la mise à jour du statut (ID: {$id}).", 'danger');
        }
        // Redirige vers la liste.
        header("Location: " . $listUrl); exit();
    }

    /**
     * Génère un nombre spécifié de campagnes de test avec Faker.
     *
     * Crée des campagnes avec des noms, dates, descriptions et statuts aléatoires.
     * Gère les potentielles collisions de noms uniques.
     *
     * @return void
     */
    public function generate(): void {
        // Récupère le nombre de campagnes à générer depuis l'URL (GET), avec limites.
        $nombre_campagnes = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 5;
        if ($nombre_campagnes < 1) $nombre_campagnes = 1;
        if ($nombre_campagnes > 50) $nombre_campagnes = 50; // Limite pour éviter abus/erreurs.

        // Initialise Faker pour générer des données en français.
        $faker = Factory::create('fr_FR');
        // Compteurs pour le suivi.
        $count_success = 0;
        $attempted_generations = 0;
        $max_attempts = $nombre_campagnes * 2; // Limite pour éviter boucle infinie en cas de collisions persistantes.
        // Récupère le matricule de l'admin pour l'enregistrer comme créateur.
        $matricule_creation = Auth::getMatricule();

        // Vérifie que la session admin est valide.
        if (empty($matricule_creation)) {
            Flasher::setFlash("Erreur: session admin invalide pour génération.", 'danger');
            header("Location: index.php?controller=Campaign&action=list"); exit();
        }

        // Bloc try...catch global pour la génération.
        try {
             // Vérification supplémentaire (peut-être redondante si Auth est fiable) que le matricule existe bien.
             $userCheckStmt = $this->pdo->prepare("SELECT 1 FROM users WHERE matricule = ?");
             $userCheckStmt->execute([$matricule_creation]);
             if ($userCheckStmt->fetchColumn() === false) {
                 Flasher::setFlash("Erreur fatale: Matricule admin invalide.", 'danger');
                  header("Location: index.php?controller=Campaign&action=list"); exit();
             }

            // Prépare la requête d'insertion.
            $sqlInsert = "INSERT INTO campagnes (nom_campagne, date_debut, date_fin, description, is_active, matricule_creation) VALUES (?, ?, ?, ?, ?, ?)";
            $stmtInsert = $this->pdo->prepare($sqlInsert);

            // Trouve le plus grand numéro existant dans les noms "Campagne X" pour commencer la numérotation.
            // Utilise une expression régulière PostgreSQL pour extraire le numéro.
            $maxNumStmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING(nom_campagne FROM E'^Campagne\\\\s+(\\\\d+)$') AS INTEGER)) FROM campagnes WHERE nom_campagne ~ E'^Campagne\\\\s+\\\\d+$'");
            $startNum = ($maxNumStmt->fetchColumn() ?: 0) + 1; // Commence au numéro suivant, ou 1 si aucun trouvé.

            // Boucle pour générer le nombre demandé de campagnes, avec limite de tentatives.
            while ($count_success < $nombre_campagnes && $attempted_generations < $max_attempts) {
                $attempted_generations++;
                $currentNum = $startNum + $count_success; // Numéro séquentiel.
                $nom_campagne = 'Campagne ' . $currentNum;
                $description = 'Description Campagne ' . $currentNum;

                $is_duplicate = false; // Flag pour gérer les doublons.
                 // Bloc try...catch interne pour gérer les erreurs d'insertion (ex: contrainte unique).
                 try {
                     // Génère des dates de début et fin aléatoires et logiques.
                     $date_debut_obj = $faker->dateTimeBetween('-1 year', '+2 months');
                     $date_debut = $date_debut_obj->format('Y-m-d');
                     $min_end_date = (clone $date_debut_obj)->modify('+1 day'); // Fin au moins 1 jour après début.
                     $max_end_date = (clone $date_debut_obj)->modify('+1 year'); // Fin max 1 an après début.
                     $date_fin_obj = $faker->dateTimeBetween($min_end_date, $max_end_date);
                     $date_fin = $date_fin_obj->format('Y-m-d');
                     // Génère un statut actif/inactif aléatoire (75% de chance d'être actif).
                     $is_active = $faker->boolean(75);

                    // Tente l'insertion.
                    if ($stmtInsert->execute([$nom_campagne, $date_debut, $date_fin, $description, $is_active, $matricule_creation])) {
                        $count_success++; // Incrémente si réussi.
                    }
                 // Capture l'erreur PDO.
                 } catch (PDOException $e) {
                     // Vérifie si l'erreur est une violation de contrainte unique (code '23505' ou message contenant 'duplicate key').
                     if ($e->getCode() == '23505' || (strpos($e->getMessage(), 'duplicate key') !== false)) {
                         // Log l'info et marque comme doublon.
                         error_log("[FAKER INFO] Doublon détecté pour campagne '{$nom_campagne}', tentative suivante.");
                         $is_duplicate = true;
                         // Génère un nom plus aléatoire pour la 2ème tentative.
                         $nom_campagne = 'Campagne ' . $faker->unique()->company . ' ' . $faker->randomNumber(4);
                         // Seconde tentative d'insertion avec le nom aléatoire.
                         try {
                              if ($stmtInsert->execute([$nom_campagne, $date_debut, $date_fin, $description, $is_active, $matricule_creation])) {
                                 $count_success++;
                                 $is_duplicate = false; // Réussi, on reset le flag doublon.
                              }
                         // Capture l'erreur PDO de la *seconde* tentative.
                         } catch (PDOException $e2){
                              // Si c'est encore un doublon (très improbable avec le nom aléatoire).
                              if ($e2->getCode() == '23505' || (strpos($e2->getMessage(), 'duplicate key') !== false)) {
                                  error_log("[FAKER WARNING] Doublon PERSISTANT même avec nom aléatoire pour '{$nom_campagne}'. Tentative suivante.");
                                  $faker->unique(true); // Réinitialise l'état unique de Faker pour la prochaine itération.
                              } else { // Autre erreur PDO sur la 2ème tentative.
                                  error_log("[FAKER ERROR] Erreur PDO (2ème tentative) pour '{$nom_campagne}': " . $e2->getMessage());
                              }
                         }
                     } else { // Erreur PDO autre qu'un doublon lors de la 1ère tentative.
                         error_log("[FAKER ERROR] Erreur PDO non-gérée pour campagne '{$nom_campagne}': " . $e->getMessage());
                     }
                 // Capture les erreurs générales (ex: problème avec Faker ou DateTime).
                 } catch (\Exception $e) {
                     error_log("[FAKER ERROR] Erreur génération données pour campagne: " . $e->getMessage());
                 }

                 // Si la première tentative était un doublon (et que la seconde a potentiellement échoué),
                 // on réinitialise Faker pour s'assurer que la prochaine tentative utilise bien des données uniques.
                 if ($is_duplicate) {
                    $faker->unique(true);
                 }
            } // Fin de la boucle while.

            // Réinitialise l'état unique de Faker à la fin.
            $faker->unique(true);

            // Définit le message flash final en fonction du nombre de succès.
            if ($count_success == $nombre_campagnes) {
                 Flasher::setFlash("{$count_success} campagnes de test générées avec succès.", 'success');
            } elseif ($count_success > 0) {
                 Flasher::setFlash("{$count_success}/{$nombre_campagnes} campagnes de test générées (certaines tentatives ont pu échouer, voir logs).", 'warning');
            } else {
                 Flasher::setFlash("Échec de la génération des campagnes de test (voir logs).", 'danger');
            }

             // Message supplémentaire si la limite de tentatives a été atteinte.
             if ($attempted_generations >= $max_attempts && $count_success < $nombre_campagnes) {
                 Flasher::setFlash("Limite de tentatives atteinte ({$max_attempts}) pour générer {$nombre_campagnes} campagnes. {$count_success} ont été créées. Vérifiez les contraintes ou les logs.", 'warning');
             }

        // Capture les erreurs PDO globales pendant la génération.
        } catch (PDOException $e) {
            error_log("Erreur pendant la génération de campagnes: " . $e->getMessage());
            Flasher::setFlash("Erreur base de données lors de la génération.", 'danger');
        }
        // Redirige vers la liste.
        header("Location: index.php?controller=Campaign&action=list"); exit();
    }

    /**
     * Supprime TOUTES les campagnes et leurs données associées.
     *
     * Fonction dangereuse destinée principalement au nettoyage lors du développement/test.
     * Supprime en cascade (manuellement) tout ce qui dépend des campagnes.
     *
     * @return void
     */
    public function deleteAll(): void {
         // Bloc try...catch pour la suppression globale.
         try {
            // Démarre une transaction.
            $this->pdo->beginTransaction();
            // Supprime tous les enregistrements de vaccination liés aux lots des campagnes existantes.
            // Utilise des sous-requêtes pour identifier les lots/vaccins concernés.
            $this->pdo->exec("DELETE FROM vaccination_records WHERE lot_id IN (SELECT lot_id FROM lots WHERE campagne_id IN (SELECT campagne_id FROM campagnes))");
            // Supprime tous les lots liés aux campagnes.
            $this->pdo->exec("DELETE FROM lots WHERE campagne_id IN (SELECT campagne_id FROM campagnes)");
            // Supprime toutes les associations vaccin-type liées aux vaccins des campagnes.
            $this->pdo->exec("DELETE FROM vaccin_type_associations WHERE vaccin_id IN (SELECT vaccin_id FROM vaccins WHERE campagne_id IN (SELECT campagne_id FROM campagnes))");
            // Supprime tous les vaccins liés aux campagnes.
            $this->pdo->exec("DELETE FROM vaccins WHERE campagne_id IN (SELECT campagne_id FROM campagnes)");
            // Supprime toutes les campagnes. exec() retourne le nombre de lignes affectées.
            $deletedRows = $this->pdo->exec("DELETE FROM campagnes");
            // Valide la transaction.
            $this->pdo->commit();
             // Message de succès indiquant le nombre de campagnes supprimées.
             Flasher::setFlash("{$deletedRows} campagnes et données associées supprimées.", 'success');
        // Capture les erreurs PDO.
        } catch (PDOException $e) {
            // Annule la transaction.
            $this->pdo->rollBack();
            // Log l'erreur.
            error_log("Erreur suppression globale campagnes: " . $e->getMessage());
            // Message flash d'erreur.
            Flasher::setFlash("Erreur lors de la suppression globale des campagnes.", 'danger');
        }
        // Redirige vers la liste.
        header("Location: index.php?controller=Campaign&action=list"); exit();
    }

}