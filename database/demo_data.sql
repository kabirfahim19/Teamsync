-- Demo data for presentations and testing.
-- Adds 3 sample teams (12 users, 26 tasks, 6 comments, and each task's history). Every demo account uses the password: demo1234
-- The database needs the task_comments and task_events tables (schema.sql, or database/add_task_comments.sql
-- and database/add_task_history.sql for an older database).
-- Running it again first removes the old demo data, so it always starts clean.
-- Your own accounts and projects are not touched.
--
-- Run it in phpMyAdmin (select the Teamsync database → SQL tab, paste, Go) or in the Terminal:
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/demo_data.sql
--
-- Log in with:
--   alex@demo.test   Team Leader of "Campus Cafe Order App"
--   minji@demo.test  Team Member of "Campus Cafe Order App"
--   prof@demo.test   Professor (sees all teams)

USE Teamsync;

-- 1. Remove old demo data (tasks and memberships go with their project)
DELETE FROM projects WHERE leader_id IN (SELECT user_id FROM users WHERE email LIKE '%@demo.test');
DELETE FROM users WHERE email LIKE '%@demo.test';

-- Tasks can wait in Review for the leader's approval (FR-30). A database made before that gets the new status here;
-- running it again changes nothing.
ALTER TABLE tasks MODIFY status ENUM('todo', 'in_progress', 'review', 'done') NOT NULL DEFAULT 'todo';

-- Today's date in Korea. A free host's database clock can be in another time zone, where CURDATE()
-- is still yesterday. With @today the demo dates are right whenever and wherever you import this file.
SET @today = DATE(UTC_TIMESTAMP() + INTERVAL 9 HOUR);

-- 2. Users (password for all: demo1234)
SET @pw = '$2y$10$5eSIfS2aHagrcM.f1sE.ueiBSxoxnj5yq3T5IWnKRfPfdVF7lJU.W';

INSERT INTO users (name, email, password_hash, role) VALUES
  ('Alex Kim',    'alex@demo.test',    @pw, 'leader'),
  ('Minji Park',  'minji@demo.test',   @pw, 'member'),
  ('Daniel Lee',  'daniel@demo.test',  @pw, 'member'),
  ('Sara Choi',   'sara@demo.test',    @pw, 'member'),
  ('Yuna Jeong',  'yuna@demo.test',    @pw, 'leader'),
  ('Jisoo Han',   'jisoo@demo.test',   @pw, 'member'),
  ('Tom Baker',   'tom@demo.test',     @pw, 'member'),
  ('Hyunwoo Lim', 'hyunwoo@demo.test', @pw, 'leader'),
  ('Ana Silva',   'ana@demo.test',     @pw, 'member'),
  ('Mark Cho',    'mark@demo.test',    @pw, 'member'),
  ('Eunbi Seo',   'eunbi@demo.test',   @pw, 'member'),
  ('Prof. Demo',  'prof@demo.test',    @pw, 'professor');

SET @alex    = (SELECT user_id FROM users WHERE email = 'alex@demo.test');
SET @minji   = (SELECT user_id FROM users WHERE email = 'minji@demo.test');
SET @daniel  = (SELECT user_id FROM users WHERE email = 'daniel@demo.test');
SET @sara    = (SELECT user_id FROM users WHERE email = 'sara@demo.test');
SET @yuna    = (SELECT user_id FROM users WHERE email = 'yuna@demo.test');
SET @jisoo   = (SELECT user_id FROM users WHERE email = 'jisoo@demo.test');
SET @tom     = (SELECT user_id FROM users WHERE email = 'tom@demo.test');
SET @hyunwoo = (SELECT user_id FROM users WHERE email = 'hyunwoo@demo.test');
SET @ana     = (SELECT user_id FROM users WHERE email = 'ana@demo.test');
SET @mark    = (SELECT user_id FROM users WHERE email = 'mark@demo.test');
SET @eunbi   = (SELECT user_id FROM users WHERE email = 'eunbi@demo.test');

-- 3. Team 1: Campus Cafe Order App (12 tasks, 4 done, 1 waiting for Alex's approval)
INSERT INTO projects (title, invite_code, leader_id) VALUES ('Campus Cafe Order App', 'TS-K7QM', @alex);
SET @p1 = LAST_INSERT_ID();
INSERT INTO project_members (project_id, user_id) VALUES (@p1, @alex), (@p1, @minji), (@p1, @daniel), (@p1, @sara);
INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES
  (@p1, @alex,   'Write requirements',          'done',        'high',   @today - INTERVAL 6 DAY),
  (@p1, @minji,  'Design the ER diagram',       'done',        'medium', @today - INTERVAL 4 DAY),
  (@p1, @daniel, 'Build the login page',        'done',        'high',   @today - INTERVAL 3 DAY),
  (@p1, @sara,   'Menu page layout',            'done',        'medium', @today - INTERVAL 1 DAY),
  (@p1, @daniel, 'Order cart and checkout',     'review',      'high',   @today + INTERVAL 1 DAY),
  (@p1, @minji,  'Draw the UML class diagram',  'in_progress', 'medium', @today + INTERVAL 3 DAY),
  (@p1, @alex,   'Admin menu editor',           'in_progress', 'medium', @today + INTERVAL 2 DAY),
  (@p1, @alex,   'Review team pull requests',   'todo',        'high',   @today),
  (@p1, @sara,   'Write test cases',            'todo',        'high',   @today - INTERVAL 1 DAY),
  (@p1, @minji,  'Order history page',          'todo',        'medium', NULL),
  (@p1, @sara,   'Payment confirmation email',  'todo',        'low',    @today + INTERVAL 6 DAY),
  (@p1, @alex,   'Prepare final slides',        'todo',        'low',    @today + INTERVAL 14 DAY);

-- 4. Team 2: Library Seat Finder (8 tasks, 6 done)
INSERT INTO projects (title, invite_code, leader_id) VALUES ('Library Seat Finder', 'TS-R4WX', @yuna);
SET @p2 = LAST_INSERT_ID();
INSERT INTO project_members (project_id, user_id) VALUES (@p2, @yuna), (@p2, @jisoo), (@p2, @tom);
INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES
  (@p2, @yuna,  'Requirements and use cases', 'done',        'high',   @today - INTERVAL 9 DAY),
  (@p2, @jisoo, 'Seat map page',              'done',        'high',   @today - INTERVAL 6 DAY),
  (@p2, @tom,   'Database design',            'done',        'medium', @today - INTERVAL 7 DAY),
  (@p2, @jisoo, 'Booking form',               'done',        'high',   @today - INTERVAL 3 DAY),
  (@p2, @tom,   'Login and roles',            'done',        'medium', @today - INTERVAL 4 DAY),
  (@p2, @yuna,  'Admin page',                 'done',        'medium', @today - INTERVAL 2 DAY),
  (@p2, @tom,   'Test the booking rules',     'in_progress', 'high',   @today + INTERVAL 2 DAY),
  (@p2, @yuna,  'Final presentation',         'todo',        'low',    @today + INTERVAL 18 DAY);

-- 5. Team 3: Bus Arrival Bot (6 tasks, 1 done, 2 overdue)
INSERT INTO projects (title, invite_code, leader_id) VALUES ('Bus Arrival Bot', 'TS-9HPD', @hyunwoo);
SET @p3 = LAST_INSERT_ID();
INSERT INTO project_members (project_id, user_id) VALUES (@p3, @hyunwoo), (@p3, @ana), (@p3, @mark), (@p3, @eunbi);
INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES
  (@p3, @hyunwoo, 'Project proposal',      'done',        'high',   @today - INTERVAL 10 DAY),
  (@p3, @ana,     'Collect bus stop data', 'in_progress', 'high',   @today - INTERVAL 2 DAY),
  (@p3, @mark,    'Chat bot replies',      'todo',        'high',   @today - INTERVAL 1 DAY),
  (@p3, @eunbi,   'Arrival time screen',   'todo',        'medium', @today + INTERVAL 3 DAY),
  (@p3, @ana,     'Favourite stops',       'todo',        'low',    @today + INTERVAL 7 DAY),
  (@p3, @hyunwoo, 'Test plan',             'todo',        'medium', @today + INTERVAL 5 DAY);

-- 6. History (FR-29): each leader added the tasks two weeks ago; each member started their own,
--    handed it in to Review, and the leader approved it to Done (FR-30). Leaders finish their own tasks directly.
INSERT INTO task_events (task_id, user_id, from_status, to_status, created_at)
SELECT t.task_id, p.leader_id, NULL, 'todo', TIMESTAMP(@today - INTERVAL 14 DAY, '10:00:00')
FROM tasks t JOIN projects p ON p.project_id = t.project_id
WHERE t.project_id IN (@p1, @p2, @p3);

INSERT INTO task_events (task_id, user_id, from_status, to_status, created_at)
SELECT t.task_id, t.assignee_id, 'todo', 'in_progress',
       CASE WHEN t.status = 'done' THEN TIMESTAMP(t.due_date - INTERVAL 3 DAY, '14:00:00')
            ELSE TIMESTAMP(@today - INTERVAL 2 DAY, '14:00:00') END
FROM tasks t
WHERE t.project_id IN (@p1, @p2, @p3) AND t.status IN ('in_progress', 'review', 'done');

INSERT INTO task_events (task_id, user_id, from_status, to_status, created_at)
SELECT t.task_id, t.assignee_id, 'in_progress', 'review',
       CASE WHEN t.status = 'done' THEN TIMESTAMP(t.due_date - INTERVAL 1 DAY, '15:00:00')
            ELSE TIMESTAMP(@today, '09:35:00') END
FROM tasks t JOIN projects p ON p.project_id = t.project_id
WHERE t.project_id IN (@p1, @p2, @p3) AND t.status IN ('review', 'done') AND t.assignee_id <> p.leader_id;

INSERT INTO task_events (task_id, user_id, from_status, to_status, created_at)
SELECT t.task_id, p.leader_id, CASE WHEN t.assignee_id = p.leader_id THEN 'in_progress' ELSE 'review' END, 'done',
       TIMESTAMP(t.due_date - INTERVAL 1 DAY, '18:00:00')
FROM tasks t JOIN projects p ON p.project_id = t.project_id
WHERE t.project_id IN (@p1, @p2, @p3) AND t.status = 'done';

-- 7. Comments on Team 1 tasks (FR-28)
SET @cart  = (SELECT task_id FROM tasks WHERE project_id = @p1 AND title = 'Order cart and checkout');
SET @tests = (SELECT task_id FROM tasks WHERE project_id = @p1 AND title = 'Write test cases');
SET @uml   = (SELECT task_id FROM tasks WHERE project_id = @p1 AND title = 'Draw the UML class diagram');
INSERT INTO task_comments (task_id, user_id, body, created_at) VALUES
  (@cart,  @daniel, 'The cart works. I still need the total price with the student discount.', TIMESTAMP(@today - INTERVAL 1 DAY, '15:20:00')),
  (@cart,  @alex,   'The discount is 10% with a student card. Can you finish it by tomorrow?',  TIMESTAMP(@today - INTERVAL 1 DAY, '16:05:00')),
  (@cart,  @daniel, 'Pushed it last night. The card is in Review now.',                         TIMESTAMP(@today, '09:40:00')),
  (@tests, @sara,   'I am waiting for the checkout page, so I will test login and the menu first.', TIMESTAMP(@today - INTERVAL 2 DAY, '11:00:00')),
  (@tests, @minji,  'I can help with the menu page tests.',                                      TIMESTAMP(@today - INTERVAL 2 DAY, '13:30:00')),
  (@uml,   @minji,  'The first version is in our shared folder. Comments welcome!',              TIMESTAMP(@today - INTERVAL 3 DAY, '20:15:00'));
