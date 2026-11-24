<?php

function reportSortOptions(string $page): array
{
    $options = [
        'tresorerie' => [
            'balance' => 'Solde',
            'siren' => 'SIREN (numérique)',
            'company' => 'Raison sociale (ordre lexicographique)',
            'transactions' => 'Transactions',
        ],
        'remises' => [
            'date' => 'Date de traitement',
            'amount' => 'Montant',
            'siren' => 'SIREN (numérique)',
            'company' => 'Raison sociale (ordre lexicographique)',
            'reference' => 'N° de remise',
            'transactions' => 'Transactions',
        ],
        'impayes' => [
            'date' => 'Date de vente',
            'amount' => 'Montant (valeur absolue)',
            'siren' => 'SIREN (numérique)',
            'company' => 'Raison sociale (ordre lexicographique)',
            'dossier' => 'N° de dossier',
            'reason' => 'Motif',
        ],
    ];

    return $options[$page] ?? $options['tresorerie'];
}

function reportColumns(string $page, array $filters = []): array
{
    $columns = [
        'tresorerie' => [
            ['num_siren', 'N° SIREN'],
            ['raison_sociale', 'Raison sociale'],
            ['nb_transactions', 'Nombre de transactions'],
            ['devise', 'Devise'],
            ['montant_total_solde', 'Solde actuel'],
            ['statut_visuel', 'Statut'],
        ],
        'remises' => [
            ['num_siren', 'N° SIREN'],
            ['raison_sociale', 'Raison sociale'],
            ['num_remise', 'N° remise'],
            ['date_traitement', 'Date de traitement'],
            ['nb_transactions', 'Nombre de transactions'],
            ['devise', 'Devise'],
            ['montant_total', 'Montant total'],
            ['sens', 'Sens'],
        ],
        'impayes' => [
            ['num_siren', 'N° SIREN'],
            ['raison_sociale', 'Raison sociale'],
            ['date_vente', 'Date de vente'],
            ['date_remise', 'Date de remise'],
            ['num_dossier_impaye', 'N° de dossier'],
            ['num_carte_masquee', 'N° de carte'],
            ['reseau', 'Réseau'],
            ['code_motif', 'Code motif'],
            ['libelle_impaye', 'Motif'],
            ['devise', 'Devise'],
            ['montant_impaye', 'Montant'],
        ],
    ];

    if ($page === 'tresorerie') {
        $columns['tresorerie'][4][1] = treasuryBalanceLabel($filters);
    }

    return $columns[$page] ?? $columns['tresorerie'];
}

function treasuryBalanceLabel(array $filters): string
{
    $dateValue = reportInput($filters, 'date_valeur');
    $validDate = $dateValue !== ''
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)
        && checkdate((int) substr($dateValue, 5, 2), (int) substr($dateValue, 8, 2), (int) substr($dateValue, 0, 4));

    return $validDate ? 'Solde au ' . date('d/m/Y', strtotime($dateValue)) : 'Solde actuel';
}

function reportDetailColumns(): array
{
    return [
        ['num_siren', 'N° SIREN'],
        ['date_vente', 'Date de vente'],
        ['num_carte_masquee', 'N° de carte'],
        ['reseau', 'Réseau'],
        ['num_autorisation', 'N° autorisation'],
        ['devise', 'Devise'],
        ['montant', 'Montant'],
        ['sens', 'Sens'],
    ];
}

function reportTitle(string $page, bool $details = false, array $rows = []): string
{
    $titles = [
        'tresorerie' => 'ANNONCES DE TRÉSORERIE',
        'remises' => 'LISTE DES REMISES',
        'impayes' => 'LISTE DES IMPAYÉS',
    ];

    $title = $details ? 'DÉTAIL DES TRANSACTIONS PAR REMISE' : ($titles[$page] ?? 'RAPPORT');
    $companies = [];
    foreach ($rows as $row) {
        if (isset($row['num_siren'], $row['raison_sociale'])) {
            $companies[$row['num_siren']] = $row['raison_sociale'];
        }
    }
    if (count($companies) === 1) {
        $siren = (string) array_key_first($companies);
        $companyName = (string) $companies[$siren];
        $companyName = function_exists('mb_strtoupper') ? mb_strtoupper($companyName, 'UTF-8') : strtoupper($companyName);
        $title .= ' DE L’ENTREPRISE ' . $companyName . ' N° SIREN ' . $siren;
    }

    return $title;
}

