<?php

session_start();

// Empêcher la mise en cache de cette page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Vérifier si déjà connecté
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index");
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

// Initialisation des variables de message
$error = "";
$message = "";

// Afficher un message de déconnexion si nécessaire
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $message = "Vous avez été déconnecté avec succès.";
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

            header("Location: index");
            exit;
        } else {
            $error = "Identifiants incorrects.";
            $log_message = date('Y-m-d H:i:s') . " - Tentative de connexion échouée pour l'utilisateur: " . $username . "\n";
            file_put_contents('../admin_access_log.txt', $log_message, FILE_APPEND);
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la connexion. Veuillez réessayer.";
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
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Montserrat:wght@300;400;500&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --color-cream: #fcf8f3;
            --color-beige: #e8d9c5;
            --color-beige-light: #f5efe6;
            --color-chocolate: #6b4c35;
            --color-chocolate-light: #8a6d54;
            --shadow-soft: 0 4px 12px rgba(107, 76, 53, 0.08);
            --border-radius: 12px;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--color-cream);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--color-chocolate);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--color-chocolate);
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-beige);
            border-radius: 6px;
            font-family: inherit;
            font-size: 16px;
        }

        button {
            background-color: var(--color-chocolate);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            margin-top: 10px;
        }

        button:hover {
            background-color: var(--color-chocolate-light);
        }

        .error {
            color: #d9534f;
            margin-bottom: 20px;
        }

        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2em;
            color: var(--color-chocolate);
            margin-bottom: 20px;
        }

        .message {
            color: #155724;
            background-color: #d4edda;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">NOURSILK</div>
        <h1>Administration</h1>

        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
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

    <script>
        /**
         * Scripts de sécurité pour la page de connexion
         * 
         * Ces scripts assurent que :
         * 1. Le formulaire est vidé au chargement de la page
         * 2. La navigation avec le bouton retour est gérée de manière sécurisée
         */

        // Vider le formulaire lors du chargement de la page
        window.onload = function() {
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
        }

        // Empêcher la navigation avec le bouton retour et réinitialiser le formulaire
        window.addEventListener('pageshow', function(event) {
            var form = document.querySelector('form');
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                form.reset();
            }
        });
    </script>
</body>

</html>