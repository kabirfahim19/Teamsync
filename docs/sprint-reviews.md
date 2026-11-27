# Teamsync – Sprint Reviews and Retrospectives

Sprints 0 to 2 are done and Sprint 3 is under way: all 31 functional requirements and all 12 user stories are merged into `main`, and the app runs online at https://teamsync-du.fwh.is. What is left of Sprint 3 is manual testing, the timed usability test, diagrams, slides and the user guide.

Requirements: `docs/Teamsync-Requirements-v1.5.pdf` (SRS v1.5, Nov 27, 2026). All 31 functional requirements are done.

## Summary

| Sprint | Planned dates | Goal | Issues (PR) | Merged |
| --- | --- | --- | --- | --- |
| 0 | Nov 3 – Nov 9 | Setup, sign-up, login, roles | Starter commit | Nov 8 |
| 1 | Nov 10 – Nov 16 | Projects, invite codes, Kanban board, tasks | #1 (#5), #2 (#6), #3 (#7), #4 (#8) | Nov 10 |
| 2 | Nov 17 – Nov 23 | Contribution report, professor view, reminders, drag-and-drop | #9 (#16), #10 (#17), #11 (#18), #12 (#19) | Nov 11 – Nov 12 |
| 3 | Nov 24 – Nov 30 | Online site, professor code, email reminders, password reset, login protection, automated tests, task comments; testing, UML/ER update, final slides | #25 (#26), #28 (#30), #31 (#32), #33 (#34), #35 (#36), #38 (#37), #48 (#49), #50 (#51), #54 (#55), #56 (#57), #58 (#60), #59 (#61), #62 (#63); #13, #14, #15 open | Nov 13 – 26 (code) |

Sprints 1 and 2 finished well before their planned dates, so Sprint 3 started early, on Nov 13.

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

## Sprint 3 – so far (Nov 13 – 16)

**Review**

