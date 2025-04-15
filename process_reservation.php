<?php

/**
 * PROCESS_RESERVATION.PHP
 *
 * Ce fichier gère le traitement des réservations pour NOURSILK.
 * Il reçoit les données du formulaire de réservation, les enregistre en base de données,
 * et transfère les informations à Zapier pour la création automatique d'événements
 * dans Google Calendar.
 *
 * @author: NOURSILK
 * @version: 2.0
 * @date: 2025
 */

// Assurer qu'aucune sortie n'est envoyée avant la redirection
ob_start();

// Démarrer la session pour stocker les informations temporaires
session_start();

// Configuration de la base de données
$servername = "localhost";
$username = "root"; // Utilisateur root pour MAMP
$password = "root"; // Mot de passe par défaut pour root sur MAMP
$dbname = "noursilk_db";

// Connexion à la base de données
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // Configurer le mode d'erreur PDO pour afficher les exceptions
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erreur de connexion: " . $e->getMessage();
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les données du formulaire
    $nom = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['phone'] ?? '';
    $service_id = ($_POST['service'] == 'indien') ? 1 : 2; // 1 pour Indien, 2 pour Tanin
    $date_reservation = $_POST['date'] ?? '';
    $heure_reservation = $_POST['time'] ?? '';

    // Validation simple des données
    if (empty($nom) || empty($email) || empty($date_reservation) || empty($heure_reservation)) {
        echo "Erreur: Tous les champs obligatoires doivent être remplis.";
        exit;
    }

    try {
        // Insérer le client s'il n'existe pas déjà
        $stmt = $conn->prepare("SELECT id FROM clients WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $client = $stmt->fetch();

        if ($client) {
            $client_id = $client['id'];
        } else {
            $stmt = $conn->prepare("INSERT INTO clients (nom, email, telephone) VALUES (:nom, :email, :telephone)");
            $stmt->execute([
                'nom' => $nom,
                'email' => $email,
                'telephone' => $telephone
            ]);
            $client_id = $conn->lastInsertId();
        }

        // Insérer la réservation
        $stmt = $conn->prepare("INSERT INTO reservations (client_id, service_id, date_reservation, heure_reservation, statut)
VALUES (:client_id, :service_id, :date_reservation, :heure_reservation, 'confirmé')");
        $stmt->execute([
            'client_id' => $client_id,
            'service_id' => $service_id,
            'date_reservation' => $date_reservation,
            'heure_reservation' => $heure_reservation
        ]);

        $reservation_id = $conn->lastInsertId();

        // Récupérer les informations du service (nom et durée)
        $stmt = $conn->prepare("SELECT nom, duree_max FROM services WHERE id = :id");
        $stmt->execute(['id' => $service_id]);
        $service = $stmt->fetch();
        $service_nom = $service ? $service['nom'] : 'Service inconnu';
        $service_duree = $service ? intval($service['duree_max']) : 120; // Durée en minutes, par défaut 120

        // Méthode 1: Enregistrer dans des fichiers locaux (pour historique et sauvegarde)
        logReservation($nom, $email, $telephone, $service_nom, $date_reservation, $heure_reservation, $service_duree);

        // Méthode 2: Envoyer les données à Zapier pour Google Calendar
        sendWebhookToZapier($nom, $email, $telephone, $service_nom, $date_reservation, $heure_reservation, $service_duree);

        // Rediriger avec un message de succès
        $redirect_url = "index.html?reservation=success";

        // Vider tout tampon de sortie avant la redirection
        ob_end_clean();

        // Effectuer la redirection
        header("Location: $redirect_url");
        exit();
    } catch (PDOException $e) {
        // Enregistrer l'erreur dans un fichier de log
        $error_message = "Erreur: " . $e->getMessage();
        file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - " . $error_message . "\n", FILE_APPEND);

        // Rediriger vers la page d'accueil avec un message d'erreur
        header("Location: index.html?reservation=error");
        exit();
    }
}

// Fonction pour enregistrer la réservation dans un fichier de logs
function logReservation($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
{
    // Créer un message formaté pour le fichier de log
    $logEntry = "=== NOUVELLE RÉSERVATION ===\n";
    $logEntry .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "Client: " . $client_nom . "\n";
    $logEntry .= "Email: " . $client_email . "\n";
    $logEntry .= "Téléphone: " . $client_telephone . "\n";
    $logEntry .= "Service: " . $service_nom . "\n";
    $logEntry .= "Date RDV: " . $date . "\n";
    $logEntry .= "Heure RDV: " . $heure . "\n";
    $logEntry .= "Durée: " . $service_duree . " minutes\n";
    $logEntry .= "================================\n\n";

    // Écrire dans le fichier de log
    file_put_contents('reservations_log.txt', $logEntry, FILE_APPEND);
}

// Fonction pour envoyer les données à Zapier pour Google Calendar
function sendWebhookToZapier($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
{
    // URL du webhook Zapier
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
    file_put_contents('webhook_log.txt', $log_message, FILE_APPEND);

    return ($status >= 200 && $status < 300);
}
