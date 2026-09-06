<?php
/**
 * Database connection + shared helpers.
 * Edit the four constants below to match your MySQL server.
 */

const DB_HOST = 'localhost';
const DB_NAME = 'paracale_sms';
const DB_USER = 'root';
const DB_PASS = '';        // XAMPP default is an empty password

// ---------------------------------------------------------------------
// Session (used for the admin login)
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

/** @return PDO */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            json_out(['error' => 'Database connection failed. Check api/config.php and import database.sql.'], 500);
        }
    }
    return $pdo;
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/** Reads a JSON request body (falls back to form-encoded POST). */
function body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_email']);
}

function require_admin(): void
{
    if (!is_admin()) {
        json_out(['error' => 'Unauthorized. Please sign in as an administrator.'], 401);
    }
}

/**
 * Weighted composite score.
 * Academics 45%, financial need 30%, activities 15%, essay 10%,
 * minus a 6 point penalty when any document was rejected.
 */
function score_of(array $app, array $docs): float
{
    $academic   = min((float) $app['gpa'] / 4, 1) * 45;
    $need       = (1 - min((float) $app['household_income'] / 60000, 1)) * 30;
    $activities = ((int) $app['extracurricular'] / 10) * 15;
    $essay      = ((int) $app['essay_score'] / 10) * 10;

    $penalty = 0;
    foreach ($docs as $d) {
        if ($d['status'] === 'rejected') {
            $penalty = 6;
            break;
        }
    }

    return max(0, round(($academic + $need + $activities + $essay - $penalty) * 10) / 10);
}

function add_notification(string $title, string $body, string $kind = 'info'): void
{
    $stmt = db()->prepare('INSERT INTO notifications (title, body, kind) VALUES (?, ?, ?)');
    $stmt->execute([$title, $body, $kind]);
}

function add_timeline(int $appId, string $label, string $actor): void
{
    $stmt = db()->prepare('INSERT INTO timeline (application_id, label, actor) VALUES (?, ?, ?)');
    $stmt->execute([$appId, $label, $actor]);
}

/**
 * Normalize a Philippine mobile number to +63 XXX XXX XXXX.
 * Accepts +63..., 09..., 9... (10 digits), with optional spaces/dashes.
 * Returns null if the number is invalid.
 */
function normalize_phone(string $raw): ?string
{
    $digits = preg_replace('/\D/', '', $raw);
    if (!preg_match('/^(0|63)?9\d{9}$/', $digits)) {
        return null;
    }
    if (str_starts_with($digits, '63')) {
        $local = substr($digits, 2);
    } elseif (str_starts_with($digits, '0')) {
        $local = substr($digits, 1);
    } else {
        $local = $digits;
    }
    return '+63 ' . substr($local, 0, 3) . ' ' . substr($local, 3, 3) . ' ' . substr($local, 6);
}

const DOC_TYPES = [
    'Registration Form',
    'School ID with 3 Signatures',
    'Barangay Indigency',
    'Certificate of Grade (COG)',
];

const STATUS_LABEL = [
    'submitted'          => 'Submitted',
    'under_verification' => 'Verification',
    'ranked'             => 'Ranked',
    'approved'           => 'Approved',
    'waitlisted'         => 'Waitlisted',
    'rejected'           => 'Rejected',
];

/** Loads one application with its documents, timeline and score. */
function load_application(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM applications WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) {
        return null;
    }
    return hydrate($app);
}

function hydrate(array $app): array
{
    $docs = db()->prepare('SELECT id, doc_type, file_name, status, note FROM documents WHERE application_id = ? ORDER BY id');
    $docs->execute([$app['id']]);
    $documents = $docs->fetchAll();

    $tl = db()->prepare('SELECT label, actor, created_at FROM timeline WHERE application_id = ? ORDER BY id');
    $tl->execute([$app['id']]);

    $app['documents']    = $documents;
    $app['timeline']     = $tl->fetchAll();
    $app['score']        = score_of($app, $documents);
    $app['status_label'] = STATUS_LABEL[$app['status']] ?? $app['status'];
    return $app;
}
