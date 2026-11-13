<?php
// Database settings for Teamsync.
//
// HOW TO USE: copy this file and rename the copy to db.php (in this same folder).
// db.php is listed in .gitignore, so your own password never goes to GitHub.
// The values below are the XAMPP defaults and work without changes.

$DB_HOST = 'localhost';
$DB_NAME = 'Teamsync';
$DB_USER = 'root';
$DB_PASS = '';   // XAMPP default password is empty

// Code a professor must type to sign up with the role Professor.
// Leave it empty ('') to turn professor sign-up off. Give the code only to the professor.
// db.php is not on GitHub, so the code stays private.
$PROFESSOR_CODE = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // show SQL errors as exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows as ['column' => value]
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]
    );
} catch (PDOException $e) {
    exit('Could not connect to the database. Is MySQL running in XAMPP, and did you import database/schema.sql?');
}
