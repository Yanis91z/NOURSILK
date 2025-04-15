<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Empêcher la mise en cache
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Log les paramètres reçus
$log_params = "=== REQUÊTE HTTP " . date('Y-m-d H:i:s') . " ===\n";
$log_params .= "URL: " . $_SERVER['REQUEST_URI'] . "\n";
$log_params .= "Méthode: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log_params .= "Paramètres GET: " . print_r($_GET, true) . "\n";
$log_params .= "Paramètres POST: " . print_r($_POST, true) . "\n";
$log_params .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Non défini') . "\n";
$log_params .= "====================\n\n";
file_put_contents(__DIR__ . '/debug_request.txt', $log_params, FILE_APPEND);

// Configuration de la base de données
$servername = "localhost";
$username = "root"; // Utilisateur root pour MAMP
$password = "root"; // Mot de passe par défaut pour root sur MAMP
$dbname = "noursilk_db";

// Inclure les fonctions nécessaires
require_once 'webhook_functions.php';

// Récupérer la date demandée
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Définir les heures d'ouverture (par défaut)
$opening_hour = 9; // 9h00
$closing_hour = 18; // 18h00
$slot_duration = 30; // minutes par créneau
$lunch_start = 12; // 12h00
$lunch_end = 13; // 13h00

// Jours de fermeture (0 = dimanche, 6 = samedi)
$closed_days = [0]; // Fermé le dimanche

// Vérifier si la date est un jour de fermeture
$day_of_week = date('w', strtotime($date));
if (in_array($day_of_week, $closed_days)) {
    // Salon fermé ce jour
    echo json_encode([
        'success' => false,
        'message' => 'Le salon est fermé ce jour',
        'available_slots' => []
    ]);
    exit;
}

// Générer tous les créneaux possibles pour la journée
$possible_slots = [];
for ($hour = $opening_hour; $hour < $closing_hour; $hour++) {
    for ($minute = 0; $minute < 60; $minute += $slot_duration) {
        // Exclure la pause déjeuner
        if ($hour >= $lunch_start && $hour < $lunch_end) {
            continue;
        }

        $time = sprintf('%02d:%02d', $hour, $minute);
        $possible_slots[] = $time;
    }
}

