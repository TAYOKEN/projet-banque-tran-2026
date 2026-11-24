<?php
$chartType = reportInput($filters, 'chart_type', 'bar');
if (!in_array($chartType, ['bar', 'line'], true)) {
    $chartType = 'bar';
}
$months = $impayesGraph['months'];
$motifs = $impayesGraph['motifs'];
$chartMaximum = 0.0;
$totalTurnover = 0.0;
$totalUnpaid = 0.0;
foreach ($months as $month) {
    $chartMaximum = max($chartMaximum, $month['chiffre_affaires'], $month['montant_impayes']);
    $totalTurnover += $month['chiffre_affaires'];
    $totalUnpaid += $month['montant_impayes'];
}
$chartMaximum = max(1, $chartMaximum);
$motifTotal = array_sum(array_map(static fn ($motif) => (float) $motif['montant_total'], $motifs));
$palette = ['#7c3aed', '#ec4899', '#0ea5e9', '#f59e0b', '#10b981', '#ef4444', '#6366f1', '#64748b'];
$pieStops = [];
$pieStart = 0.0;
foreach ($motifs as $index => $motif) {
    $share = $motifTotal > 0 ? (float) $motif['montant_total'] / $motifTotal * 100 : 0;
    $pieEnd = $pieStart + $share;
    $pieStops[] = $palette[$index % count($palette)] . ' ' . round($pieStart, 2) . '% ' . round($pieEnd, 2) . '%';
    $pieStart = $pieEnd;
}
$pieBackground = $pieStops ? 'conic-gradient(' . implode(', ', $pieStops) . ')' : '#e2e8f0';
$caPoints = [];
$impayePoints = [];
$monthCount = count($months);
foreach ($months as $index => $month) {
    $x = 45 + ($monthCount > 1 ? 910 * $index / ($monthCount - 1) : 455);
    $caY = 280 - ($month['chiffre_affaires'] / $chartMaximum * 235);
    $impayeY = 280 - ($month['montant_impayes'] / $chartMaximum * 235);
    $caPoints[] = round($x, 2) . ',' . round($caY, 2);
    $impayePoints[] = round($x, 2) . ',' . round($impayeY, 2);
}
?>