function reportInput(array $filters, string $key, string $default = ''): string
{
    $value = $filters[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function buildReportQuery(string $page, array $filters, ?int $clientId = null): array
{
    $queries = [
        'tresorerie' => 'SELECT * FROM v_annonces_tresorerie WHERE 1 = 1',
        'remises' => 'SELECT * FROM v_liste_remises WHERE 1 = 1',
        'impayes' => 'SELECT i.*, c.raison_sociale FROM v_recherche_impayes i JOIN client c ON c.id_client = i.id_client WHERE 1 = 1',
    ];
    $page = isset($queries[$page]) ? $page : 'tresorerie';
    $sql = $queries[$page];
    $params = [];
    $dateValue = reportInput($filters, 'date_valeur');
    $validDateValue = $dateValue !== ''
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)
        && checkdate((int) substr($dateValue, 5, 2), (int) substr($dateValue, 8, 2), (int) substr($dateValue, 0, 4));

    if ($page === 'tresorerie' && $validDateValue) {
        $sql = "SELECT snapshot.*, CASE WHEN snapshot.montant_total_solde < 0 THEN 'ALERTE_ROUGE' ELSE 'OK' END AS statut_visuel FROM (SELECT v.id_compte, v.id_client, v.num_siren, v.raison_sociale, v.montant_total_solde - COALESCE(SUM(CASE WHEN t.date_vente >= DATE_ADD(?, INTERVAL 1 DAY) THEN t.montant ELSE 0 END), 0) AS montant_total_solde, SUM(CASE WHEN t.date_vente < DATE_ADD(?, INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS nb_transactions FROM v_annonces_tresorerie v JOIN compte co ON co.id_compte = v.id_compte LEFT JOIN transactionbancaire t ON t.id_compte_dest = v.id_compte GROUP BY v.id_compte, v.id_client, v.num_siren, v.raison_sociale, v.montant_total_solde) AS snapshot WHERE 1 = 1";
        $params = [$dateValue, $dateValue];
    }

    if ($clientId !== null) {
        $sql .= ' AND ' . ($page === 'impayes' ? 'i.id_client' : 'id_client') . ' = ?';
        $params[] = $clientId;
    }

    if ($page !== 'impayes' || $clientId === null) {
        $siren = reportInput($filters, 'siren');
        if ($siren !== '') {
            $sql .= ' AND num_siren LIKE ?';
            $params[] = '%' . $siren . '%';
        }

        $company = reportInput($filters, 'raison');
        if ($company !== '') {
            $sql .= ' AND raison_sociale LIKE ?';
            $params[] = '%' . $company . '%';
        }
    }

    if ($page === 'remises') {
        $reference = reportInput($filters, 'remise');
        if ($reference !== '') {
            $sql .= ' AND num_remise LIKE ?';
            $params[] = '%' . $reference . '%';
        }
    }

    if ($page === 'impayes') {
        $dossier = reportInput($filters, 'dossier');
        if ($dossier !== '') {
            $sql .= ' AND num_dossier_impaye LIKE ?';
            $params[] = '%' . $dossier . '%';
        }
    }

    if ($page === 'remises' || $page === 'impayes') {
        $dateColumn = $page === 'remises' ? 'date_traitement_raw' : 'date_vente_raw';
        foreach (['date_debut' => '>=', 'date_fin' => '<'] as $key => $operator) {
            $date = reportInput($filters, $key);
            if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $sql .= " AND $dateColumn $operator ?";
                $params[] = $key === 'date_fin' ? date('Y-m-d', strtotime($date . ' +1 day')) : $date;
            }
        }
    }

    $sortColumns = [
        'tresorerie' => [
            'balance' => 'montant_total_solde',
            'siren' => 'CAST(num_siren AS UNSIGNED)',
            'company' => 'LOWER(raison_sociale)',
            'transactions' => 'nb_transactions',
        ],
        'remises' => [
            'date' => 'date_traitement_raw',
            'amount' => 'montant_total',
            'siren' => 'CAST(num_siren AS UNSIGNED)',
            'company' => 'LOWER(raison_sociale)',
            'reference' => 'num_remise',
            'transactions' => 'nb_transactions',
        ],
        'impayes' => [
            'date' => 'date_vente_raw',
            'amount' => 'ABS(montant_impaye)',
            'siren' => 'CAST(num_siren AS UNSIGNED)',
            'company' => 'LOWER(raison_sociale)',
            'dossier' => 'num_dossier_impaye',
            'reason' => 'libelle_impaye',
        ],
    ];
    $defaultSort = $page === 'tresorerie' ? 'balance' : ($page === 'remises' ? 'date' : 'amount');
    $sort = reportInput($filters, 'sort', $defaultSort);
    $sort = isset($sortColumns[$page][$sort]) ? $sort : array_key_first($sortColumns[$page]);
    $direction = strtolower(reportInput($filters, 'direction', $page === 'impayes' ? 'desc' : 'asc'));
    $direction = $direction === 'desc' ? 'DESC' : 'ASC';

    return [$page, $sql, $params, $sortColumns[$page][$sort], $direction];
}

