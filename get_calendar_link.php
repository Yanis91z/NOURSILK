/**
* GET_CALENDAR_LINK.PHP
*
* Ce fichier récupère un lien Google Calendar stocké dans la session
* et le renvoie au format JSON pour être utilisé côté client.
*
* C'est utilisé par la page de confirmation de réservation pour proposer
* au client d'ajouter l'événement à son propre calendrier Google.
*/

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