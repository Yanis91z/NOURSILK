<?php
session_start();

// Définir le fuseau horaire sur Europe/Paris
date_default_timezone_set('Europe/Paris');

// Empêcher la mise en cache de cette page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: /Noursilk/admin/login");
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

    // Préparer les paramètres de redirection
    $redirect_params = [];
    if (isset($_GET['section'])) {
        $redirect_params['section'] = $_GET['section'];
    }
    if (isset($_GET['date']) && !empty($_GET['date'])) {
        $redirect_params['date'] = $_GET['date'];
    }
    $redirect_params['status_updated'] = 1;

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
                require_once '../PHP/webhook_functions.php';
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
                require_once '../PHP/webhook_functions.php';

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

            // Construire l'URL de redirection avec les paramètres
            $redirect_url = '/Noursilk/admin/';
            if (isset($_GET['section']) && $_GET['section'] === 'clients') {
                $redirect_url .= 'clients';
            } else {
                $redirect_url .= 'reservations';
            }

            // Ajouter les paramètres de requête
            $query_params = [];
            if (isset($_GET['date']) && !empty($_GET['date'])) {
                $query_params['date'] = $_GET['date'];
            }
            $query_params['status_updated'] = 1;

            if (!empty($query_params)) {
                $redirect_url .= '?' . http_build_query($query_params);
            }

            header("Location: " . $redirect_url);
        } else {
            $debug_log .= "Changement de statut non autorisé: $error_message\n";

            // Construire l'URL de redirection avec les paramètres
            $redirect_url = '/Noursilk/admin/';
            if (isset($_GET['section']) && $_GET['section'] === 'clients') {
                $redirect_url .= 'clients';
            } else {
                $redirect_url .= 'reservations';
            }

            // Ajouter les paramètres de requête
            $query_params = [];
            if (isset($_GET['date']) && !empty($_GET['date'])) {
                $query_params['date'] = $_GET['date'];
            }
            $query_params['error'] = urlencode($error_message);

            if (!empty($query_params)) {
                $redirect_url .= '?' . http_build_query($query_params);
            }

            header("Location: " . $redirect_url);
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la mise à jour: " . $e->getMessage();
        $debug_log .= "ERREUR: " . $e->getMessage() . "\n";

        // Construire l'URL de redirection avec les paramètres
        $redirect_url = '/Noursilk/admin/';
        if (isset($_GET['section']) && $_GET['section'] === 'clients') {
            $redirect_url .= 'clients';
        } else {
            $redirect_url .= 'reservations';
        }

        // Ajouter les paramètres de requête
        $query_params = [];
        if (isset($_GET['date']) && !empty($_GET['date'])) {
            $query_params['date'] = $_GET['date'];
        }
        $query_params['error'] = urlencode($error_message);

        if (!empty($query_params)) {
            $redirect_url .= '?' . http_build_query($query_params);
        }

        header("Location: " . $redirect_url);
    }

    $debug_log .= "====================\n\n";
    file_put_contents(__DIR__ . '/../logs/debug_log.txt', $debug_log, FILE_APPEND);
    exit;
}

// Message de succès basé sur le paramètre GET
if (isset($_GET['status_updated']) && $_GET['status_updated'] == 1) {
    $success_message = "Statut mis à jour avec succès.";
} elseif (isset($_GET['client_updated']) && $_GET['client_updated'] == 1) {
    $success_message = "Les informations du client ont été mises à jour avec succès.";
}

