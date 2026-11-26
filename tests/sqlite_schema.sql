-- The Teamsync tables for the automated tests, in SQLite.
-- The tests build a fresh database in memory from this file, so real data is never touched.
-- Keep it in step with database/schema.sql (same tables and columns; SQLite types).

CREATE TABLE users (
  user_id       INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('leader', 'member', 'professor')),
  created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE projects (
  project_id  INTEGER PRIMARY KEY AUTOINCREMENT,
  title       TEXT NOT NULL,
  invite_code TEXT NOT NULL UNIQUE,
  leader_id   INTEGER NOT NULL REFERENCES users(user_id),
  created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE project_members (
  project_id INTEGER NOT NULL REFERENCES projects(project_id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  joined_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, user_id)
);

CREATE TABLE tasks (
  task_id          INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id       INTEGER NOT NULL REFERENCES projects(project_id) ON DELETE CASCADE,
  assignee_id      INTEGER NULL REFERENCES users(user_id) ON DELETE SET NULL,
  title            TEXT NOT NULL,
  status           TEXT NOT NULL DEFAULT 'todo' CHECK (status IN ('todo', 'in_progress', 'review', 'done')),
  priority         TEXT NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
  due_date         TEXT NULL,
  reminder_sent_at TEXT NULL
);

CREATE TABLE password_resets (
  reset_id   INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE login_attempts (
  attempt_id   INTEGER PRIMARY KEY AUTOINCREMENT,
  email        TEXT NOT NULL,
  attempted_at TEXT NOT NULL
);
CREATE INDEX login_attempts_email ON login_attempts (email, attempted_at);

CREATE TABLE task_comments (
  comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id    INTEGER NOT NULL REFERENCES tasks(task_id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  body       TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX task_comments_task ON task_comments (task_id, created_at);

CREATE TABLE task_events (
  event_id    INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id     INTEGER NOT NULL REFERENCES tasks(task_id) ON DELETE CASCADE,
  user_id     INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
  from_status TEXT,
  to_status   TEXT NOT NULL,
  created_at  TEXT NOT NULL
);
CREATE INDEX task_events_task ON task_events (task_id, created_at);

CREATE TABLE task_handins (
  handin_id  INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id    INTEGER NOT NULL REFERENCES tasks(task_id) ON DELETE CASCADE,
  user_id    INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
  link       TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX task_handins_task ON task_handins (task_id, created_at);
