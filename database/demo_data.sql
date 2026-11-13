-- Demo data for presentations and testing.
-- Adds 3 sample teams (12 users, 26 tasks). Every demo account uses the password: demo1234
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

-- 3. Team 1: Campus Cafe Order App (12 tasks, 4 done)
INSERT INTO projects (title, invite_code, leader_id) VALUES ('Campus Cafe Order App', 'TS-K7QM', @alex);
SET @p1 = LAST_INSERT_ID();
INSERT INTO project_members (project_id, user_id) VALUES (@p1, @alex), (@p1, @minji), (@p1, @daniel), (@p1, @sara);
INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES
  (@p1, @alex,   'Write requirements',          'done',        'high',   CURDATE() - INTERVAL 6 DAY),
  (@p1, @minji,  'Design the ER diagram',       'done',        'medium', CURDATE() - INTERVAL 4 DAY),
  (@p1, @daniel, 'Build the login page',        'done',        'high',   CURDATE() - INTERVAL 3 DAY),
  (@p1, @sara,   'Menu page layout',            'done',        'medium', CURDATE() - INTERVAL 1 DAY),
  (@p1, @daniel, 'Order cart and checkout',     'in_progress', 'high',   CURDATE() + INTERVAL 1 DAY),
  (@p1, @minji,  'Draw the UML class diagram',  'in_progress', 'medium', CURDATE() + INTERVAL 3 DAY),
  (@p1, @alex,   'Admin menu editor',           'in_progress', 'medium', CURDATE() + INTERVAL 2 DAY),
  (@p1, @alex,   'Review team pull requests',   'todo',        'high',   CURDATE()),
  (@p1, @sara,   'Write test cases',            'todo',        'high',   CURDATE() - INTERVAL 1 DAY),
  (@p1, @minji,  'Order history page',          'todo',        'medium', NULL),
  (@p1, @sara,   'Payment confirmation email',  'todo',        'low',    CURDATE() + INTERVAL 6 DAY),
  (@p1, @alex,   'Prepare final slides',        'todo',        'low',    CURDATE() + INTERVAL 14 DAY);

-- 4. Team 2: Library Seat Finder (8 tasks, 6 done)
INSERT INTO projects (title, invite_code, leader_id) VALUES ('Library Seat Finder', 'TS-R4WX', @yuna);
SET @p2 = LAST_INSERT_ID();
INSERT INTO project_members (project_id, user_id) VALUES (@p2, @yuna), (@p2, @jisoo), (@p2, @tom);
INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES
  (@p2, @yuna,  'Requirements and use cases', 'done',        'high',   CURDATE() - INTERVAL 9 DAY),
  (@p2, @jisoo, 'Seat map page',              'done',        'high',   CURDATE() - INTERVAL 6 DAY),
  (@p2, @tom,   'Database design',            'done',        'medium', CURDATE() - INTERVAL 7 DAY),
  (@p2, @jisoo, 'Booking form',               'done',        'high',   CURDATE() - INTERVAL 3 DAY),
  (@p2, @tom,   'Login and roles',            'done',        'medium', CURDATE() - INTERVAL 4 DAY),
  (@p2, @yuna,  'Admin page',                 'done',        'medium', CURDATE() - INTERVAL 2 DAY),
  (@p2, @tom,   'Test the booking rules',     'in_progress', 'high',   CURDATE() + INTERVAL 2 DAY),
  (@p2, @yuna,  'Final presentation',         'todo',        'low',    CURDATE() + INTERVAL 18 DAY);

-- 5. Team 3: Bus Arrival Bot (6 tasks, 1 done, 2 overdue)
INSERT INTO projects (title, invite_code, leader_id) VALUES ('Bus Arrival Bot', 'TS-9HPD', @hyunwoo);
SET @p3 = LAST_INSERT_ID();
INSERT INTO project_members (project_id, user_id) VALUES (@p3, @hyunwoo), (@p3, @ana), (@p3, @mark), (@p3, @eunbi);
INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES
  (@p3, @hyunwoo, 'Project proposal',      'done',        'high',   CURDATE() - INTERVAL 10 DAY),
  (@p3, @ana,     'Collect bus stop data', 'in_progress', 'high',   CURDATE() - INTERVAL 2 DAY),
  (@p3, @mark,    'Chat bot replies',      'todo',        'high',   CURDATE() - INTERVAL 1 DAY),
  (@p3, @eunbi,   'Arrival time screen',   'todo',        'medium', CURDATE() + INTERVAL 3 DAY),
  (@p3, @ana,     'Favourite stops',       'todo',        'low',    CURDATE() + INTERVAL 7 DAY),
  (@p3, @hyunwoo, 'Test plan',             'todo',        'medium', CURDATE() + INTERVAL 5 DAY);
