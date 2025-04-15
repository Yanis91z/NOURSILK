/**
* LOGOUT.PHP
*
* Ce fichier gère la déconnexion de l'interface d'administration.
* Il détruit la session et redirige vers la page de connexion.
*/

// Démarrer ou reprendre la session existante
session_start();

// Vider toutes les données de session
$_SESSION = array();

// Détruire le cookie de session si nécessaire
if (ini_get("session.use_cookies")) {
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000,
$params["path"], $params["domain"],
$params["secure"], $params["httponly"]
);
}

// Détruire la session
session_destroy();

// Définir des en-têtes pour empêcher la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Rediriger vers la page de connexion avec un message de déconnexion
header("Location: login?logout=1");
exit;