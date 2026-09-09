<?php
/**
 * Settings (admin only).
 *   GET  settings.php -> current application-window settings
 *   POST settings.php -> { application_window: OPEN|CLOSED,
 *                          application_open_date?, application_close_date? }
 */
require __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_out([
        'application_window'     => strtoupper(setting('application_window', 'OPEN')),
        'application_open_date'  => setting('application_open_date'),
        'application_close_date' => setting('application_close_date'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST required.'], 405);
}

$data  = body();
$window = strtoupper(trim((string) ($data['application_window'] ?? '')));
$open   = trim((string) ($data['application_open_date'] ?? ''));
$close  = trim((string) ($data['application_close_date'] ?? ''));

$errors = [];
if (!in_array($window, ['OPEN', 'CLOSED'], true)) {
    $errors['application_window'] = 'Application window must be OPEN or CLOSED.';
}
if (mb_strlen($open) > 120 || mb_strlen($close) > 120) {
    $errors['application_open_date'] = 'Opening / closing dates must be 120 characters or fewer.';
}
if ($errors) {
    json_out(['errors' => $errors], 422);
}

$pdo = db();
$upsert = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
$upsert->execute(['application_window', $window]);
$upsert->execute(['application_open_date', $open]);
$upsert->execute(['application_close_date', $close]);

$remarks = 'Application window set to ' . $window
    . (($open !== '' || $close !== '') ? ' (' . ($open !== '' ? $open : 'no open date') . ' to ' . ($close !== '' ? $close : 'no close date') . ')' : '');
add_audit('settings_update', 0, null, null, null, $remarks);

json_out(['ok' => true, 'settings' => [
    'application_window'     => $window,
    'application_open_date'  => $open,
    'application_close_date' => $close,
]]);