<?php
// Sends the email reminders that are due (FR-19). The dashboard calls this in the background.
// Answers with JSON: {"enabled": bool, "sent": n, "skipped": n, "failed": n}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tasks.php';
require_once __DIR__ . '/../includes/reminders.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Please log in first.']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Use POST.']));
}
check_csrf();

// Links in the email point to this site. $SITE_URL in config/db.php wins; otherwise use this request's address.
if (!empty($SITE_URL)) {
    $siteUrl = (string) $SITE_URL;
} else {
    $https   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $base    = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    $siteUrl = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $base;
}

echo json_encode(send_due_reminders($pdo, $siteUrl));
