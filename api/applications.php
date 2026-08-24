<?php
/**
 * Applications (admin only).
 *   GET  applications.php            -> list with docs, timeline and score
 *   GET  applications.php?id=3       -> one application
 *   GET  applications.php?ref=SCH-.. -> one application by reference code
 */
require __DIR__ . '/config.php';
require_admin();

$pdo = db();

if (!empty($_GET['id']) || !empty($_GET['ref'])) {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
        $stmt->execute([(int) $_GET['id']]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM applications WHERE ref_code = ?');
        $stmt->execute([trim((string) $_GET['ref'])]);
    }
    $app = $stmt->fetch();
    if (!$app) {
        json_out(['error' => 'Application not found.'], 404);
    }
    json_out(['application' => hydrate($app)]);
}

$rows = $pdo->query('SELECT * FROM applications ORDER BY submitted_at DESC, id DESC')->fetchAll();
$apps = array_map('hydrate', $rows);

$budget = (int) ($pdo->query("SELECT svalue FROM settings WHERE skey = 'budget'")->fetchColumn() ?: 0);

json_out(['applications' => $apps, 'budget' => $budget]);
