-- Sample tasks for testing the Kanban board.
-- Adds 6 tasks to the newest project, all assigned to its team leader.
-- Run it in phpMyAdmin (select the Teamsync database → SQL tab) or in the Terminal:
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/sample_tasks.sql

SET @project = (SELECT MAX(project_id) FROM projects);
SET @leader  = (SELECT leader_id FROM projects WHERE project_id = @project);

INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES
  (@project, @leader, 'Write requirements document',  'done',        'high',   CURDATE() - INTERVAL 3 DAY),
  (@project, @leader, 'Draw UML diagrams',            'done',        'medium', CURDATE() - INTERVAL 1 DAY),
  (@project, @leader, 'Build Kanban board page',      'in_progress', 'high',   CURDATE() + INTERVAL 2 DAY),
  (@project, @leader, 'Add and edit task form',       'todo',        'high',   CURDATE() + INTERVAL 5 DAY),
  (@project, @leader, 'Write test cases for login',   'todo',        'medium', CURDATE() - INTERVAL 1 DAY),
  (@project, @leader, 'Prepare final presentation',   'todo',        'low',    NULL);
