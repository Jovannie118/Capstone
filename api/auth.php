<?php
/**
 * Admin authentication.
 *   POST ?action=login   { email, password }
 *   POST ?action=logout
 *   GET  ?action=session
 */
require __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'session';

if ($action === 'session') {
    json_out(['signedIn' => is_admin(), 'email' => $_SESSION['admin_email'] ?? null]);
}

if ($action === 'logout') {
    session_destroy();
    json_out(['signedIn' => false]);
}

if ($action === 'login') {
    $data     = body();
    $email    = strtolower(trim($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $password === '') {
        json_out(['error' => 'Email and password are required.'], 422);
    }

    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        json_out(['error' => 'Those credentials do not match our records.'], 401);
    }

    $_SESSION['admin_id']    = (int) $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name']  = $admin['full_name'];
    add_audit('login', 0, null, null, null, 'Administrator signed in.');
    json_out(['signedIn' => true, 'email' => $admin['email'], 'name' => $admin['full_name']]);
}

json_out(['error' => 'Unknown action.'], 400);
