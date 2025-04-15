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
$stmt = $conn->prepare("INSERT INTO reservations (client_id, service_id, date_reservation, heure_reservation)
VALUES (:client_id, :service_id, :date_reservation, :heure_reservation)");
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
logReservationForGoogleCalendar($nom, $email, $telephone, $service_nom, $date_reservation, $heure_reservation, $service_duree);

// Méthode 2: Utiliser un webhook pour l'automatisation complète via Zapier
sendWebhookForAutomation($nom, $email, $telephone, $service_nom, $date_reservation, $heure_reservation, $service_duree);

// Rediriger avec un message de succès
header("Location: index.html?reservation=success");
exit;
} catch (PDOException $e) {
echo "Erreur: " . $e->getMessage();
exit;
}
}

/**
* Fonction pour enregistrer la réservation dans un fichier de logs
*
* Cette fonction est utilisée pour garder une trace locale des réservations,
* indépendamment de l'automatisation via Zapier.
*
* @param string $client_nom Nom du client
* @param string $client_email Email du client
* @param string $client_telephone Téléphone du client
* @param string $service_nom Nom du service réservé
* @param string $date Date de la réservation (Y-m-d)
* @param string $heure Heure de la réservation (H:i)
* @param int $service_duree Durée du service en minutes
*/
function logReservationForGoogleCalendar($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
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

// Ces méthodes ne sont plus utilisées depuis l'intégration avec Zapier
// Mais on les garde comme référence en cas de besoin
/*
// Méthode 1 : Générer un email avec un fichier .ics en pièce jointe pour Nour
sendICalendarByEmail($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree);

// Méthode 2 : Préparer une URL Google Calendar que Nour pourra ouvrir manuellement
createGoogleCalendarURLFile($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree);
*/
}

