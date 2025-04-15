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

// Inclure les fonctions webhook
require_once 'webhook_functions.php';

// Configuration de la base de données
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "noursilk_db";

// Récupérer les paramètres de la requête
$reservation_id = isset($_GET['id']) ? $_GET['id'] : null;
$token = isset($_GET['token']) ? $_GET['token'] : null;

// Vérifier si les paramètres nécessaires sont présents
if (!$reservation_id || !$token) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    // Connexion à la base de données
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les informations de la réservation
    $stmt = $conn->prepare("
        SELECT r.id, r.date_reservation, TIME_FORMAT(r.heure_reservation, '%H:%i') as heure_reservation, 
               r.statut, c.nom as client_nom, s.nom as service_nom
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN services s ON r.service_id = s.id
        WHERE r.id = :id
    ");
    $stmt->execute(['id' => $reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Réservation non trouvée']);
        exit;
    }

    // Vérifier le token (à améliorer avec un vrai système de sécurité)
    $expected_token = md5($reservation['id'] . $reservation['client_nom'] . 'noursilk_token_secret');
    if ($token !== $expected_token) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Token invalide']);
        exit;
    }

    // Annuler la réservation dans la base de données
    $stmt = $conn->prepare("UPDATE reservations SET statut = 'annulé' WHERE id = :id");
    $stmt->execute(['id' => $reservation_id]);

    // Envoyer le webhook à Zapier pour supprimer l'événement dans Google Calendar
    sendWebhookToZapierForCancellation(
        $reservation['client_nom'],
        $reservation['service_nom'],
        $reservation['date_reservation'],
        $reservation['heure_reservation']
    );

    // Répondre avec succès
    echo json_encode([
        'success' => true,
        'message' => 'Réservation annulée avec succès'
    ]);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    error_log("Erreur dans process_cancellation.php: " . $e->getMessage());
}
