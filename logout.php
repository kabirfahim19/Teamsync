<?php
// Log out (POST only, with CSRF check)
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
check_csrf();

$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
