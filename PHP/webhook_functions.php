<?php

// Fonction pour envoyer les données à Zapier pour Google Calendar
function sendWebhookToZapier($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
{
    // URL du webhook Zapier pour l'ajout d'événements
    $webhook_url = "https://hooks.zapier.com/hooks/catch/22528164/207wzzd/";

    // Calculer les dates et heures
    $start_date = new DateTime($date . ' ' . $heure);
    $start_date->modify('-2 hours'); // Retirer 2 heures pour corriger le décalage

    $end_date = clone $start_date;
    $end_date->modify('+' . $service_duree . ' minutes'); // Durée en minutes

    // Formats d'heure pour Zapier
    $start_simple = $start_date->format('H\hi');
    $end_simple = $end_date->format('H\hi');
    $start_formatted = $start_date->format('n/j/Y g:iA');
    $end_formatted = $end_date->format('n/j/Y g:iA');
    $start_iso = $start_date->format('c');
    $end_iso = $end_date->format('c');

    // Titre et description pour l'événement
    $title = "NOURSILK - $service_nom - $client_nom";
    $description = "Client: $client_nom\nEmail: $client_email\nTéléphone: $client_telephone\nService: $service_nom\nDate: $date\nHeure: $heure\nDurée: $service_duree minutes";

    // Données pour Zapier
    $data = json_encode([
        'client_nom' => $client_nom,
        'client_email' => $client_email,
        'client_telephone' => $client_telephone,
        'service_nom' => $service_nom,
        'date_reservation' => $date,
        'heure_reservation' => $heure,
        'start_date_iso' => $start_simple,
        'end_date_iso' => $end_simple,
        'start_date_formatted' => $start_formatted,
        'end_date_formatted' => $end_formatted,
        'start_date_iso_complete' => $start_iso,
        'end_date_iso_complete' => $end_iso,
        'event_title' => $title,
        'event_description' => $description,
        'reminder_minutes' => 30,
        'timestamp' => time(),
        'duree_minutes' => $service_duree,
        'cle_secrete' => 'noursilk_reservation_secret'
    ]);

    // Envoyer les données avec cURL
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);

    // Exécuter la requête et récupérer le résultat
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log pour débogage
    $log_message = "=== WEBHOOK " . date('Y-m-d H:i:s') . " ===\n";
    $log_message .= "Client: $client_nom\n";
    $log_message .= "Statut HTTP: $status\n";
    if (!empty($error)) {
        $log_message .= "Erreur cURL: $error\n";
    }
    $log_message .= "Réponse: $result\n";
    $log_message .= "====================\n\n";
    file_put_contents(__DIR__ . '/webhook_log.txt', $log_message, FILE_APPEND);

    return ($status >= 200 && $status < 300);
}

// Fonction pour envoyer les données à Zapier pour la suppression d'un événement Google Calendar
function sendWebhookToZapierForCancellation($client_nom, $service_nom, $date, $heure)
{
    // URL du webhook Zapier pour la suppression d'événements
    $webhook_url = "https://hooks.zapier.com/hooks/catch/22528164/20j7ce5/";

    // Calculer les dates et heures
    $start_date = new DateTime($date . ' ' . $heure);
    $start_date->modify('-2 hours'); // Retirer 2 heures pour corriger le décalage

    // Données pour Zapier
    $data = json_encode([
        'action' => 'delete_event',
        'client_nom' => $client_nom,
        'service_nom' => $service_nom,
        'date_reservation' => $date,
        'heure_reservation' => $heure,
        'start_date_iso' => $start_date->format('H\hi'),
        'start_date_formatted' => $start_date->format('n/j/Y g:iA'),
        'start_date_iso_complete' => $start_date->format('c'),
        'event_title' => "NOURSILK - $service_nom - $client_nom",
        'timestamp' => time(),
        'cle_secrete' => 'noursilk_reservation_secret'
    ]);

    // Envoyer les données avec cURL
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);

    // Exécuter la requête et récupérer le résultat
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log pour débogage
    $log_message = "=== WEBHOOK CANCELLATION " . date('Y-m-d H:i:s') . " ===\n";
    $log_message .= "Client: $client_nom\n";
    $log_message .= "Statut HTTP: $status\n";
    if (!empty($error)) {
        $log_message .= "Erreur cURL: $error\n";
    }
    $log_message .= "Réponse: $result\n";
    $log_message .= "====================\n\n";
    file_put_contents(__DIR__ . '/webhook_log.txt', $log_message, FILE_APPEND);

    return ($status >= 200 && $status < 300);
}

/**
 * Fonction pour vérifier si deux créneaux se chevauchent
 * 
 * @param string $start1 Heure de début du premier créneau (format HH:MM)
 * @param int $duration1 Durée du premier créneau en minutes
 * @param string $start2 Heure de début du deuxième créneau (format HH:MM)
 * @param int $duration2 Durée du deuxième créneau en minutes
 * @return bool True si les créneaux se chevauchent, false sinon
 */
