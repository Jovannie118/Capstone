<?php
/**
 * Stream an uploaded document so it can be previewed inline (admin only).
 *   GET document.php?id=<doc_id>
 */
require __DIR__ . '/config.php';
require_admin();

$docId = (int) ($_GET['id'] ?? 0);
if (!$docId) {
    http_response_code(422);
    exit('A document id is required.');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
$stmt->execute([$docId]);
$doc = $stmt->fetch();
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

// ADJUST HERE if your uploads live somewhere else, or if file_name is
// already a full/relative path rather than a bare filename.
$path = __DIR__ . '/../uploads/' . $doc['file_name'];

if (!is_file($path)) {
    http_response_code(404);
    exit('File missing on disk.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
