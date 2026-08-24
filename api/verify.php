<?php
/**
 * Document verification (admin only).
 *   POST { doc_id, status: pending|verified|rejected, note? }
 * Re-derives the application status: all verified -> ranked, otherwise -> under_verification.
 * Approved / rejected applications keep their final decision.
 */
require __DIR__ . '/config.php';
require_admin();

$data   = body();
$docId  = (int) ($data['doc_id'] ?? 0);
$status = $data['status'] ?? '';
$note   = trim((string) ($data['note'] ?? ''));

if (!$docId || !in_array($status, ['pending', 'verified', 'rejected'], true)) {
    json_out(['error' => 'A document id and a valid status are required.'], 422);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT d.*, a.ref_code, a.status AS app_status FROM documents d
                       JOIN applications a ON a.id = d.application_id WHERE d.id = ?');
$stmt->execute([$docId]);
$doc = $stmt->fetch();
if (!$doc) {
    json_out(['error' => 'Document not found.'], 404);
}

if ($status === 'rejected' && $note === '') {
    $note = 'Scan unreadable - please re-upload.';
}

$pdo->prepare('UPDATE documents SET status = ?, note = ? WHERE id = ?')
    ->execute([$status, $status === 'rejected' ? $note : null, $docId]);

$appId = (int) $doc['application_id'];

$counts = $pdo->prepare('SELECT SUM(status <> "verified") AS unverified FROM documents WHERE application_id = ?');
$counts->execute([$appId]);
$unverified = (int) $counts->fetchColumn();

if (!in_array($doc['app_status'], ['approved', 'rejected'], true)) {
    $newStatus = $unverified === 0 ? 'ranked' : 'under_verification';
    $pdo->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$newStatus, $appId]);
}

add_timeline($appId, 'Document ' . $doc['doc_type'] . ' marked ' . $status, 'Verification Officer');
add_notification(
    'Document ' . $status,
    $doc['ref_code'] . ': ' . $doc['doc_type'] . ' was marked ' . $status . '.',
    $status === 'verified' ? 'success' : 'warning'
);

json_out(['ok' => true, 'application' => load_application($appId)]);
