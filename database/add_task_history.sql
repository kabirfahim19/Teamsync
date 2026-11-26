-- Task history (FR-29): adds one table to an existing Teamsync database.
-- Run it ONCE on a database made before this change. New databases from schema.sql already have it.
--
-- Mac XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/add_task_history.sql
-- Online:     importing database/online.sql again adds it too (and refreshes the demo data)

CREATE TABLE IF NOT EXISTS task_events (
  event_id    INT AUTO_INCREMENT PRIMARY KEY,
  task_id     INT         NOT NULL,
  user_id     INT         NULL,
  from_status VARCHAR(20) NULL,            -- NULL when the task was added
  to_status   VARCHAR(20) NOT NULL,
  created_at  DATETIME    NOT NULL,
  INDEX (task_id, created_at),
  FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
