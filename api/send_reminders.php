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

// Links in the email point to this site (this file is one folder below the app).
echo json_encode(send_due_reminders($pdo, site_url(1)));
