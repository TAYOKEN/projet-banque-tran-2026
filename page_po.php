<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'connection.php';

$page = $_GET['page'] ?? 'tresorerie';

$user_id = $_SESSION['user']['id'];
$user_profil = $_SESSION['user']['profil'];
$user_id_client = $_SESSION['user']['id_client'];

$nom_utilisateur = htmlspecialchars($_SESSION['user']['prenom'] . " " . $_SESSION['user']['nom']);

$success_msg = "";
$error_msg = "";

$tresoreries = [];
$remises = [];
$details_remises = [];
$impayes = [];

switch ($page) {
    case 'tresorerie':
        $stmt = $pdo->query("SELECT * FROM v_annonces_tresorerie");
        $tresoreries = $stmt->fetchAll();
        break;

    case 'remises':
        $stmt = $pdo->query("SELECT * FROM v_liste_remises");
        $remises = $stmt->fetchAll();
        
        $stmt_det = $pdo->query("SELECT * FROM v_details_remises");
        $raw_details = $stmt_det->fetchAll();
        foreach ($raw_details as $det) {
            $details_remises[$det['num_remise']][] = $det;
        }
        break;

    case 'impayes':
    case 'impayes_graph':
        $stmt = $pdo->query("SELECT * FROM v_recherche_impayes");
        $impayes = $stmt->fetchAll();
        break;

    case 'comptes':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $prenom = trim($_POST['prenom'] ?? '');
            $nom    = trim($_POST['nom'] ?? '');
            $login  = trim($_POST['login'] ?? ''); 
            $mdp1   = $_POST['mdp1'] ?? '';
            $mdp2   = $_POST['mdp2'] ?? '';
            $profil = 'product_owner';

            if ($mdp1 !== $mdp2) {
                $error_msg = "Les mots de passe ne correspondent pas.";
            } elseif (!empty($prenom) && !empty($nom) && !empty($login) && !empty($mdp1)) {
                try {
                    $hash = password_hash($mdp1, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO utilisateur (login, mdp, nom, prenom, profil, id_client) VALUES (?, ?, ?, ?, ?, NULL)");
                    $stmt->execute([$login, $hash, $nom, $prenom, $profil]);
                    $success_msg = "Le compte Product Owner a été créé avec succès.";
                } catch (PDOException $e) {
                    $error_msg = "Erreur lors de la création (ce login existe peut-être déjà).";
                }
            } else {
                $error_msg = "Veuillez remplir tous les champs.";
            }
        }
        break;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Product Owner</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: #f4f5f7; color: #333; display: flex; height: 100vh; overflow-x: hidden; }
        
        .sidebar { width: 100px; background-color: #1a1a1a; color: white; display: flex; flex-direction: column; align-items: center; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .user-profile { display: flex; flex-direction: column; align-items: center; margin-bottom: 40px; }
        .avatar { width: 60px; height: 60px; border-radius: 50%; border: 2px solid white; display: flex; justify-content: center; align-items: center; margin-bottom: 10px; background-color: #FF4785; }
        .user-name { font-size: 11px; text-align: center; color: #ccc; }
        .nav-menu { display: flex; flex-direction: column; gap: 25px; width: 100%; align-items: center; flex-grow: 1; }
        .nav-item { display: flex; flex-direction: column; align-items: center; color: #888; font-size: 11px; text-decoration: none; width: 80%; padding: 10px 0; border-radius: 12px; }
        .nav-item.active { background-color: #2a3441; color: #8fa0b5; }
        .nav-item svg { width: 24px; height: 24px; margin-bottom: 5px; fill: currentColor; }
        .logout-btn, .back-btn { width: 50px; height: 50px; border-radius: 50%; background-color: #ef4444; display: flex; justify-content: center; align-items: center; cursor: pointer; border: none; margin-top: auto; }

        .main-content { flex-grow: 1; padding: 40px; overflow-y: auto; display: flex; flex-direction: column;}
        h1 { font-size: 32px; font-weight: 500; letter-spacing: 2px; margin-bottom: 30px; color: #0f172a; }
        .dark-card { background-color: #242b3b; border-radius: 20px; color: white; padding: 25px 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        .top-section { display: flex; gap: 20px; margin-bottom: 20px; }
        .search-form { flex: 2; display: flex; flex-direction: column; gap: 15px; }
        .form-row { display: flex; align-items: center; }
        .form-label { width: 170px; font-size: 14px; letter-spacing: 1px; }
        .form-input { background-color: #1a1f2b; border: none; border-radius: 8px; height: 30px; width: 250px; color: white; padding: 0 10px; }
        .date-selects { display: flex; gap: 10px; }
        .date-selects select { background-color: transparent; border: 1px solid #475569; color: white; padding: 5px 10px; border-radius: 5px; appearance: none; cursor: pointer; }
        .action-panel { flex: 1; display: flex; flex-direction: column; gap: 15px; }
        .info-box { background-color: #e5e7eb; color: #374151; padding: 15px; border-radius: 12px; font-size: 13px; display: flex; align-items: flex-start; gap: 10px; line-height: 1.4; }
        .btn-pink { background-color: #FF4785; color: white; border: none; border-radius: 12px; padding: 15px; display: flex; justify-content: center; align-items: center; cursor: pointer; flex-grow: 1; text-decoration: none;}

        .results-section { margin-bottom: 20px; }
        .results-header { font-size: 14px; margin-bottom: 20px; letter-spacing: 1px; font-weight: 500; display: flex; justify-content: space-between; align-items: center;}
        .data-table { width: 100%; border-collapse: collapse; text-align: center; }
        .data-table th { font-weight: 600; padding-bottom: 10px; font-size: 13px; border-bottom: 4px solid white; letter-spacing: 1px; }
        .data-table td { padding: 12px 5px; font-size: 12px; font-family: monospace; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .data-table th:not(:first-child), .data-table td:not(:first-child) { border-left: 4px solid white; }
        .data-table td:not(:first-child) { border-left: none; }
        .data-table th:nth-child(2) { text-align: left; padding-left: 10px; }

        .main-row td { cursor: pointer; }
        .main-row td:first-child { display: flex; align-items: center; justify-content: center; gap: 10px; border-left: none !important;}
        .expanded-content { background-color: #1f2533; padding: 15px 0 15px 40px; display: none; } 
        .nested-table { width: 100%; border-collapse: collapse; text-align: center; }
        .nested-table th { font-weight: 600; padding-bottom: 8px; font-size: 12px; border-bottom: 3px solid white; border-left: 4px solid white; }
        .nested-table th.icon-header { border-left: none; width: 40px; }
        .nested-table td { padding: 8px 0; font-size: 12px; font-family: monospace; color: #d1d5db; }

        .form-card { background-color: #242b3b; border-radius: 25px; color: white; padding: 40px 60px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); max-width: 900px; margin: 0 auto; width: 100%; }
        .form-header { text-align: center; margin-bottom: 40px; }
        .form-header h2 { font-size: 24px; font-weight: 600; letter-spacing: 1px; margin-bottom: 10px; }
        .form-header p { color: #8fa0b5; font-size: 13px; letter-spacing: 0.5px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
        .form-group { display: flex; flex-direction: column; position: relative; }
        .full-width { grid-column: span 2; }
        .form-group label { font-size: 13px; font-weight: 600; letter-spacing: 1px; margin-bottom: 10px; }
        .form-group input, .form-group select { background-color: #1a1f2b; border: none; border-radius: 10px; height: 45px; color: white; padding: 0 15px; font-size: 14px; width: 100%; outline: none; }
        .submit-btn { background-color: #FF4785; color: white; border: none; border-radius: 25px; padding: 12px 25px; font-size: 15px; font-weight: 500; display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .alert-success { background: #10b981; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .alert-error { background: #ef4444; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }

        .chart-container { position: relative; height: 250px; margin-left: 40px; margin-right: 20px; border-left: 1px solid rgba(255, 255, 255, 0.3); border-bottom: 1px solid rgba(255, 255, 255, 0.3); display: flex; align-items: flex-end; justify-content: space-around; padding: 0 40px; }
        .grid-line { position: absolute; left: -5px; right: 0; border-top: 1px solid rgba(255, 255, 255, 0.1); z-index: 1; }
        .y-label { position: absolute; left: -40px; font-size: 10px; color: #ccc; transform: translateY(-50%); }
        .bar-group { display: flex; flex-direction: column; align-items: center; z-index: 2; width: 20%; height: 100%; justify-content: flex-end;}
        .bar { background-color: #FF4785; width: 100%; border-top-left-radius: 15px; border-top-right-radius: 15px; }
        .x-label { margin-top: 15px; font-size: 10px; color: #ccc; white-space: nowrap; position: absolute; bottom: -25px;}

        .export-bar { display: inline-flex; align-items: center; padding: 12px 25px; border-radius: 20px; gap: 30px; }
        .export-title { font-weight: 600; letter-spacing: 1px; font-size: 14px; }
        .radio-group { display: flex; gap: 25px; }
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
            <a href="?page=tresorerie" class="nav-item <?= $page === 'tresorerie' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>Trésorerie
            </a>
            <a href="?page=remises" class="nav-item <?= $page === 'remises' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6l4-2"/></svg>Remises
            </a>
            <a href="?page=impayes" class="nav-item <?= in_array($page, ['impayes', 'impayes_graph']) ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="10" cy="13" r="2"/><line x1="12" y1="15" x2="15" y2="18"/></svg>Impayés
            </a>
            <a href="?page=comptes" class="nav-item <?= $page === 'comptes' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Comptes
            </a>
        </nav>

        <a href="logout.php" class="logout-btn" style="text-decoration: none;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
    </aside>

    <main class="main-content">
        
        <?php if ($page === 'tresorerie'): ?>
            <h1>Annonces de Trésorerie</h1>
            <div class="top-section">
                <div class="dark-card search-form">
                    <div class="form-row"><div class="form-label">N° de SIREN :</div><input type="text" class="form-input"></div>
                    <div class="form-row"><div class="form-label">Raison Sociale :</div><input type="text" class="form-input"></div>
                    <div class="form-row"><div class="form-label">Date de Valeur :</div><div class="date-selects"><select><option>Tous</option></select></div></div>
                </div>
                <div class="action-panel">
                    <div class="info-box">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        À noter que si aucun numéro de SIREN ou aucune raison sociale n'est renseigné, ceci traitera pour toutes les entreprises disponibles.
                    </div>
                    <button class="btn-pink"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
                </div>
            </div>

            <div class="dark-card results-section">
                <div class="results-header">État Global des Comptes</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° de SIREN</th>
                            <th>Raison Sociale</th>
                            <th>Nombre de Transactions</th>
                            <th>Devise</th>
                            <th>Solde Actuel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tresoreries as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['num_siren']) ?></td>
                            <td style="font-family: inherit;"><?= htmlspecialchars($row['raison_sociale']) ?></td>
                            <td><?= htmlspecialchars($row['nb_transactions'] ?? 0) ?></td>
                            <td>EUR</td>
                            <td><?= number_format($row['montant_total_solde'], 2, ',', ' ') ?> €</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($tresoreries)) echo "<tr><td colspan='5'>Aucune donnée.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'remises'): ?>
            <h1>Remises</h1>
            <div class="top-section">
                <div class="dark-card search-form">
                    <div class="form-row"><div class="form-label">N° de SIREN :</div><input type="text" class="form-input"></div>
                    <div class="form-row"><div class="form-label">Raison Sociale :</div><input type="text" class="form-input"></div>
                </div>
                <div class="action-panel">
                    <div class="info-box">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Filtrez par SIREN pour trouver les remises spécifiques à un client.
                    </div>
                    <button class="btn-pink"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
                </div>
            </div>

            <div class="dark-card results-section">
                <div class="results-header">Historique Général des Remises</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° SIREN</th>
                            <th>Raison Sociale</th>
                            <th>N° Remise</th>
                            <th>Date Traitement</th>
                            <th>Transactions</th>
                            <th>Devise</th>
                            <th>Montant Total</th>
                            <th>Sens</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($remises as $row): ?>
                        <tr class="main-row" onclick="this.nextElementSibling.querySelector('.expanded-content').style.display = this.nextElementSibling.querySelector('.expanded-content').style.display === 'block' ? 'none' : 'block'">
                            <td><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><polyline points="6 9 12 15 18 9"></polyline></svg> <?= htmlspecialchars($row['num_siren']) ?></td>
                            <td style="font-family: inherit;"><?= htmlspecialchars($row['raison_sociale']) ?></td>
                            <td><?= htmlspecialchars($row['num_remise']) ?></td>
                            <td><?= htmlspecialchars($row['date_traitement']) ?></td>
                            <td><?= htmlspecialchars($row['nb_transactions']) ?></td>
                            <td>EUR</td>
                            <td><?= number_format($row['montant_total'], 2, ',', ' ') ?> €</td>
                            <td><?= htmlspecialchars($row['sens'] === '+' ? 'Crédit' : 'Débit') ?></td>
                        </tr>
                        <tr>
                            <td colspan="8" style="padding: 0; border: none;">
                                <div class="expanded-content">
                                    <table class="nested-table">
                                        <thead>
                                            <tr>
                                                <th class="icon-header">↳</th>
                                                <th>N° SIREN</th>
                                                <th>Date Vente</th>
                                                <th>N° Carte</th>
                                                <th>Réseau</th>
                                                <th>N° Autorisation</th>
                                                <th>Montant</th>
                                                <th>Sens</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($details_remises[$row['num_remise']])): ?>
                                                <?php foreach ($details_remises[$row['num_remise']] as $det): ?>
                                                <tr>
                                                    <td></td>
                                                    <td><?= htmlspecialchars($det['num_siren']) ?></td>
                                                    <td><?= htmlspecialchars($det['date_vente']) ?></td>
                                                    <td><?= htmlspecialchars($det['num_carte_masquee']) ?></td>
                                                    <td><?= htmlspecialchars($det['reseau']) ?></td>
                                                    <td><?= htmlspecialchars($det['num_autorisation']) ?></td>
                                                    <td><?= number_format($det['montant'], 2, ',', ' ') ?> €</td>
                                                    <td><?= htmlspecialchars($det['sens'] === '+' ? 'Crédit' : 'Débit') ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'impayes'): ?>
            <h1>Impayés</h1>
            <div class="top-section">
                <div class="dark-card search-form">
                    <div class="form-row"><div class="form-label">N° de SIREN :</div><input type="text" class="form-input"></div>
                    <div class="form-row"><div class="form-label">Raison Sociale :</div><input type="text" class="form-input"></div>
                    <div class="form-row"><div class="form-label">N° de dossier :</div><input type="text" class="form-input"></div>
                </div>
                <div class="action-panel">
                    <div class="info-box">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        A noter que si aucun numéro de SIREN ou aucune raison sociale, ceci traitera pour toutes les entreprises disponibles.
                    </div>
                    <button class="btn-pink"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
                </div>
            </div>

            <div class="dark-card results-section">
                <div class="results-header">
                    <span>Base de données globale des impayés</span>
                    <a href="?page=impayes_graph" class="btn-pink" style="padding: 8px 15px; font-size:12px; width:auto; flex-grow:0;">Voir Graphique</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° SIREN</th>
                            <th>Date Vente</th>
                            <th>Date Remise</th>
                            <th>N° Carte</th>
                            <th>Réseau</th>
                            <th>N° Impayé</th>
                            <th>Devise</th>
                            <th>Montant</th>
                            <th>Libellé Impayé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($impayes as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['num_siren']) ?></td>
                            <td><?= htmlspecialchars($row['date_vente']) ?></td>
                            <td><?= htmlspecialchars($row['date_remise']) ?></td>
                            <td><?= htmlspecialchars($row['num_carte_masquee']) ?></td>
                            <td><?= htmlspecialchars($row['reseau']) ?></td>
                            <td><?= htmlspecialchars($row['num_dossier_impaye']) ?></td>
                            <td>EUR</td>
                            <td><?= number_format($row['montant_impaye'], 2, ',', ' ') ?> €</td>
                            <td style="font-family: inherit;"><?= htmlspecialchars($row['libelle_impaye']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'impayes_graph'): ?>
            <!-- ================= VUE IMPAYÉS (GRAPHIQUE) ================= -->
            <h1>Impayés - Graphique</h1>
            <div class="top-section">
                <div class="dark-card search-form">
                    <div style="padding: 15px;">Filtrez la période souhaitée pour observer les évolutions d'impayés globaux.</div>
                </div>
                <div class="action-panel">
                    <a href="?page=impayes" class="btn-pink" style="display:flex; align-items:center; text-align:center;">Retour au Tableau de données</a>
                </div>
            </div>

            <div class="dark-card chart-section">
                <div class="chart-header">Annonces récentes</div>
                <div class="chart-container">
                    <div class="y-title">Montant</div>
                    <div class="grid-line" style="bottom: 80%;"><span class="y-label">4 000</span></div>
                    <div class="grid-line" style="bottom: 60%;"><span class="y-label">3 000</span></div>
                    <div class="grid-line" style="bottom: 40%;"><span class="y-label">2 000</span></div>
                    <div class="grid-line" style="bottom: 20%;"><span class="y-label">1 000</span></div>

                    <div class="bar-group"><div class="bar" style="height: 75%;"></div><div class="x-label">Septembre</div></div>
                    <div class="bar-group"><div class="bar" style="height: 48%;"></div><div class="x-label">Octobre</div></div>
                    <div class="bar-group"><div class="bar" style="height: 25%;"></div><div class="x-label">Novembre</div></div>
                    <div class="bar-group"><div class="bar" style="height: 90%;"></div><div class="x-label">Décembre</div></div>
                </div>
            </div>

        <?php elseif ($page === 'comptes'): ?>
            <h1>Gestion des comptes</h1>
            
            <?php if ($success_msg): ?>
                <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-header">
                    <h2>Nouveau compte Product Owner</h2>
                    <p>Remplissez les informations ci-dessous pour configurer un accès restreint aux fonctionnalités d'administration.</p>
                </div>

                <form method="POST" action="?page=comptes">
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
                            <select disabled>
                                <option>PRODUCT OWNER</option>
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
        <?php endif; ?>

        <?php if (in_array($page, ['tresorerie', 'remises', 'impayes'])): ?>
        <div class="dark-card export-bar" style="margin-top: 20px;">
            <div class="export-title">Exporter au format :</div>
            <div class="radio-group">
                <label class="radio-label"><input type="radio" name="format"> XLS</label>
                <label class="radio-label"><input type="radio" name="format"> CSV</label>
                <label class="radio-label"><input type="radio" name="format"> PDF</label>
            </div>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 16 16 12 12 8"></polyline><line x1="8" y1="12" x2="16" y2="12"></line></svg>
        </div>
        <?php endif; ?>

    </main>
</body>
</html>