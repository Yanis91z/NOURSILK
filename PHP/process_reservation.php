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

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure les fonctions webhook
require_once 'webhook_functions.php';

// Configuration de la base de données
$servername = "localhost";
$username = "root"; // Utilisateur root pour MAMP
$password = "root"; // Mot de passe par défaut pour root sur MAMP
$dbname = "noursilk_db";

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

// Fonction pour créer un événement Google Calendar pour une réservation existante
function createGoogleCalendarEvent($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
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
    $log_message = "=== WEBHOOK CREATE EVENT " . date('Y-m-d H:i:s') . " ===\n";
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

// Log les paramètres reçus
$log_params = "=== SOUMISSION FORMULAIRE " . date('Y-m-d H:i:s') . " ===\n";
$log_params .= "URL: " . $_SERVER['REQUEST_URI'] . "\n";
$log_params .= "Méthode: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log_params .= "Paramètres POST: \n" . print_r($_POST, true) . "\n";
$log_params .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Non défini') . "\n";
$log_params .= "====================\n\n";
file_put_contents(__DIR__ . '/debug_form_submission.txt', $log_params, FILE_APPEND);

// Assurer qu'aucune sortie n'est envoyée avant la redirection
ob_start();

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
    $service_input = $_POST['service'] ?? '';

    // Journaliser les données du formulaire
    $form_log = "=== DEBUG FORMULAIRE " . date('Y-m-d H:i:s') . " ===\n";
    $form_log .= "Nom: $nom\n";
    $form_log .= "Email: $email\n";
    $form_log .= "Téléphone: $telephone\n";
    $form_log .= "Service (valeur brute): " . var_export($service_input, true) . "\n";
    $form_log .= "Type de service: " . gettype($service_input) . "\n";

    // Déterminer l'ID du service en fonction de l'entrée
    $service_id = 0;
    if ($service_input === 'indien') {
        $service_id = 1;
        $form_log .= "Service détecté: Indien (ID: 1)\n";
    } elseif ($service_input === 'tanin') {
        $service_id = 2;
        $form_log .= "Service détecté: Tanin (ID: 2)\n";
    } else {
        // Essayer de déterminer le service à partir de la base de données
        try {
            $stmt = $conn->prepare("SELECT id FROM services WHERE LOWER(nom) LIKE :nom");
            $stmt->execute(['nom' => '%' . strtolower($service_input) . '%']);
            $service_db = $stmt->fetch();

            if ($service_db) {
                $service_id = $service_db['id'];
                $form_log .= "Service trouvé dans la base de données (ID: $service_id)\n";
            } else {
                $form_log .= "Service non reconnu, tentative de correction...\n";

                // Dernière tentative avec une valeur par défaut
                if (strpos(strtolower($service_input), 'indien') !== false) {
                    $service_id = 1;
                    $form_log .= "Service corrigé: Indien (ID: 1)\n";
                } elseif (strpos(strtolower($service_input), 'tanin') !== false) {
                    $service_id = 2;
                    $form_log .= "Service corrigé: Tanin (ID: 2)\n";
                } else {
                    // Valeur par défaut si rien ne correspond
                    $service_id = 1; // Par défaut Indien
                    $form_log .= "Aucun service reconnu, utilisation de la valeur par défaut: Indien (ID: 1)\n";
                }
            }
        } catch (Exception $e) {
            $form_log .= "Erreur lors de la recherche du service: " . $e->getMessage() . "\n";
            // Valeur par défaut en cas d'erreur
            $service_id = 1;
        }
    }

    $date_reservation = $_POST['date'] ?? '';
    $heure_reservation = $_POST['time'] ?? '';

    $form_log .= "Date: $date_reservation\n";
    $form_log .= "Heure: $heure_reservation\n";
    $form_log .= "====================\n\n";
    file_put_contents(__DIR__ . '/debug_form.txt', $form_log, FILE_APPEND);

    // Validation simple des données
    if (empty($nom) || empty($email) || empty($date_reservation) || empty($heure_reservation) || $service_id === 0) {
        echo "Erreur: Tous les champs obligatoires doivent être remplis.";
        exit;
    }

    try {
        // Récupérer les informations du service (nom et durée)
        $stmt = $conn->prepare("SELECT nom, duree_max FROM services WHERE id = :id");
        $stmt->execute(['id' => $service_id]);
        $service = $stmt->fetch();
        $service_nom = $service ? $service['nom'] : 'Service inconnu';
        $service_duree = $service ? intval($service['duree_max']) : 120; // Durée en minutes, par défaut 120

        // Vérifier si le créneau est déjà réservé
        $stmt = $conn->prepare("
            SELECT TIME_FORMAT(heure_reservation, '%H:%i') as heure_debut, 
                   s.duree_max,
                   s.id as service_id,
                   s.nom as service_nom,
                   r.statut 
            FROM reservations r
            JOIN services s ON r.service_id = s.id
            WHERE r.date_reservation = :date
            AND r.statut = 'confirmé'
        ");
        $stmt->execute(['date' => $date_reservation]);
        $booked_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug des créneaux récupérés de la base de données
        $sql_debug = "=== DEBUG SQL RÉSERVATION " . date('Y-m-d H:i:s') . " ===\n";
        $sql_debug .= "Date demandée: $date_reservation\n";
        $sql_debug .= "Nouvelle réservation: Service " . $service_nom . " (ID: " . $service_id . ") à $heure_reservation pour $service_duree min\n";
        $sql_debug .= "Heure de fin prévue: " . minutesToTime(timeToMinutes($heure_reservation) + $service_duree) . "\n";
        $sql_debug .= "\nRéservations existantes (tous services confondus):\n";
        foreach ($booked_slots as $slot) {
            $end_time = minutesToTime(timeToMinutes($slot['heure_debut']) + intval($slot['duree_max']));
            $sql_debug .= "- Service: " . $slot['service_nom'] . " (ID: " . $slot['service_id'] . ")\n";
            $sql_debug .= "  Début: " . $slot['heure_debut'] . ", Durée: " . $slot['duree_max'] . " min, Fin: " . $end_time . "\n";
        }
        $sql_debug .= "====================\n\n";
        file_put_contents(__DIR__ . '/debug_reservation_sql.txt', $sql_debug, FILE_APPEND);

        // Vérifier si le nouveau créneau est en conflit avec une réservation existante
        $has_conflict = false;
        $conflict_with_service = "";
        $new_start_minutes = timeToMinutes($heure_reservation);
        $new_end_minutes = $new_start_minutes + $service_duree;
        $new_end_time = minutesToTime($new_end_minutes);

        $debug_log = "=== DEBUG VÉRIFICATION CONFLIT " . date('Y-m-d H:i:s') . " ===\n";
        $debug_log .= "Nouvelle réservation: $service_nom (ID: $service_id) de $heure_reservation à $new_end_time ($service_duree min)\n";

        foreach ($booked_slots as $booking) {
            $booking_start = $booking['heure_debut'];
            $booking_duration = intval($booking['duree_max']);
            $booking_service = $booking['service_nom'];
            $booking_service_id = $booking['service_id'];

            $booking_start_minutes = timeToMinutes($booking_start);
            $booking_end_minutes = $booking_start_minutes + $booking_duration;
            $booking_end = minutesToTime($booking_end_minutes);

            $debug_log .= "\nVérification avec réservation existante: $booking_service (ID: $booking_service_id)\n";
            $debug_log .= "Horaire existant: $booking_start à $booking_end ($booking_duration min)\n";

            // Utiliser la fonction checkTimeSlotConflict pour vérifier le conflit
            if (checkTimeSlotConflict($heure_reservation, $service_duree, $booking_start, $booking_duration)) {
                $has_conflict = true;
                $conflict_with_service = $booking_service;
                $debug_log .= "CONFLIT: Les réservations se chevauchent selon la fonction checkTimeSlotConflict\n";
                break;
            }
        }

        $debug_log .= "\nRésultat: " . ($has_conflict ? "CONFLIT DÉTECTÉ avec $conflict_with_service" : "PAS DE CONFLIT") . "\n";
        $debug_log .= "====================\n\n";
        file_put_contents(__DIR__ . '/debug_reservation.txt', $debug_log, FILE_APPEND);

        if ($has_conflict) {
            // Ce créneau est déjà réservé
            // Log de l'erreur
            $error_log = "=== CONFLIT DE RESERVATION " . date('Y-m-d H:i:s') . " ===\n";
            $error_log .= "Client: $nom\n";
            $error_log .= "Email: $email\n";
            $error_log .= "Date: $date_reservation\n";
            $error_log .= "Heure: $heure_reservation\n";
            $error_log .= "Service: $service_nom ($service_duree min)\n";
            $error_log .= "ERREUR: Ce créneau est déjà réservé.\n";
            $error_log .= "====================\n\n";
            file_put_contents(__DIR__ . '/error_log.txt', $error_log, FILE_APPEND);

            // Rediriger vers la page d'accueil avec un message d'erreur
            header("Location: ../index.html?reservation=conflict");
            exit();
        }

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

        // Méthode 1: Enregistrer dans des fichiers locaux (pour historique et sauvegarde)
        logReservation($nom, $email, $telephone, $service_nom, $date_reservation, $heure_reservation, $service_duree);

        // Méthode 2: Envoyer les données à Zapier pour Google Calendar
        sendWebhookToZapier($nom, $email, $telephone, $service_nom, $date_reservation, $heure_reservation, $service_duree);

        // Rediriger avec un message de succès
        $redirect_url = "../index.html?reservation=success";

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
        header("Location: ../index.html?reservation=error");
        exit();
    }
}
