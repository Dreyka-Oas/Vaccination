<?php

/**
 * Espace de noms pour les contrôleurs de l'application.
 */
namespace App\Controller;

// Importations des classes nécessaires.
use App\Core\Auth;        // Gestion de l'authentification et récupération des infos utilisateur.
use App\Core\Flasher;     // Messages flash pour l'utilisateur.
use App\Model\Database;   // Connexion à la base de données.
use PDO;                  // Utilisation de PDO.
use PDOException;         // Gestion des erreurs PDO.
use DateTime;             // Manipulation des dates.
use Faker\Factory;        // Génération de données de test (si admin).

/**
 * Contrôleur pour la gestion des enregistrements de vaccination.
 *
 * Gère l'affichage du formulaire/liste, l'ajout, la modification, la suppression
 * et la génération de données de test pour les enregistrements de vaccination.
 * Fournit également des méthodes AJAX pour charger dynamiquement les vaccins et lots
 * dans le formulaire d'ajout/modification.
 * L'accès général nécessite une authentification, certaines actions (génération) nécessitent
 * des droits d'administrateur.
 */
class VaccinationRecordController {

    /**
     * Instance de la connexion PDO.
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur du contrôleur VaccinationRecordController.
     *
     * Vérifie que l'utilisateur est authentifié et initialise la connexion PDO.
     */
    public function __construct() {
        // Vérifie que l'utilisateur est connecté (peut être admin ou autre rôle).
        Auth::checkAuthentication();
        // Récupère l'instance PDO.
        $this->pdo = Database::getInstance();
    }

