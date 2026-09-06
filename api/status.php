<?php
/**
 * Public status lookup for students.
 *   GET status.php?q=SCH-2026-1001   (reference code or email)
 * Returns only the applicant's own information - no other records.
 */
require __DIR__ . '/config.php';

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') {
    json_out(['error' => 'Enter your application ID or the email you applied with.'], 422);
}

$stmt = db()->prepare(
    'SELECT * FROM applications WHERE ref_code = ? OR LOWER(email) = LOWER(?) ORDER BY id DESC LIMIT 1'
);
$stmt->execute([$q, $q]);
$app = $stmt->fetch();

if (!$app) {
    json_out(['error' => 'No application found for "' . htmlspecialchars($q) . '".'], 404);
}

$full = hydrate($app);

json_out([
    'application' => [
        'ref_code'     => $full['ref_code'],
        'full_name'    => $full['full_name'],
        'email'        => $full['email'],
        'phone'        => $full['phone'],
        'address'      => $full['address'],
        'school'       => $full['school'],
        'status'       => $full['status'],
        'status_label' => $full['status_label'],
        'submitted_at' => $full['submitted_at'],
        'documents'    => $full['documents'],
        'timeline'     => $full['timeline'],
    ],
]);
