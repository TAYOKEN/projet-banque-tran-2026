<?php
session_start();
require 'connection.php';

$error = '';
if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $mdp = $_POST['mdp'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($mdp, $user['mdp'])) {
        $_SESSION['failed_attempts'] = 0;
        $_SESSION['user'] = [
            'id' => $user['id_utilisateur'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'profil' => $user['profil'],
            'id_client' => $user['id_client']
        ];

        if ($user['profil'] === 'admin') {
            header('Location: page_admin.php');
            exit;
        } elseif ($user['profil'] === 'product_owner') {
            header('Location: page_po.php');
            exit;
        } elseif ($user['profil'] === 'client') {
            header('Location: page_client.php');
            exit;
        }
    } else {
        $_SESSION['failed_attempts']++;
        $error = "Identifiant ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Portail Monétique</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f4f5f7; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-card { background-color: #242b3b; padding: 40px; border-radius: 20px; color: white; width: 400px; box-shadow: 0 15px 30px rgba(0,0,0,0.2); }
        h2 { text-align: center; margin-bottom: 25px; letter-spacing: 1px; }
        .form-group { margin-bottom: 20px; position: relative; }
        label { display: block; font-size: 13px; margin-bottom: 8px; color: #8fa0b5; }
        input { width: 100%; height: 45px; background: #1a1f2b; border: none; border-radius: 8px; color: white; padding: 0 15px; font-size: 14px; outline: none; }
        .password-icon { position: absolute; right: 15px; top: 38px; cursor: pointer; color: #8fa0b5; }
        .btn-submit { width: 100%; height: 45px; background: #FF4785; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .alert-warning { background-color: #ef4444; color: white; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; text-align: center; font-weight: 500; }
        .error-msg { color: #ef4444; font-size: 13px; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Connexion</h2>
        
        <?php if ($_SESSION['failed_attempts'] >= 2): ?>
            <div class="alert-warning">
                ATTENTION: C'est votre dernier essai...
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Identifiant (Login)</label>
                <input type="text" name="login" required placeholder="Ex: po1, dupont_user, admin1">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="mdp" id="password" required value="password">
                <!-- Bouton l'œil pour voir/cacher le mot de passe (TS6 / Epic 6) -->
                <svg class="password-icon" id="togglePassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </div>
            <button type="submit" class="btn-submit">Se connecter</button>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
        });
    </script>
</body>
</html>