-- Email reminders (FR-19): adds one column to an existing Teamsync database.
-- Run it ONCE on a database made before this change. New databases from schema.sql already have it.
--
-- Mac XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root Teamsync < database/add_reminders.sql
-- Online:     phpMyAdmin → select your database → SQL tab → paste the line below → Go

ALTER TABLE tasks ADD COLUMN reminder_sent_at DATETIME NULL;
