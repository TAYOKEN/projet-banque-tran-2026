<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user']['profil'] !== 'admin') {
    header('Location: ' . ($_SESSION['user']['profil'] === 'client' ? 'page_client.php' : 'page_po.php'));
    exit;
}

require 'connection.php';

$user_id = $_SESSION['user']['id'];
$user_profil = $_SESSION['user']['profil'];
$user_id_client = $_SESSION['user']['id_client'];

$nom_utilisateur = htmlspecialchars($_SESSION['user']['prenom'] . " " . $_SESSION['user']['nom']);
$stmt_clients = $pdo->query("SELECT id_client, raison_sociale, siren FROM client ORDER BY raison_sociale ASC");
$clients_list = $stmt_clients->fetchAll();
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Le formulaire a expiré. Veuillez réessayer.";
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (($_POST['accord_po'] ?? '') !== '1') {
            $error_msg = "L'accord du Product Owner est obligatoire pour supprimer un compte.";
        } elseif ($targetId === false || $targetId === (int) $user_id) {
            $error_msg = "Ce compte ne peut pas être supprimé.";
        } else {
            $stmt = $pdo->prepare('DELETE FROM utilisateur WHERE id_utilisateur = ?');
            $stmt->execute([$targetId]);
            if ($stmt->rowCount() === 0) {
                $error_msg = "Le compte demandé n'existe plus.";
            } else {
                $success_msg = "Le compte a été supprimé.";
            }
        }
    } else {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $profil = $_POST['profil'] ?? '';
        $id_client = filter_var($_POST['id_client'] ?? null, FILTER_VALIDATE_INT);
        $mdp1 = $_POST['mdp1'] ?? '';
        $mdp2 = $_POST['mdp2'] ?? '';

        if (($_POST['accord_po'] ?? '') !== '1') {
            $error_msg = "L'accord du Product Owner est obligatoire pour créer un compte.";
        } elseif (!in_array($profil, ['admin', 'product_owner', 'client'], true)) {
            $error_msg = "Le type de compte sélectionné est invalide.";
        } elseif ($profil === 'client' && ($id_client === false || $id_client === null)) {
            $error_msg = "Veuillez sélectionner une entreprise pour ce compte Client.";
        } elseif ($mdp1 !== $mdp2) {
            $error_msg = "Les mots de passe ne correspondent pas.";
        } elseif ($prenom === '' || $nom === '' || $login === '' || $mdp1 === '') {
            $error_msg = "Veuillez remplir tous les champs obligatoires.";
        } else {
            if ($profil !== 'client') {
                $id_client = null;
            } else {
                $clientCheck = $pdo->prepare('SELECT COUNT(*) FROM client WHERE id_client = ?');
                $clientCheck->execute([$id_client]);
                if ((int) $clientCheck->fetchColumn() === 0) {
                    $error_msg = "L'entreprise sélectionnée n'existe pas.";
                }
            }

            if ($error_msg === '') {
                try {
                    $hash = password_hash($mdp1, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO utilisateur (login, mdp, nom, prenom, profil, id_client) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$login, $hash, $nom, $prenom, $profil, $id_client]);
                    $success_msg = "Le compte " . strtoupper($profil) . " a été créé avec succès.";
                } catch (PDOException $e) {
                    error_log('Account creation failed: ' . $e->getMessage());
                    $error_msg = "Erreur lors de la création du compte. Vérifiez le login et réessayez.";
                }
            }
        }
    }
}
$stmt_users = $pdo->query('SELECT id_utilisateur, login, nom, prenom, profil FROM utilisateur ORDER BY id_utilisateur ASC');
$users_list = $stmt_users->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Administrateur - Gestion des comptes</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: #f4f5f7; color: #333; display: flex; height: 100vh; overflow-x: hidden; }
        
        .sidebar { width: 100px; background-color: #1a1a1a; color: white; display: flex; flex-direction: column; align-items: center; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .user-profile { display: flex; flex-direction: column; align-items: center; margin-bottom: 40px; }
        .avatar { width: 60px; height: 60px; border-radius: 50%; border: 2px solid white; display: flex; justify-content: center; align-items: center; margin-bottom: 10px; background-color: #34D399; }
        .user-name { font-size: 11px; text-align: center; color: #ccc; }
        .nav-menu { display: flex; flex-direction: column; gap: 25px; width: 100%; align-items: center; flex-grow: 1; }
        .nav-item { display: flex; flex-direction: column; align-items: center; color: #888; font-size: 11px; text-decoration: none; width: 80%; padding: 10px 0; border-radius: 12px; opacity: 0.5; cursor: default; }
        .nav-item.active { background-color: #2a3441; color: #8fa0b5; opacity: 1; cursor: pointer; }
        .nav-item svg { width: 24px; height: 24px; margin-bottom: 5px; fill: currentColor; }
        .logout-btn { width: 50px; height: 50px; border-radius: 50%; background-color: #ef4444; display: flex; justify-content: center; align-items: center; cursor: pointer; border: none; margin-top: auto; }

        .main-content { flex-grow: 1; padding: 40px; overflow-y: auto; display: flex; flex-direction: column;}
        h1 { font-size: 32px; font-weight: 500; letter-spacing: 2px; margin-bottom: 30px; color: #0f172a; }
        
        .form-card { background-color: #242b3b; border-radius: 25px; color: white; padding: 40px 60px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); max-width: 900px; margin: 0 auto; width: 100%; }
        .form-header { text-align: center; margin-bottom: 40px; }
        .form-header h2 { font-size: 24px; font-weight: 600; letter-spacing: 1px; margin-bottom: 10px; }
        .form-header p { color: #8fa0b5; font-size: 13px; letter-spacing: 0.5px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
        .form-group { display: flex; flex-direction: column; position: relative; }
        .full-width { grid-column: span 2; }
        .form-group label { font-size: 13px; font-weight: 600; letter-spacing: 1px; margin-bottom: 10px; }
        .form-group input, .form-group select { background-color: #1a1f2b; border: none; border-radius: 10px; height: 45px; color: white; padding: 0 15px; font-size: 14px; width: 100%; outline: none; }
        
        .form-select { appearance: none; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%238fa0b5' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 15px center; padding-right: 40px; }
        
        .submit-btn { background-color: #34D399; color: white; border: none; border-radius: 25px; padding: 12px 25px; font-size: 15px; font-weight: 500; display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .alert-success { background: #10b981; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .alert-error { background: #ef4444; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .approval-row { grid-column: span 2; display: flex; align-items: center; gap: 10px; color: #fbbf24; font-size: 13px; }
        .approval-row input, .approval-check input { width: auto; height: auto; accent-color: #34D399; }
        .accounts-card { background: #fff; border-radius: 18px; padding: 28px; margin: 24px auto 0; width: min(1100px, 100%); box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        .accounts-card h2 { color: #0f172a; font-size: 20px; margin-bottom: 18px; }
        .accounts-table-wrap { overflow-x: auto; border: 1px solid #dbe2ea; border-radius: 12px; }
        .accounts-table { width: 100%; border-collapse: collapse; text-align: left; }
        .accounts-table th { background: #eef2f7; color: #334155; padding: 12px; font-size: 12px; }
        .accounts-table td { color: #334155; padding: 12px; border-top: 1px solid #e5e7eb; font-size: 13px; }
        .accounts-table tr:nth-child(even) { background: #f8fafc; }
        .delete-form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .approval-check { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #475569; white-space: nowrap; }
        .delete-btn { border: 0; border-radius: 7px; background: #dc2626; color: #fff; padding: 8px 10px; cursor: pointer; }
        
        #bloc_entreprise { display: none; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="user-profile">
            <div class="avatar">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <span class="user-name"><?= htmlspecialchars($nom_utilisateur) ?></span>
        </div>

        <nav class="nav-menu">
            <div class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>Trésorerie
            </div>
            <div class="nav-item">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6l4-2"/></svg>Remises
            </div>
            <div class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="10" cy="13" r="2"/><line x1="12" y1="15" x2="15" y2="18"/></svg>Impayés
            </div>
            <a href="#" class="nav-item active">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Comptes
            </a>
        </nav>

        <a href="logout.php" class="logout-btn" style="text-decoration: none;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
    </aside>

    <main class="main-content">
        <h1>Gestion des comptes</h1>
        
        <?php if ($success_msg): ?>
            <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-header">
                <h2>Nouveau compte utilisateur</h2>
                <p>Création de comptes Administrateurs, Product Owners ou Clients</p>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" required placeholder="Ex: Jean">
                    </div>
                    
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" required placeholder="Ex: DUPONT">
                    </div>

                    <div class="form-group full-width">
                        <label>Adresse email professionnelle</label>
                        <input type="email" name="login" required placeholder="jean.dupont@entreprise.fr">
                    </div>

                    <div class="form-group full-width">
                        <label>Rôle / Type de compte</label>
                        <select name="profil" id="select_profil" class="form-select" onchange="toggleEntrepriseField()">
                            <option value="admin">ADMINISTRATEUR</option>
                            <option value="product_owner">PRODUCT OWNER</option>
                            <option value="client" selected>CLIENT</option>
                        </select>
                    </div>

                    <div class="form-group full-width" id="bloc_entreprise">
                        <label>Lier à l'entreprise (Obligatoire pour les clients)</label>
                        <select name="id_client" class="form-select">
                            <option value="">-- Sélectionnez une entreprise --</option>
                            <?php foreach ($clients_list as $cli): ?>
                                <option value="<?= $cli['id_client'] ?>">
                                    <?= htmlspecialchars($cli['raison_sociale'] . ' (SIREN: ' . $cli['siren'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Mot de passe</label>
                        <input type="password" name="mdp1" required placeholder="********">
                    </div>

                    <div class="form-group">
                        <label>Confirmer le mot de passe</label>
                        <input type="password" name="mdp2" required placeholder="********">
                    </div>

                    <label class="approval-row">
                        <input type="checkbox" name="accord_po" value="1" required>
                        Demande de création validée par le Product Owner
                    </label>
                </div>

                <div style="display:flex; justify-content: flex-end; align-items: center; gap: 20px;">
                    <span style="color: #64748b; font-size: 12px;">Tous les champs sont requis.</span>
                    <button type="submit" class="submit-btn">
                        Créer le compte 
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </div>
            </form>
        </div>

        <section class="accounts-card">
            <h2>Comptes utilisateurs existants</h2>
            <div class="accounts-table-wrap">
                <table class="accounts-table">
                    <thead><tr><th>Utilisateur</th><th>Nom</th><th>Rôle</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($users_list as $account): ?>
                            <tr>
                                <td><?= htmlspecialchars($account['login']) ?></td>
                                <td><?= htmlspecialchars($account['prenom'] . ' ' . $account['nom']) ?></td>
                                <td><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $account['profil']))) ?></td>
                                <td>
                                    <?php if ((int) $account['id_utilisateur'] === (int) $user_id): ?>
                                        Compte connecté
                                    <?php else: ?>
                                        <form method="POST" class="delete-form" onsubmit="return confirm('Confirmer la suppression de ce compte ?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= (int) $account['id_utilisateur'] ?>">
                                            <label class="approval-check"><input type="checkbox" name="accord_po" value="1" required> Accord PO</label>
                                            <button type="submit" class="delete-btn">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <script>
            function toggleEntrepriseField() {
                const profil = document.getElementById('select_profil').value;
                const blocEntreprise = document.getElementById('bloc_entreprise');
                
                if (profil === 'client') {
                    blocEntreprise.style.display = 'block';
                    blocEntreprise.querySelector('select').setAttribute('required', 'required');
                } else {
                    blocEntreprise.style.display = 'none';
                    blocEntreprise.querySelector('select').removeAttribute('required');
                }
            }
            toggleEntrepriseField();
        </script>
    </main>
</body>
</html>