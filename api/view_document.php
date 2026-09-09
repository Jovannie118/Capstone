<?php
/**
 * Stream a document directly from the database (admin only).
 * Documents are stored as LONGBLOB rows; there is no file on disk, so this
 * endpoint never touches the filesystem and never exposes a server path.
 *
 *   GET view_document.php?id=<doc_id>             -> preview inline
 *   GET view_document.php?id=<doc_id>&download=1  -> force download
 *
 * Serves whatever MIME type was stored at upload time, so PDF, JPG, JPEG,
 * PNG (and any other allowed type) preview correctly in the browser.
 */
require __DIR__ . '/config.php';
require_admin();

$docId = (int) ($_GET['id'] ?? 0);
if (!$docId) {
    http_response_code(422);
    exit('A document id is required.');
}

$stmt = db()->prepare(
    'SELECT d.file_name, d.mime_type, d.file_size, d.file_data
     FROM documents d
     JOIN applications a ON a.id = d.application_id
     WHERE d.id = ?'
);
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc || $doc['file_data'] === null) {
    http_response_code(404);
    exit('Document not found.');
}

$data     = $doc['file_data'];
$mime     = !empty($doc['mime_type']) ? $doc['mime_type'] : 'application/octet-stream';
$display  = !empty($_GET['download']) ? 'attachment' : 'inline';
$filename = addcslashes(basename($doc['file_name']), '"\\');

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $display . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($data));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $data;