- App put online at https://teamsync-du.fwh.is (InfinityFree: PHP 8.3 and MySQL, free) with three sample teams; README links to it (#24)
- A drag that gets a web page instead of JSON back now shows a clear message (#23)
- #25 Signing up as Professor needs a secret professor code – FR-25 (#26). Found because the site was now public: anyone could sign up as Professor and see every team.
- SRS v1.2 with FR-25 (#27)
- #28 Email reminder to the assignee the day before a task is due – FR-19 (#30). Sent through Gmail SMTP with an App Password; the dashboard starts the sending in the background, because free hosting has no scheduled jobs.
- Guide for team members (PDF) and GitHub invitations sent
- #31 Forgot password: a reset link by email that works once, for one hour; only a hash of the link is stored – FR-26 (#32)
- #33 Login protection: 5 wrong passwords within 15 minutes block that email for 15 minutes – FR-27 (#34)
- #35 Automated tests: `tests/run.php` checks 59 rules, each named after its FR or TC; GitHub Actions runs them on every push and pull request (#36)
- #38 Task comments: each task has its own page where the team discusses it; the card shows the number of comments; professors can only read – FR-28 (#37). Also online since Nov 16.
- SRS v1.3 (Nov 16): FR-26 to FR-28, US-10, US-11, UC-09, UC-10, NFR-15, NFR-16 and TC-26 to TC-28

**Retrospective (so far)**

| Went well | Went wrong | Change |
| --- | --- | --- |
| Going online showed a real security gap, and it was fixed the same night | Free hosting has no PHP `mail()`, no cron, and a database clock in another time zone | Gmail SMTP; reminders run when someone opens the dashboard; dates come from PHP (Korean time), never from the database clock |
| Every change still went through an issue and a pull request | Editing `config/db.php` in `nano` went wrong on screen | Change config values with one-line commands and check with `php -l` |
| | The same issue was opened twice (#28, #29) | Look at the issue list before opening a new one |
| Breaking the code on purpose made the automated tests fail, so the tests really catch mistakes | GitHub refused to push the test workflow: the access token had no `workflow` permission | Added the `workflow` scope to the token |
| Comments went online in two steps (database first, then files), so the live board never broke | A pull request was opened before its issue, so it took the issue's number (#37) | Open the issue first, then the branch and the pull request |
| | The reset email did not arrive at first | A one-off mail check script showed that sending worked: the account existed only on XAMPP, not online. Both emails reached Gmail on Nov 18 |

---

## Sprint 3 – Nov 17 to Nov 27

**Review**

- #44 Non-functional checks against SRS v1.4: 14 of 16 pass; the comment Delete buttons were made 40 px tall (#45); `main` now needs green tests to merge (#46); Safari checked (#47). Results in `docs/nfr-checks.md`
- #48 Login session cookie: HttpOnly, SameSite=Lax, Secure on https, strict session IDs (#49)
- #50 Security headers: Content-Security-Policy, no framing, nosniff (#51)
- #54 One zip for the live site (`tools/online-zip.sh`) and demo data that uses the Korean date, so `online.sql` can be imported at any time of day (#55)
- #56 A browser whose account was deleted or changed is logged out at its next click (#57)
- #58 Task history: the task page shows who added the task and who moved it where, and when – FR-29 (#60)
- #59 Leader approval: members move finished tasks to Review; only the leader moves a task into Done or out of it, and the leader's dashboard lists what is waiting – FR-30 (#61)
- #62 Hand-in link: moving a task to Review needs a link to the work, posted as a comment and shown in the approval list; links in comments are clickable – FR-31 (#63)
- The team joined our own project on the live site, and each member's Sprint 3 work is a card on its board
- SRS v1.5 (Nov 27): FR-29 to FR-31, US-12, UC-11, TC-29 to TC-31; 97 automated tests

**Retrospective**

| Went well | Went wrong | Change |
| --- | --- | --- |
| Running our own Sprint 3 in Teamsync showed a real gap: a member could drag unfinished work to Done. History, approval and hand-in links fixed it in one day | Importing the demo data again left an old browser logged in to an account that no longer existed | The account behind every session is checked on each request (#56) |
| Each new rule came with its tests first, so the stale-session bug was shown failing before it was fixed | `version.txt` showed an old commit after an upload | It was the browser cache: hard refresh (Cmd+Shift+R) or add `?x=1` to the address |
| Database changes went online first and the files second, so the live board never broke | The database file and the zip were confused in the host's File Manager | `online.sql` goes to phpMyAdmin (Import), the zip goes to the File Manager; the README lists the order |
| | A pull request description was posted as an issue comment | Open the pull request from `github.com/<owner>/<repo>/pull/new/<branch>` |

---

## Process retrospective (Sprints 0–2)

- **The code is ahead of the team.** All coding so far was done by Kabir Md Fahim. The other three members have not yet accepted their GitHub invitations, so issues #13 to #15 are still waiting for them.
- **Testing is the biggest risk.** Features were tested by the developer only. No one else has run the test cases yet.
- **Estimates were too cautious.** Sprints 1 and 2 were planned for two weeks but took three days. The extra time goes to testing and the presentation.

## Action items for Sprint 3

| # | Action | Owner | Issue |
| --- | --- | --- | --- |
| 1 | Accept the GitHub invitation and take over your issue | Miraj Md, Fahim Iftekhar Zaman, Hossain Md Fahim Al | – |
| 2 | Run test cases TC-01 to TC-28 (SRS §9) on a fresh XAMPP install; open a bug issue for each failure | Fahim Iftekhar Zaman | #14 |
| 3 | Update the Use Case, Class and ER diagrams to match the final code (new: UC-09, UC-10; tables `password_resets`, `login_attempts`, `task_comments`) | Miraj Md | #13 |
| 4 | Final presentation slides with real screenshots and a demo script | Hossain Md Fahim Al | #15 |
| 5 | Fix every bug issue from item 2 before Week 14 | Kabir Md Fahim | bug issues |
| 6 | Show SRS v1.2, the live site and the email reminder to the professor (Sprint review, Mon Nov 16) | Kabir Md Fahim | – |
| 7 | Decide whether FR-19 (email) is dropped or built: built (#28) | Team | #28 |
| 8 | Get a reminder and a reset email into a real Gmail inbox, then mark FR-19 Done in the SRS: done Nov 18 (SRS v1.4) | Kabir Md Fahim | #28 |

Merge checklist kept from the retrospectives: `chmod 644` new files → test as every role → hard refresh → push → pull request → merge → `git pull`.
