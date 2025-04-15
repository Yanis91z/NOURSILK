<?php

/**
 * PROCESS_CANCELLATION.PHP
 *
 * Ce fichier gère l'annulation des réservations pour NOURSILK.
 * Il reçoit les données de la réservation à annuler et envoie un webhook
 * à Zapier pour supprimer l'événement correspondant dans Google Calendar.
 *
 * @author: NOURSILK
 * @version: 1.0
 * @date: 2025
 */

// Fonction pour envoyer les données à Zapier pour la suppression d'un événement Google Calendar
function sendWebhookToZapierForCancellation($client_nom, $service_nom, $date, $heure)
{
    // URL du webhook Zapier pour la suppression d'événements
    $webhook_url = "https://hooks.zapier.com/hooks/catch/22528164/20j7ce5/";

    // Vérifier que tous les paramètres sont présents
    if (empty($client_nom) || empty($service_nom) || empty($date) || empty($heure)) {
        error_log("Paramètres manquants pour l'annulation: client_nom=$client_nom, service_nom=$service_nom, date=$date, heure=$heure");
        return false;
    }

    // Configuration de la base de données
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $dbname = "noursilk_db";

    try {
        // Connexion à la base de données
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Récupérer la durée du service
        $stmt = $conn->prepare("SELECT duree_max FROM services WHERE nom = ?");
        $stmt->execute([$service_nom]);
        $service = $stmt->fetch();
        $service_duree = $service ? intval($service['duree_max']) : 120; // Durée en minutes, par défaut 120
    } catch (PDOException $e) {
        error_log("Erreur de base de données: " . $e->getMessage());
        $service_duree = 120; // Durée par défaut en cas d'erreur
    }

    // Calculer les dates et heures
    $start_date = new DateTime($date . ' ' . $heure);
    $start_date->modify('-2 hours'); // Retirer 2 heures pour corriger le décalage

    // Calculer la date de fin en utilisant la durée du service
    $end_date = clone $start_date;
    $end_date->modify('+' . $service_duree . ' minutes');

    // Données pour Zapier
    $data = [
        'action' => 'delete_event',
        'client_nom' => $client_nom,
        'service_nom' => $service_nom,
        'date_reservation' => $date,
        'heure_reservation' => $heure,
        'start_date_iso' => $start_date->format('H\hi'),
        'start_date_formatted' => $start_date->format('n/j/Y g:iA'),
        'start_date_iso_complete' => $start_date->format('c'),
        'end_date_iso_complete' => $end_date->format('c'),
        'event_title' => "NOURSILK - $service_nom - $client_nom",
        'timestamp' => time(),
        'cle_secrete' => 'noursilk_reservation_secret'
    ];

    // Convertir en JSON
    $json_data = json_encode($data);

    // Log des données envoyées
    $log_message = "=== WEBHOOK CANCELLATION " . date('Y-m-d H:i:s') . " ===\n";
    $log_message .= "URL: $webhook_url\n";
    $log_message .= "Données envoyées:\n" . print_r($data, true) . "\n";
    $log_message .= "JSON envoyé:\n" . $json_data . "\n";

    // Envoyer les données avec cURL
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_data)
    ]);

    // Exécuter la requête et récupérer le résultat
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Ajouter les informations de réponse au log
    $log_message .= "Statut HTTP: $status\n";
    if (!empty($error)) {
        $log_message .= "Erreur cURL: $error\n";
    }
    $log_message .= "Réponse: $result\n";
    $log_message .= "====================\n\n";

    // Écrire le log complet
    $log_file = dirname(__FILE__) . '/webhook_log.txt';
    if (file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
        error_log("Impossible d'écrire dans le fichier de log: $log_file");
    }

    // Retourner true si la requête a réussi
    return ($status >= 200 && $status < 300);
}
