<?php
/**
 * Cycle reporting (admin only).
 *   GET reports.php            -> JSON summary
 *   GET reports.php?export=csv -> CSV download of every application
 */
require __DIR__ . '/config.php';
require_admin();

$pdo  = db();
$rows = $pdo->query('SELECT * FROM applications ORDER BY id')->fetchAll();
$apps = array_map('hydrate', $rows);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="paracale-scholarship-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Reference', 'Name', 'Email', 'Address', 'School', 'Status', 'Score', 'Requested', 'Submitted']);
    foreach ($apps as $a) {
        fputcsv($out, [
            $a['ref_code'], $a['full_name'], $a['email'], $a['address'], $a['school'],
            $a['status_label'], $a['score'], $a['requested'], $a['submitted_at'],
        ]);
    }
    fclose($out);
    exit;
}

$budget    = (int) ($pdo->query("SELECT svalue FROM settings WHERE skey = 'budget'")->fetchColumn() ?: 0);
$byStatus  = [];
$bySchool  = [];
$committed = 0;
$docsPending = 0;

foreach ($apps as $a) {
    $byStatus[$a['status_label']] = ($byStatus[$a['status_label']] ?? 0) + 1;
    $bySchool[$a['school']]       = ($bySchool[$a['school']] ?? 0) + 1;
    if ($a['status'] === 'approved') {
        $committed += (int) $a['requested'];
    }
    foreach ($a['documents'] as $d) {
        if ($d['status'] !== 'verified') {
            $docsPending++;
        }
    }
}

arsort($bySchool);

json_out([
    'total'       => count($apps),
    'budget'      => $budget,
    'committed'   => $committed,
    'docsPending' => $docsPending,
    'byStatus'    => $byStatus,
    'bySchool'    => $bySchool,
    'applications' => array_map(static fn ($a) => [
        'ref_code' => $a['ref_code'],
        'full_name' => $a['full_name'],
        'school'   => $a['school'],
        'status'   => $a['status'],
        'status_label' => $a['status_label'],
        'score'    => $a['score'],
        'requested' => $a['requested'],
    ], $apps),
]);
