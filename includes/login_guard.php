<?php
// Login protection against password guessing (FR-27).
//
// Rule: after 5 wrong passwords for the same email within 15 minutes, logging in to that email is
// blocked for 15 minutes from the last try. A correct login or a password reset clears the count.
// The rule works the same for emails that have no account, so it does not show who has one.

const LOGIN_MAX_FAILS    = 5;    // wrong passwords in a row before the block
const LOGIN_LOCK_MINUTES = 15;   // length of the block, and of the window the 5 tries must fall in

function normalize_login_email(string $email): string
{
    return strtolower(trim($email));
}

// Minutes the user still has to wait before trying this email again, or 0 when logging in is allowed.
function login_wait_minutes(PDO $pdo, string $email): int
{
    $stmt = $pdo->prepare(
        'SELECT attempted_at FROM login_attempts WHERE email = ? ORDER BY attempted_at DESC LIMIT ' . LOGIN_MAX_FAILS
    );
    $stmt->execute([normalize_login_email($email)]);
    $times = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($times) < LOGIN_MAX_FAILS) {
        return 0;
    }

    $latest = new DateTime($times[0]);
    $oldest = new DateTime(end($times));
    $windowSeconds = LOGIN_LOCK_MINUTES * 60;
    if ($latest->getTimestamp() - $oldest->getTimestamp() > $windowSeconds) {
        return 0;   // the 5 tries were spread over more than 15 minutes
    }

    $secondsLeft = $latest->getTimestamp() + $windowSeconds - time();
    return $secondsLeft > 0 ? (int) ceil($secondsLeft / 60) : 0;
}

function record_failed_login(PDO $pdo, string $email): void
{
    // Keep the table small: tries older than a day no longer matter.
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
        ->execute([(new DateTime('-1 day'))->format('Y-m-d H:i:s')]);

    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, attempted_at) VALUES (?, ?)');
    $stmt->execute([normalize_login_email($email), date('Y-m-d H:i:s')]);
}

function clear_failed_logins(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = ?');
    $stmt->execute([normalize_login_email($email)]);
}
