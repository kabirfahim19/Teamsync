<?php
// Password reset by email (FR-26).
//
// How it stays safe:
// - The link holds a long random token (64 characters). The database keeps only its SHA-256 hash,
//   so someone who reads the database still cannot use a link.
// - A link works for 1 hour and only once: a new link or a new password deletes the old links.
// - The "forgot password" page shows the same message whether the email has an account or not,
//   so nobody can use it to find out who has an account.

require_once __DIR__ . '/mail.php';

const RESET_LINK_MINUTES = 60;   // how long a reset link works
const RESET_WAIT_MINUTES = 2;    // at most one email per account in this time (stops email flooding)

function find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT user_id, name, email FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

// True if this user asked for a link in the last few minutes.
function reset_requested_recently(PDO $pdo, int $userId): bool
{
    $since = (new DateTime('-' . RESET_WAIT_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
    $stmt  = $pdo->prepare('SELECT 1 FROM password_resets WHERE user_id = ? AND created_at >= ?');
    $stmt->execute([$userId, $since]);
    return (bool) $stmt->fetch();
}

// Make a new link for the user and return its token. Older links of the user stop working.
function create_reset_token(PDO $pdo, int $userId): string
{
    $token   = bin2hex(random_bytes(32));
    $now     = new DateTime();
    $expires = (clone $now)->modify('+' . RESET_LINK_MINUTES . ' minutes');

    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, hash('sha256', $token), $expires->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
    return $token;
}

// The reset row and its user for a token that is unused and not expired, or null.
function find_valid_reset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT r.reset_id, r.user_id, u.name, u.email
         FROM password_resets r JOIN users u ON u.user_id = r.user_id
         WHERE r.token_hash = ? AND r.expires_at > ?'
    );
    $stmt->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
    return $stmt->fetch() ?: null;
}

// Save the new password and make every link of this user stop working.
function reset_password(PDO $pdo, array $reset, string $newPassword): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $reset['user_id']]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// Subject, HTML body and plain-text body of the reset email.
function reset_email(string $name, string $link): array
{
    $subject = 'Reset your Teamsync password';
    $text = "Hello {$name},\n\n"
          . "Someone asked to reset the password of your Teamsync account.\n"
          . "To choose a new password, open this link within " . RESET_LINK_MINUTES . " minutes:\n{$link}\n\n"
          . "If it was not you, ignore this email. Your password stays the same.\n"
          . "- Teamsync";
    $html = '<p>Hello ' . e($name) . ',</p>'
          . '<p>Someone asked to reset the password of your Teamsync account.</p>'
          . '<p><a href="' . e($link) . '">Choose a new password</a> (the link works for ' . RESET_LINK_MINUTES . ' minutes)</p>'
          . '<p style="color:#4A5468">If it was not you, ignore this email. Your password stays the same.<br>Teamsync</p>';
    return [$subject, $html, $text];
}
