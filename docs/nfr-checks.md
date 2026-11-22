# Non-functional requirement checks

Checked on Nov 20, 2026 (Sprint 3) against SRS v1.4, issue #44.

**Result: 14 of 16 pass, 1 passes in part, 1 is not checked yet.** One problem was found and fixed: the Delete buttons under comments were 32 px tall instead of at least 40 px (NFR-09). The open items need people or other computers; they are listed at the end with an owner.

How: the browser checks ran with a Playwright script against a test copy of the app (PHP's built-in server with SQLite, the demo data, and one extra project with 100 tasks), plus a review of the code, the database schema and the GitHub history. Lighthouse ran on the live site.

| NFR | Requirement (short) | How we checked | Result |
|---|---|---|---|
| NFR-01 | Board with 100 tasks loads in 2 s or less | Opened a board with 100 tasks 7 times and read the browser's page timing | **Pass**: 54 ms (median); the server answered in 6 ms. Measured on the test copy, not on XAMPP |
| NFR-02 | Moving a card is saved and shown in 1 s or less | Dragged a card to another column 5 times and checked the database | **Pass**: 143 ms (median), including the drag itself |
| NFR-03 | Passwords stored only as bcrypt hashes | Signed up a new user, then read the `users` table | **Pass**: all 13 values start with `$2y$`; the password itself appears nowhere |
| NFR-04 | Every query is a prepared statement | Typed `' OR 1=1 --` into the login and join forms; searched the code for `query()` and `exec()` | **Pass**: "Wrong email or password." and "Invite code not found."; 0 direct queries and 45 prepared statements. Two `LIMIT` values are joined into SQL, both numbers set in code |
| NFR-05 | Every form that changes data needs a CSRF token | Sent 14 data-changing requests without the token: log in, sign up, log out, forgot and reset password, create and join a project, add, edit and delete a task, move a task, send reminders, add and delete a comment | **Pass**: all 14 refused with HTTP 400 |
| NFR-06 | All user text is escaped | Put `<script>` and `<img onerror>` in a project title, a task title and a comment; opened 7 pages, including the professor dashboard | **Pass**: 0 alerts, 0 injected elements; the tags show as text |
| NFR-07 | Permissions are checked on the server | 16 requests as a leader of another team, a member, a professor and a logged-out visitor | **Pass**: the 13 that must be refused got HTTP 403; the 2 allowed reads got 200; logged-out visitors go to the login page |
| NFR-08 | A new user signs up, joins and moves a task in 3 minutes or less | Needs a timed test with 3 classmates | **Not checked yet** |
| NFR-09 | Buttons at least 40 px tall, every field labelled, text contrast 4.5:1 or more; Lighthouse accessibility 90+ | A script measured every button, field and text on 18 pages for all roles; Lighthouse on the live login page | **Pass after a fix**: every field has a label and all text reaches 4.5:1 (3:1 for large text). The comment Delete buttons were 32 px, now 40 px. Lighthouse accessibility: 100 |
| NFR-10 | Works in Chrome, Safari and Edge, and at 390 px phone width | Chromium 153 (the engine of Chrome and Edge): log in, board, drag-and-drop, buttons, task page. Safari on a Mac, by hand on the live site: log in, drag a card, write a comment. iPhone 13 size (390 px): 9 pages | **Pass**: Chrome/Edge engine, Safari (Nov 23) and phone width pass, with no page wider than the screen and the board columns stacked |
| NFR-11 | A project and its leader membership are saved together or not at all | Automated test "FR-06 if saving a project fails, no half-made project is left" | **Pass** |
| NFR-12 | Every change reaches `main` through a pull request | `git log --first-parent main` | **Pass since Nov 11**: 24 of the 27 changes on `main` came through pull requests. The other 3 are from Nov 8–11, before the rule: the initial commit, the starter project and SRS v1.0 |
| NFR-13 | Runs on XAMPP on Windows and macOS | XAMPP on macOS (Kabir), and the automated tests on Linux with PHP 8.2 and 8.3 | **Pass in part**: Windows is not checked yet |
| NFR-14 | Stores only what it needs; passwords never shown | Read `database/schema.sql` and searched the code for `password_hash` | **Pass**: names, emails, roles, project and task data, comments, hashed reset links and login-attempt times. No student ID or phone number. `password_hash` is only written and checked, never shown |
| NFR-15 | Reset links: 64 random characters, stored only as a SHA-256 hash, one email per 2 minutes | Automated tests under "Password reset (FR-26)" | **Pass** |
| NFR-16 | The automated tests run on every push and pull request; merge only when they pass | `.github/workflows/tests.yml` and the pull requests on GitHub | **Pass** (since Nov 20): the tests run on every push and pull request with PHP 8.2 and 8.3, and a GitHub ruleset on `main` now blocks merging until both pass. It also blocks deleting `main` and force pushes |

## Still to do

| NFR | What to do | Who |
|---|---|---|
| NFR-08 | Ask 3 classmates who have never seen Teamsync to sign up on the live site, join with an invite code and move a task, without help. Time each one; 3 minutes or less passes | Fahim Iftekhar Zaman, with #14 |
| NFR-13 | Set Teamsync up from the README on a Windows laptop with XAMPP | A team member with a Windows laptop |