    /**
     * Récupère les vaccins associés à une campagne via AJAX.
     *
     * Utilisé par le formulaire modal pour peupler la liste déroulante des vaccins
     * après sélection d'une campagne. Renvoie une réponse JSON.
     *
     * @return void
     */
    public function fetchVaccinsAjax(): void {
        // Définit l'en-tête pour une réponse JSON.
        header('Content-Type: application/json');
        // Initialise la structure de la réponse.
        $response = ['error' => null, 'vaccins' => []];
        // Récupère et valide l'ID de campagne depuis les paramètres GET.
        $campagne_id = filter_input(INPUT_GET, 'campagne_id', FILTER_VALIDATE_INT);

        // Si l'ID de campagne est invalide, renvoie une erreur JSON et arrête.
        if (!$campagne_id || $campagne_id <= 0) {
            $response['error'] = 'ID de campagne invalide.';
            echo json_encode($response); exit;
        }

        // Récupère les vaccins correspondants.
        try {
            $stmt = $this->pdo->prepare("SELECT vaccin_id, nom_vaccin FROM vaccins WHERE campagne_id = :campagne_id ORDER BY nom_vaccin");
            $stmt->bindParam(':campagne_id', $campagne_id, PDO::PARAM_INT);
            $stmt->execute();
            $response['vaccins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Si aucun vaccin n'est trouvé, ajoute une note d'erreur (pas bloquant).
            if (empty($response['vaccins'])) { $response['error'] = 'Aucun vaccin trouvé pour cette campagne.'; }
        } catch (PDOException $e) {
            // En cas d'erreur DB, loggue l'erreur et renvoie une erreur générique.
            error_log("AJAX Fetch Vaccins Error: " . $e->getMessage());
            $response['error'] = 'Erreur base de données (vaccins).';
        }
        // Encode la réponse en JSON et l'envoie.
        echo json_encode($response); exit;
    }

    /**
     * Récupère les lots valides (non expirés) associés à une campagne et un vaccin via AJAX.
     *
     * Utilisé par le formulaire modal pour peupler la liste déroulante des lots
     * après sélection d'une campagne et d'un vaccin. Renvoie une réponse JSON.
     *
     * @return void
     */
    public function fetchLotsAjax(): void {
        // Définit l'en-tête pour une réponse JSON.
        header('Content-Type: application/json');
        // Initialise la structure de la réponse.
        $response = ['error' => null, 'lots' => []];
        // Récupère et valide les ID de campagne et de vaccin.
        $campagne_id = filter_input(INPUT_GET, 'campagne_id', FILTER_VALIDATE_INT);
        $vaccin_id = filter_input(INPUT_GET, 'vaccin_id', FILTER_VALIDATE_INT);

        // Si les ID sont invalides, renvoie une erreur JSON et arrête.
        if (!$campagne_id || $campagne_id <= 0 || !$vaccin_id || $vaccin_id <= 0) {
            $response['error'] = 'ID de campagne ou de vaccin invalide.';
            echo json_encode($response); exit;
        }

        // Récupère les lots correspondants et non expirés.
        try {
            // Prépare la requête : sélectionne les lots correspondants à la campagne ET au vaccin,
            // ET dont la date d'expiration est supérieure ou égale à la date actuelle.
            $stmt = $this->pdo->prepare("SELECT lot_id, nom_lot, date_expiration FROM lots
                                        WHERE campagne_id = :campagne_id AND vaccin_id = :vaccin_id
                                          AND date_expiration >= CURRENT_DATE ORDER BY nom_lot");
            $stmt->bindParam(':campagne_id', $campagne_id, PDO::PARAM_INT);
            $stmt->bindParam(':vaccin_id', $vaccin_id, PDO::PARAM_INT);
            $stmt->execute();
            $response['lots'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
             // Si aucun lot valide n'est trouvé, ajoute une note d'erreur.
             if (empty($response['lots'])) { $response['error'] = 'Aucun lot valide trouvé.'; }
        } catch (PDOException $e) {
            // Gestion des erreurs DB.
            error_log("AJAX Fetch Lots Error: " . $e->getMessage());
            $response['error'] = 'Erreur base de données (lots).';
        }
        // Encode et envoie la réponse JSON.
        echo json_encode($response); exit;
    }

    /**
     * Affiche la page principale de gestion des vaccinations.
     *
     * Cette page combine un formulaire (via une modale) pour ajouter/modifier
     * un enregistrement et une liste des enregistrements existants (filtrable).
     * Gère le pré-remplissage du matricule patient si fourni via GET (recherche dashboard).
     *
     * @return void
     */
    public function form(): void {

        // Gère le cas où un matricule patient est passé via GET (depuis le dashboard).
        // Stocke ce matricule en session pour pré-remplir le formulaire d'ajout
        // et redirige vers la même page sans le paramètre GET pour éviter les re-soumissions.
        if (isset($_GET['matricule_patient'])) {
             $matricule_from_get = trim($_GET['matricule_patient']);
             if (!empty($matricule_from_get)) {
                 // Stocke le matricule nettoyé en session.
                 $_SESSION['selected_matricule_patient'] = htmlspecialchars($matricule_from_get, ENT_QUOTES, 'UTF-8');
                 // Prépare la redirection vers la même action, mais sans 'matricule_patient' dans l'URL.
                 $query_params = $_GET;
                 unset($query_params['matricule_patient']);
                 unset($query_params['controller']);
                 unset($query_params['action']);
                 $redirect_url = 'index.php?controller=VaccinationRecord&action=form';
                 // Reconstruit les autres paramètres GET (ex: filtre) s'il y en a.
                 if (!empty($query_params)) { $redirect_url .= '&' . http_build_query($query_params); }
                 header('Location: ' . $redirect_url); // Effectue la redirection.
                 exit;
             } else {
                 // Si le matricule GET est vide, efface la session.
                 unset($_SESSION['selected_matricule_patient']);
             }
         }
        // Récupère le matricule depuis la session (s'il a été défini ci-dessus).
        $matricule_to_prefill_add = $_SESSION['selected_matricule_patient'] ?? null;

        // Initialisation des variables pour la vue.
        $active_campaigns_for_modal = []; // Campagnes actives pour la modale add/edit.
        $all_campaigns_for_filter = [];   // Toutes les campagnes pour le filtre de la liste.
        $vaccinations_list = [];          // Liste des enregistrements à afficher.
        $page_error_message = null;       // Message d'erreur général pour la page.
        // Récupère et valide le filtre par campagne depuis GET.
        $filter_campagne_id = filter_input(INPUT_GET, 'filter_campagne_id', FILTER_VALIDATE_INT);
        if ($filter_campagne_id !== false && $filter_campagne_id <= 0) { $filter_campagne_id = null; } // Invalide si <= 0.
        // Récupère les infos de l'utilisateur connecté.
        $user_matricule = Auth::getMatricule();
        $user_username = Auth::getUsername();
        $user_role = Auth::getUserRole();
        $isAdmin = Auth::isAdmin(); // Pour afficher conditionnellement le bouton "Générer".

        try {
            // Récupère les campagnes actives pour la modale.
            $stmt_modal_campagnes = $this->pdo->query("SELECT campagne_id, nom_campagne FROM campagnes WHERE is_active = true ORDER BY nom_campagne");
            $active_campaigns_for_modal = $stmt_modal_campagnes->fetchAll(PDO::FETCH_ASSOC);
            // Récupère toutes les campagnes pour le filtre.
            $stmt_filter_campagnes = $this->pdo->query("SELECT campagne_id, nom_campagne FROM campagnes ORDER BY nom_campagne");
            $all_campaigns_for_filter = $stmt_filter_campagnes->fetchAll(PDO::FETCH_ASSOC);

            // Construit la requête pour lister les enregistrements de vaccination.
            // Jointures pour obtenir les noms (campagne, lot, vaccin, patient).
            $sql_list = "SELECT vr.vaccination_record_id, vr.date_vaccination, vr.matricule_patient,
                            vr.campagne_id, c.nom_campagne, vr.lot_id, l.nom_lot, l.vaccin_id,
                            v.nom_vaccin, p.username AS patient_username
                         FROM vaccination_records vr
                         JOIN campagnes c ON vr.campagne_id = c.campagne_id
                         JOIN lots l ON vr.lot_id = l.lot_id
                         JOIN vaccins v ON l.vaccin_id = v.vaccin_id
                         LEFT JOIN users p ON vr.matricule_patient = p.matricule -- LEFT JOIN au cas où le patient user a été supprimé
                         WHERE 1=1"; // Base de la clause WHERE.
            $params = []; // Paramètres pour la requête préparée.

            // Ajoute la condition de filtre par campagne si elle est définie.
            if ($filter_campagne_id !== null) {
                $sql_list .= " AND vr.campagne_id = :campagne_id";
                $params[':campagne_id'] = $filter_campagne_id;
            }

            // Trie les résultats par date de vaccination puis par date de création (pour stabilité).
            $sql_list .= " ORDER BY vr.date_vaccination DESC, vr.date_creation DESC";
            $stmt_list = $this->pdo->prepare($sql_list);
            $stmt_list->execute($params); // Exécute avec les paramètres de filtre.
            $vaccinations_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            // Gestion des erreurs lors de la récupération des données pour la page.
            error_log("Page Load Data Fetch Error (VaccRec): " . $e->getMessage());
            $page_error_message = "Erreur critique lors de la récupération des données.";
        }

        // Prépare les URLs pour les actions du formulaire et les appels AJAX.
        // Conserve le filtre actuel dans les URLs d'action.
        $currentQueryString = http_build_query(array_filter(['filter_campagne_id' => $filter_campagne_id]));
        $form_action_url = "index.php?controller=VaccinationRecord&action=save" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');
        $delete_action_url = "index.php?controller=VaccinationRecord&action=delete" . (!empty($currentQueryString) ? '&' . $currentQueryString : '');
        $filter_action_url = "index.php?controller=VaccinationRecord&action=form"; // URL pour soumettre le filtre.
        $ajax_base_url = "index.php?controller=VaccinationRecord"; // Base pour les appels AJAX (fetchVaccins/Lots).

        // Charge la vue principale qui contient la liste et la modale.
        require_once '../app/View/admin/vaccination_record/form.php';
    }

    /**
     * Sauvegarde un enregistrement de vaccination (ajout ou modification).
     *
     * Traite les données POST du formulaire modal. Valide les entrées.
     * Insère ou met à jour l'enregistrement dans la base de données.
     * Gère les erreurs potentielles (validation, clés étrangères, doublons).
     *
     * @return void
     */
    public function save(): void {
        // Vérifie la méthode HTTP.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             header("Location: index.php?controller=VaccinationRecord&action=form"); exit();
        }

        // Récupération et validation/nettoyage des données POST.
        $record_id = filter_input(INPUT_POST, 'vaccination_record_id', FILTER_VALIDATE_INT); // ID pour modif.
        $matricule_patient = filter_input(INPUT_POST, 'matricule_patient', FILTER_SANITIZE_SPECIAL_CHARS);
        $campagne_id = filter_input(INPUT_POST, 'campagne_id', FILTER_VALIDATE_INT);
        $lot_id = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
        $date_vaccination = filter_input(INPUT_POST, 'date_vaccination', FILTER_SANITIZE_SPECIAL_CHARS); // Date (format AAAA-MM-JJ).
        $user_matricule = Auth::getMatricule(); // Matricule de l'opérateur.

        // Récupère le filtre actuel pour l'URL de redirection.
        $filter_campagne_id = filter_input(INPUT_GET, 'filter_campagne_id', FILTER_VALIDATE_INT);
        $redirect_url = 'index.php?controller=VaccinationRecord&action=form';
        if ($filter_campagne_id) { $redirect_url .= '&filter_campagne_id=' . $filter_campagne_id; }

        // Validation simple des champs requis.
        if (empty($matricule_patient) || !$campagne_id || !$lot_id || empty($date_vaccination)) {
            Flasher::setFlash('Erreur: Tous les champs requis (Matricule, Campagne, Lot, Date) ne sont pas remplis.', 'danger');
            header('Location: ' . $redirect_url); exit();
        }

        // Validation du format de la date.
        $d = DateTime::createFromFormat('Y-m-d', $date_vaccination);
        if (!$d || $d->format('Y-m-d') !== $date_vaccination) {
             Flasher::setFlash('Format de date de vaccination invalide (AAAA-MM-JJ attendu).', 'danger');
             header('Location: ' . $redirect_url); exit();
        }

        // Bloc try...catch...finally pour la sauvegarde.
        try {
            // Si record_id existe et est > 0, c'est une modification.
            if ($record_id > 0) {
                // Prépare la requête UPDATE.
                // Note: Ne permet pas de modifier matricule_patient ici (logique applicative).
                $sql = "UPDATE vaccination_records SET date_vaccination = :date_vacc,
                            campagne_id = :camp_id, lot_id = :lot_id
                            WHERE vaccination_record_id = :rec_id";
                $stmt = $this->pdo->prepare($sql);
                // Lie les paramètres.
                $stmt->bindParam(':rec_id', $record_id, PDO::PARAM_INT);
                $stmt->bindParam(':date_vacc', $date_vaccination);
                $stmt->bindParam(':camp_id', $campagne_id, PDO::PARAM_INT);
                $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
                $stmt->execute();
                $message = 'Vaccination modifiée avec succès.';
            } else { // Sinon, c'est un ajout.
                 // Prépare la requête INSERT.
                 $sql = "INSERT INTO vaccination_records (date_vaccination, matricule_patient, campagne_id, lot_id, matricule_creation, date_creation)
                         VALUES (:date_vacc, :mat_p, :camp_id, :lot_id, :creator, CURRENT_TIMESTAMP)"; // Date de création gérée par DB.
                 $stmt = $this->pdo->prepare($sql);
                 // Lie les paramètres.
                 $stmt->bindParam(':creator', $user_matricule, PDO::PARAM_STR); // Qui a créé l'enregistrement.
                 $stmt->bindParam(':date_vacc', $date_vaccination);
                 $stmt->bindParam(':mat_p', $matricule_patient);
                 $stmt->bindParam(':camp_id', $campagne_id, PDO::PARAM_INT);
                 $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
                 $stmt->execute();
                 $message = 'Vaccination ajoutée avec succès.';
                 // Efface le matricule pré-rempli de la session après ajout réussi.
                 unset($_SESSION['selected_matricule_patient']);
            }

            // Définit le message de succès.
            Flasher::setFlash($message, 'success');

        } catch (PDOException $e) {
            // Gestion des erreurs PDO.
            error_log("Save Vaccination Error: " . $e->getMessage());
            $errorMsg = 'Erreur base de données lors de l\'enregistrement.';
            // Tente de donner des messages plus spécifiques basés sur les contraintes de clé étrangère ou unique.
             if (strpos($e->getMessage(), 'vaccination_records_lot_id_fkey') !== false) { $errorMsg = 'Erreur: Le lot sélectionné est invalide.'; }
             elseif (strpos($e->getMessage(), 'vaccination_records_campagne_id_fkey') !== false) { $errorMsg = 'Erreur: La campagne sélectionnée est invalide.'; }
             elseif ($e->getCode() == '23505') { $errorMsg = 'Erreur: Un enregistrement similaire existe déjà (contrainte unique).'; } // Code 23505 = unique_violation
            Flasher::setFlash($errorMsg, 'danger');
        } finally {
            // Redirige TOUJOURS vers la page du formulaire/liste à la fin (que ce soit succès ou erreur).
            header('Location: ' . $redirect_url); exit();
        }
    }

    /**
     * Supprime un enregistrement de vaccination.
     *
     * Gère la suppression d'un enregistrement spécifique via POST (depuis un bouton dans la liste).
     *
     * @return void
     */
    public function delete(): void {
        // Vérifie la méthode HTTP (devrait être POST pour une action de suppression).
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             header("Location: index.php?controller=VaccinationRecord&action=form"); exit();
        }

        // Récupère l'ID de l'enregistrement à supprimer et le filtre pour la redirection.
        $record_id = filter_input(INPUT_POST, 'vaccination_record_id', FILTER_VALIDATE_INT);
        $filter_campagne_id = filter_input(INPUT_GET, 'filter_campagne_id', FILTER_VALIDATE_INT);
        $redirect_url = 'index.php?controller=VaccinationRecord&action=form';
        if ($filter_campagne_id) { $redirect_url .= '&filter_campagne_id=' . $filter_campagne_id; }

        // Vérifie que l'ID est valide.
        if ($record_id > 0) {
            try {
                // Prépare et exécute la requête DELETE.
                $stmt = $this->pdo->prepare("DELETE FROM vaccination_records WHERE vaccination_record_id = :rec_id");
                $stmt->bindParam(':rec_id', $record_id, PDO::PARAM_INT);
                $deleted = $stmt->execute();
                // Définit le message flash en fonction du succès de l'exécution.
                if ($deleted) { Flasher::setFlash('Vaccination supprimée avec succès.', 'success'); }
                else { Flasher::setFlash('La suppression a échoué ou l\'enregistrement n\'existait pas.', 'warning'); }
            } catch (PDOException $e) {
                // Gestion des erreurs DB.
                error_log("Delete Vaccination Error: " . $e->getMessage());
                Flasher::setFlash('Erreur base de données lors de la suppression.', 'danger');
            }
        } else {
            // ID invalide.
            Flasher::setFlash('ID d\'enregistrement invalide pour la suppression.', 'warning');
        }
        // Redirige vers la page du formulaire/liste.
        header('Location: ' . $redirect_url); exit();
    }

    /**
     * Génère un nombre spécifié d'enregistrements de vaccination de test.
     * Réservé aux administrateurs.
     *
     * Si un matricule patient est sélectionné en session, génère les enregistrements
     * uniquement pour ce patient. Sinon, choisit des patients au hasard parmi les utilisateurs.
     * Utilise des lots valides (campagne active, non expiré).
     *
     * @return void
     */
    public function generate(): void {
         // Vérifie les droits d'administrateur.
         Auth::checkAdminAccess();
         // Récupère et valide le nombre à générer (limites min/max).
         $nombre = isset($_GET['nombre']) ? (int)$_GET['nombre'] : 2;
         $nombre = max(2, min(15, $nombre)); // Force entre 2 et 15.

         // Récupère le matricule de l'admin et prépare l'URL de redirection.
         $matricule_creation = Auth::getMatricule();
         $count_success = 0;
         $redirect_url = 'index.php?controller=VaccinationRecord&action=form';

         // Vérifie si un patient spécifique a été sélectionné via le dashboard.
         $specific_patient_matricule = $_SESSION['selected_matricule_patient'] ?? null;

         try {
             // Récupère les lots valides : associés à une campagne active ET non expirés.
             $sql_lots = "SELECT l.lot_id, l.campagne_id
                          FROM lots l
                          JOIN campagnes c ON l.campagne_id = c.campagne_id
                          WHERE c.is_active = TRUE AND l.date_expiration >= CURRENT_DATE";
             $stmt_lots = $this->pdo->query($sql_lots);
             $valid_lots = $stmt_lots->fetchAll(PDO::FETCH_ASSOC);

             // Détermine la liste des matricules patients à utiliser.
             $patient_matricules_to_use = [];
             if ($specific_patient_matricule !== null) {
                 // Si un patient spécifique est défini, vérifie qu'il existe encore.
                 $stmt_check = $this->pdo->prepare("SELECT matricule FROM users WHERE matricule = ?");
                 $stmt_check->execute([$specific_patient_matricule]);
                 $found_matricule = $stmt_check->fetchColumn();
                 if ($found_matricule) {
                     // Utilise uniquement ce matricule.
                     $patient_matricules_to_use[] = $found_matricule;
                 } else {
                     // Si le patient n'existe plus, annule la génération.
                     Flasher::setFlash("Le patient sélectionné en session ({$specific_patient_matricule}) n'existe plus. Génération annulée.", 'warning');
                     // Supprime le matricule invalide de la session.
                     unset($_SESSION['selected_matricule_patient']);
                     header("Location: " . $redirect_url); exit();
                 }
             } else {
                 // Si aucun patient spécifique, récupère tous les matricules utilisateurs.
                 $sql_patients = "SELECT matricule FROM users";
                 $stmt_patients = $this->pdo->query($sql_patients);
                 $patient_matricules_to_use = $stmt_patients->fetchAll(PDO::FETCH_COLUMN);
             }

             // Vérifie s'il y a des lots valides ET des patients à utiliser.
             if (empty($valid_lots) || empty($patient_matricules_to_use)) {
                  $error_msg = "Impossible de générer: ";
                  if (empty($valid_lots)) $error_msg .= "Manque de lots valides (actifs et non expirés). ";
                  if (empty($patient_matricules_to_use)) $error_msg .= "Manque d'utilisateurs (patients).";
                  Flasher::setFlash(trim($error_msg), 'warning');
                  header("Location: " . $redirect_url); exit();
             }

             // Initialisation de Faker et de la requête INSERT.
             $faker = Factory::create('fr_FR');
             $sqlInsert = "INSERT INTO vaccination_records (date_vaccination, matricule_patient, campagne_id, lot_id, matricule_creation, date_creation)
                           VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
             $stmtInsert = $this->pdo->prepare($sqlInsert);

             // Début de la transaction pour insérer tous les enregistrements d'un coup.
             $this->pdo->beginTransaction();
             // Boucle de génération.
             for ($i = 0; $i < $nombre; $i++) {
                 try {
                     // Sélectionne un lot valide aléatoire.
                     $random_lot = $faker->randomElement($valid_lots);
                     // Sélectionne un patient (le patient spécifique si défini, sinon un patient aléatoire).
                     $patient_to_insert = ($specific_patient_matricule !== null) ? $specific_patient_matricule : $faker->randomElement($patient_matricules_to_use);
                     // Génère une date de vaccination aléatoire dans les 2 dernières années.
                     $random_date = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d');

                     // Exécute l'insertion.
                     $stmtInsert->execute([
                         $random_date,
                         $patient_to_insert,
                         $random_lot['campagne_id'], // Récupère l'ID campagne du lot choisi.
                         $random_lot['lot_id'],
                         $matricule_creation // Matricule de l'admin.
                     ]);
                     $count_success++;
                 } catch (PDOException $e) {
                     // Loggue les erreurs d'insertion individuelles mais continue la boucle.
                     error_log("[FAKER] Erreur insertion enregistrement vaccination: " . $e->getMessage());
                 }
             }
             // Valide la transaction.
             $this->pdo->commit();

             // Définit le message flash final.
             $message_type = $count_success > 0 ? 'success' : 'danger';
             if ($count_success > 0 && $count_success < $nombre) {
                 $message_type = 'warning'; // Partiellement réussi.
             }
             Flasher::setFlash("{$count_success}/{$nombre} enregistrements de vaccination générés.", $message_type);

         } catch (PDOException $e) { // Erreur DB globale.
              if ($this->pdo->inTransaction()) { // Annule la transaction si elle était en cours.
                  $this->pdo->rollBack();
              }
              error_log("Erreur DB pendant génération enregistrements: " . $e->getMessage());
              Flasher::setFlash("Erreur base de données lors de la génération.", 'danger');
         } catch (\Exception $e) { // Erreur générale (ex: Faker).
              if ($this->pdo->inTransaction()) {
                  $this->pdo->rollBack();
              }
              error_log("Erreur générale pendant génération enregistrements: " . $e->getMessage());
              Flasher::setFlash("Erreur inattendue lors de la génération.", 'danger');
         }

         // Redirige vers la page du formulaire/liste.
         header("Location: " . $redirect_url); exit();
    }

}