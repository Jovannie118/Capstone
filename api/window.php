<?php
/**
 * Application window status (public).
 *   GET window.php -> { status: 'open'|'closed', open_date, close_date, message }
 * Used by the homepage (and apply page) so they reflect the database setting.
 */
require __DIR__ . '/config.php';

$status = strtoupper(setting('application_window', 'OPEN'));
$open   = setting('application_open_date');
$close  = setting('application_close_date');

if ($status === 'OPEN') {
    $message = 'Applications are currently open' . ($close !== '' ? ' until ' . $close : '') . '.';
} else {
    $message = 'Applications are currently closed';
    if ($open !== '' && $close !== '') {
        $message .= '. The application window runs from ' . $open . ' to ' . $close;
    } elseif ($open !== '') {
        $message .= '. Applications will reopen on ' . $open;
    } elseif ($close !== '') {
        $message .= ' (this window closed on ' . $close . ')';
    }
    $message .= '.';
}

json_out([
    'status'     => $status,
    'open_date'  => $open !== '' ? $open : null,
    'close_date' => $close !== '' ? $close : null,
    'message'    => $message,
]);