<?php

session_start();

// Empêcher la mise en cache de cette page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Initialisation des variables de message
$error_message = '';
$success_message = '';

// Afficher un message si l'utilisateur vient de se déconnecter
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $success_message = "Vous avez été déconnecté avec succès.";
}

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Rediriger vers la page d'administration
    header("Location: /Noursilk/admin");
    exit;
}

// Configuration de la base de données
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "noursilk_db";

// Connexion à la base de données
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Traitement du formulaire de connexion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        // Récupérer l'utilisateur
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header("Location: /Noursilk/admin");
            exit;
        } else {
            $error_message = "Identifiants incorrects.";
            $log_message = date('Y-m-d H:i:s') . " - Tentative de connexion échouée pour l'utilisateur: " . $username . "\n";
            file_put_contents('../logs/admin_access_log.txt', $log_message, FILE_APPEND);
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la connexion. Veuillez réessayer.";
        error_log("Erreur de base de données : " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Administration NOURSILK - Connexion</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-styles.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="logo">NOURSILK</div>
        <h1>Administration</h1>

        <?php if (!empty($success_message)): ?>
            <div class="message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required autocomplete="off">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="off">
            </div>

            <button type="submit">Se connecter</button>
        </form>
    </div>

    <script src="js/admin-scripts.js"></script>
</body>

</html>