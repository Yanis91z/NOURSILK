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

// Mise à jour du statut d'une réservation si demandé
if (isset($_POST['update_status'])) {
    $reservation_id = $_POST['reservation_id'] ?? 0;
    $new_status = $_POST['status'] ?? '';
    $date_redirect = $_POST['date_filter'] ?? $date_filter;
    $token = $_POST['csrf_token'] ?? '';

    // Vérifier le token CSRF
    if (!empty($token) && hash_equals($_SESSION['csrf_token'], $token)) {
        if ($reservation_id && $new_status) {
            try {
                $stmt = $conn->prepare("UPDATE reservations SET statut = :statut WHERE id = :id");
                $stmt->execute([
                    'statut' => $new_status,
                    'id' => $reservation_id
                ]);
                // Rediriger vers GET pour éviter la résoumission du formulaire
                header("Location: index?date=" . $date_redirect . "&status_updated=1");
                exit;
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la mise à jour: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Erreur de sécurité: token invalide.";
    }
}

// Message de succès basé sur le paramètre GET
if (isset($_GET['status_updated']) && $_GET['status_updated'] == 1) {
    $success_message = "Statut mis à jour avec succès.";
}

// Récupérer les réservations
try {
    $stmt = $conn->prepare("
        SELECT r.id, c.nom, c.email, c.telephone, s.nom as service_nom, r.date_reservation, 
               r.heure_reservation, r.statut, r.date_creation
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN services s ON r.service_id = s.id
        WHERE r.date_reservation = :date_filter
        ORDER BY 
            CASE r.statut 
                WHEN 'confirmé' THEN 1
                WHEN 'annulé' THEN 2
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
                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                    <input type="hidden" name="date_filter" value="<?php echo $date_filter; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <select name="status" class="status-select">
                                        <option value="confirmé" <?php echo $reservation['statut'] === 'confirmé' ? 'selected' : ''; ?>>Confirmé</option>
                                        <option value="annulé" <?php echo $reservation['statut'] === 'annulé' ? 'selected' : ''; ?>>Annulé</option>
                                    </select>
                                    <button type="submit" name="update_status">Modifier</button>
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