<?php
session_start();

// Empêcher la mise en cache de cette page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Générer un token CSRF s'il n'existe pas déjà
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Configuration de la base de données
$servername = "localhost";
$username = "root"; // Utilisateur root pour MAMP
$password = "root"; // Mot de passe par défaut pour root sur MAMP
$dbname = "noursilk_db"; // À remplacer par le nom de votre base de données

// Connexion à la base de données
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

// Paramètre pour filtrer par date
$date_filter = $_GET['date'] ?? date('Y-m-d'); // Par défaut aujourd'hui

// Mise à jour du statut
if (isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $new_status = $_POST['new_status'];
    $old_status = $_POST['current_status'];
    $date_redirect = $_GET['date'] ?? date('Y-m-d');

    // Log pour débogage avec chemin absolu
    $debug_log = "=== DEBUG STATUS UPDATE " . date('Y-m-d H:i:s') . " ===\n";
    $debug_log .= "ID: $id\n";
    $debug_log .= "Ancien statut (from POST): $old_status\n";
    $debug_log .= "Nouveau statut: $new_status\n";
    $debug_log .= "POST data: " . print_r($_POST, true) . "\n";

    try {
        // Vérifier d'abord le statut actuel dans la base de données
        $check_stmt = $conn->prepare("SELECT statut FROM reservations WHERE id = ?");
        $check_stmt->execute([$id]);
        $current_db_status = $check_stmt->fetchColumn();

        $debug_log .= "Statut actuel dans la DB: $current_db_status\n";

        // Récupérer les informations de la réservation
        $stmt = $conn->prepare("
            SELECT r.date_reservation, r.heure_reservation, s.nom as service_nom, c.nom as client_nom,
                   c.email, c.telephone, s.duree_max, r.statut as current_status
            FROM reservations r
            JOIN services s ON r.service_id = s.id
            JOIN clients c ON r.client_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch();

        $debug_log .= "Données réservation complètes:\n" . print_r($reservation, true) . "\n";

        // Vérifier si le changement de statut est autorisé
        $status_change_allowed = true;
        $error_message = '';

        if ($current_db_status === 'annulé') {
            $status_change_allowed = false;
            $error_message = "Impossible de modifier une réservation annulée.";
        }

        if ($status_change_allowed) {
            // Mettre à jour le statut
            $update_stmt = $conn->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
            $update_result = $update_stmt->execute([$new_status, $id]);
            $debug_log .= "Résultat de la mise à jour: " . ($update_result ? "succès" : "échec") . "\n";

            // Si on passe de confirmé à en attente OU à annulé
            if ($current_db_status === 'confirmé' && ($new_status === 'en attente' || $new_status === 'annulé')) {
                require_once '../webhook_functions.php';
                $result = sendWebhookToZapierForCancellation(
                    $reservation['client_nom'],
                    $reservation['service_nom'],
                    $reservation['date_reservation'],
                    $reservation['heure_reservation']
                );
                $debug_log .= "Suppression de l'événement Google Calendar: " . ($result ? "succès" : "échec") . "\n";
            }

            // Si on passe de en attente à confirmé
            if ($current_db_status === 'en attente' && $new_status === 'confirmé') {
                require_once '../webhook_functions.php';

                // Récupérer la durée du service
                $service_stmt = $conn->prepare("SELECT duree_max FROM services WHERE nom = ?");
                $service_stmt->execute([$reservation['service_nom']]);
                $service_duree = $service_stmt->fetchColumn();

                $result = sendWebhookToZapier(
                    $reservation['client_nom'],
                    $reservation['email'],
                    $reservation['telephone'],
                    $reservation['service_nom'],
                    $reservation['date_reservation'],
                    $reservation['heure_reservation'],
                    $service_duree
                );
                $debug_log .= "Création de l'événement Google Calendar: " . ($result ? "succès" : "échec") . "\n";
            }

            // Si on passe à annulé, on libère le créneau
            if ($new_status === 'annulé') {
                $stmt = $conn->prepare("UPDATE reservations SET creneau_libere = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $debug_log .= "Créneau libéré\n";
            }

            $debug_log .= "Statut mis à jour avec succès\n";
            header("Location: index.php?date=" . $date_redirect . "&status_updated=1");
        } else {
            $debug_log .= "Changement de statut non autorisé: $error_message\n";
            header("Location: index.php?date=" . $date_redirect . "&error=" . urlencode($error_message));
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la mise à jour: " . $e->getMessage();
        $debug_log .= "ERREUR: " . $e->getMessage() . "\n";
        header("Location: index.php?date=" . $date_redirect . "&error=" . urlencode($error_message));
    }

    $debug_log .= "====================\n\n";
    file_put_contents(__DIR__ . '/debug_log.txt', $debug_log, FILE_APPEND);
    exit;
}

// Message de succès basé sur le paramètre GET
if (isset($_GET['status_updated']) && $_GET['status_updated'] == 1) {
    $success_message = "Statut mis à jour avec succès.";
}

// Récupérer les réservations
try {
    $stmt = $conn->prepare("
        SELECT r.id, c.nom, c.email, c.telephone, s.nom as service_nom, r.date_reservation, 
               r.heure_reservation, r.statut, r.date_creation, r.creneau_libere
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN services s ON r.service_id = s.id
        WHERE r.date_reservation = :date_filter
        ORDER BY 
            CASE r.statut 
                WHEN 'confirmé' THEN 1
                WHEN 'en_attente' THEN 2
                WHEN 'annulé' THEN 3
            END,
            r.heure_reservation ASC
    ");
    $stmt->execute(['date_filter' => $date_filter]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des réservations: " . $e->getMessage());
}

// Récupérer tous les clients
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.nom, c.email, c.telephone, 
               COUNT(r.id) as nombre_reservations,
               MAX(r.date_reservation) as derniere_reservation
        FROM clients c
        LEFT JOIN reservations r ON c.id = r.client_id
        GROUP BY c.id
        ORDER BY c.nom ASC
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des clients: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration NOURSILK - Réservations</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-cream: #fcf8f3;
            --color-beige: #e8d9c5;
            --color-beige-light: #f5efe6;
            --color-chocolate: #6b4c35;
            --color-chocolate-light: #8a6d54;
            --color-chocolate-dark: #513823;
            --shadow-soft: 0 4px 12px rgba(107, 76, 53, 0.08);
            --border-radius: 12px;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--color-cream);
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--color-beige);
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--color-chocolate);
            margin: 0;
        }

        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8em;
            color: var(--color-chocolate);
            font-weight: 600;
        }

        nav {
            display: flex;
            gap: 20px;
        }

        nav a {
            color: var(--color-chocolate);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
        }

        nav a:hover {
            background-color: var(--color-beige-light);
        }

        .logout {
            color: var(--color-chocolate-dark);
        }

        .filters {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow-soft);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filters label {
            margin-right: 5px;
        }

        .date-input {
            padding: 10px;
            border: 1px solid var(--color-beige);
            border-radius: 4px;
        }

        button {
            background-color: var(--color-chocolate);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: var(--color-chocolate-light);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        th,
        td {
            padding: 15px;
            text-align: left;
        }

        th {
            background-color: var(--color-chocolate);
            color: white;
        }

        tr:nth-child(even) {
            background-color: var(--color-beige-light);
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            text-align: center;
            font-weight: 500;
        }

        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .no-reservations {
            background-color: white;
            padding: 30px;
            text-align: center;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
        }

        .status-form {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .status-select {
            padding: 5px;
            border: 1px solid var(--color-beige);
            border-radius: 4px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .export-btn {
            background-color: var(--color-chocolate);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .export-btn:hover {
            background-color: var(--color-chocolate-light);
        }

        .calendar-icon {
            font-size: 1.2em;
        }

        .export-single {
            display: inline-block;
            margin-left: 5px;
            padding: 5px 10px;
            background-color: var(--color-chocolate-light);
            color: white;
            border-radius: 4px;
            text-decoration: none;
        }

        .export-single:hover {
            background-color: var(--color-chocolate);
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div class="logo"><a href="." style="text-decoration: none; color: inherit;">NOURSILK</a></div>
            <h1>Gestion des réservations</h1>
            <nav>
                <a href=".">Réservations</a>
                <a href="#clients">Clients</a>
                <a href="logout">Déconnexion</a>
            </nav>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" action="">
                <label for="date">Filtrer par date:</label>
                <input type="date" id="date" name="date" class="date-input" value="<?php echo $date_filter; ?>">
                <button type="submit">Filtrer</button>
            </form>
        </div>

        <h2 style="margin-top: 20px; margin-bottom: 20px; color: var(--color-chocolate);">Liste des Réservations</h2>

        <?php if (count($reservations) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Heure</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Service</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?php echo date('H:i', strtotime($reservation['heure_reservation'])); ?></td>
                            <td><?php echo htmlspecialchars($reservation['nom']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($reservation['email']); ?><br>
                                <?php echo htmlspecialchars($reservation['telephone']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($reservation['service_nom']); ?></td>
                            <td>
                                <?php
                                $status_class = '';
                                switch ($reservation['statut']) {
                                    case 'confirmé':
                                        $status_class = 'status-confirmed';
                                        break;
                                    case 'en_attente':
                                        $status_class = 'status-pending';
                                        break;
                                    case 'annulé':
                                        $status_class = 'status-cancelled';
                                        break;
                                }
                                ?>
                                <span class="status <?php echo $status_class; ?>">
                                    <?php echo ucfirst(htmlspecialchars($reservation['statut'])); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="" class="status-form">
                                    <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($reservation['statut']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <select name="new_status" class="status-select" <?php echo $reservation['statut'] === 'annulé' ? 'disabled' : ''; ?>>
                                        <option value="confirmé" <?php echo $reservation['statut'] === 'confirmé' ? 'selected' : ''; ?>>Confirmé</option>
                                        <option value="en attente" <?php echo $reservation['statut'] === 'en attente' ? 'selected' : ''; ?>>En attente</option>
                                        <option value="annulé" <?php echo $reservation['statut'] === 'annulé' ? 'selected' : ''; ?>>Annulé</option>
                                    </select>
                                    <button type="submit" name="update_status" <?php echo $reservation['statut'] === 'annulé' ? 'disabled' : ''; ?>>Modifier</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-reservations">
                <p>Aucune réservation pour cette date.</p>
            </div>
        <?php endif; ?>

        <!-- Section Liste des Clients -->
        <h2 id="clients" style="margin-top: 40px; margin-bottom: 20px; color: var(--color-chocolate);">Liste des Clients</h2>

        <?php if (count($clients) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Nombre de Réservations</th>
                        <th>Dernière Réservation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['nom']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['telephone']); ?></td>
                            <td><?php echo $client['nombre_reservations']; ?></td>
                            <td>
                                <?php
                                if ($client['derniere_reservation']) {
                                    echo date('d/m/Y', strtotime($client['derniere_reservation']));
                                } else {
                                    echo 'Aucune';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-reservations">
                <p>Aucun client enregistré.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>