<?php
// The login session and its cookie (issue #48).
//
// The session cookie is what keeps a user logged in, so it gets three protections:
// - HttpOnly: JavaScript cannot read it, so an XSS bug could not steal the login.
// - Secure:   on an https site it is only sent over https, never over plain http.
// - SameSite=Lax: other websites cannot send it along with their own forms (extra CSRF protection).
// Strict mode makes PHP refuse session ids it did not create itself (stops session fixation).

// Was this page requested over https? (directly, or through the host's proxy)
function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

// The cookie settings for the session cookie.
function session_cookie_options(bool $https): array
{
    return [
        'lifetime' => 0,        // until the browser is closed
        'path'     => '/',
        'secure'   => $https,   // on XAMPP (http) it must be false, or no login would work
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function start_secure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(session_cookie_options(request_is_https()));
    session_start();
}

// Log out: empty the session, delete it on the server and remove the cookie from the browser.
function end_session(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $options = session_get_cookie_params();
        unset($options['lifetime']);
        setcookie(session_name(), '', ['expires' => time() - 3600] + $options);
        session_destroy();
    }
}

// Is the account this session belongs to still there, with the same role? (issue #56)
// Importing the demo data again deletes the demo accounts and makes them anew with new ids, so a browser
// that stayed logged in would otherwise go on as an account that no longer exists.
function session_account_is_current(PDO $pdo, int $userId, string $role): bool
{
    $stmt = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() === $role;
}

// Log out but keep a session: empty it and give it a new id, so the page can go on
// (for example show the login form with a fresh CSRF token).
function forget_login(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
