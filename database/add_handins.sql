-- Hand-ins (FR-31): adds one table to an existing Teamsync database.
-- Run it ONCE on a database made before this change. New databases from schema.sql already have it.
-- Run it BEFORE you put the new files online: moving a task to Review saves its link here.
--
-- Mac XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/add_handins.sql
-- Online:     importing database/online.sql again adds it too (and refreshes the demo data)

CREATE TABLE IF NOT EXISTS task_handins (
  handin_id  INT AUTO_INCREMENT PRIMARY KEY,
  task_id    INT          NOT NULL,
  user_id    INT          NULL,
  link       VARCHAR(500) NOT NULL,
  created_at DATETIME     NOT NULL,
  INDEX (task_id, created_at),
  FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
