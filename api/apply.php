<?php
/**
 * Public student application (multipart/form-data).
 * Fields: full_name, email, phone, address, school
 * Files:  doc_0 .. doc_3 matching DOC_TYPES order
 */
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST required.'], 405);
}

$name    = trim($_POST['full_name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phoneRaw = trim($_POST['phone'] ?? '');
$phone   = normalize_phone($phoneRaw);
$address = trim($_POST['address'] ?? '');
$school  = trim($_POST['school'] ?? '');

$errors = [];
if (mb_strlen($name) < 2)                             $errors['full_name'] = 'Enter your full name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errors['email']     = 'Enter a valid email address.';
if ($phone === null)                                  $errors['phone']     = 'Enter a valid Philippine mobile number (e.g. 0912 345 6789).';
if (mb_strlen($address) < 5)                          $errors['address']   = 'Enter your complete address.';
if (mb_strlen($school) < 2)                            $errors['school']    = 'Enter your school name.';

foreach (DOC_TYPES as $i => $type) {
    if (empty($_FILES['doc_' . $i]['name'])) {
        $errors['doc_' . $i] = $type . ' is required.';
    }
}

if ($errors) {
    json_out(['errors' => $errors], 422);
}

$uploadDir = dirname(__DIR__) . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$allowed = ['pdf', 'jpg', 'jpeg', 'png'];
$pdo     = db();
$pdo->beginTransaction();

try {
    $refCode = 'SCH-2026-' . str_pad((string) random_int(2000, 9999), 4, '0');

    $stmt = $pdo->prepare(
        'INSERT INTO applications (ref_code, full_name, email, phone, address, school, status)
         VALUES (?, ?, ?, ?, ?, ?, "submitted")'
    );
    $stmt->execute([$refCode, $name, $email, $phone, $address, $school]);
    $appId = (int) $pdo->lastInsertId();

    $insertDoc = $pdo->prepare(
        'INSERT INTO documents (application_id, doc_type, file_name, stored_path, status)
         VALUES (?, ?, ?, ?, "pending")'
    );

    foreach (DOC_TYPES as $i => $type) {
        $file = $_FILES['doc_' . $i];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException($type . ': only PDF, JPG or PNG files are accepted.');
        }
        if ($file['size'] > 8 * 1024 * 1024) {
            throw new RuntimeException($type . ': file must be 8 MB or smaller.');
        }

        $safe   = $refCode . '-' . ($i + 1) . '.' . $ext;
        $target = $uploadDir . '/' . $safe;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Could not save ' . $type . '. Check folder permissions on /uploads.');
        }

        $insertDoc->execute([$appId, $type, $file['name'], 'uploads/' . $safe]);
    }

    add_timeline($appId, 'Application submitted', $name);
    add_notification(
        'New application received',
        $name . ' from ' . $school . ' submitted a scholarship application (' . $refCode . ').',
        'info'
    );

    $pdo->commit();
    json_out(['ok' => true, 'ref_code' => $refCode], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_out(['error' => $e->getMessage()], 422);
}
