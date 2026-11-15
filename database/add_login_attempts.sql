-- Login protection (FR-27): adds one table to an existing Teamsync database.
-- Run it ONCE on a database made before this change. New databases from schema.sql already have it.
--
-- Mac XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/add_login_attempts.sql
-- Online:     phpMyAdmin → select your database → SQL tab → paste everything below → Go

CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_id   INT AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(100) NOT NULL,
  attempted_at DATETIME     NOT NULL,
  INDEX (email, attempted_at)
) ENGINE=InnoDB;
