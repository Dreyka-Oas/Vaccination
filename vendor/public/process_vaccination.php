<?php
session_start(); // Start session to access user data and store messages

// Configuration - Adjust path if necessary
require_once '../app/config/config.php';

// --- Security: Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect if accessed directly or via GET
    header('Location: form_vaccination.php');
    exit();
}

// --- Security: Check if User is Logged In ---
$user_matricule = $_SESSION['matricule'] ?? null;
if (!$user_matricule) {
    // Store error message and redirect to login
    $_SESSION['form_message'] = "Vous devez être connecté pour enregistrer une vaccination.";
    $_SESSION['form_message_type'] = "warning"; // Or 'danger'
    header('Location: login.php'); // Redirect to login page
    exit();
}

// --- Optional Security: Role Check (Uncomment and adapt if needed) ---
/*
$allowed_roles = ['admin', 'vaccinateur']; // Example roles allowed to record
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    $_SESSION['form_message'] = "Vous n'avez pas les permissions nécessaires pour enregistrer une vaccination.";
    $_SESSION['form_message_type'] = "danger";
    header('Location: dashboard.php'); // Redirect to dashboard or another appropriate page
    exit();
}
*/

// --- Retrieve Submitted Data ---
// Use null coalescing operator (??) for safety, although 'required' on form helps
$matricule_patient = trim($_POST['matricule_patient'] ?? '');
$campagne_id = $_POST['campagne_id'] ?? null;
$lot_id = $_POST['lot_id'] ?? null;
$date_vaccination = $_POST['date_vaccination'] ?? '';

// --- Validation ---
$errors = [];

if (empty($matricule_patient)) {
    $errors[] = "Le matricule du patient est obligatoire.";
} // Add more specific validation for matricule format if needed

if (empty($campagne_id) || !filter_var($campagne_id, FILTER_VALIDATE_INT) || $campagne_id <= 0) {
    $errors[] = "La campagne de vaccination sélectionnée est invalide.";
}

if (empty($lot_id) || !filter_var($lot_id, FILTER_VALIDATE_INT) || $lot_id <= 0) {
    $errors[] = "Le lot de vaccin sélectionné est invalide.";
}

if (empty($date_vaccination)) {
    $errors[] = "La date de vaccination est obligatoire.";
} else {
    // Validate date format (YYYY-MM-DD) and ensure it's a real date
    $d = DateTime::createFromFormat('Y-m-d', $date_vaccination);
    if (!$d || $d->format('Y-m-d') !== $date_vaccination) {
        $errors[] = "Le format de la date de vaccination est invalide (AAAA-MM-JJ requis).";
    } elseif (new DateTime($date_vaccination) > new DateTime()) {
         // Check if the date is in the future (allow today)
        $errors[] = "La date de vaccination ne peut pas être dans le futur.";
    }
}

// --- Database Interaction (if validation passes) ---
$pdo = null;
if (empty($errors)) {
    try {
        // Establish PDO Connection
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // **Advanced Validation (Optional but Recommended):**
        // Check if the lot actually belongs to the selected campaign
        $stmt_check = $pdo->prepare("SELECT campagne_id FROM lots WHERE lot_id = :lot_id");
        $stmt_check->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
        $stmt_check->execute();
        $lot_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$lot_data || $lot_data['campagne_id'] != $campagne_id) {
             $errors[] = "Incohérence détectée : le lot sélectionné n'appartient pas à la campagne sélectionnée.";
        } else {
            // Proceed with Insertion if the check passes
            $sql = "INSERT INTO vaccination_records
                        (lot_id, campagne_id, date_vaccination, matricule_patient, matricule_creation)
                    VALUES
                        (:lot_id, :campagne_id, :date_vaccination, :matricule_patient, :matricule_creation)";

            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $stmt->bindParam(':lot_id', $lot_id, PDO::PARAM_INT);
            $stmt->bindParam(':campagne_id', $campagne_id, PDO::PARAM_INT);
            $stmt->bindParam(':date_vaccination', $date_vaccination, PDO::PARAM_STR);
            $stmt->bindParam(':matricule_patient', $matricule_patient, PDO::PARAM_STR);
            $stmt->bindParam(':matricule_creation', $user_matricule, PDO::PARAM_STR); // User from session

            // Execute the statement
            if ($stmt->execute()) {
                $_SESSION['form_message'] = "Vaccination enregistrée avec succès pour le patient matricule : " . htmlspecialchars($matricule_patient);
                $_SESSION['form_message_type'] = "success";
            } else {
                // This part might not be reached if PDOException is thrown, but good as a fallback
                $errors[] = "Une erreur inconnue est survenue lors de l'enregistrement.";
            }
        } // End of advanced validation else

    } catch (PDOException $e) {
        error_log("Erreur PDO dans process_vaccination.php: " . $e->getMessage()); // Log detailed error
        // Check for specific constraint violations if needed (e.g., duplicate)
        if ($e->getCode() == '23505') { // PostgreSQL unique violation code
             $errors[] = "Erreur : Un enregistrement similaire existe déjà (vérifiez les contraintes uniques).";
        } else {
            $errors[] = "Erreur de base de données. Impossible d'enregistrer la vaccination. Veuillez contacter l'administrateur.";
        }
    } catch (Exception $e) {
        error_log("Erreur Générale dans process_vaccination.php: " . $e->getMessage());
        $errors[] = "Une erreur technique est survenue.";
    } finally {
        // Close connection
        $pdo = null;
    }
}

// --- Set Feedback Message if Errors Occurred ---
if (!empty($errors)) {
    $_SESSION['form_message'] = "Échec de l'enregistrement :<br>" . implode("<br>", $errors);
    $_SESSION['form_message_type'] = "danger";
    // Optional: Preserve submitted data to re-fill the form (more complex)
    // $_SESSION['form_data'] = $_POST;
}

// --- Redirect Back to the Form ---
// Redirect back to the form page to display the message
// Append the original matricule_patient if available, so the form can potentially pre-fill it again even on error
$redirect_url = 'form_vaccination.php';
if (!empty($matricule_patient_prefill)) { // If matricule came from GET originally
    $redirect_url .= '?matricule_patient=' . urlencode($matricule_patient_prefill);
} elseif (!empty($matricule_patient) && !empty($errors)) { // If entered manually and there was an error
     $redirect_url .= '?matricule_patient=' . urlencode($matricule_patient);
}


header("Location: " . $redirect_url);
exit(); // Ensure script stops after redirection
?>