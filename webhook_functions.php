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
