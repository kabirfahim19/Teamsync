-- Teamsync database
-- Import this file in phpMyAdmin (Import tab) or run it in the MySQL console.
-- It creates the Teamsync database and the four tables from our ER diagram.

CREATE DATABASE IF NOT EXISTS Teamsync
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE Teamsync;

-- Everyone who can log in
CREATE TABLE IF NOT EXISTS users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(50)  NOT NULL,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('leader', 'member', 'professor') NOT NULL DEFAULT 'member',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- One row per team project
CREATE TABLE IF NOT EXISTS projects (
  project_id  INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(100) NOT NULL,
  invite_code CHAR(7)      NOT NULL UNIQUE,   -- for example TS-4821
  leader_id   INT          NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (leader_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Which user is in which project (many-to-many)
CREATE TABLE IF NOT EXISTS project_members (
  project_id INT      NOT NULL,
  user_id    INT      NOT NULL,
  joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tasks on the Kanban board
CREATE TABLE IF NOT EXISTS tasks (
  task_id     INT AUTO_INCREMENT PRIMARY KEY,
  project_id  INT          NOT NULL,
  assignee_id INT          NULL,
  title       VARCHAR(100) NOT NULL,
  status      ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo',
  priority    ENUM('low', 'medium', 'high')       NOT NULL DEFAULT 'medium',
  due_date    DATE         NULL,
  reminder_sent_at DATETIME NULL,              -- when the email reminder went out (FR-19)
  FOREIGN KEY (project_id)  REFERENCES projects(project_id) ON DELETE CASCADE,
  FOREIGN KEY (assignee_id) REFERENCES users(user_id)       ON DELETE SET NULL
) ENGINE=InnoDB;
