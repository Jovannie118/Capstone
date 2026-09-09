<?php
/**
 * One-off migration: copy documents already stored on disk into the database.
 *
 * Run AFTER importing migration.sql, from the project root:
 *     php migrate_documents.php
 *
 * For every document row that still references a stored_path, this script:
 *   1. reads the physical file,
 *   2. inserts its bytes into documents.file_data (with MIME + size),
 *   3. re-reads the row from the database and verifies the copy matches,
 *   4. only then deletes the old physical file and clears stored_path.
 */

require __DIR__ . '/api/config.php';

$pdo = db();

$rows = $pdo->query(
    "SELECT id, application_id, file_name, stored_path, mime_type, file_size
     FROM documents
     WHERE stored_path IS NOT NULL AND stored_path <> ''"
)->fetchAll();

if (!$rows) {
    echo "No documents reference files on disk - nothing to migrate.\n";
    exit(0);
}

echo count($rows) . " document(s) reference files on disk.\n";

$errors = 0;
foreach ($rows as $row) {
    $path = __DIR__ . '/' . $row['stored_path'];

    if (!is_file($path)) {
        echo "Skip document #{$row['id']}: file not found on disk ('{$row['stored_path']}').\n";
        continue;
    }

    $data = @file_get_contents($path);
    if ($data === false) {
        echo "FAIL document #{$row['id']}: could not read '{$row['stored_path']}'.\n";
        $errors++;
        continue;
    }

    $mime = !empty($row['mime_type'])
        ? $row['mime_type']
        : ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream');
    $size = filesize($path);

    $pdo->prepare('UPDATE documents SET file_data = ?, mime_type = ?, file_size = ? WHERE id = ?')
        ->execute([$data, $mime, $size, $row['id']]);

    // Verify the database copy before touching the original file.
    $check = $pdo->prepare('SELECT file_data, file_size, mime_type FROM documents WHERE id = ?');
    $check->execute([$row['id']]);
    $saved = $check->fetch();

    if (!$saved || strlen((string) $saved['file_data']) !== strlen($data)
        || (int) $saved['file_size'] !== (int) $size) {
        echo "FAIL document #{$row['id']}: database copy does not match the disk file.\n";
        $errors++;
        continue;
    }

    @unlink($path);
    $pdo->prepare('UPDATE documents SET stored_path = NULL WHERE id = ?')->execute([$row['id']]);

    echo "Migrated document #{$row['id']} ({$row['file_name']}, {$size} bytes, {$mime}).\n";
}

echo $errors ? "Finished with {$errors} error(s).\n" : "All documents migrated to the database.\n";
exit($errors ? 1 : 0);