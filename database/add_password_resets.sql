-- Password reset by email (FR-26): adds one table to an existing Teamsync database.
-- Run it ONCE on a database made before this change. New databases from schema.sql already have it.
--
-- Mac XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/add_password_resets.sql
-- Online:     phpMyAdmin → select your database → SQL tab → paste everything below → Go

CREATE TABLE IF NOT EXISTS password_resets (
  reset_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT      NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
