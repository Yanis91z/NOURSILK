<?php
// Démarrer la session
session_start();

// En-têtes pour indiquer que la réponse est au format JSON
header('Content-Type: application/json');

// Vérifier si le lien Google Calendar existe dans la session
if (isset($_SESSION['gcal_link'])) {
    // Renvoyer le lien au format JSON
    echo json_encode([
        'success' => true,
        'calendarLink' => $_SESSION['gcal_link']
    ]);

    // Supprimer le lien de la session pour éviter les problèmes si l'utilisateur rafraîchit la page
    unset($_SESSION['gcal_link']);
} else {
    // Renvoyer une réponse d'erreur
    echo json_encode([
        'success' => false,
        'calendarLink' => null
    ]);
}