/**
* Fonction pour envoyer un email avec une pièce jointe iCalendar
* REMARQUE: Cette fonction n'est plus utilisée mais conservée pour référence
*/
function sendICalendarByEmail($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
{
// Adresse email de Nour où envoyer la notification
$nour_email = "chailiyanis.pro@gmail.com";

// Créer le contenu iCalendar
$ical = createICalendarEvent($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree);

// Sujet et corps du message
$subject = "Nouvelle réservation: $client_nom - $service_nom";
$message = "Nouvelle réservation NOURSILK\n\n";
$message .= "Client: $client_nom\n";
$message .= "Email: $client_email\n";
$message .= "Téléphone: $client_telephone\n";
$message .= "Service: $service_nom\n";
$message .= "Date: $date\n";
$message .= "Heure: $heure\n";
$message .= "Durée: $service_duree minutes\n\n";
$message .= "Vérifiez la pièce jointe pour ajouter cet événement à votre Google Calendar.";

// En-têtes pour l'email
$headers = "From: reservations@noursilk.com\r\n";
$headers .= "Reply-To: $client_email\r\n";
$headers .= "MIME-Version: 1.0\r\n";

// Créer un séparateur unique pour le message
$boundary = md5(time());
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

// Préparer le corps du message
$email_body = "--$boundary\r\n";
$email_body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$email_body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$email_body .= $message . "\r\n\r\n";

// Ajouter la pièce jointe iCalendar
$email_body .= "--$boundary\r\n";
$email_body .= "Content-Type: text/calendar; charset=UTF-8; method=REQUEST; name=\"reservation.ics\"\r\n";
$email_body .= "Content-Transfer-Encoding: base64\r\n";
$email_body .= "Content-Disposition: attachment; filename=\"reservation.ics\"\r\n\r\n";
$email_body .= chunk_split(base64_encode($ical)) . "\r\n";
$email_body .= "--$boundary--";

// Tentative d'envoi de l'email (note: peut ne pas fonctionner en local sans serveur SMTP configuré)
mail($nour_email, $subject, $email_body, $headers);
}

/**
* Fonction pour créer le contenu iCalendar
* REMARQUE: Cette fonction n'est plus utilisée mais conservée pour référence
*/
function createICalendarEvent($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
{
// Calculer les dates et heures
$start_date = $date . ' ' . $heure;
$start_timestamp = strtotime($start_date);
$end_timestamp = $start_timestamp + $service_duree * 60; // Durée en secondes

$start_date_formatted = date('Ymd\THis', $start_timestamp);
$end_date_formatted = date('Ymd\THis', $end_timestamp);

// Créer un identifiant unique
$uid = md5(uniqid(rand(), true));

// Préparer le titre et la description
$summary = "NOURSILK - $service_nom - $client_nom";

$description = "Service: $service_nom\r\n";
$description .= "Client: $client_nom\r\n";
$description .= "Email: $client_email\r\n";
$description .= "Téléphone: $client_telephone\r\n";
$description .= "Statut: en attente\r\n";

// Créer le contenu iCalendar
$ical = "BEGIN:VCALENDAR\r\n";
$ical .= "VERSION:2.0\r\n";
$ical .= "PRODID:-//NOURSILK//Reservations//FR\r\n";
$ical .= "CALSCALE:GREGORIAN\r\n";
$ical .= "METHOD:REQUEST\r\n";

$ical .= "BEGIN:VEVENT\r\n";
$ical .= "UID:" . $uid . "@noursilk.com\r\n";
$ical .= "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
$ical .= "DTSTART:" . $start_date_formatted . "\r\n";
$ical .= "DTEND:" . $end_date_formatted . "\r\n";
$ical .= "SUMMARY:" . $summary . "\r\n";
$ical .= "DESCRIPTION:" . str_replace("\r\n", "\\n", $description) . "\r\n";
$ical .= "STATUS:TENTATIVE\r\n";
$ical .= "BEGIN:VALARM\r\n";
$ical .= "ACTION:DISPLAY\r\n";
$ical .= "DESCRIPTION:Rappel: " . $summary . "\r\n";
$ical .= "TRIGGER:-PT30M\r\n";
$ical .= "END:VALARM\r\n";
$ical .= "END:VEVENT\r\n";

$ical .= "END:VCALENDAR\r\n";

return $ical;
}

/**
* Fonction pour créer un fichier HTML avec un lien Google Calendar
* REMARQUE: Cette fonction n'est plus utilisée mais conservée pour référence
*/
function createGoogleCalendarURLFile($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
{
// Calculer la date et heure de début et fin
$start_date = new DateTime($date . ' ' . $heure);
$end_date = clone $start_date;
$end_date->modify('+' . $service_duree . ' minutes'); // Durée en minutes

// Convertir au format Google Calendar
$start_time = $start_date->format('Ymd\THis');
$end_time = $end_date->format('Ymd\THis');

// Préparer le titre et la description
$title = urlencode("NOURSILK - $service_nom - $client_nom");
$description = urlencode("Client: $client_nom\nEmail: $client_email\nTéléphone: $client_telephone\nService: $service_nom\nDate: $date\nHeure: $heure\nDurée: $service_duree minutes");

// Créer le lien Google Calendar
$link = "https://calendar.google.com/calendar/render?action=TEMPLATE";
$link .= "&text=$title";
$link .= "&dates=$start_time/$end_time";
$link .= "&details=$description";
$link .= "&sf=true&output=xml&reminders[]=30";

// Créer un fichier HTML unique pour cette réservation
$filename = 'gcal_links/' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/i', '_', $client_nom) . '.html';

// Créer le répertoire si nécessaire
if (!is_dir('gcal_links')) {
mkdir('gcal_links', 0755, true);
}

// Contenu HTML
$html = '
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Ajouter à Google Calendar - ' . htmlspecialchars($client_nom) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        h1 {
            color: #333;
        }

        .details {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .btn {
            display: inline-block;
            background: #4285F4;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Nouvelle réservation</h1>
        <div class="details">
            <p><strong>Client:</strong> ' . htmlspecialchars($client_nom) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($client_email) . '</p>
            <p><strong>Téléphone:</strong> ' . htmlspecialchars($client_telephone) . '</p>
            <p><strong>Service:</strong> ' . htmlspecialchars($service_nom) . '</p>
            <p><strong>Date:</strong> ' . htmlspecialchars($date) . '</p>
            <p><strong>Heure:</strong> ' . htmlspecialchars($heure) . '</p>
            <p><strong>Durée:</strong> ' . htmlspecialchars($service_duree) . ' minutes</p>
        </div>
        <p>Cliquez sur le bouton ci-dessous pour ajouter cet événement à votre Google Calendar:</p>
        <a href="' . $link . '" class="btn" target="_blank">Ajouter à Google Calendar</a>
    </div>
</body>

</html>';

// Écrire le fichier
file_put_contents($filename, $html);
}

/**
* Fonction pour envoyer les données de réservation à Zapier
*
* Cette fonction envoie les détails de la réservation à un webhook Zapier
* qui s'occupe de créer automatiquement l'événement dans Google Calendar.
*
* @param string $client_nom Nom du client
* @param string $client_email Email du client
* @param string $client_telephone Téléphone du client
* @param string $service_nom Nom du service réservé
* @param string $date Date de la réservation (Y-m-d)
* @param string $heure Heure de la réservation (H:i)
* @param int $service_duree Durée du service en minutes
* @return bool Succès ou échec de l'envoi au webhook
*/
function sendWebhookForAutomation($client_nom, $client_email, $client_telephone, $service_nom, $date, $heure, $service_duree)
{
// URL du webhook Zapier
$webhook_url = "https://hooks.zapier.com/hooks/catch/22528164/207wzzd/";

// Calculer les dates et heures
$start_date = new DateTime($date . ' ' . $heure);
$start_date->modify('-2 hours'); // Retirer 2 heures pour corriger le décalage

$end_date = clone $start_date;
$end_date->modify('+' . $service_duree . ' minutes'); // Durée en minutes

// Format simple pour l'heure (19h50)
$start_simple = $start_date->format('H\hi');
$end_simple = $end_date->format('H\hi');

// Format américain avec l'heure locale corrigée
$start_formatted = $start_date->format('n/j/Y g:iA');
$end_formatted = $end_date->format('n/j/Y g:iA');

// Format ISO 8601
$start_iso = $start_date->format('c');
$end_iso = $end_date->format('c');

// Création d'un titre bien formaté
$title = "NOURSILK - $service_nom - $client_nom";

// Création d'une description complète
$description = "Client: $client_nom\n";
$description .= "Email: $client_email\n";
$description .= "Téléphone: $client_telephone\n";
$description .= "Service: $service_nom\n";
$description .= "Date: $date\n";
$description .= "Heure: $heure\n";
$description .= "Durée: $service_duree minutes\n";

// Préparer les données au format JSON
$data = json_encode([
'client_nom' => $client_nom,
'client_email' => $client_email,
'client_telephone' => $client_telephone,
'service_nom' => $service_nom,
'date_reservation' => $date,
'heure_reservation' => $heure,
'start_date_iso' => $start_simple, // Format simple (19h50)
'end_date_iso' => $end_simple, // Format simple (20h50)
'start_date_formatted' => $start_formatted, // Format américain avec l'heure locale
'end_date_formatted' => $end_formatted, // Format américain avec l'heure locale
'start_date_iso_complete' => $start_iso, // Format ISO 8601 complet (pour compatibilité)
'end_date_iso_complete' => $end_iso, // Format ISO 8601 complet (pour compatibilité)
'event_title' => $title,
'event_description' => $description,
'reminder_minutes' => 30,
'timestamp' => time(),
'duree_minutes' => $service_duree,
'cle_secrete' => 'noursilk_reservation_secret'
]);

// Initialiser cURL
$ch = curl_init($webhook_url);

// Configurer la requête cURL
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
'Content-Type: application/json',
'Content-Length: ' . strlen($data)
]);

// Exécuter la requête
$result = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Fermer la session cURL
curl_close($ch);

// Journaliser le résultat pour le débogage (avec plus de détails)
$log_message = "=== WEBHOOK " . date('Y-m-d H:i:s') . " ===\n";
$log_message .= "Client: $client_nom\n";
$log_message .= "Date formatée: $start_formatted\n";
$log_message .= "Statut HTTP: $status\n";
if (!empty($error)) {
$log_message .= "Erreur cURL: $error\n";
}
$log_message .= "Réponse: $result\n";
$log_message .= "====================\n\n";
file_put_contents('webhook_log.txt', $log_message, FILE_APPEND);

return ($status >= 200 && $status < 300);
    }