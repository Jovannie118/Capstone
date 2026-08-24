<?php
/**
 * Notifications (admin only).
 *   GET  notifications.php
 *   POST notifications.php?action=read-all
 */
require __DIR__ . '/config.php';
require_admin();

if (($_GET['action'] ?? '') === 'read-all') {
    db()->query('UPDATE notifications SET is_read = 1');
    json_out(['ok' => true]);
}

$rows = db()->query('SELECT * FROM notifications ORDER BY created_at DESC, id DESC LIMIT 100')->fetchAll();
json_out(['notifications' => $rows]);
