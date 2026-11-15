-- Task comments (FR-28): adds one table to an existing Teamsync database.
-- Run it ONCE on a database made before this change. New databases from schema.sql already have it.
--
-- Mac XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/add_task_comments.sql
-- Online:     phpMyAdmin → select your database → SQL tab → paste everything below → Go

CREATE TABLE IF NOT EXISTS task_comments (
  comment_id INT AUTO_INCREMENT PRIMARY KEY,
  task_id    INT      NOT NULL,
  user_id    INT      NOT NULL,
  body       TEXT     NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX (task_id, created_at),
  FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