function checkTimeSlotConflict($start1, $duration1, $start2, $duration2)
{
    // S'assurer que les durées sont des entiers
    $duration1 = (int)$duration1;
    $duration2 = (int)$duration2;

    // Nettoyer les formats d'heure en cas de problème
    $start1 = trim($start1);
    $start2 = trim($start2);

    // Log détaillé des paramètres d'entrée
    $debug_input = "=== CONFLIT TEST " . date('Y-m-d H:i:s') . " ===\n";
    $debug_input .= "ENTRÉE: start1=$start1, duration1=$duration1, start2=$start2, duration2=$duration2\n";
    file_put_contents(__DIR__ . '/debug_conflict.txt', $debug_input, FILE_APPEND);

    // Normaliser le format des heures (retirer les secondes si présentes)
    $start1 = normalizeTimeFormat($start1);
    $start2 = normalizeTimeFormat($start2);

    if ($start1 === false || $start2 === false) {
        file_put_contents(__DIR__ . '/debug_conflict.txt', "ERREUR FORMAT après normalisation\n\n", FILE_APPEND);
        return false;
    }

    // Convertir les heures de début en minutes depuis minuit
    $start1_minutes = timeToMinutes($start1);
    $start2_minutes = timeToMinutes($start2);

    // Calculer les heures de fin en minutes depuis minuit
    $end1_minutes = $start1_minutes + $duration1;
    $end2_minutes = $start2_minutes + $duration2;

    // Journal de débogage
    $debug = "CHECK NORMALISÉ: [$start1 -> " . minutesToTime($end1_minutes) . "] vs [$start2 -> " . minutesToTime($end2_minutes) . "]\n";
    $debug .= "MINUTES: [$start1_minutes -> $end1_minutes] vs [$start2_minutes -> $end2_minutes]\n";

    // Vérifier les 3 cas de chevauchement possibles

    // 1. Le deuxième créneau commence pendant le premier
    $case1 = $start2_minutes >= $start1_minutes && $start2_minutes < $end1_minutes;
    $debug .= "CAS 1 (commence pendant): " . ($case1 ? "VRAI" : "FAUX") . " - $start2_minutes >= $start1_minutes && $start2_minutes < $end1_minutes\n";

    // 2. Le deuxième créneau se termine pendant le premier
    $case2 = $end2_minutes > $start1_minutes && $end2_minutes <= $end1_minutes;
    $debug .= "CAS 2 (finit pendant): " . ($case2 ? "VRAI" : "FAUX") . " - $end2_minutes > $start1_minutes && $end2_minutes <= $end1_minutes\n";

    // 3. Le deuxième créneau englobe complètement le premier
    $case3 = $start2_minutes <= $start1_minutes && $end2_minutes >= $end1_minutes;
    $debug .= "CAS 3 (englobe): " . ($case3 ? "VRAI" : "FAUX") . " - $start2_minutes <= $start1_minutes && $end2_minutes >= $end1_minutes\n";

    $result = $case1 || $case2 || $case3;
    $debug .= "RÉSULTAT FINAL: " . ($result ? "CONFLIT DÉTECTÉ" : "PAS DE CONFLIT") . "\n\n";
    file_put_contents(__DIR__ . '/debug_conflict.txt', $debug, FILE_APPEND);

    if ($case1) {
        return true;
    }

    if ($case2) {
        return true;
    }

    if ($case3) {
        return true;
    }

    // Pas de chevauchement
    return false;
}

/**
 * Fonction pour normaliser le format de l'heure (HH:MM ou HH:MM:SS -> HH:MM)
 * 
 * @param string $time L'heure à normaliser
 * @return string|false L'heure au format HH:MM ou false en cas d'erreur
 */
function normalizeTimeFormat($time)
{
    // Vérifier si c'est déjà au format HH:MM
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time;
    }

    // Vérifier si c'est au format HH:MM:SS
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        $normalized = substr($time, 0, 5);
        file_put_contents(__DIR__ . '/debug_timeformat.txt', "FORMAT NORMALISÉ: $time -> $normalized\n", FILE_APPEND);
        return $normalized;
    }

    // Format inconnu
    file_put_contents(__DIR__ . '/debug_timeformat.txt', "FORMAT INCONNU: $time\n", FILE_APPEND);
    return false;
}

/**
 * Fonction pour convertir une heure au format HH:MM en minutes depuis minuit
 * 
 * @param string $time Heure au format HH:MM
 * @return int Nombre de minutes depuis minuit
 */
function timeToMinutes($time)
{
    return (int)substr($time, 0, 2) * 60 + (int)substr($time, 3, 2);
}

/**
 * Fonction pour convertir des minutes depuis minuit en heure au format HH:MM
 * 
 * @param int $minutes Nombre de minutes depuis minuit
 * @return string Heure au format HH:MM
 */
function minutesToTime($minutes)
{
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%02d:%02d", $hours, $mins);
}