try {
    // Connexion à la base de données
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les réservations confirmées pour cette date
    $stmt = $conn->prepare("
        SELECT TIME_FORMAT(heure_reservation, '%H:%i') as heure_debut, 
               s.duree_max,
               s.id as service_id,
               s.nom as service_nom,
               r.statut,
               r.id as reservation_id 
        FROM reservations r
        JOIN services s ON r.service_id = s.id
        WHERE r.date_reservation = :date
        AND r.statut IN ('confirmé', 'en attente')
        ORDER BY r.heure_reservation ASC
    ");
    $stmt->execute(['date' => $date]);
    $booked_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log SQL pour debug
    $sql_log = "=== SQL RÉSERVATIONS " . date('Y-m-d H:i:s') . " ===\n";
    $sql_log .= "Date: $date\n";
    $sql_log .= "Nombre de réservations trouvées: " . count($booked_slots) . "\n\n";
    $sql_log .= "Détail des réservations:\n";
    foreach ($booked_slots as $booking) {
        $sql_log .= "- ID: " . $booking['reservation_id'] . ", Service: " . $booking['service_nom'] . " (" . $booking['service_id'] . ")\n";
        $sql_log .= "  Heure: " . $booking['heure_debut'] . ", Durée: " . $booking['duree_max'] . " min, Statut: " . $booking['statut'] . "\n";
    }
    $sql_log .= "====================\n\n";
    file_put_contents(__DIR__ . '/debug_sql.txt', $sql_log, FILE_APPEND);

    // Récupérer la durée du service sélectionné (si spécifié dans la requête)
    $selected_service = isset($_GET['service']) ? $_GET['service'] : null;
    $service_duration = $slot_duration; // Durée par défaut = durée du créneau (30 min)

    // Journaliser la valeur du service reçue
    $service_log = "=== DEBUG SERVICE " . date('Y-m-d H:i:s') . " ===\n";
    $service_log .= "Service reçu dans paramètre GET: " . var_export($selected_service, true) . "\n";
    $service_log .= "Type: " . gettype($selected_service) . "\n";

    // Traitement spécial pour les valeurs "indien" et "tanin"
    $service_id = null;
    if ($selected_service === 'indien') {
        $service_id = 1;
        $service_log .= "Service identifié comme 'Indien' (ID: 1)\n";
    } elseif ($selected_service === 'tanin') {
        $service_id = 2;
        $service_log .= "Service identifié comme 'Tanin' (ID: 2)\n";
    }

    if ($service_id) {
        // Récupérer la durée basée sur l'ID du service
        $stmt = $conn->prepare("SELECT id, nom, duree_max FROM services WHERE id = :id");
        $stmt->execute(['id' => $service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        $service_log .= "Requête SQL avec ID: SELECT id, nom, duree_max FROM services WHERE id = $service_id\n";
    } elseif ($selected_service) {
        // Essayer de trouver le service par le nom s'il n'est pas "indien" ou "tanin"
        $stmt = $conn->prepare("SELECT id, nom, duree_max FROM services WHERE id = :id OR LOWER(nom) LIKE :nom");
        $stmt->execute([
            'id' => $selected_service,
            'nom' => '%' . strtolower($selected_service) . '%'
        ]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        $service_log .= "Requête SQL par nom: SELECT id, nom, duree_max FROM services WHERE id = '$selected_service' OR LOWER(nom) LIKE '%" . strtolower($selected_service) . "%'\n";
    } else {
        $service_log .= "Aucun service spécifié\n";
        $service = null;
    }

    $service_log .= "Résultat de la requête: " . ($service ? json_encode($service) : "Aucun résultat") . "\n";

    if ($service) {
        $service_duration = intval($service['duree_max']);
        $service_log .= "Service trouvé: " . $service['nom'] . " (ID: " . $service['id'] . ", Durée: " . $service_duration . " min)\n";

        // Vérification additionnelle de la durée
        if ($service_duration <= 0) {
            $service_log .= "ATTENTION: La durée du service est invalide (<=0). Utilisation de la durée par défaut.\n";
            $service_duration = $slot_duration;
        }
    }

    // Journaliser tous les services disponibles dans la base de données
    $stmt = $conn->prepare("SELECT id, nom, duree_max FROM services");
    $stmt->execute();
    $all_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $service_log .= "\nTous les services disponibles dans la base de données:\n";
    foreach ($all_services as $srv) {
        $service_log .= "- ID: " . $srv['id'] . ", Nom: " . $srv['nom'] . ", Durée: " . $srv['duree_max'] . " min\n";
    }

    $service_log .= "====================\n\n";
    file_put_contents(__DIR__ . '/debug_service.txt', $service_log, FILE_APPEND);

    // Debug des réservations récupérées
    $debug_log = "=== DEBUG RÉSERVATIONS " . date('Y-m-d H:i:s') . " ===\n";
    $debug_log .= "Date demandée: $date\n";
    $debug_log .= "Service sélectionné dans le formulaire: " . ($selected_service ?: "Aucun") . "\n";
    $debug_log .= "Durée du service sélectionné: $service_duration min\n";
    $debug_log .= "Nombre de réservations confirmées pour cette date: " . count($booked_slots) . "\n";
    $debug_log .= "Paramètres de configuration: Ouverture " . sprintf('%02d:%02d', $opening_hour, 0) . " - Fermeture " . sprintf('%02d:%02d', $closing_hour, 0) . " - Durée de créneau $slot_duration min\n\n";

    // Détailler toutes les réservations existantes
    if (count($booked_slots) > 0) {
        $debug_log .= "\nRéservations existantes pour cette date (tous services confondus):\n";
        foreach ($booked_slots as $booking) {
            $debug_log .= "- Service: " . $booking['service_nom'] . " (ID: " . $booking['service_id'] . ")\n";
            $debug_log .= "  Début: " . $booking['heure_debut'] . ", Durée: " . $booking['duree_max'] . " min\n";

            // Calculer l'heure de fin
            $start_minutes = timeToMinutes($booking['heure_debut']);
            $end_minutes = $start_minutes + intval($booking['duree_max']);
            $end_time = minutesToTime($end_minutes);
            $debug_log .= "  Fin: $end_time\n";
        }
    }

    // Initialiser le tableau des créneaux indisponibles
    $unavailable_slots = [];

    // Pour chaque réservation confirmée, bloquer tous les créneaux pendant sa durée
    // IMPORTANT: On bloque pour TOUTES les réservations, indépendamment du service demandé
    foreach ($booked_slots as $booking) {
        $booked_start_time = $booking['heure_debut'];
        $booked_duration = intval($booking['duree_max']);
        $service_name = $booking['service_nom'];
        $service_id_booked = $booking['service_id'];

        $debug_log .= "\nTraitement de la réservation: $service_name (ID: $service_id_booked)\n";
        $debug_log .= "Début à $booked_start_time, durée $booked_duration min\n";

        // Calculer l'heure de fin en minutes depuis minuit
        $start_minutes = timeToMinutes($booked_start_time);
        $end_minutes = $start_minutes + $booked_duration;
        $end_time = minutesToTime($end_minutes);

        $debug_log .= "Heure de fin calculée: $end_time\n";
        $debug_log .= "Bloque les créneaux entre $booked_start_time et $end_time\n";

        // Bloquer tous les créneaux entre l'heure de début et de fin
        foreach ($possible_slots as $slot) {
            // Vérifier si le créneau est en conflit avec la réservation existante
            // Ajout d'un log détaillé pour comprendre exactement ce qui se passe
            $debug_log .= "  Vérification du créneau $slot (durée $slot_duration min) avec réservation $booked_start_time (durée $booked_duration min):\n";

            if (checkTimeSlotConflict($slot, $slot_duration, $booked_start_time, $booked_duration)) {
                $unavailable_slots[] = $slot;
                $debug_log .= "  - CONFLIT DÉTECTÉ: Créneau $slot bloqué (en conflit avec la réservation de $service_name)\n";
            } else {
                $debug_log .= "  - PAS DE CONFLIT: Créneau $slot disponible\n";
            }
        }
    }

    // Enlever les doublons des créneaux indisponibles
    $unavailable_slots = array_unique($unavailable_slots);

    $debug_log .= "\nCréneaux indisponibles: " . implode(", ", $unavailable_slots) . "\n";
    $debug_log .= "====================\n\n";
    file_put_contents(__DIR__ . '/debug_slots.txt', $debug_log, FILE_APPEND);

    // Filtrer les créneaux disponibles
    $available_slots = array_diff($possible_slots, $unavailable_slots);

    // Si un service spécifique est demandé, vérifier que chaque créneau peut accueillir ce service
    if ($selected_service && $service_duration > $slot_duration) {
        $filtered_slots = [];
        $debug_log .= "\nVérification des créneaux pour le service $selected_service (durée: $service_duration min):\n";

        foreach ($available_slots as $slot) {
            $can_fit_service = true;
            $slot_start_minutes = timeToMinutes($slot);
            $slot_end_minutes = $slot_start_minutes + $service_duration;
            $slot_end = minutesToTime($slot_end_minutes);

            $debug_log .= "\nVérification si le service peut s'intégrer dans le créneau $slot:\n";
            $debug_log .= "- Début du service: $slot\n";
            $debug_log .= "- Fin du service: $slot_end (durée: $service_duration min)\n";

            // Vérifier que le service ne dépasse pas l'heure de fermeture
            if ($slot_end_minutes > timeToMinutes(sprintf('%02d:%02d', $closing_hour, 0))) {
                $debug_log .= "- Le service dépasserait l'heure de fermeture\n";
                $can_fit_service = false;
            } else {
                // Vérifier que le service ne serait pas en conflit avec les réservations existantes
                foreach ($booked_slots as $booking) {
                    $booked_start_time = $booking['heure_debut'];
                    $booked_duration = intval($booking['duree_max']);

                    $debug_log .= "  - Test avec réservation existante: $booked_start_time (durée $booked_duration min):\n";

                    // Log détaillé pour comprendre le conflit
                    $conflict_result = checkTimeSlotConflict($slot, $service_duration, $booked_start_time, $booked_duration);
                    $debug_log .= "    - Résultat du test de conflit: " . ($conflict_result ? "CONFLIT" : "PAS DE CONFLIT") . "\n";

                    if ($conflict_result) {
                        $debug_log .= "    - CONFLIT DÉTECTÉ avec la réservation de " . $booking['service_nom'] . " à $booked_start_time\n";
                        $can_fit_service = false;
                        break;
                    }
                }
            }

            if ($can_fit_service) {
                $debug_log .= "- Le service peut s'intégrer dans ce créneau\n";
                $filtered_slots[] = $slot;
            } else {
                $debug_log .= "- Le service ne peut pas s'intégrer dans ce créneau\n";
            }
        }

        $available_slots = $filtered_slots;
    }

    // Pour aujourd'hui, ne pas afficher les créneaux déjà passés
    if ($date == date('Y-m-d')) {
        $current_time = date('H:i');
        foreach ($available_slots as $key => $slot) {
            if ($slot <= $current_time) {
                unset($available_slots[$key]);
            }
        }
    }

    // Réindexer le tableau
    $available_slots = array_values($available_slots);

    // Renvoyer les créneaux disponibles au format JSON
    echo json_encode([
        'success' => true,
        'date' => $date,
        'available_slots' => $available_slots,
        'unavailable_slots' => $unavailable_slots,
        'message' => "",
        'debug' => [
            'service_requested' => $selected_service,
            'service_id' => $service_id ?? null,
            'service_duration' => $service_duration,
            'total_slots' => count($possible_slots),
            'unavailable_count' => count($unavailable_slots),
            'available_count' => count($available_slots),
            'booked_services' => array_map(function ($booking) {
                return [
                    'service' => $booking['service_nom'],
                    'start' => $booking['heure_debut'],
                    'duration' => $booking['duree_max']
                ];
            }, $booked_slots),
            'today' => date('Y-m-d'),
            'is_today' => ($date == date('Y-m-d')),
            'current_time' => date('H:i'),
            'timestamp' => time()
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion: ' . $e->getMessage(),
        'available_slots' => []
    ]);
}
