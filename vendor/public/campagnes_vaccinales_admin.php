<?php
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle 'admin' (protection d'accès)
if (!isset($_SESSION['matricule']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fichier de configuration pour la connexion à la base de données
require_once '../app/config/config.php';

$campagnes = []; // Initialisation du tableau des campagnes vaccinales

try {
    // Connexion à la base de données PostgreSQL
    $pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Requête pour récupérer toutes les campagnes vaccinales
    $stmt = $pdo->query("SELECT campagne_id, nom_campagne, date_debut, date_fin, is_active FROM campagnes");
    $campagnes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Gérer l'erreur de connexion à la base de données
    echo "Erreur de base de données : " . $e->getMessage();
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Campagnes Vaccinales - Projet Vaccination</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .campaign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .campaign-table th, .campaign-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .campaign-table th {
            background-color: #f4f4f4;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        .action-button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            font-size: 0.9em;
        }
        .btn-activate { background-color: #28a745; } /* Vert */
        .btn-deactivate { background-color: #dc3545; } /* Rouge */
        .btn-modify { background-color: #007bff; } /* Bleu */
        .btn-delete { background-color: #ffc107; color: black; } /* Jaune */
    </style>
</head>
<body>
    <div class="campaigns-container">
        <header class="campaigns-header">
            <h1>Campagnes Vaccinales</h1>
            <p>Gestion et administration des campagnes de vaccination.</p>
        </header>

        <section class="campaigns-content">
            <div class="add-campaign-button-container" style="text-align: right; margin-bottom: 15px;">
                <a href="#" class="btn-primary">Ajouter une Campagne</a>
            </div>

            <?php if (!empty($campagnes)): ?>
                <table class="campaign-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Date de Début</th>
                            <th>Date de Fin</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campagnes as $campagne): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($campagne['nom_campagne']); ?></td>
                                <td><?php echo htmlspecialchars($campagne['date_debut']); ?></td>
                                <td><?php echo htmlspecialchars($campagne['date_fin']); ?></td>
                                <td><?php echo $campagne['is_active'] ? 'Oui' : 'Non'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($campagne['is_active']): ?>
                                            <a href="#" class="action-button btn-deactivate">Désactiver</a>
                                        <?php else: ?>
                                            <a href="#" class="action-button btn-activate">Activer</a>
                                        <?php endif; ?>
                                        <a href="#" class="action-button btn-modify">Modifier</a>
                                        <a href="#" class="action-button btn-delete">Supprimer</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="font-style: italic; color: #777;">Aucune campagne vaccinale enregistrée.</p>
            <?php endif; ?>
        </section>

        <footer class="campaigns-footer">
            <a href="admin.php" class="admin-back-button">Retour à l'administration</a>
        </footer>
    </div>
</body>
</html>