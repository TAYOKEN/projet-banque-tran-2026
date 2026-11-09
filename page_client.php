<?php

require 'connection.php';

$page = $_GET['page'] ?? 'tresorerie';

// Vu que ya pas de système d'utilisateur pour l'instant on force le truc avc l'id mais je vais changer ça après
$stmt_user = $pdo->query("SELECT raison_sociale FROM client WHERE id_client = 1");
$nom_utilisateur = $stmt_user->fetchColumn() ?: "Mode\nParisienne";

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
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail Monétique</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: #f4f5f7; color: #333; display: flex; height: 100vh; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar { width: 100px; background-color: #161618; color: white; display: flex; flex-direction: column; align-items: center; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .user-profile { display: flex; flex-direction: column; align-items: center; margin-bottom: 40px; }
        .avatar { width: 60px; height: 60px; border-radius: 50%; border: 2px solid white; display: flex; justify-content: center; align-items: center; margin-bottom: 10px; background-color: #7c3aed; }
        .user-name { font-size: 11px; text-align: center; color: #ccc; line-height: 1.3; }
        .nav-menu { display: flex; flex-direction: column; gap: 25px; width: 100%; align-items: center; flex-grow: 1; }
        .nav-item { display: flex; flex-direction: column; align-items: center; color: #888; font-size: 11px; text-decoration: none; width: 80%; padding: 10px 0; border-radius: 12px; }
        .nav-item.active { background-color: #242b3b; color: #8fa0b5; }
        .nav-item svg { width: 24px; height: 24px; margin-bottom: 5px; fill: currentColor; }
        .back-btn { width: 50px; height: 50px; border-radius: 50%; background-color: #ef4444; display: flex; justify-content: center; align-items: center; cursor: pointer; border: none; margin-top: auto; }

        /* Contenu Principal */
        .main-content { flex-grow: 1; padding: 40px; overflow-y: auto; }
        h1 { font-size: 36px; font-weight: 500; letter-spacing: 2px; margin-bottom: 30px; color: #0f172a; }
        .dark-card { background-color: #242b3b; border-radius: 20px; color: white; padding: 25px 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        /* Filtres et Top Section */
        .top-section { display: flex; gap: 20px; margin-bottom: 20px; align-items: stretch; }
        .filter-card { display: flex; align-items: center; padding: 20px 30px; border-radius: 25px; flex-grow: 1; }
        .date-filters { display: flex; flex-direction: column; gap: 15px; padding-right: 30px; }
        .filter-row { display: flex; align-items: center; gap: 20px; }
        .form-label { font-size: 14px; letter-spacing: 1px; width: 120px; }
        .date-selects { display: flex; gap: 10px; }
        .date-selects select { background-color: transparent; border: 1px solid #475569; color: white; padding: 5px 10px; border-radius: 5px; appearance: none; cursor: pointer; }
        .vertical-divider { width: 1px; background-color: rgba(255, 255, 255, 0.2); height: 60px; margin: 0 30px; }
        .dossier-filter { display: flex; flex-direction: column; gap: 10px; flex-grow: 1; }
        .dossier-input { background-color: #1a1f2b; border: none; border-radius: 8px; height: 30px; width: 100%; max-width: 250px; color: white; padding: 0 10px; }
        .btn-search-purple { background-color: #7c3aed; color: white; border: none; border-radius: 20px; width: 110px; display: flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-purple { background-color: #8b5cf6; color: white; border: none; border-radius: 12px; padding: 15px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }

        /* Tableaux */
        .results-section { margin-bottom: 20px; padding-top: 30px; }
        .results-header { font-size: 15px; margin-bottom: 25px; letter-spacing: 1px; font-weight: 500; display: flex; justify-content: space-between; }
        .data-table { width: 100%; border-collapse: collapse; text-align: center; }
        .data-table th { font-weight: 600; padding-bottom: 10px; font-size: 13px; border-bottom: 4px solid white; letter-spacing: 1px; }
        .data-table td { padding: 12px 5px; font-size: 13px; font-family: monospace; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .data-table th:not(:first-child), .data-table td:not(:first-child) { border-left: 4px solid white; }
        .data-table td:not(:first-child) { border-left: none; }
        
        /* Tableaux imbriqués (Remises) */
        .main-row td { cursor: pointer; }
        .main-row td:first-child { display: flex; align-items: center; justify-content: center; gap: 10px; border-left: none !important;}
        .expanded-content { background-color: #1f2533; padding: 15px 0 15px 40px; display: none; } 
        .nested-table { width: 100%; border-collapse: collapse; text-align: center; }
        .nested-table th { font-weight: 600; padding-bottom: 8px; font-size: 12px; border-bottom: 3px solid white; border-left: 4px solid white; }
        .nested-table th.icon-header { border-left: none; width: 40px; }
        .nested-table td { padding: 8px 0; font-size: 12px; font-family: monospace; color: #d1d5db; }

        /* Graphiques (Impayés) */
        .chart-container { position: relative; height: 250px; margin-left: 40px; margin-right: 20px; border-left: 1px solid rgba(255, 255, 255, 0.3); border-bottom: 1px solid rgba(255, 255, 255, 0.3); display: flex; align-items: flex-end; justify-content: space-around; padding: 0 40px; }
        .grid-line { position: absolute; left: -5px; right: 0; border-top: 1px solid rgba(255, 255, 255, 0.1); z-index: 1; }
        .y-label { position: absolute; left: -40px; font-size: 10px; color: #ccc; transform: translateY(-50%); }
        .bar-group { display: flex; flex-direction: column; align-items: center; z-index: 2; width: 20%; height: 100%; justify-content: flex-end;}
        .bar { background-color: #8b5cf6; width: 100%; border-top-left-radius: 15px; border-top-right-radius: 15px; }
        .x-label { margin-top: 15px; font-size: 10px; color: #ccc; white-space: nowrap; position: absolute; bottom: -25px;}

        /* Export Bar */
        .export-bar { display: flex; align-items: center; padding: 15px 30px; border-radius: 20px; }
        .export-title { margin-right: 40px; font-weight: 600; letter-spacing: 1px; font-size: 14px; }
        .radio-group { display: flex; gap: 30px; flex-grow: 1; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="user-profile">
            <div class="avatar">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <span class="user-name"><?= nl2br(htmlspecialchars($nom_utilisateur)) ?></span>
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
        </nav>

        <button class="back-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </button>
    </aside>

    <main class="main-content">
        
        <?php if ($page === 'tresorerie'): ?>
            <h1>Vos Annonces de Trésorerie</h1>
            <div class="top-section">
                <div class="dark-card filter-card">
                    <div class="form-label">Filtre Date :</div>
                    <div class="date-selects">
                        <select><option>Tous les mois</option></select>
                    </div>
                </div>
                <button class="btn-search-purple"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
            </div>

            <div class="dark-card results-section">
                <div class="results-header">État Actuel des Comptes</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° de SIREN</th>
                            <th>Raison Sociale</th>
                            <th>Transactions</th>
                            <th>Devise</th>
                            <th>Solde Actuel</th>
                            <th>Statut</th>
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
                            <td style="color: <?= $row['statut_visuel'] === 'ALERTE_ROUGE' ? '#ef4444' : '#10b981' ?>;">
                                <?= htmlspecialchars($row['statut_visuel']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tresoreries)): ?>
                            <tr><td colspan="6">Aucune donnée trouvée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'remises'): ?>
            <h1>Vos Remises</h1>
            <div class="top-section">
                <div class="dark-card filter-card">
                    <div class="form-label">Période :</div>
                    <div class="date-selects"><select><option>Toutes</option></select></div>
                </div>
                <button class="btn-search-purple"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
            </div>

            <div class="dark-card results-section">
                <div class="results-header">Historique des Remises</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° SIREN</th>
                            <th>Raison Sociale</th>
                            <th>N° Remise</th>
                            <th>Date Traitement</th>
                            <th>Transactions</th>
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
                            <td><?= number_format($row['montant_total'], 2, ',', ' ') ?> €</td>
                            <td><?= htmlspecialchars($row['sens'] === '+' ? 'Crédit' : 'Débit') ?></td>
                        </tr>
                        <tr>
                            <td colspan="7" style="padding: 0; border: none;">
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
            <h1>Vos Impayés</h1>
            <div class="top-section">
                <div class="dark-card filter-card">
                    <div class="dossier-filter">
                        <div class="form-label">N° de dossier :</div>
                        <input type="text" class="dossier-input" placeholder="DOS01...">
                    </div>
                </div>
                <button class="btn-search-purple"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
            </div>

            <div class="dark-card results-section">
                <div class="results-header">
                    <span>Liste des dossiers impayés</span>
                    <a href="?page=impayes_graph" class="btn-purple" style="padding: 5px 15px; font-size:12px;">Voir les graphiques</a>
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
                            <td><?= number_format($row['montant_impaye'], 2, ',', ' ') ?> €</td>
                            <td style="font-family: inherit;"><?= htmlspecialchars($row['libelle_impaye']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'impayes_graph'): ?>
            <h1>Impayés - Graphique</h1>
            <div class="top-section">
                <div class="dark-card filter-card">
                    <div class="form-label">Filtre Rapide :</div>
                    <div class="date-selects"><select><option>4 derniers mois</option></select></div>
                </div>
                <a href="?page=impayes" class="btn-purple" style="display:flex; align-items:center;">Retour au Tableau</a>
            </div>

            <div class="dark-card results-section">
                <div class="results-header">Évolution des Impayés</div>
                <div class="chart-container">
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
        <?php endif; ?>

        <div class="dark-card export-bar">
            <div class="export-title">Exporter au format :</div>
            <div class="radio-group">
                <label><input type="radio" name="format"> XLS</label>
                <label><input type="radio" name="format"> CSV</label>
                <label><input type="radio" name="format"> PDF</label>
            </div>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 16 16 12 12 8"></polyline><line x1="8" y1="12" x2="16" y2="12"></line></svg>
        </div>

    </main>
</body>
</html>