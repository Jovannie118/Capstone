<?php
/**
 * Approval decisions (admin only).
 *   POST { app_id, status: approved|waitlisted|rejected|ranked|under_verification, note? }
 */
require __DIR__ . '/config.php';
require_admin();

$data   = body();
$appId  = (int) ($data['app_id'] ?? 0);
$status = $data['status'] ?? '';
$note   = trim((string) ($data['note'] ?? ''));

$allowed = ['submitted', 'under_verification', 'ranked', 'approved', 'waitlisted', 'rejected'];
if (!$appId || !in_array($status, $allowed, true)) {
    json_out(['error' => 'An application id and a valid status are required.'], 422);
}

$pdo  = db();
$stmt = $pdo->prepare('SELECT ref_code, full_name FROM applications WHERE id = ?');
$stmt->execute([$appId]);
$app = $stmt->fetch();
if (!$app) {
    json_out(['error' => 'Application not found.'], 404);
}

$pdo->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$status, $appId]);

$label = STATUS_LABEL[$status];
add_timeline($appId, $label . ($note !== '' ? ' - ' . $note : ''), 'Review Committee');
add_notification(
    $app['ref_code'] . ' ' . strtolower($label),
    $note !== '' ? $note : $app['full_name'] . ' was notified by email about the ' . strtolower($label) . ' decision.',
    $status === 'approved' ? 'success' : ($status === 'rejected' ? 'warning' : 'info')
);

json_out(['ok' => true, 'application' => load_application($appId)]);