<style>
    .graph-filters { flex-wrap: wrap; }
    .graph-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 20px; }
    .graph-kpi { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
    .graph-kpi span { display: block; color: #64748b; font-size: 12px; margin-bottom: 6px; }
    .graph-kpi strong { color: #0f172a; font-size: 20px; }
    .chart-legend { display: flex; gap: 18px; flex-wrap: wrap; color: #475569; font-size: 13px; margin-bottom: 12px; }
    .legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
    .chart-scroll { overflow-x: auto; padding: 8px 0; }
    .monthly-bars { display: flex; align-items: end; gap: 14px; min-height: 300px; min-width: max-content; padding: 10px 12px 0; border-bottom: 1px solid #cbd5e1; }
    .month-column { display: flex; flex-direction: column; align-items: center; gap: 8px; width: 62px; color: #64748b; font-size: 11px; }
    .bar-pair { display: flex; align-items: end; gap: 5px; height: 250px; }
    .chart-bar { width: 20px; min-height: 2px; border-radius: 5px 5px 0 0; }
    .chart-bar-turnover { background: #0ea5e9; }
    .chart-bar-unpaid { background: #ef4444; }
    .line-chart { width: 100%; min-width: 600px; height: 330px; }
    .line-chart .grid { stroke: #e2e8f0; stroke-width: 1; }
    .line-chart .ca-line { fill: none; stroke: #0ea5e9; stroke-width: 3; }
    .line-chart .unpaid-line { fill: none; stroke: #ef4444; stroke-width: 3; }
    .month-labels { display: flex; justify-content: space-between; gap: 8px; min-width: max-content; color: #64748b; font-size: 11px; }
    .month-labels span { min-width: 44px; text-align: center; }
    .motif-layout { display: flex; align-items: center; gap: 28px; flex-wrap: wrap; }
    .motif-pie { width: 210px; height: 210px; border-radius: 50%; background: <?= htmlspecialchars($pieBackground, ENT_QUOTES, 'UTF-8') ?>; flex: 0 0 auto; border: 1px solid #e2e8f0; }
    .motif-legend { flex: 1; min-width: 250px; }
    .motif-legend-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #e5e7eb; color: #334155; font-size: 13px; }
    .motif-legend-item:last-child { border-bottom: 0; }
    .motif-swatch { width: 12px; height: 12px; border-radius: 3px; flex: 0 0 auto; }
    .print-button { border: 0; border-radius: 8px; padding: 10px 16px; background: #334155; color: #fff; font-weight: 600; cursor: pointer; }
    .chart-empty { color: #64748b; text-align: center; padding: 28px; }
    @media print {
        .sidebar, .graph-filters, .print-control { display: none !important; }
        body { display: block; height: auto; overflow: visible; background: #fff; }
        .main-content { padding: 0; overflow: visible; }
        .dark-card { background: #fff !important; color: #111827 !important; box-shadow: none; border: 1px solid #d1d5db; }
        .results-section { break-inside: avoid; }
    }
</style>

<h1>Statistiques des impayés</h1>
<form method="get" class="top-section graph-filters">
    <input type="hidden" name="page" value="impayes_graph">
    <div class="dark-card filter-card">
        <div class="filter-controls">
            <div class="filter-field">
                <label for="periode">Période glissante</label>
                <select id="periode" name="periode" class="sort-select">
                    <option value="4" <?= (string) $impayesGraph['periode'] === '4' ? 'selected' : '' ?>>4 mois</option>
                    <option value="12" <?= (string) $impayesGraph['periode'] === '12' ? 'selected' : '' ?>>12 mois</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="date_debut">Ou du</label>
                <input id="date_debut" type="date" name="date_debut" class="form-input" value="<?= htmlspecialchars(reportInput($filters, 'date_debut')) ?>">
            </div>
            <div class="filter-field">
                <label for="date_fin">Au</label>
                <input id="date_fin" type="date" name="date_fin" class="form-input" value="<?= htmlspecialchars(reportInput($filters, 'date_fin')) ?>">
            </div>
            <div class="filter-field">
                <label for="chart_type">Type de graphique</label>
                <select id="chart_type" name="chart_type" class="sort-select">
                    <option value="bar" <?= $chartType === 'bar' ? 'selected' : '' ?>>Histogramme</option>
                    <option value="line" <?= $chartType === 'line' ? 'selected' : '' ?>>Courbe</option>
                </select>
            </div>
        </div>
    </div>
    <button type="submit" class="filter-submit">Actualiser</button>
    <a href="?page=impayes" class="btn-purple">Retour au tableau</a>
</form>

<div class="dark-card results-section">
    <div class="results-header">
        <span>Évolution mensuelle — <?= htmlspecialchars(date('d/m/Y', strtotime($impayesGraph['date_debut']))) ?> au <?= htmlspecialchars(date('d/m/Y', strtotime($impayesGraph['date_fin']))) ?></span>
        <button type="button" class="print-button print-control" onclick="window.print()">Imprimer / enregistrer en PDF</button>
    </div>
    <div class="graph-kpis">
        <div class="graph-kpi"><span>Chiffre d’affaires positif</span><strong><?= number_format($totalTurnover, 2, ',', ' ') ?> €</strong></div>
        <div class="graph-kpi"><span>Montant des impayés</span><strong><?= number_format($totalUnpaid, 2, ',', ' ') ?> €</strong></div>
        <div class="graph-kpi"><span>Taux impayés / chiffre d’affaires</span><strong><?= $totalTurnover > 0 ? number_format($totalUnpaid / $totalTurnover * 100, 2, ',', ' ') . ' %' : '—' ?></strong></div>
    </div>
    <div class="chart-legend">
        <span><i class="legend-dot" style="background:#0ea5e9"></i>Chiffre d’affaires</span>
        <span><i class="legend-dot" style="background:#ef4444"></i>Impayés (valeur absolue)</span>
    </div>
    <?php if (!$months): ?>
        <div class="chart-empty">Aucune donnée sur cette période.</div>
    <?php elseif ($chartType === 'bar'): ?>
        <div class="chart-scroll">
            <div class="monthly-bars">
                <?php foreach ($months as $month): ?>
                    <div class="month-column">
                        <div class="bar-pair">
                            <div class="chart-bar chart-bar-turnover" title="CA : <?= number_format($month['chiffre_affaires'], 2, ',', ' ') ?> €" style="height: <?= max(2, round($month['chiffre_affaires'] / $chartMaximum * 240)) ?>px"></div>
                            <div class="chart-bar chart-bar-unpaid" title="Impayés : <?= number_format($month['montant_impayes'], 2, ',', ' ') ?> €" style="height: <?= max(2, round($month['montant_impayes'] / $chartMaximum * 240)) ?>px"></div>
                        </div>
                        <span><?= htmlspecialchars($month['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="chart-scroll">
            <svg class="line-chart" viewBox="0 0 1000 330" role="img" aria-label="Évolution du chiffre d’affaires et des impayés">
                <line class="grid" x1="40" y1="45" x2="960" y2="45"></line>
                <line class="grid" x1="40" y1="105" x2="960" y2="105"></line>
                <line class="grid" x1="40" y1="165" x2="960" y2="165"></line>
                <line class="grid" x1="40" y1="225" x2="960" y2="225"></line>
                <line class="grid" x1="40" y1="280" x2="960" y2="280"></line>
                <polyline class="ca-line" points="<?= htmlspecialchars(implode(' ', $caPoints), ENT_QUOTES, 'UTF-8') ?>"></polyline>
                <polyline class="unpaid-line" points="<?= htmlspecialchars(implode(' ', $impayePoints), ENT_QUOTES, 'UTF-8') ?>"></polyline>
            </svg>
            <div class="month-labels"><?php foreach ($months as $month): ?><span><?= htmlspecialchars($month['label']) ?></span><?php endforeach; ?></div>
        </div>
    <?php endif; ?>
</div>

<div class="dark-card results-section">
    <div class="results-header">Répartition des impayés par motif</div>
    <?php if (!$motifs): ?>
        <div class="chart-empty">Aucun impayé par motif sur cette période.</div>
    <?php else: ?>
        <div class="motif-layout">
            <div class="motif-pie" role="img" aria-label="Diagramme en secteurs des impayés par motif"></div>
            <div class="motif-legend">
                <?php foreach ($motifs as $index => $motif): ?>
                    <div class="motif-legend-item">
                        <i class="motif-swatch" style="background:<?= $palette[$index % count($palette)] ?>"></i>
                        <span><?= htmlspecialchars($motif['code_motif'] . ' — ' . $motif['motif']) ?></span>
                        <strong><?= number_format((float) $motif['montant_total'], 2, ',', ' ') ?> €</strong>
                        <small>(<?= (int) $motif['nb_incidents'] ?>)</small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="table-scroll" style="margin-top:20px">
            <table class="data-table">
                <thead><tr><th>Code motif</th><th>Motif</th><th>Nombre d’incidents</th><th>Montant total</th></tr></thead>
                <tbody>
                    <?php foreach ($motifs as $motif): ?>
                        <tr><td><?= htmlspecialchars($motif['code_motif']) ?></td><td><?= htmlspecialchars($motif['motif']) ?></td><td><?= (int) $motif['nb_incidents'] ?></td><td><?= number_format((float) $motif['montant_total'], 2, ',', ' ') ?> €</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
