<?php
// Shared code for every page: session, database connection, login checks and helpers.
// Put this at the top of each page:  require_once __DIR__ . '/includes/auth.php';

date_default_timezone_set('Asia/Seoul');   // deadlines use Korean time

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configFile = __DIR__ . '/../config/db.php';
if (!file_exists($configFile)) {
    exit('Setup needed: copy config/db.example.php and rename the copy to config/db.php');
}
require_once $configFile;   // gives us $pdo

// ---------- Login and roles ----------

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

// Send the visitor to the login page if they are not logged in.
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

// Allow only some roles, e.g. require_role(['professor']).
function require_role(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to open this page.');
    }
}

function role_label(string $role): string
{
    $labels = ['leader' => 'Team Leader', 'member' => 'Team Member', 'professor' => 'Professor'];
    return $labels[$role] ?? $role;
}

// ---------- Security helpers ----------

// Escape text before printing it in HTML (stops XSS).
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// A hidden form field with this session's CSRF token.
function csrf_field(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

// Stop the request if the form's CSRF token is missing or wrong.
function check_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(400);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}
