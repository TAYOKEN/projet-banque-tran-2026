<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if (!in_array($_SESSION['user']['profil'], ['client', 'product_owner'], true)) {
    header('Location: page_admin.php');
    exit;
}

require 'connection.php';
require 'report_data.php';

$page = $_GET['page'] ?? 'tresorerie';
if (!in_array($page, ['tresorerie', 'remises', 'impayes'], true)) {
    http_response_code(400);
    exit('Rapport invalide.');
}

$format = strtolower($_GET['format'] ?? '');
if (!in_array($format, ['csv', 'xls', 'pdf'], true)) {
    http_response_code(400);
    exit('Format d’export invalide.');
}

$user = $_SESSION['user'];
$clientId = $user['profil'] === 'client' ? (int) $user['id_client'] : null;
$detailExport = $page === 'remises' && reportInput($_GET, 'detail') === '1';
if ($detailExport) {
    $remises = fetchReportRows($pdo, 'remises', $_GET, $clientId);
    $rows = fetchRemiseDetails($pdo, array_column($remises, 'id_remise'));
    $columns = reportDetailColumns();
    $titleRows = $remises;
} else {
    $rows = fetchReportRows($pdo, $page, $_GET, $clientId);
    $columns = reportColumns($page, $_GET);
    $titleRows = $rows;
}
$title = reportTitle($page, $detailExport, $titleRows);
$extractedAt = date('d/m/Y H:i');
$filename = 'rapport-' . $page . ($detailExport ? '-details' : '') . '-' . date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, [$title], ';');
    fputcsv($output, ['EXTRAIT DU ' . $extractedAt], ';');
    fputcsv($output, [], ';');
    fputcsv($output, array_column($columns, 1), ';');
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as [$key]) {
            $value = reportCellValue($row, $key);
            if (!is_numeric($row[$key] ?? null) && preg_match('/^[\t\r ]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
            $values[] = $value;
        }
        fputcsv($output, $values, ';');
    }
    fclose($output);
    exit;
}

if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo "\xEF\xBB\xBF<!DOCTYPE html><html lang=\"fr\"><head><meta charset=\"UTF-8\"><title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body>';
    echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>EXTRAIT DU ' . htmlspecialchars($extractedAt, ENT_QUOTES, 'UTF-8') . '</p><table><thead><tr>';
    foreach ($columns as [, $label]) {
        echo '<th>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as [$key]) {
            $value = reportCellValue($row, $key);
            if (!is_numeric($row[$key] ?? null) && preg_match('/^[\t\r ]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
            echo '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

$lines = [$title, 'EXTRAIT DU ' . $extractedAt];
foreach ($rows as $row) {
    $cells = [];
    foreach ($columns as [$key, $label]) {
        $cells[] = $label . ': ' . reportCellValue($row, $key);
    }
    $lines[] = implode(' | ', $cells);
}
if (count($rows) === 0) {
    $lines[] = 'AUCUNE DONNÉE POUR CES CRITÈRES.';
}

$pdfLines = [];
foreach ($lines as $line) {
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $line);
    foreach (explode("\n", wordwrap($encoded === false ? $line : $encoded, 115, "\n", true)) as $wrapped) {
        $pdfLines[] = $wrapped;
    }
}
$pages = array_chunk($pdfLines, 58);
$objects = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
];
$pageReferences = [];
foreach ($pages as $index => $pageLines) {
    $pageObject = 4 + $index * 2;
    $contentObject = $pageObject + 1;
    $pageReferences[] = "$pageObject 0 R";
    $content = "BT /F1 9 Tf 40 800 Td 12 TL\n";
    foreach ($pageLines as $line) {
        $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $content .= '(' . $line . ") Tj T*\n";
    }
    $content .= 'ET';
    $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
    $objects[$contentObject] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
}
$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageReferences) . '] /Count ' . count($pages) . ' >>';
ksort($objects);
$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $number => $object) {
    $offsets[$number] = strlen($pdf);
    $pdf .= "$number 0 obj\n$object\nendobj\n";
}
$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($number = 1; $number <= count($objects); $number++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
}
$pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