function fetchReportRows(PDO $pdo, string $page, array $filters, ?int $clientId = null): array
{
    [$page, $sql, $params, $sortColumn, $direction] = buildReportQuery($page, $filters, $clientId);
    $tieColumn = ['tresorerie' => 'id_compte', 'remises' => 'id_remise', 'impayes' => 'id_impaye'][$page];
    $sql .= ' ORDER BY ' . $sortColumn . ' ' . $direction . ', ' . $tieColumn . ' ASC';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function fetchReportPage(PDO $pdo, string $page, array $filters, ?int $clientId = null): array
{
    [$page, $baseSql, $params, $sortColumn, $direction] = buildReportQuery($page, $filters, $clientId);
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM (' . $baseSql . ') AS filtered_report');
    $countStatement->execute($params);
    $total = (int) $countStatement->fetchColumn();
    $pageSizeInput = $filters['page_size'] ?? '10';
    $pageSize = is_scalar($pageSizeInput)
        ? filter_var((string) $pageSizeInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $pageSizeInvalid = array_key_exists('page_size', $filters) && $pageSize === false;
    if ($pageSize === false) {
        $pageSize = 10;
    }
    $totalPages = max(1, (int) ceil($total / $pageSize));
    $pageNumber = max(1, (int) reportInput($filters, 'page_num', '1'));
    $pageNumber = min($pageNumber, $totalPages);
    $offset = ($pageNumber - 1) * $pageSize;

    $tieColumn = ['tresorerie' => 'id_compte', 'remises' => 'id_remise', 'impayes' => 'id_impaye'][$page];
    $statement = $pdo->prepare($baseSql . ' ORDER BY ' . $sortColumn . ' ' . $direction . ', ' . $tieColumn . " ASC LIMIT $pageSize OFFSET $offset");
    $statement->execute($params);

    return [
        'rows' => $statement->fetchAll(),
        'total' => $total,
        'page' => $pageNumber,
        'page_size' => $pageSize,
        'page_size_invalid' => $pageSizeInvalid,
        'total_pages' => $totalPages,
    ];
}

function fetchRemiseDetails(PDO $pdo, array $remiseIds): array
{
    if (!$remiseIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($remiseIds), '?'));
    $statement = $pdo->prepare("SELECT * FROM v_details_remises WHERE id_remise IN ($placeholders) ORDER BY date_vente_raw ASC");
    $statement->execute(array_values($remiseIds));

    return $statement->fetchAll();
}

function fetchImpayeSummaryBySiren(PDO $pdo, array $filters, ?int $clientId = null): array
{
    [, $sql, $params] = buildReportQuery('impayes', $filters, $clientId);
    $statement = $pdo->prepare('SELECT num_siren, raison_sociale, COUNT(*) AS nb_incidents, SUM(ABS(montant_impaye)) AS montant_total FROM (' . $sql . ') AS filtered_impayes GROUP BY num_siren, raison_sociale ORDER BY CAST(num_siren AS UNSIGNED) ASC');
    $statement->execute($params);

    return $statement->fetchAll();
}

function fetchImpayesGraphData(PDO $pdo, array $filters, ?int $clientId = null): array
{
    $period = reportInput($filters, 'periode', '12');
    $period = in_array($period, ['4', '12'], true) ? (int) $period : 12;
    $monthStart = new DateTimeImmutable('first day of this month');
    $defaultStart = $monthStart->modify('-' . ($period - 1) . ' months');
    $defaultEnd = $monthStart->modify('+1 month');
    $start = reportInput($filters, 'date_debut');
    $end = reportInput($filters, 'date_fin');
    $validStart = $start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && checkdate((int) substr($start, 5, 2), (int) substr($start, 8, 2), (int) substr($start, 0, 4));
    $validEnd = $end !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && checkdate((int) substr($end, 5, 2), (int) substr($end, 8, 2), (int) substr($end, 0, 4));
    $startDate = $validStart ? new DateTimeImmutable($start) : $defaultStart;
    $endDate = $validEnd ? (new DateTimeImmutable($end))->modify('+1 day') : $defaultEnd;
    if ($startDate >= $endDate) {
        $startDate = $defaultStart;
        $endDate = $defaultEnd;
    }

    $monthlySql = "SELECT DATE_FORMAT(t.date_vente, '%Y-%m-01') AS mois, SUM(CASE WHEN t.montant > 0 THEN t.montant ELSE 0 END) AS chiffre_affaires, SUM(CASE WHEN i.id_impaye IS NOT NULL THEN ABS(t.montant) ELSE 0 END) AS montant_impayes FROM transactionbancaire t JOIN compte co ON co.id_compte = t.id_compte_dest LEFT JOIN impayes i ON i.id_transaction = t.id_transaction WHERE t.date_vente >= ? AND t.date_vente < ?";
    $params = [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
    if ($clientId !== null) {
        $monthlySql .= ' AND co.id_client = ?';
        $params[] = $clientId;
    }
    $monthlySql .= " GROUP BY DATE_FORMAT(t.date_vente, '%Y-%m-01') ORDER BY mois";
    $monthlyStatement = $pdo->prepare($monthlySql);
    $monthlyStatement->execute($params);
    $monthlyRows = [];
    foreach ($monthlyStatement->fetchAll() as $row) {
        $monthlyRows[$row['mois']] = $row;
    }

    $motifSql = 'SELECT m.code_motif, m.libelle AS motif, COUNT(i.id_impaye) AS nb_incidents, SUM(ABS(t.montant)) AS montant_total FROM impayes i JOIN transactionbancaire t ON t.id_transaction = i.id_transaction JOIN motif_impaye m ON m.code_motif = i.code_motif JOIN compte co ON co.id_compte = t.id_compte_dest WHERE t.date_vente >= ? AND t.date_vente < ?';
    if ($clientId !== null) {
        $motifSql .= ' AND co.id_client = ?';
    }
    $motifSql .= ' GROUP BY m.code_motif, m.libelle ORDER BY montant_total DESC, m.code_motif ASC';
    $motifStatement = $pdo->prepare($motifSql);
    $motifStatement->execute($params);

    $months = [];
    $cursor = new DateTimeImmutable($startDate->format('Y-m-01'));
    $lastMonth = new DateTimeImmutable($endDate->modify('-1 day')->format('Y-m-01'));
    while ($cursor <= $lastMonth) {
        $key = $cursor->format('Y-m-d');
        $months[] = [
            'key' => $key,
            'label' => $cursor->format('m/Y'),
            'chiffre_affaires' => (float) ($monthlyRows[$key]['chiffre_affaires'] ?? 0),
            'montant_impayes' => (float) ($monthlyRows[$key]['montant_impayes'] ?? 0),
        ];
        $cursor = $cursor->modify('+1 month');
    }

    return [
        'months' => $months,
        'motifs' => $motifStatement->fetchAll(),
        'date_debut' => $startDate->format('Y-m-d'),
        'date_fin' => $endDate->modify('-1 day')->format('Y-m-d'),
        'periode' => $period,
    ];
}

function impayeAmountBand(float $amount): string
{
    $absoluteAmount = abs($amount);

    if ($absoluteAmount < 100) {
        return 'amount-band-low';
    }
    if ($absoluteAmount < 200) {
        return 'amount-band-medium';
    }
    if ($absoluteAmount < 300) {
        return 'amount-band-high';
    }

    return 'amount-band-critical';
}

function reportCellValue(array $row, string $column): string
{
    $value = $row[$column] ?? '';

    if ($column === 'sens') {
        return $value === '+' ? 'Crédit' : 'Débit';
    }
    if (in_array($column, ['montant', 'montant_total', 'montant_impaye', 'montant_total_solde'], true)) {
        return number_format((float) $value, 2, ',', ' ') . ' €';
    }

    return (string) $value;
}
