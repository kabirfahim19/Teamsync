<?php
// Top of every page. Set $pageTitle before including this file.
// Set $authLayout = true on the login and sign-up pages.
$pageTitle  = $pageTitle ?? 'Teamsync';
$authLayout = $authLayout ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Teamsync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $authLayout ? 'auth-page' : 'app-page' ?>">
<?php if (!$authLayout && is_logged_in()): ?>
<header class="topbar">
    <a class="brand" href="dashboard.php">
        <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true"><rect x="2" y="4" width="6" height="20" rx="2" fill="#9AA3B5"/><rect x="11" y="4" width="6" height="14" rx="2" fill="#F08A3C"/><rect x="20" y="4" width="6" height="9" rx="2" fill="#2F5BEA"/></svg>
        <span>Teamsync</span>
    </a>
    <div class="topbar-user">
        <span class="user-name"><?= e($_SESSION['name']) ?> <small><?= e(role_label($_SESSION['role'])) ?></small></span>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost">Log out</button>
        </form>
    </div>
</header>
<?php endif; ?>
