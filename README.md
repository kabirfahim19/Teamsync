# Teamsync

**Student Team Project & Task Tracker**
Software Engineering team project · Dong-eui University · Fall 2026

Teamsync is a web application where a student team can plan, assign and track its tasks in one place, and where the professor can follow each team's progress.

## Team

| Name | Student ID | Role |
|---|---|---|
| Kabir Md Fahim | 20244154 | Team Leader |
| Miraj Md | 20244166 | Member |
| Fahim Iftekhar Zaman | 20244229 | Member |
| Hossain Md Fahim Al | 20244229 | Member |

## Features

- Sign-up and login with role-based access (Team Leader, Team Member, Professor)
- Create a project and join it with an invite code
- Kanban board: To Do, In Progress, Done
- Tasks with assignee, deadline and priority
- Deadline reminders
- Contribution report for each member
- Professor dashboard with every team's progress

## Tech stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP 8 |
| Database | MySQL / MariaDB |
| Local server | XAMPP |

## Run it on your computer

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Clone this repository into the `htdocs` folder:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/<username>/Teamsync.git
   ```
3. Open `http://localhost/phpmyadmin` → **Import** → choose `database/schema.sql` → **Import**.
   This creates the `Teamsync` database and its four tables.
4. Copy `config/db.example.php` and rename the copy to `config/db.php`.
   The XAMPP defaults (user `root`, empty password) already work. `config/db.php` is in `.gitignore`, so passwords never go to GitHub.
5. Open `http://localhost/Teamsync`, create an account and log in.

## Folder structure

```
Teamsync/
├── index.php            Log in page
├── register.php         Sign up page
├── dashboard.php        Page after login
├── logout.php
├── config/
│   └── db.example.php   Database settings (copy to db.php)
├── includes/
│   ├── auth.php         Session, login check, CSRF, helpers
│   ├── header.php
│   └── footer.php
├── api/                 PHP endpoints for the board (update_task.php …)
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── database/
│   └── schema.sql       Tables: users, projects, project_members, tasks
├── docs/                UML diagrams, ER diagram, requirements
└── tests/               Test cases
```

## Database

| Table | Purpose |
|---|---|
| `users` | Everyone who can log in, with a role |
| `projects` | One row per team project, with its invite code and leader |
| `project_members` | Which user is in which project |
| `tasks` | Tasks on the Kanban board |

## How we work (Git workflow)

1. Get the latest code: `git pull`
2. Make a branch for your task: `git checkout -b feature/<task-name>`
3. Commit small changes often: `git add .` then `git commit -m "Add login form"`
4. Push the branch: `git push -u origin feature/<task-name>`
5. Open a **Pull Request** on GitHub. The team leader reviews and merges it into `main`.

Every task is an **Issue** on our GitHub Projects board, assigned to one member.

## Security

- Passwords are stored with `password_hash()` and checked with `password_verify()`
- All database queries use PDO prepared statements (no SQL injection)
- Every form has a CSRF token
- Pages check the user's role before showing data
- All output is escaped with `htmlspecialchars()` (no XSS)

## Schedule

| Week | Dates | Work |
|---|---|---|
| 9 | Oct 27 – Nov 2 | Requirements and roles |
| 10 | Nov 3 – 9 | UML and database design, project setup |
| 11 | Nov 10 – 16 | Login and Kanban board |
| 12 | Nov 17 – 23 | Reminders, contribution report, professor dashboard |
| 13 | Nov 24 – 30 | Testing and bug fixes |
| 14 | Dec 1 – 7 | Final presentation and demo |
