<?php
/**
 * Public student application (multipart/form-data).
 * Fields: full_name, email, phone, address, school
 * Files:  doc_0 .. doc_3 matching DOC_TYPES order
 * Documents are stored directly in the database (LONGBLOB) - never on disk.
 */
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST required.'], 405);
}

$window = strtoupper(setting('application_window', 'OPEN'));
if ($window !== 'OPEN') {
    $open  = setting('application_open_date');
    $close = setting('application_close_date');
    $msg   = 'Applications are currently closed.';
    if ($open !== '' && $close !== '') {
        $msg .= ' Application window: ' . $open . ' to ' . $close . '.';
    } elseif ($open !== '') {
        $msg .= ' Applications will reopen on ' . $open . '.';
    } elseif ($close !== '') {
        $msg .= ' This window closed on ' . $close . '.';
    }
    json_out(['error' => $msg], 403);
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

$pdo = db();

// Section 12 - duplicate application protection. Reject the submission when
// the same email address or mobile number already has a non-rejected
// application, so applicants can never silently create a second one.
$dupe = $pdo->prepare(
    'SELECT ref_code FROM applications
     WHERE (LOWER(email) = LOWER(?) OR phone = ?) AND status <> "rejected"
     LIMIT 1'
);
$dupe->execute([$email, $phone]);
if ($existing = $dupe->fetch()) {
    json_out([
        'error'     => 'An application using this email address or mobile number already exists. ' .
                       'Track it with reference ' . $existing['ref_code'] . '.',
        'duplicate' => true,
    ], 409);
}

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
        'INSERT INTO documents (application_id, doc_type, file_name, mime_type, file_size, file_data, status)
         VALUES (?, ?, ?, ?, ?, ?, "pending")'
    );

    $allowedExt  = ['pdf', 'jpg', 'jpeg', 'png'];
    $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
    $maxBytes    = 8 * 1024 * 1024;
    $finfo       = null;

    foreach (DOC_TYPES as $i => $type) {
        $file = $_FILES['doc_' . $i];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException($type . ': upload failed. Please try again.');
        }
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException($type . ': file must be 8 MB or smaller.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException($type . ': only PDF, JPG or PNG files are accepted.');
        }

        // Never trust the browser-supplied name or MIME type - inspect the
        // actual file contents so renamed executables are rejected.
        if ($finfo === null) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
        }
        $detected = (string) $finfo->file($file['tmp_name']);
        if (!in_array($detected, $allowedMime, true)) {
            throw new RuntimeException($type . ': file contents are not a PDF, JPG or PNG image.');
        }

        $data = file_get_contents($file['tmp_name']);
        if ($data === false) {
            throw new RuntimeException($type . ': could not read the uploaded file.');
        }

        // Store the raw bytes as a LONGBLOB. The temporary upload is never
        // moved to disk and is removed automatically when the request ends.
        $insertDoc->bindValue(1, $appId, PDO::PARAM_INT);
        $insertDoc->bindValue(2, $type);
        $insertDoc->bindValue(3, $file['name']);
        $insertDoc->bindValue(4, $detected);
        $insertDoc->bindValue(5, $file['size'], PDO::PARAM_INT);
        $insertDoc->bindValue(6, $data, PDO::PARAM_LOB);
        $insertDoc->execute();

        unset($data);
        @unlink($file['tmp_name']);
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
    // Map the database-level unique constraints (active_email/active_phone)
    // back to the same friendly duplicate message instead of a raw SQL error.
    $info    = ($e instanceof PDOException) ? $e->errorInfo : null;
    $unique  = $info !== null && ($info[0] === '23000' || (int) ($info[1] ?? 0) === 1062);
    $msg     = $unique ? (string) $e->getMessage() : '';
    $isDup   = str_contains($msg, 'uniq_active_email') || str_contains($msg, 'uniq_active_phone');
    if ($unique && $isDup) {
        json_out([
            'error'     => 'An application using this email address or mobile number already exists.',
            'duplicate' => true,
        ], 409);
    }
    json_out(['error' => $e->getMessage()], 422);
}
