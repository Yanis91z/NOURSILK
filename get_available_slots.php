<?php
// Empêcher la mise en cache
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Configuration de la base de données
$servername = "localhost";
$username = "root"; // Utilisateur root pour MAMP
$password = "root"; // Mot de passe par défaut pour root sur MAMP
$dbname = "noursilk_db";

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

    // Récupérer les réservations existantes pour cette date
    $stmt = $conn->prepare("
        SELECT heure_reservation, s.duree_max 
        FROM reservations r
        JOIN services s ON r.service_id = s.id
        WHERE r.date_reservation = :date
        AND r.statut = 'confirmé'
    ");
    $stmt->execute(['date' => $date]);
    $booked_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marquer les créneaux déjà réservés comme indisponibles
    $unavailable_slots = [];
    foreach ($booked_slots as $slot) {
        $start_time = $slot['heure_reservation'];
        $duration = $slot['duree_max'] ?? 60; // Durée par défaut : 60 minutes

        // Calculer l'heure de fin
        $start_timestamp = strtotime("$date $start_time");
        $end_timestamp = $start_timestamp + ($duration * 60);

        // Marquer tous les créneaux qui se chevauchent comme indisponibles
        foreach ($possible_slots as $possible_slot) {
            $slot_timestamp = strtotime("$date $possible_slot");
            $slot_end_timestamp = $slot_timestamp + ($slot_duration * 60);

            // Si le créneau chevauche une réservation existante
            if (
                ($slot_timestamp >= $start_timestamp && $slot_timestamp < $end_timestamp) ||
                ($slot_end_timestamp > $start_timestamp && $slot_end_timestamp <= $end_timestamp) ||
                ($slot_timestamp <= $start_timestamp && $slot_end_timestamp >= $end_timestamp)
            ) {
                $unavailable_slots[] = $possible_slot;
            }
        }
    }

    // Filtrer les créneaux disponibles
    $available_slots = array_diff($possible_slots, $unavailable_slots);

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
        'available_slots' => $available_slots
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion: ' . $e->getMessage(),
        'available_slots' => []
    ]);
}