// Récupérer les réservations
try {
    // Vérifier si un filtre de date est appliqué
    $date_filter_active = isset($_GET['date']) && !empty($_GET['date']);

    // Requête SQL de base
    $sql = "
        SELECT r.id, c.nom, c.email, c.telephone, s.nom as service_nom, r.date_reservation, 
               r.heure_reservation, r.statut, r.date_creation, r.creneau_libere
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN services s ON r.service_id = s.id
    ";

    // Ajouter la condition de filtre par date si nécessaire
    if ($date_filter_active) {
        $sql .= " WHERE r.date_reservation = :date_filter";
    } else {
        // Si pas de filtre, récupérer les réservations futures (et celles d'aujourd'hui)
        $sql .= " WHERE r.date_reservation >= CURDATE()";
    }

    // Ajouter l'ordre de tri
    $sql .= " ORDER BY r.date_reservation, 
            CASE r.statut 
                WHEN 'confirmé' THEN 1
                WHEN 'en attente' THEN 2
                WHEN 'annulé' THEN 3
            END,
            r.heure_reservation ASC";

    // Limiter à un nombre raisonnable si pas de filtre de date
    if (!$date_filter_active) {
        $sql .= " LIMIT 100";
    }

    $stmt = $conn->prepare($sql);

    // Exécuter la requête avec le paramètre de date si nécessaire
    if ($date_filter_active) {
        $stmt->execute(['date_filter' => $_GET['date']]);
    } else {
        $stmt->execute();
    }

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
    <title>NOURSILK - Administration</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-styles.css">
    <style>
        /* Styles pour le modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: var(--color-cream);
            margin: 5% auto;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            width: 90%;
            max-width: 600px;
        }

        .close-modal {
            float: right;
            font-size: 1.8em;
            font-weight: bold;
            cursor: pointer;
            color: var(--color-chocolate);
            margin-top: -10px;
        }

        .close-modal:hover {
            color: var(--color-chocolate-dark);
        }

        .modal h3 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--color-chocolate);
            font-size: 1.8em;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--color-beige);
            padding-bottom: 15px;
        }

        .edit-client-btn {
            background-color: var(--color-chocolate-light);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .edit-client-btn:hover {
            background-color: var(--color-chocolate);
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .form-actions button {
            padding: 10px 20px;
        }

        .cancel-btn {
            background-color: #6c757d;
        }

        .cancel-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body class="admin-page">
    <div class="container">
        <!-- Navigation principale -->
        <header>
            <div class="logo"><a href="/Noursilk/admin">NOURSILK</a></div>
            <nav>
                <a href="/Noursilk/admin/reservations" class="nav-link <?php echo (!isset($_GET['section']) || $_GET['section'] === 'reservations') ? 'active' : ''; ?>">Réservations</a>
                <a href="/Noursilk/admin/clients" class="nav-link <?php echo (isset($_GET['section']) && $_GET['section'] === 'clients') ? 'active' : ''; ?>">Clients</a>
                <a href="/Noursilk/admin/logout" class="logout">Déconnexion</a>
            </nav>
        </header>

        <!-- Message de succès -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Message d'erreur -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <?php
        // Déterminer quelle section afficher
        $current_section = isset($_GET['section']) ? $_GET['section'] : 'reservations';

        // Section des réservations
        if ($current_section === 'reservations'):
        ?>

            <!-- Filtres de date pour les réservations -->
            <div class="filters">
                <form method="GET" action="/Noursilk/admin/reservations">
                    <div class="filter-options">
                        <label for="date">Filtrer par date:</label>
                        <input type="date" id="date" name="date" class="date-input" value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>">
                        <button type="submit">Filtrer</button>
                        <?php if (isset($_GET['date']) && !empty($_GET['date'])): ?>
                            <a href="/Noursilk/admin/reservations" class="reset-filter">Voir toutes les réservations</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Liste des réservations -->
            <h2>
                <?php if (isset($_GET['date']) && !empty($_GET['date'])): ?>
                    Réservations du <?php echo date('d/m/Y', strtotime($_GET['date'])); ?>
                <?php else: ?>
                    Prochaines réservations
                <?php endif; ?>
            </h2>

            <?php if (count($reservations) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Service</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_date = null;
                        foreach ($reservations as $reservation):
                            $reservation_date = $reservation['date_reservation'];
                            $is_new_date = $current_date !== $reservation_date;
                            $current_date = $reservation_date;

                            // Déterminer si c'est aujourd'hui, demain, etc.
                            $date_display = date('d/m/Y', strtotime($reservation_date));
                            $today = date('Y-m-d');
                            $tomorrow = date('Y-m-d', strtotime('+1 day'));

                            // Comparer la date avec aujourd'hui et demain
                            if ($reservation_date === $today) {
                                $date_label = "Aujourd'hui";
                                $row_class = $is_new_date ? 'new-date today' : 'today';
                            } else if ($reservation_date === $tomorrow) {
                                $date_label = "Demain";
                                $row_class = $is_new_date ? 'new-date' : '';
                            } else {
                                // Pour les autres dates, afficher le jour de la semaine en français
                                $jour_en = date('l', strtotime($reservation_date));
                                $jours_fr = [
                                    'Monday' => 'Lundi',
                                    'Tuesday' => 'Mardi',
                                    'Wednesday' => 'Mercredi',
                                    'Thursday' => 'Jeudi',
                                    'Friday' => 'Vendredi',
                                    'Saturday' => 'Samedi',
                                    'Sunday' => 'Dimanche'
                                ];
                                $date_label = $jours_fr[$jour_en];
                                $row_class = $is_new_date ? 'new-date' : '';
                            }
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <?php echo $date_display; ?>
                                    <?php if ($reservation_date === $today): ?>
                                        <span class="date-label">(Aujourd'hui)</span>
                                    <?php elseif ($reservation_date === $tomorrow): ?>
                                        <span class="date-label">(Demain)</span>
                                    <?php else: ?>
                                        <span class="date-label">(<?php echo $date_label; ?>)</span>
                                    <?php endif; ?>
                                </td>
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
                                        case 'en attente':
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
                                        <select name="new_status" class="status-select" onchange="this.form.submit()" <?php echo $reservation['statut'] === 'annulé' ? 'disabled' : ''; ?>>
                                            <option value="confirmé" <?php echo $reservation['statut'] === 'confirmé' ? 'selected' : ''; ?>>Confirmé</option>
                                            <option value="en attente" <?php echo $reservation['statut'] === 'en attente' ? 'selected' : ''; ?>>En attente</option>
                                            <option value="annulé" <?php echo $reservation['statut'] === 'annulé' ? 'selected' : ''; ?>>Annulé</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-reservations">
                    <p>
                        <?php if (isset($_GET['date']) && !empty($_GET['date'])): ?>
                            Aucune réservation pour cette date.
                        <?php else: ?>
                            Aucune réservation à venir.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

        <?php
        // Section des clients
        elseif ($current_section === 'clients'):
        ?>

            <!-- Section Liste des Clients -->
            <h2 id="clients">Liste des Clients</h2>

            <!-- Ajout d'une zone de recherche client -->
            <div class="search-container">
                <form method="GET" action="/Noursilk/admin/clients" class="search-form">
                    <input type="text" id="client-search" name="client_search" placeholder="Rechercher un client..." class="search-input" value="<?php echo isset($_GET['client_search']) ? htmlspecialchars($_GET['client_search']) : ''; ?>">
                    <button type="submit" class="search-button">Rechercher</button>
                    <?php if (isset($_GET['client_search']) && !empty($_GET['client_search'])): ?>
                        <a href="/Noursilk/admin/clients" class="reset-search">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php
            // Filtrage des clients par le terme de recherche
            $filtered_clients = $clients;
            if (isset($_GET['client_search']) && !empty($_GET['client_search'])) {
                $search_term = $_GET['client_search'];
                $filtered_clients = array_filter($clients, function ($client) use ($search_term) {
                    return (stripos($client['nom'], $search_term) !== false) ||
                        (stripos($client['email'], $search_term) !== false) ||
                        (stripos($client['telephone'], $search_term) !== false);
                });
            }
            ?>

            <?php if (count($filtered_clients) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Nombre de Réservations</th>
                            <th>Dernière Réservation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_clients as $client): ?>
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
                                <td>
                                    <button class="edit-client-btn" onclick="openEditModal(<?php echo $client['id']; ?>, '<?php echo addslashes(htmlspecialchars($client['nom'])); ?>', '<?php echo addslashes(htmlspecialchars($client['email'])); ?>', '<?php echo addslashes(htmlspecialchars($client['telephone'])); ?>')">Modifier</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-reservations">
                    <p>Aucun client trouvé pour cette recherche.</p>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Modal pour éditer un client -->
    <div id="editClientModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h3>Modifier le client</h3>
            <form id="editClientForm" class="modal-form" method="POST" action="update_client.php">
                <input type="hidden" id="client_id" name="client_id">

                <div class="form-group">
                    <label for="client_name">Nom</label>
                    <input type="text" id="client_name" name="client_name" required>
                </div>

                <div class="form-group">
                    <label for="client_email">Email</label>
                    <input type="email" id="client_email" name="client_email" required>
                </div>

                <div class="form-group">
                    <label for="client_phone">Téléphone</label>
                    <input type="tel" id="client_phone" name="client_phone" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" class="save-btn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script pour la gestion du modal -->
    <script>
        function openEditModal(id, name, email, phone) {
            // Remplir le formulaire avec les données du client
            document.getElementById('client_id').value = id;
            document.getElementById('client_name').value = name;
            document.getElementById('client_email').value = email;
            document.getElementById('client_phone').value = phone;

            // Afficher le modal
            document.getElementById('editClientModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editClientModal').style.display = 'none';
        }

        // Fermer le modal si l'utilisateur clique en dehors
        window.onclick = function(event) {
            var modal = document.getElementById('editClientModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>

    <!-- Script admin principal -->
    <script src="js/admin-scripts.js"></script>
</body>

</html>