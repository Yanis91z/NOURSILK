<?php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: /Noursilk/admin/login");
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les données du formulaire
    $client_id = $_POST['client_id'] ?? null;
    $client_name = $_POST['client_name'] ?? '';
    $client_email = $_POST['client_email'] ?? '';
    $client_phone = $_POST['client_phone'] ?? '';

    // Valider les données
    if (!$client_id || empty($client_name) || empty($client_email) || empty($client_phone)) {
        header("Location: /Noursilk/admin/clients?error=Tous les champs sont obligatoires");
        exit;
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

        // Mise à jour des informations du client
        $stmt = $conn->prepare("UPDATE clients SET nom = :nom, email = :email, telephone = :telephone WHERE id = :id");
        $stmt->execute([
            'nom' => $client_name,
            'email' => $client_email,
            'telephone' => $client_phone,
            'id' => $client_id
        ]);

        // Enregistrer un log de l'action
        $log_message = date('Y-m-d H:i:s') . " - Admin " . $_SESSION['admin_username'] . " a modifié le client #" . $client_id . "\n";
        file_put_contents('../logs/admin_actions_log.txt', $log_message, FILE_APPEND);

        // Rediriger avec un message de succès
        header("Location: /Noursilk/admin/clients?client_updated=1");
        exit;
    } catch (PDOException $e) {
        // En cas d'erreur, enregistrer dans un log et rediriger avec message d'erreur
        $error_message = "Erreur: " . $e->getMessage();
        file_put_contents('../logs/error_log.txt', date('Y-m-d H:i:s') . " - " . $error_message . "\n", FILE_APPEND);

        header("Location: /Noursilk/admin/clients?error=" . urlencode("Erreur lors de la mise à jour du client. Veuillez réessayer."));
        exit;
    }
} else {
    // Si le formulaire n'a pas été soumis, rediriger vers la page principale
    header("Location: /Noursilk/admin");
    exit;
}
