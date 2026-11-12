# Teamsync – Sprint Reviews and Retrospectives

Sprints 0 to 2 are done: 23 of 24 functional requirements and all 9 user stories are merged into `main`. Sprint 3 is for testing, diagrams and slides.

Requirements: `docs/Teamsync-Requirements-v1.1.pdf` (SRS v1.1, Nov 12, 2026).

## Summary

| Sprint | Planned dates | Goal | Issues (PR) | Merged |
| --- | --- | --- | --- | --- |
| 0 | Nov 3 – Nov 9 | Setup, sign-up, login, roles | Starter commit | Nov 8 |
| 1 | Nov 10 – Nov 16 | Projects, invite codes, Kanban board, tasks | #1 (#5), #2 (#6), #3 (#7), #4 (#8) | Nov 10 |
| 2 | Nov 17 – Nov 23 | Contribution report, professor view, reminders, drag-and-drop | #9 (#16), #10 (#17), #11 (#18), #12 (#19) | Nov 11 – Nov 12 |
| 3 | Nov 24 – Nov 30 | Testing, UML/ER update, final slides | #13, #14, #15 | – |

Sprints 1 and 2 finished well before their planned dates, so Sprint 3 can start early.

---

## Sprint 0 – Setup and accounts

**Review (what was delivered)**

- GitHub repository, `README.md`, `.gitignore` (keeps `config/db.php` with the database password out of Git)
- Database `Teamsync` with four tables: `users`, `projects`, `project_members`, `tasks`
- Sign up, log in, log out, three roles (Team Leader, Team Member, Professor) – FR-01 to FR-04
- Security base: hashed passwords, PDO prepared statements, CSRF tokens, escaped output
- Requirements v0.1 (Oct 26) and v0.2 (Nov 9, after the professor asked for the process model and proper requirements)

**Retrospective**

| Went well | Went wrong | Change |
| --- | --- | --- |
| Small starter project ran on XAMPP on the first try | GitHub refused password login on `git push` | Use a personal access token |
| Scrum chosen and written down early | A token was shared outside the terminal and had to be revoked | Tokens only go into the terminal prompt, never into chat or files |

---

## Sprint 1 – Projects and the board

**Review**

- #1 Create a project with a unique invite code (`TS-XXXX`) – FR-05, FR-06
- #2 Join a project by invite code, case and spaces ignored – FR-07 to FR-09
- #3 Kanban board with To Do / In Progress / Done, overdue marking and progress bar – FR-10 to FR-12, FR-15, FR-16
- #4 Leader adds, edits and deletes tasks (delete asks first) – FR-13, FR-14
- SRS v1.0 written and exported to `docs/` (Nov 11)

**Retrospective**

| Went well | Went wrong | Change |
| --- | --- | --- |
| One branch and one pull request per issue; `closes #n` closed the issue automatically | New PHP files gave HTTP 500 because Apache could not read them | Run `chmod 644` on new files before testing |
| Every story had acceptance criteria to test against | Switched to `main` before merging the pull request, so the new files seemed to disappear | Merge on GitHub first, then `git checkout main` and `git pull` |
| | Old CSS was shown after changes | Hard refresh (Cmd+Shift+R) after every CSS or JS change |

---

## Sprint 2 – Report, professor view, reminders, drag-and-drop

**Review**

- #9 Contribution report per project: table and bar chart of done / in progress / to do per member – FR-20, FR-21
- #10 Professor dashboard of all teams, read-only boards and reports; every change request from a professor is refused – FR-22 to FR-24
- #11 "Due soon" list on the dashboard: own tasks that are overdue or due within 2 days – FR-18
- #12 Drag-and-drop between columns, saved without a reload; the buttons still work without JavaScript – FR-17
- SRS v1.1 (Nov 12): statuses, issue numbers and traceability updated

Not done: FR-19 (email one day before the due date). It is a *Could*, and XAMPP has no mail server by default, so it stays in the backlog.

**Retrospective**

| Went well | Went wrong | Change |
| --- | --- | --- |
| Each feature was tested for access rules (403 for the wrong role), CSRF and escaping before merging | Dragged cards jumped back because the browser still used the old `app.js` | Open the changed JS file once and hard refresh it |
| Drag-and-drop reuses the same server check as the buttons, so the rules are in one place | Progress bars on the professor dashboard were invisible (inline elements) | Check every new page at least once as each role |
| | The overdue colour was overridden in the reminder list | Same as above |

---

## Process retrospective (Sprints 0–2)

- **The code is ahead of the team.** All coding so far was done by Kabir Md Fahim. The other three members have not yet accepted their GitHub invitations, so issues #13 to #15 are still waiting for them.
- **Testing is the biggest risk.** Features were tested by the developer only. No one else has run the test cases yet.
- **Estimates were too cautious.** Sprints 1 and 2 were planned for two weeks but took three days. The extra time goes to testing and the presentation.

## Action items for Sprint 3

| # | Action | Owner | Issue |
| --- | --- | --- | --- |
| 1 | Accept the GitHub invitation and take over your issue | Miraj Md, Fahim Iftekhar Zaman, Hossain Md Fahim Al | – |
| 2 | Run test cases TC-01 to TC-24 (SRS §9) on a fresh XAMPP install; open a bug issue for each failure | Fahim Iftekhar Zaman | #14 |
| 3 | Update the Use Case, Class and ER diagrams to match the final code | Miraj Md | #13 |
| 4 | Final presentation slides with real screenshots and a demo script | Hossain Md Fahim Al | #15 |
| 5 | Fix every bug issue from item 2 before Week 14 | Kabir Md Fahim | bug issues |
| 6 | Show SRS v1.1 and the running app to the professor (Sprint review) | Kabir Md Fahim | – |
| 7 | Decide at Sprint 3 planning whether FR-19 (email) is dropped or built | Team | – |

Merge checklist kept from the retrospectives: `chmod 644` new files → test as every role → hard refresh → push → pull request → merge → `git pull`.
