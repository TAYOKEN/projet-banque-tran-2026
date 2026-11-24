<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user']['profil'] !== 'client') {
    header('Location: ' . ($_SESSION['user']['profil'] === 'product_owner' ? 'page_po.php' : 'page_admin.php'));
    exit;
}

require 'connection.php';
require 'report_data.php';

$page = $_GET['page'] ?? 'tresorerie';
if (!in_array($page, ['tresorerie', 'remises', 'impayes', 'impayes_graph'], true)) {
    $page = 'tresorerie';
}

$user_id = $_SESSION['user']['id'];
$user_profil = $_SESSION['user']['profil'];
$user_id_client = $_SESSION['user']['id_client'];

$nom_utilisateur = htmlspecialchars($_SESSION['user']['prenom'] . " " . $_SESSION['user']['nom']);

$filters = $_GET;
$reportPage = $page === 'impayes_graph' ? 'impayes' : $page;
$tresoreries = [];
$remises = [];
$details_remises = [];
$impayes = [];
$reportTotal = 0;
$reportPageNumber = 1;
$reportPageSize = 10;
$reportPageSizeInvalid = false;
$reportTotalPages = 1;
$impayesGraph = null;

if (in_array($reportPage, ['tresorerie', 'remises', 'impayes'], true)) {
    $reportData = fetchReportPage($pdo, $reportPage, $filters, (int) $user_id_client);
    $reportRows = $reportData['rows'];
    $reportTotal = $reportData['total'];
    $reportPageNumber = $reportData['page'];
    $reportPageSize = $reportData['page_size'];
    $reportPageSizeInvalid = $reportData['page_size_invalid'];
    $reportTotalPages = $reportData['total_pages'];
    if ($reportPage === 'tresorerie') {
        $tresoreries = $reportRows;
    } elseif ($reportPage === 'remises') {
        $remises = $reportRows;
        if ($remises) {
            foreach (fetchRemiseDetails($pdo, array_column($remises, 'id_remise')) as $det) {
                $details_remises[$det['num_remise']][] = $det;
            }
        }
    } else {
        $impayes = $reportRows;
    }
}
if ($page === 'impayes_graph') {
    $impayesGraph = fetchImpayesGraphData($pdo, $filters, (int) $user_id_client);
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
        
        .sidebar { width: 100px; background-color: #161618; color: white; display: flex; flex-direction: column; align-items: center; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .user-profile { display: flex; flex-direction: column; align-items: center; margin-bottom: 40px; }
        .avatar { width: 60px; height: 60px; border-radius: 50%; border: 2px solid white; display: flex; justify-content: center; align-items: center; margin-bottom: 10px; background-color: #7c3aed; }
        .user-name { font-size: 11px; text-align: center; color: #ccc; line-height: 1.3; }
        .nav-menu { display: flex; flex-direction: column; gap: 25px; width: 100%; align-items: center; flex-grow: 1; }
        .nav-item { display: flex; flex-direction: column; align-items: center; color: #888; font-size: 11px; text-decoration: none; width: 80%; padding: 10px 0; border-radius: 12px; }
        .nav-item.active { background-color: #242b3b; color: #8fa0b5; }
        .nav-item svg { width: 24px; height: 24px; margin-bottom: 5px; fill: currentColor; }
        .back-btn { width: 50px; height: 50px; border-radius: 50%; background-color: #ef4444; display: flex; justify-content: center; align-items: center; cursor: pointer; border: none; margin-top: auto; }

        .main-content { flex-grow: 1; padding: 40px; overflow-y: auto; }
        h1 { font-size: 36px; font-weight: 500; letter-spacing: 2px; margin-bottom: 30px; color: #0f172a; }
        .dark-card { background-color: #242b3b; border-radius: 20px; color: white; padding: 25px 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
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

        .results-section { margin-bottom: 20px; padding-top: 30px; background: #fff; color: #1f2937; }
        .results-header { font-size: 15px; margin-bottom: 25px; letter-spacing: 1px; font-weight: 500; display: flex; justify-content: space-between; }
        .table-scroll { overflow-x: auto; border: 1px solid #dbe2ea; border-radius: 12px; }
        .data-table { width: 100%; min-width: 760px; border-collapse: collapse; text-align: left; }
        .data-table th { background: #eef2f7; color: #334155; font-weight: 600; padding: 13px 15px; font-size: 12px; border-bottom: 1px solid #cbd5e1; letter-spacing: .3px; }
        .data-table td { padding: 12px 15px; font-size: 13px; color: #334155; border-bottom: 1px solid #e5e7eb; }
        .data-table tbody tr:nth-child(even) { background: #f8fafc; }
        .data-table tbody tr:hover { background: #f1f5f9; }
        .data-table tbody tr:last-child td { border-bottom: 0; }
        .filter-controls { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; width: 100%; }
        .filter-field { display: flex; flex-direction: column; gap: 6px; min-width: 150px; }
        .filter-field label { color: #cbd5e1; font-size: 12px; }
        .form-input, .sort-select { width: 100%; min-width: 150px; height: 38px; padding: 0 10px; border: 1px solid #475569; border-radius: 8px; background: #1a1f2b; color: #fff; }
        .sort-select { min-width: 170px; }
        .filter-submit { min-height: 42px; padding: 0 18px; border: 0; border-radius: 8px; background: #7c3aed; color: white; font-weight: 600; cursor: pointer; }
        .table-pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: -4px 0 18px; color: #64748b; font-size: 13px; }
        .pagination-links { display: flex; align-items: center; gap: 8px; }
        .pagination-link { color: #334155; text-decoration: none; background: #fff; border: 1px solid #cbd5e1; border-radius: 7px; padding: 7px 12px; }
        .pagination-link:hover { background: #eef2f7; }
        .pagination-link.disabled { color: #94a3b8; pointer-events: none; }
        .negative-amount { color: #dc2626 !important; font-weight: 700; }
        .amount-band-low, .amount-band-medium, .amount-band-high, .amount-band-critical { display: inline-block; padding: 4px 8px; border-radius: 6px; font-weight: 700; white-space: nowrap; }
        .amount-band-low { color: #1d4ed8; background: #dbeafe; }
        .amount-band-medium { color: #b45309; background: #fef3c7; }
        .amount-band-high { color: #c2410c; background: #ffedd5; }
        .amount-band-critical { color: #b91c1c; background: #fee2e2; }
        .amount-legend { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; font-size: 12px; color: #475569; }
        .amount-legend span { padding: 5px 8px; border-radius: 6px; }
        
        .main-row td { cursor: pointer; }
        .main-row td:first-child { display: flex; align-items: center; justify-content: center; gap: 10px; border-left: none !important;}
        .expanded-content { background-color: #1f2533; padding: 15px 0 15px 40px; display: none; } 
        .nested-table { width: 100%; border-collapse: collapse; text-align: left; background: #fff; }
        .nested-table th { background: #eef2f7; color: #334155; font-weight: 600; padding: 10px; font-size: 12px; border-bottom: 1px solid #cbd5e1; white-space: nowrap; }
        .nested-table th.icon-header { width: 40px; }
        .nested-table td { padding: 9px 10px; font-size: 12px; color: #334155; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }

        .chart-container { position: relative; height: 250px; margin-left: 40px; margin-right: 20px; border-left: 1px solid rgba(255, 255, 255, 0.3); border-bottom: 1px solid rgba(255, 255, 255, 0.3); display: flex; align-items: flex-end; justify-content: space-around; padding: 0 40px; }
        .grid-line { position: absolute; left: -5px; right: 0; border-top: 1px solid rgba(255, 255, 255, 0.1); z-index: 1; }
        .y-label { position: absolute; left: -40px; font-size: 10px; color: #ccc; transform: translateY(-50%); }
        .bar-group { display: flex; flex-direction: column; align-items: center; z-index: 2; width: 20%; height: 100%; justify-content: flex-end;}
        .bar { background-color: #8b5cf6; width: 100%; border-top-left-radius: 15px; border-top-right-radius: 15px; }
        .x-label { margin-top: 15px; font-size: 10px; color: #ccc; white-space: nowrap; position: absolute; bottom: -25px;}

        .export-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; padding: 16px 20px; border-radius: 14px; gap: 16px; }
        .export-title { font-weight: 600; letter-spacing: .3px; font-size: 14px; }
        .export-links { display: flex; gap: 10px; flex-wrap: wrap; }
        .export-link { color: white; text-decoration: none; border: 1px solid #64748b; border-radius: 8px; padding: 9px 16px; font-size: 13px; transition: background .2s; }
        .export-link:hover { background: #374151; }
        .main-row td:first-child { display: table-cell; }
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

        <a href="logout.php" class="back-btn" style="text-decoration: none;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
    </aside>

    <main class="main-content">
        
        <?php if ($page === 'tresorerie'): ?>
            <h1>Vos Annonces de Trésorerie</h1>
            <form method="get" class="top-section">
                <input type="hidden" name="page" value="tresorerie">
                <div class="dark-card filter-card">
                    <div class="filter-controls">
                        <div class="filter-field"><label for="date_valeur">Date de valeur</label><input id="date_valeur" type="date" name="date_valeur" class="form-input" value="<?= htmlspecialchars(reportInput($filters, 'date_valeur')) ?>"></div>
                        <div class="filter-field"><label for="sort">Trier par</label><select id="sort" name="sort" class="sort-select"><?php foreach (reportSortOptions($reportPage) as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($filters['sort'] ?? 'balance') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
                        <div class="filter-field"><label for="direction">Ordre</label><select id="direction" name="direction" class="sort-select"><option value="asc" <?= ($filters['direction'] ?? 'asc') === 'asc' ? 'selected' : '' ?>>Croissant (0→9 / A→Z)</option><option value="desc" <?= ($filters['direction'] ?? '') === 'desc' ? 'selected' : '' ?>>Décroissant (9→0 / Z→A)</option></select></div>
                        <div class="filter-field"><label for="page_size">Lignes par page</label><input id="page_size" type="number" min="1" step="1" required name="page_size" class="sort-select" inputmode="numeric" value="<?= $reportPageSize ?>"></div>
                    </div>
                </div>
                <button type="submit" class="filter-submit">Appliquer</button>
            </form>

            <div class="dark-card results-section">
                <div class="results-header"><?= htmlspecialchars(treasuryBalanceLabel($filters) === 'Solde actuel' ? 'État Actuel des Comptes' : 'État des Comptes — ' . treasuryBalanceLabel($filters)) ?></div>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° de SIREN</th>
                            <th>Raison Sociale</th>
                            <th>Transactions</th>
                            <th>Devise</th>
                            <th><?= htmlspecialchars(treasuryBalanceLabel($filters)) ?></th>
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
                            <td class="<?= (float) $row['montant_total_solde'] < 0 ? 'negative-amount' : '' ?>"><?= number_format($row['montant_total_solde'], 2, ',', ' ') ?> €</td>
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
            </div>

        <?php elseif ($page === 'remises'): ?>
            <h1>Vos Remises</h1>
            <form method="get" class="top-section">
                <input type="hidden" name="page" value="remises">
                <div class="dark-card filter-card">
                    <div class="filter-controls">
                        <div class="filter-field"><label for="date_debut">Du</label><input id="date_debut" type="date" name="date_debut" class="form-input" value="<?= htmlspecialchars($filters['date_debut'] ?? '') ?>"></div>
                        <div class="filter-field"><label for="date_fin">Au</label><input id="date_fin" type="date" name="date_fin" class="form-input" value="<?= htmlspecialchars($filters['date_fin'] ?? '') ?>"></div>
                        <div class="filter-field"><label for="remise">N° de remise</label><input id="remise" name="remise" class="form-input" value="<?= htmlspecialchars($filters['remise'] ?? '') ?>" placeholder="REM0000001"></div>
                        <div class="filter-field"><label for="sort">Trier par</label><select id="sort" name="sort" class="sort-select"><?php foreach (reportSortOptions($reportPage) as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($filters['sort'] ?? 'date') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
                        <div class="filter-field"><label for="direction">Ordre</label><select id="direction" name="direction" class="sort-select"><option value="asc" <?= ($filters['direction'] ?? 'asc') === 'asc' ? 'selected' : '' ?>>Croissant (0→9 / A→Z)</option><option value="desc" <?= ($filters['direction'] ?? '') === 'desc' ? 'selected' : '' ?>>Décroissant (9→0 / Z→A)</option></select></div>
                        <div class="filter-field"><label for="page_size">Lignes par page</label><input id="page_size" type="number" min="1" step="1" required name="page_size" class="sort-select" inputmode="numeric" value="<?= $reportPageSize ?>"></div>
                    </div>
                </div>
                <button type="submit" class="filter-submit">Appliquer</button>
            </form>

            <div class="dark-card results-section">
                <div class="results-header">Historique des Remises — <?= $reportTotal ?> résultat(s)</div>
                <div class="table-scroll">
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
                            <td class="<?= (float) $row['montant_total'] < 0 ? 'negative-amount' : '' ?>"><?= number_format($row['montant_total'], 2, ',', ' ') ?> €</td>
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
                                                    <td class="<?= (float) $det['montant'] < 0 ? 'negative-amount' : '' ?>"><?= number_format($det['montant'], 2, ',', ' ') ?> €</td>
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
                                        <?php if (empty($remises)): ?><tr><td colspan="7">Aucune remise trouvée.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

        <?php elseif ($page === 'impayes'): ?>
            <h1>Vos Impayés</h1>
            <form method="get" class="top-section">
                <input type="hidden" name="page" value="impayes">
                <div class="dark-card filter-card">
                    <div class="filter-controls">
                        <div class="filter-field"><label for="dossier">N° de dossier</label><input id="dossier" type="text" name="dossier" class="form-input" value="<?= htmlspecialchars($filters['dossier'] ?? '') ?>" placeholder="DOS01"></div>
                        <div class="filter-field"><label for="date_debut">Du</label><input id="date_debut" type="date" name="date_debut" class="form-input" value="<?= htmlspecialchars($filters['date_debut'] ?? '') ?>"></div>
                        <div class="filter-field"><label for="date_fin">Au</label><input id="date_fin" type="date" name="date_fin" class="form-input" value="<?= htmlspecialchars($filters['date_fin'] ?? '') ?>"></div>
                        <div class="filter-field"><label for="sort">Trier par</label><select id="sort" name="sort" class="sort-select"><?php foreach (reportSortOptions($reportPage) as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($filters['sort'] ?? 'amount') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
                        <div class="filter-field"><label for="direction">Ordre</label><select id="direction" name="direction" class="sort-select"><option value="asc" <?= ($filters['direction'] ?? '') === 'asc' ? 'selected' : '' ?>>Croissant (0→9 / A→Z)</option><option value="desc" <?= ($filters['direction'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>Décroissant (9→0 / Z→A)</option></select></div>
                        <div class="filter-field"><label for="page_size">Lignes par page</label><input id="page_size" type="number" min="1" step="1" required name="page_size" class="sort-select" inputmode="numeric" value="<?= $reportPageSize ?>"></div>
                    </div>
                </div>
                <button type="submit" class="filter-submit">Appliquer</button>
            </form>

            <div class="dark-card results-section">
                <div class="results-header">
                    <span>Liste des dossiers impayés</span>
                    <a href="?page=impayes_graph" class="btn-purple" style="padding: 5px 15px; font-size:12px;">Voir les graphiques</a>
                </div>
                <div class="amount-legend">
                    <span class="amount-band-low">Moins de 100 €</span>
                    <span class="amount-band-medium">100 à 199,99 €</span>
                    <span class="amount-band-high">200 à 299,99 €</span>
                    <span class="amount-band-critical">300 € et plus</span>
                </div>
                <div class="table-scroll"><table class="data-table">
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
                            <td><span class="<?= impayeAmountBand((float) $row['montant_impaye']) ?>"><?= number_format($row['montant_impaye'], 2, ',', ' ') ?> €</span></td>
                            <td style="font-family: inherit;"><?= htmlspecialchars($row['libelle_impaye']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($impayes)): ?><tr><td colspan="8">Aucun impayé trouvé.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

        <?php elseif ($page === 'impayes_graph'): ?>
            <?php include 'impayes_graph_view.php'; ?>
        <?php endif; ?>

        <?php if (in_array($page, ['tresorerie', 'remises', 'impayes'], true)): ?>
            <?php $firstResult = $reportTotal === 0 ? 0 : (($reportPageNumber - 1) * $reportPageSize + 1); ?>
            <?php $lastResult = min($reportTotal, $reportPageNumber * $reportPageSize); ?>
            <div class="table-pagination">
                <span>Affichage de <?= $firstResult ?> à <?= $lastResult ?> sur <?= $reportTotal ?> résultat(s)</span>
                <?php if ($reportPageSizeInvalid): ?><span role="alert">Nombre de lignes invalide : 10 lignes par page ont été appliquées.</span><?php endif; ?>
                <div class="pagination-links">
                    <?php if ($reportPageNumber > 1): ?>
                        <a class="pagination-link" href="?<?= htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page, 'page_num' => $reportPageNumber - 1, 'page_size' => $reportPageSize])), ENT_QUOTES, 'UTF-8') ?>">Précédent</a>
                    <?php else: ?>
                        <span class="pagination-link disabled">Précédent</span>
                    <?php endif; ?>
                    <span>Page <?= $reportPageNumber ?> / <?= $reportTotalPages ?></span>
                    <?php if ($reportPageNumber < $reportTotalPages): ?>
                        <a class="pagination-link" href="?<?= htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page, 'page_num' => $reportPageNumber + 1, 'page_size' => $reportPageSize])), ENT_QUOTES, 'UTF-8') ?>">Suivant</a>
                    <?php else: ?>
                        <span class="pagination-link disabled">Suivant</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array($page, ['tresorerie', 'remises', 'impayes'], true)): ?>
        <div class="dark-card export-bar">
            <div class="export-title"><?= $page === 'remises' ? 'Exporter le tableau principal :' : 'Exporter les résultats filtrés :' ?></div>
            <div class="export-links">
                <?php foreach (['xls' => 'Excel (XLS)', 'csv' => 'CSV', 'pdf' => 'PDF'] as $format => $label): ?>
                    <a class="export-link" href="export.php?<?= htmlspecialchars(http_build_query(array_merge($filters, ['page' => $reportPage, 'format' => $format])), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($page === 'remises'): ?>
        <div class="dark-card export-bar" style="margin-top: 10px;">
            <div class="export-title">Exporter les détails des remises filtrées :</div>
            <div class="export-links">
                <?php foreach (['xls' => 'Excel (XLS)', 'csv' => 'CSV', 'pdf' => 'PDF'] as $format => $label): ?>
                    <a class="export-link" href="export.php?<?= htmlspecialchars(http_build_query(array_merge($filters, ['page' => 'remises', 'detail' => '1', 'format' => $format])), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </main>
</body>
</html>