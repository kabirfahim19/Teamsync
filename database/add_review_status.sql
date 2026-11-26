-- Review column (FR-30): lets tasks wait in Review for the leader's approval.
-- Run it ONCE on a database made before this change. New databases from schema.sql already have it.
-- Run it BEFORE you put the new files online: the new board needs the new status.
--
-- Mac XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/add_review_status.sql
-- Online:     importing database/online.sql again does it too (and refreshes the demo data)

ALTER TABLE tasks MODIFY status ENUM('todo', 'in_progress', 'review', 'done') NOT NULL DEFAULT 'todo';
