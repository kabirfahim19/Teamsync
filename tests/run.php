<?php
// Teamsync automated tests (issue #35).
//
// Run them from the Teamsync folder:
//   php tests/run.php
//   Mac XAMPP: /Applications/XAMPP/xamppfiles/bin/php tests/run.php
//
// Each test checks one rule of the app and names its requirement (FR) and test case (TC) from the SRS.
// Database tests get a new, empty SQLite database in memory, so your MySQL data is never touched
// and XAMPP does not need to be running. GitHub runs this file on every push (.github/workflows/tests.yml).
// The pages themselves (forms, HTTP 403 answers, drag and drop) are still checked by hand with the SRS test cases.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);   // never run from a browser
    exit;
}

date_default_timezone_set('Asia/Seoul');   // same as includes/auth.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// A PHP warning or notice inside a test makes that test fail.
set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level)) {
        return false;   // the warning was silenced with @
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

define('APP_DIR', dirname(__DIR__));

// e() lives in includes/auth.php, but auth.php also starts a session and connects to MySQL,
// so the tests use this copy of it instead of loading auth.php.
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Email settings, as in config/db.php. Email is off unless a test turns it on.
$MAIL_HOST = $MAIL_USER = $MAIL_PASS = $SITE_URL = '';
$MAIL_PORT = 587;
$MAIL_SECURE = 'tls';
$MAIL_FROM_NAME = 'Teamsync';

require_once APP_DIR . '/includes/projects.php';
require_once APP_DIR . '/includes/tasks.php';
require_once APP_DIR . '/includes/reports.php';
require_once APP_DIR . '/includes/mail.php';
require_once APP_DIR . '/includes/reminders.php';
require_once APP_DIR . '/includes/password_resets.php';
require_once APP_DIR . '/includes/login_guard.php';
require_once APP_DIR . '/includes/comments.php';
require_once APP_DIR . '/includes/session.php';
require_once APP_DIR . '/includes/security_headers.php';


// ======================================================================
// A small test runner
// ======================================================================

final class TestFailure extends Exception {}

$results = ['passed' => 0, 'failed' => 0, 'skipped' => 0];
$hasSqlite = extension_loaded('pdo_sqlite');

function group(string $title): void
{
    echo "\n" . $title . "\n";
}

function test(string $name, callable $body): void
{
    global $results;
    $serverBefore = $_SERVER;
    try {
        $body();
        $results['passed']++;
        echo "  PASS  $name\n";
    } catch (Throwable $ex) {
        $results['failed']++;
        $where = $ex instanceof TestFailure
            ? ''
            : ' (' . get_class($ex) . ' in ' . basename($ex->getFile()) . ' line ' . $ex->getLine() . ')';
        echo "  FAIL  $name\n        " . $ex->getMessage() . $where . "\n";
    } finally {
        mail_off();                 // tests that turn email on never leak into the next test
        $_SERVER = $serverBefore;
    }
}

// A test that needs a database: it gets a new, empty one.
function db_test(string $name, callable $body): void
{
    global $hasSqlite, $results;
    if (!$hasSqlite) {
        $results['skipped']++;
        echo "  SKIP  $name (PHP has no pdo_sqlite)\n";
        return;
    }
    test($name, fn() => $body(fresh_db()));
}

function check(bool $condition, string $message = 'expected true, got false'): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

function check_same($expected, $actual, string $label = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(($label !== '' ? "$label: " : '') . 'expected ' . show($expected) . ', got ' . show($actual));
    }
}

function show($value): string
{
    return is_array($value)
        ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : var_export($value, true);
}


// ======================================================================
// Helpers for the tests
// ======================================================================

function fresh_db(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(file_get_contents(__DIR__ . '/sqlite_schema.sql'));
    return $pdo;
}

function add_user(PDO $pdo, string $name, string $email, string $role = 'member', string $password = 'demo1234'): int
{
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);   // low cost: the tests stay fast
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $hash, $role]);
    return (int) $pdo->lastInsertId();
}

function add_task(PDO $pdo, int $projectId, ?int $assigneeId, string $title, string $status = 'todo',
                  ?string $due = null, string $priority = 'medium'): int
{
    $stmt = $pdo->prepare('INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$projectId, $assigneeId, $title, $status, $priority, $due]);
    return (int) $pdo->lastInsertId();
}

// A project with a leader and two members, made the same way the app makes it.
function make_team(PDO $pdo): array
{
    $leader  = add_user($pdo, 'Yuna Park', 'yuna@demo.test', 'leader');
    $alex    = add_user($pdo, 'Alex Kim', 'alex@demo.test');
    $minji   = add_user($pdo, 'Minji Lee', 'minji@demo.test');
    $project = create_project($pdo, 'Campus Cafe Order App', $leader);
    join_project($pdo, $project['project_id'], $alex);
    join_project($pdo, $project['project_id'], $minji);
    return ['pid' => $project['project_id'], 'code' => $project['invite_code'],
            'leader' => $leader, 'alex' => $alex, 'minji' => $minji];
}

// A date counted from today, e.g. day(1) is tomorrow: "2026-11-16"
function day(int $offset): string
{
    return (new DateTime('today'))->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
}

function minutes_ago(int $minutes): string
{
    return (new DateTime("-$minutes minutes"))->format('Y-m-d H:i:s');
}

function value(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function titles(array $rows): array
{
    return array_column($rows, 'title');
}

function mail_on(array $settings = []): void
{
    $settings += ['host' => 'smtp.example.test', 'port' => 587, 'secure' => 'tls',
                  'user' => 'teamsync.bot@example.test', 'pass' => 'app-password'];
    $GLOBALS['MAIL_HOST']   = $settings['host'];
    $GLOBALS['MAIL_PORT']   = $settings['port'];
    $GLOBALS['MAIL_SECURE'] = $settings['secure'];
    $GLOBALS['MAIL_USER']   = $settings['user'];
    $GLOBALS['MAIL_PASS']   = $settings['pass'];
}

function mail_off(): void
{
    $GLOBALS['MAIL_HOST'] = $GLOBALS['MAIL_USER'] = $GLOBALS['MAIL_PASS'] = $GLOBALS['SITE_URL'] = '';
    $GLOBALS['MAIL_PORT'] = 587;
    $GLOBALS['MAIL_SECURE'] = 'tls';
}

// Table name => column names, read from a CREATE TABLE file (one column per line).
function schema_columns(string $sql): array
{
    $tables  = [];
    $current = null;
    foreach (preg_split('/\R/', $sql) as $line) {
        $line = trim(preg_replace('/--.*$/', '', $line));
        if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?(\w+)/i', $line, $m)) {
            $current = strtolower($m[1]);
            $tables[$current] = [];
        } elseif ($current !== null && str_starts_with($line, ')')) {
            $current = null;
        } elseif ($current !== null && preg_match('/^(\w+)\s/', $line, $m)
                  && !in_array(strtoupper($m[1]), ['PRIMARY', 'FOREIGN', 'INDEX', 'UNIQUE', 'KEY', 'CONSTRAINT'], true)) {
            $tables[$current][] = strtolower($m[1]);
        }
    }
    ksort($tables);
    return $tables;
}


// ======================================================================
// The tests
// ======================================================================

echo 'Teamsync automated tests (PHP ' . PHP_VERSION . ")\n";
if (!$hasSqlite) {
    echo "Note: this PHP has no pdo_sqlite, so the database tests are skipped.\n";
}


group('Projects and invite codes (FR-05 to FR-09)');

db_test('TC-05  a new project gets a code like TS-K7QM, without look-alike letters (O, 0, I, 1)', function (PDO $pdo) {
    $leader  = add_user($pdo, 'Yuna Park', 'yuna@demo.test', 'leader');
    $project = create_project($pdo, 'Campus Cafe Order App', $leader);
    check((bool) preg_match('/^TS-[A-HJ-NP-Z2-9]{4}$/', $project['invite_code']), 'bad code ' . $project['invite_code']);
    check_same($project['invite_code'], value($pdo, 'SELECT invite_code FROM projects WHERE project_id = ?', [$project['project_id']]));
});

db_test('TC-05  2,000 generated codes are all different and all easy to read', function (PDO $pdo) {
    $leader = add_user($pdo, 'Yuna Park', 'yuna@demo.test', 'leader');
    $insert = $pdo->prepare('INSERT INTO projects (title, invite_code, leader_id) VALUES (?, ?, ?)');
    $codes  = [];
    for ($i = 0; $i < 2000; $i++) {
        $code = generate_invite_code($pdo);
        $insert->execute(["Project $i", $code, $leader]);
        $codes[] = $code;
    }
    check_same(2000, count(array_unique($codes)), 'different codes');
    $hard = preg_grep('/^TS-[A-HJ-NP-Z2-9]{4}$/', $codes, PREG_GREP_INVERT);
    check($hard === [], 'codes with O, 0, I or 1: ' . implode(', ', array_slice($hard, 0, 5)));
});

db_test('TC-06  after creating a project, the leader is its first member', function (PDO $pdo) {
    $team = make_team($pdo);
    check(is_project_member($pdo, $team['pid'], $team['leader']), 'leader is not a member');
    $names = array_column(get_project_members($pdo, $team['pid']), 'name');
    check_same(['Yuna Park', 'Alex Kim', 'Minji Lee'], $names, 'members (leader first, then by name)');
});

db_test('FR-06  if saving a project fails, no half-made project is left', function (PDO $pdo) {
    try {
        create_project($pdo, 'Broken', 999);   // user 999 does not exist
        throw new TestFailure('create_project should have failed');
    } catch (PDOException $ex) {
        // expected
    }
    check_same(0, (int) value($pdo, 'SELECT COUNT(*) FROM projects'), 'projects left');
    check(!$pdo->inTransaction(), 'the transaction was not closed');
});

test('TC-07  joining with "ts kkk7" (lower case, space) finds the code TS-KKK7', function () {
    check_same('TS-KKK7', normalize_invite_code('ts kkk7'));
    check_same('TS-KKK7', normalize_invite_code('tskkk7'));
    check_same('TS-K7QM', normalize_invite_code('  TS-K7QM  '));
    check_same('TS-K7QM', normalize_invite_code('ts - k7 qm'));
});

test('TC-08  a code that cannot be right is refused', function () {
    foreach (['', 'TS-K7Q', 'TS-K7QMX', 'AB-K7QM', 'TS-K7Q!', 'K7QM'] as $bad) {
        check_same(null, normalize_invite_code($bad), "code '$bad'");
    }
});

db_test('TC-08  an unknown code finds no project, and joining twice is refused', function (PDO $pdo) {
    $team = make_team($pdo);
    check_same(null, find_project_by_code($pdo, 'TS-ZZZZ'), 'unknown code');
    check_same($team['pid'], (int) find_project_by_code($pdo, $team['code'])['project_id'], 'right code');
    check_same(false, join_project($pdo, $team['pid'], $team['alex']), 'second join');
    check_same(1, (int) value($pdo, 'SELECT COUNT(*) FROM project_members WHERE user_id = ?', [$team['alex']]), 'member rows');
});

db_test('TC-09  after joining, the user is a member of the project', function (PDO $pdo) {
    $team = make_team($pdo);
    $newcomer = add_user($pdo, 'Jun Choi', 'jun@demo.test');
    check(!is_project_member($pdo, $team['pid'], $newcomer), 'member before joining');
    check_same(true, join_project($pdo, $team['pid'], $newcomer), 'first join');
    check(is_project_member($pdo, $team['pid'], $newcomer), 'not a member after joining');
});


group('Tasks and the board (FR-10 to FR-15)');

db_test('TC-10  the board has three columns with the right tasks, high priority first', function (PDO $pdo) {
    $team = make_team($pdo);
    add_task($pdo, $team['pid'], $team['alex'], 'Low, tomorrow', 'todo', day(1), 'low');
    add_task($pdo, $team['pid'], $team['alex'], 'High, no date', 'todo', null, 'high');
    add_task($pdo, $team['pid'], $team['alex'], 'High, tomorrow', 'todo', day(1), 'high');
    add_task($pdo, $team['pid'], $team['minji'], 'Medium, today', 'todo', day(0), 'medium');
    add_task($pdo, $team['pid'], $team['minji'], 'Doing', 'in_progress', null);
    add_task($pdo, $team['pid'], null, 'Finished', 'done', null);
    $columns = get_tasks_by_status($pdo, $team['pid']);
    check_same(['todo', 'in_progress', 'done'], array_keys($columns), 'columns');
    check_same(['High, tomorrow', 'High, no date', 'Medium, today', 'Low, tomorrow'], titles($columns['todo']), 'To Do order');
    check_same(['Doing'], titles($columns['in_progress']), 'In Progress');
    check_same(['Finished'], titles($columns['done']), 'Done');
});

db_test('FR-10  an empty project still shows all three columns', function (PDO $pdo) {
    $team = make_team($pdo);
    check_same(['todo' => [], 'in_progress' => [], 'done' => []], get_tasks_by_status($pdo, $team['pid']));
});

test('TC-11  a task past its due date is overdue, unless it is done', function () {
    check_same(true, is_overdue(['status' => 'todo', 'due_date' => day(-1)]), 'yesterday, To Do');
    check_same(true, is_overdue(['status' => 'in_progress', 'due_date' => day(-5)]), '5 days ago, In Progress');
    check_same(false, is_overdue(['status' => 'done', 'due_date' => day(-1)]), 'yesterday, Done');
    check_same(false, is_overdue(['status' => 'todo', 'due_date' => day(0)]), 'today');
    check_same(false, is_overdue(['status' => 'todo', 'due_date' => null]), 'no due date');
});

test('TC-11  due labels: "Due today", "Due tomorrow", "Overdue · Nov 12", "Due Nov 20"', function () {
    check_same('Due today', due_label(day(0)));
    check_same('Due tomorrow', due_label(day(1)));
    check_same('Overdue · ' . (new DateTime(day(-3)))->format('M j'), due_label(day(-3)));
    check_same('Due ' . (new DateTime(day(5)))->format('M j'), due_label(day(5)));
});

test('TC-12  1 of 4 tasks done shows 25%', function () {
    check_same(25, percent(1, 4));
    check_same(67, percent(2, 3), '2 of 3');
    check_same(100, percent(4, 4), '4 of 4');
    check_same(0, percent(0, 0), 'no tasks');
});

test('TC-13  an empty or too long title is refused', function () {
    check_same(['Enter a task title.'], validate_task_input([], [])[1], 'no title');
    check_same(['Enter a task title.'], validate_task_input(['title' => '   '], [])[1], 'only spaces');
    check_same(['The title can be at most 100 characters.'], validate_task_input(['title' => str_repeat('a', 101)], [])[1], '101 letters');
    check_same([], validate_task_input(['title' => str_repeat('a', 100)], [])[1], '100 letters');
    check_same([], validate_task_input(['title' => str_repeat('가', 100)], [])[1], '100 Korean letters');
});

test('TC-13  only a member can be assigned; priority and date must be valid', function () {
    $members = [7, '8'];   // ids can come from the database as numbers or as text
    check_same([], validate_task_input(['title' => 'A', 'assignee_id' => '8'], $members)[1], 'member 8');
    check_same(['Choose a member of this project.'], validate_task_input(['title' => 'A', 'assignee_id' => '9'], $members)[1], 'not a member');
    check_same(['Choose a priority.'], validate_task_input(['title' => 'A', 'priority' => 'urgent'], $members)[1], 'priority');
    check_same(['Enter a valid due date.'], validate_task_input(['title' => 'A', 'due_date' => '2026-02-30'], $members)[1], 'Feb 30');
    check_same(['Enter a valid due date.'], validate_task_input(['title' => 'A', 'due_date' => '16/11/2026'], $members)[1], 'wrong format');
});

test('TC-13  a valid form gives clean data', function () {
    [$data, $errors] = validate_task_input(
        ['title' => '  Write report  ', 'assignee_id' => '3', 'priority' => 'high', 'due_date' => '2026-11-20'], [3]
    );
    check_same([], $errors, 'errors');
    check_same(['title' => 'Write report', 'assignee_id' => 3, 'priority' => 'high', 'due_date' => '2026-11-20'], $data);

    [$data] = validate_task_input(['title' => 'Plan', 'assignee_id' => '', 'due_date' => ''], [3]);
    check_same(['title' => 'Plan', 'assignee_id' => null, 'priority' => 'medium', 'due_date' => null], $data, 'empty fields');
});

db_test('TC-13  a new task starts in To Do', function (PDO $pdo) {
    $team = make_team($pdo);
    $id   = create_task($pdo, $team['pid'], ['title' => 'Draw ER diagram', 'assignee_id' => $team['alex'], 'priority' => 'high', 'due_date' => day(3)]);
    $task = get_task($pdo, $id);
    check_same('todo', $task['status'], 'status');
    check_same('Draw ER diagram', $task['title'], 'title');
    check_same($team['alex'], (int) $task['assignee_id'], 'assignee');
});

db_test('TC-14  edits are saved and a deleted task is gone', function (PDO $pdo) {
    $team = make_team($pdo);
    $id   = add_task($pdo, $team['pid'], $team['alex'], 'Old title', 'in_progress', day(2), 'low');
    update_task($pdo, $id, ['title' => 'New title', 'assignee_id' => $team['minji'], 'priority' => 'high', 'due_date' => day(4)]);
    $task = get_task($pdo, $id);
    check_same('New title', $task['title'], 'title');
    check_same($team['minji'], (int) $task['assignee_id'], 'assignee');
    check_same('high', $task['priority'], 'priority');
    check_same(day(4), $task['due_date'], 'due date');
    check_same('in_progress', $task['status'], 'editing does not move the task');

    delete_task($pdo, $id);
    check_same(null, get_task($pdo, $id), 'after delete');
});

test('TC-15  only the assignee or the leader may move a task', function () {
    $leader = 1;
    check_same(true, can_move_task(['assignee_id' => 5], 5, $leader), 'assignee');
    check_same(true, can_move_task(['assignee_id' => '5'], 5, $leader), 'assignee id as text (MySQL)');
    check_same(true, can_move_task(['assignee_id' => 5], 1, $leader), 'leader');
    check_same(false, can_move_task(['assignee_id' => 5], 7, $leader), 'another member');
    check_same(false, can_move_task(['assignee_id' => null], 7, $leader), 'unassigned, member');
    check_same(true, can_move_task(['assignee_id' => null], 1, $leader), 'unassigned, leader');
});

test('FR-15  each column shows only the moves that make sense', function () {
    check_same([['in_progress', 'Start →']], task_moves('todo'));
    check_same([['todo', '← Back'], ['done', 'Done →']], task_moves('in_progress'));
    check_same([['in_progress', '← Reopen']], task_moves('done'));
    check_same([], task_moves('lost'), 'unknown status');
});

test('FR-10  avatar initials: "Kabir Md Fahim" becomes "KM"', function () {
    check_same('KM', initials('Kabir Md Fahim'));
    check_same('M', initials('minji'));
    check_same('KL', initials('  kim   lee '));
    check_same('?', initials(''));
    check_same('?', initials(null));
});


group('Due soon and email reminders (FR-18, FR-19)');

db_test('TC-18  my unfinished tasks due within 2 days are listed, overdue first', function (PDO $pdo) {
    $team = make_team($pdo);
    add_task($pdo, $team['pid'], $team['alex'], 'Tomorrow', 'in_progress', day(1));
    add_task($pdo, $team['pid'], $team['alex'], 'In 2 days', 'todo', day(2));
    add_task($pdo, $team['pid'], $team['alex'], 'Overdue', 'todo', day(-3));
    add_task($pdo, $team['pid'], $team['alex'], 'Today', 'todo', day(0), 'high');
    add_task($pdo, $team['pid'], $team['alex'], 'In 3 days', 'todo', day(3));
    add_task($pdo, $team['pid'], $team['alex'], 'Done already', 'done', day(1));
    add_task($pdo, $team['pid'], $team['alex'], 'No date', 'todo', null);
    add_task($pdo, $team['pid'], $team['minji'], 'Someone else', 'todo', day(1));
    check_same(['Overdue', 'Today', 'Tomorrow', 'In 2 days'], titles(get_due_soon($pdo, $team['alex'])));
});

db_test('FR-19  reminders are for unfinished tasks due today or tomorrow that got none yet', function (PDO $pdo) {
    $team = make_team($pdo);
    $outsider = add_user($pdo, 'Left Team', 'left@demo.test');   // assigned once, no longer a member
    add_task($pdo, $team['pid'], $team['minji'], 'Tomorrow', 'in_progress', day(1));
    add_task($pdo, $team['pid'], $team['alex'], 'Today', 'todo', day(0));
    add_task($pdo, $team['pid'], $team['alex'], 'Done', 'done', day(1));
    add_task($pdo, $team['pid'], $team['alex'], 'In 2 days', 'todo', day(2));
    add_task($pdo, $team['pid'], $team['alex'], 'Overdue', 'todo', day(-1));
    add_task($pdo, $team['pid'], null, 'Unassigned', 'todo', day(1));
    add_task($pdo, $team['pid'], $outsider, 'Not a member', 'todo', day(1));
    $sent = add_task($pdo, $team['pid'], $team['alex'], 'Already reminded', 'todo', day(1));
    $pdo->exec("UPDATE tasks SET reminder_sent_at = '2026-01-01 09:00:00' WHERE task_id = $sent");

    $due = get_reminders_due($pdo, 10);
    check_same(['Today', 'Tomorrow'], titles($due), 'tasks');
    check_same('minji@demo.test', $due[1]['assignee_email'], 'email of the assignee');
    check_same('Campus Cafe Order App', $due[1]['project_title'], 'project');
});

db_test('TC-19  a reminder can be claimed only once, so nobody gets it twice', function (PDO $pdo) {
    $team = make_team($pdo);
    $id = add_task($pdo, $team['pid'], $team['alex'], 'Slides', 'todo', day(1));
    check_same(true, claim_reminder($pdo, $id), 'first claim');
    check_same(false, claim_reminder($pdo, $id), 'second claim');
    check_same([], get_reminders_due($pdo, 10), 'still due');
    release_reminder($pdo, $id);
    check_same(['Slides'], titles(get_reminders_due($pdo, 10)), 'after release');
});

db_test('FR-19  a new due date or a new assignee gets a new reminder, other edits do not', function (PDO $pdo) {
    $team = make_team($pdo);
    $id = add_task($pdo, $team['pid'], $team['alex'], 'Slides', 'todo', day(1), 'medium');
    $edit = fn(array $changes) => update_task($pdo, $id, $changes + [
        'title' => 'Slides', 'assignee_id' => $team['alex'], 'priority' => 'medium', 'due_date' => day(1)]);
    $sentAt = fn() => value($pdo, 'SELECT reminder_sent_at FROM tasks WHERE task_id = ?', [$id]);

    claim_reminder($pdo, $id);
    $edit(['title' => 'Final slides', 'priority' => 'high']);
    check($sentAt() !== null, 'title or priority change cleared the reminder');

    $edit(['due_date' => day(0)]);
    check_same(null, $sentAt(), 'after a new due date');

    claim_reminder($pdo, $id);
    $edit(['due_date' => day(0), 'assignee_id' => $team['minji']]);
    check_same(null, $sentAt(), 'after a new assignee');
});

db_test('FR-19  with email turned off, nothing is sent and nothing changes', function (PDO $pdo) {
    $team = make_team($pdo);
    add_task($pdo, $team['pid'], $team['alex'], 'Slides', 'todo', day(1));
    check_same(['enabled' => false, 'sent' => 0, 'skipped' => 0, 'failed' => 0], send_due_reminders($pdo, 'http://localhost/Teamsync'));
    check_same(['Slides'], titles(get_reminders_due($pdo, 10)), 'still waiting for its reminder');
});

db_test('FR-19  demo accounts (.test emails) are marked as reminded without sending', function (PDO $pdo) {
    $team = make_team($pdo);
    add_task($pdo, $team['pid'], $team['alex'], 'Slides', 'todo', day(1));
    add_task($pdo, $team['pid'], $team['minji'], 'Report', 'todo', day(0));
    mail_on();
    check_same(['enabled' => true, 'sent' => 0, 'skipped' => 2, 'failed' => 0], send_due_reminders($pdo, 'http://localhost/Teamsync'), 'first visit');
    check_same(['enabled' => true, 'sent' => 0, 'skipped' => 0, 'failed' => 0], send_due_reminders($pdo, 'http://localhost/Teamsync'), 'second visit');
});

db_test('FR-19  when the mail server does not answer, the reminder is tried again later', function (PDO $pdo) {
    $team = make_team($pdo);
    $real = add_user($pdo, 'Real Student', 'real.student@gmail.com');
    join_project($pdo, $team['pid'], $real);
    $first  = add_task($pdo, $team['pid'], $real, 'Slides', 'todo', day(0));
    $second = add_task($pdo, $team['pid'], $real, 'Report', 'todo', day(1));
    mail_on(['host' => '127.0.0.1', 'port' => 1, 'secure' => '']);   // nothing listens on port 1

    $log = tempnam(sys_get_temp_dir(), 'teamsync-test');
    $oldLog = ini_set('error_log', $log);   // keep the "email failed" log line out of the test output
    try {
        $result = send_due_reminders($pdo, 'http://localhost/Teamsync');
    } finally {
        ini_set('error_log', (string) $oldLog);
        $logged = (string) file_get_contents($log);
        unlink($log);
    }
    check_same(['enabled' => true, 'sent' => 0, 'skipped' => 0, 'failed' => 1], $result, 'result');
    check(str_contains($logged, 'real.student@gmail.com'), 'the failure was not logged');
    check_same([$first, $second], array_map('intval', array_column(get_reminders_due($pdo, 10), 'task_id')), 'both still waiting');
});

test('FR-19  the reminder email names the task, the project and the board link', function () {
    [$subject, $html, $text] = reminder_email([
        'title' => 'Write report', 'due_date' => day(1), 'priority' => 'high', 'project_id' => 3,
        'project_title' => 'Bus Arrival Bot', 'assignee_name' => 'Alex <Kim>',
    ], 'https://teamsync-du.fwh.is/');
    check_same('Reminder: "Write report" is due tomorrow', $subject, 'subject');
    check(str_contains($text, 'Priority: High'), 'priority missing');
    check(str_contains($text, 'https://teamsync-du.fwh.is/board.php?project=3'), 'board link missing');
    check(str_contains($html, 'Alex &lt;Kim&gt;') && !str_contains($html, '<Kim>'), 'name is not escaped in the HTML');
});


group('Contribution report (FR-20)');

db_test('TC-20  per-member counts match the board, most done first', function (PDO $pdo) {
    $team = make_team($pdo);
    foreach (['done', 'done', 'in_progress'] as $status) {
        add_task($pdo, $team['pid'], $team['alex'], "Alex $status", $status);
    }
    foreach (['done', 'todo', 'todo'] as $status) {
        add_task($pdo, $team['pid'], $team['minji'], "Minji $status", $status);
    }
    add_task($pdo, $team['pid'], null, 'Nobody todo', 'todo');
    add_task($pdo, $team['pid'], null, 'Nobody done', 'done');
    // A task in another project must not count here.
    $other = create_project($pdo, 'Library Seat Finder', $team['alex']);
    add_task($pdo, $other['project_id'], $team['alex'], 'Other project', 'done');

    $report = get_contribution($pdo, $team['pid']);
    $rows = array_map(fn($m) => [$m['name'], $m['done'], $m['in_progress'], $m['todo'], $m['total']], $report['members']);
    check_same([['Alex Kim', 2, 1, 0, 3], ['Minji Lee', 1, 0, 2, 3], ['Yuna Park', 0, 0, 0, 0]], $rows, 'members');
    check_same(['done' => 1, 'in_progress' => 0, 'todo' => 1, 'total' => 2], $report['unassigned'], 'unassigned');
    check_same(['done' => 4, 'in_progress' => 1, 'todo' => 3, 'total' => 8], $report['totals'], 'totals');
    check_same(50, percent($report['totals']['done'], $report['totals']['total']), 'progress');
});

test('FR-20  counts from the database become whole numbers', function () {
    check_same(['done' => 2, 'in_progress' => 0, 'todo' => 1, 'total' => 3], count_row(['done' => '2', 'in_progress' => null, 'todo' => '1']));
    check_same(['done' => 0, 'in_progress' => 0, 'todo' => 0, 'total' => 0], count_row([]));
});


group('Password reset (FR-26)');

db_test('FR-26  a reset link has a long random token, and only its hash is stored', function (PDO $pdo) {
    $user  = add_user($pdo, 'Alex Kim', 'alex@demo.test');
    $token = create_reset_token($pdo, $user);
    check((bool) preg_match('/^[0-9a-f]{64}$/', $token), 'token is not 64 hex letters');
    check_same(hash('sha256', $token), value($pdo, 'SELECT token_hash FROM password_resets WHERE user_id = ?', [$user]), 'stored hash');
    check_same(0, (int) value($pdo, 'SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$token]), 'the token itself is stored');
    check_same('alex@demo.test', find_valid_reset($pdo, $token)['email'], 'link finds the user');
});

db_test('FR-26  a link works for 1 hour', function (PDO $pdo) {
    $user  = add_user($pdo, 'Alex Kim', 'alex@demo.test');
    $token = create_reset_token($pdo, $user);
    $row   = $pdo->query('SELECT created_at, expires_at FROM password_resets')->fetch();
    check_same(3600, strtotime($row['expires_at']) - strtotime($row['created_at']), 'seconds');
    $pdo->exec("UPDATE password_resets SET expires_at = '" . minutes_ago(1) . "'");
    check_same(null, find_valid_reset($pdo, $token), 'expired link');
});

db_test('FR-26  a wrong or made-up token is refused', function (PDO $pdo) {
    $user = add_user($pdo, 'Alex Kim', 'alex@demo.test');
    create_reset_token($pdo, $user);
    foreach (['', 'abc', str_repeat('g', 64), bin2hex(random_bytes(32)), "' OR 1=1 --"] as $bad) {
        check_same(null, find_valid_reset($pdo, $bad), "token '$bad'");
    }
});

db_test('FR-26  a new link makes the older link stop working', function (PDO $pdo) {
    $user = add_user($pdo, 'Alex Kim', 'alex@demo.test');
    $old  = create_reset_token($pdo, $user);
    $new  = create_reset_token($pdo, $user);
    check_same(null, find_valid_reset($pdo, $old), 'old link');
    check(find_valid_reset($pdo, $new) !== null, 'new link does not work');
});

db_test('FR-26  the new password works and the link cannot be used again', function (PDO $pdo) {
    $user  = add_user($pdo, 'Alex Kim', 'alex@demo.test', 'member', 'demo1234');
    $token = create_reset_token($pdo, $user);
    reset_password($pdo, find_valid_reset($pdo, $token), 'new-password-42');
    $hash = value($pdo, 'SELECT password_hash FROM users WHERE user_id = ?', [$user]);
    check(password_verify('new-password-42', $hash), 'new password does not work');
    check(!password_verify('demo1234', $hash), 'old password still works');
    check_same(null, find_valid_reset($pdo, $token), 'used link');
});

db_test('FR-26  at most one email per account every 2 minutes', function (PDO $pdo) {
    $user = add_user($pdo, 'Alex Kim', 'alex@demo.test');
    check_same(false, reset_requested_recently($pdo, $user), 'before any link');
    create_reset_token($pdo, $user);
    check_same(true, reset_requested_recently($pdo, $user), 'just after a link');
    $pdo->exec("UPDATE password_resets SET created_at = '" . minutes_ago(3) . "'");
    check_same(false, reset_requested_recently($pdo, $user), '3 minutes later');
});

db_test('FR-26  an unknown email finds no account', function (PDO $pdo) {
    add_user($pdo, 'Alex Kim', 'alex@demo.test');
    check_same(null, find_user_by_email($pdo, 'nobody@demo.test'));
    check_same('Alex Kim', find_user_by_email($pdo, 'alex@demo.test')['name']);
});

test('FR-26  the reset email has the link and escapes the name', function () {
    [$subject, $html, $text] = reset_email('Kim <b>', 'https://teamsync-du.fwh.is/reset_password.php?token=abc');
    check_same('Reset your Teamsync password', $subject, 'subject');
    check(str_contains($text, 'https://teamsync-du.fwh.is/reset_password.php?token=abc'), 'link missing in text');
    check(str_contains($html, 'Kim &lt;b&gt;'), 'name is not escaped in the HTML');
});


group('Login protection (FR-27)');

// Wrong-password tries for $email, $minutesAgo each.
function fails_at(PDO $pdo, string $email, array $minutesAgo): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, attempted_at) VALUES (?, ?)');
    foreach ($minutesAgo as $m) {
        $stmt->execute([$email, minutes_ago($m)]);
    }
}

db_test('FR-27  4 wrong passwords still allow logging in, the 5th blocks it for 15 minutes', function (PDO $pdo) {
    for ($i = 0; $i < 4; $i++) {
        record_failed_login($pdo, 'alex@demo.test');
    }
    check_same(0, login_wait_minutes($pdo, 'alex@demo.test'), 'after 4');
    record_failed_login($pdo, 'alex@demo.test');
    check_same(15, login_wait_minutes($pdo, 'alex@demo.test'), 'after 5');
});

db_test('FR-27  the block is per email and ignores upper/lower case', function (PDO $pdo) {
    fails_at($pdo, 'alex@demo.test', [0, 0, 0, 0, 0]);
    check_same(15, login_wait_minutes($pdo, 'ALEX@Demo.Test'), 'same email, other case');
    check_same(15, login_wait_minutes($pdo, '  alex@demo.test '), 'with spaces');
    check_same(0, login_wait_minutes($pdo, 'minji@demo.test'), 'another email');
    record_failed_login($pdo, 'Minji@DEMO.test');
    check_same('minji@demo.test', value($pdo, "SELECT email FROM login_attempts WHERE email LIKE 'minji%'"), 'stored email');
});

db_test('FR-27  the block ends 15 minutes after the last try', function (PDO $pdo) {
    fails_at($pdo, 'alex@demo.test', [14, 13, 12, 11, 10]);
    check_same(5, login_wait_minutes($pdo, 'alex@demo.test'), 'last try 10 minutes ago');
    $pdo->exec('DELETE FROM login_attempts');
    fails_at($pdo, 'alex@demo.test', [20, 19, 18, 17, 16]);
    check_same(0, login_wait_minutes($pdo, 'alex@demo.test'), 'last try 16 minutes ago');
});

db_test('FR-27  5 tries spread over more than 15 minutes do not block', function (PDO $pdo) {
    fails_at($pdo, 'alex@demo.test', [20, 15, 10, 5, 0]);
    check_same(0, login_wait_minutes($pdo, 'alex@demo.test'));
});

db_test('FR-27  a correct login or a new password clears the count', function (PDO $pdo) {
    fails_at($pdo, 'alex@demo.test', [0, 0, 0, 0, 0]);
    clear_failed_logins($pdo, 'Alex@Demo.test');
    check_same(0, login_wait_minutes($pdo, 'alex@demo.test'), 'after clearing');
    check_same(0, (int) value($pdo, 'SELECT COUNT(*) FROM login_attempts'), 'rows left');
});

db_test('FR-27  an email with no account is blocked the same way (it does not show who has one)', function (PDO $pdo) {
    add_user($pdo, 'Alex Kim', 'alex@demo.test');
    fails_at($pdo, 'alex@demo.test', [0, 0, 0, 0, 0]);
    fails_at($pdo, 'ghost@demo.test', [0, 0, 0, 0, 0]);
    check_same(login_wait_minutes($pdo, 'alex@demo.test'), login_wait_minutes($pdo, 'ghost@demo.test'));
});

db_test('FR-27  tries older than a day are deleted', function (PDO $pdo) {
    fails_at($pdo, 'old@demo.test', [60 * 24 + 5]);
    record_failed_login($pdo, 'alex@demo.test');
    check_same(0, (int) value($pdo, "SELECT COUNT(*) FROM login_attempts WHERE email = 'old@demo.test'"), 'old rows');
    check_same(1, (int) value($pdo, "SELECT COUNT(*) FROM login_attempts WHERE email = 'alex@demo.test'"), 'new row');
});


group('Task comments (FR-28)');

test('FR-28  an empty or too long comment is refused', function () {
    check_same(['Write a comment first.'], validate_comment('')[1], 'empty');
    check_same(['Write a comment first.'], validate_comment("  \n\t ")[1], 'only spaces');
    check_same(['A comment can be at most 1000 characters.'], validate_comment(str_repeat('a', 1001))[1], '1001 letters');
    check_same([], validate_comment(str_repeat('a', 1000))[1], '1000 letters');
    check_same([], validate_comment(str_repeat('가', 1000))[1], '1000 Korean letters');
});

test('FR-28  a comment is trimmed and keeps its line breaks', function () {
    check_same(["First line\nSecond line", []], validate_comment("  First line\r\nSecond line \n"));
    check_same(['<b>Hi</b> & bye', []], validate_comment('<b>Hi</b> & bye'), 'stored as typed, escaped only when shown');
});

db_test('FR-28  comments are listed oldest first with their author', function (PDO $pdo) {
    $team = make_team($pdo);
    $task = add_task($pdo, $team['pid'], $team['alex'], 'Slides', 'todo', day(1));
    $first = add_comment($pdo, $task, $team['alex'], 'I started the slides.');
    $pdo->exec("UPDATE task_comments SET created_at = '" . minutes_ago(30) . "' WHERE comment_id = $first");
    add_comment($pdo, $task, $team['leader'], 'Great, thanks!');
    $other = add_task($pdo, $team['pid'], $team['minji'], 'Report', 'todo');
    add_comment($pdo, $other, $team['minji'], 'Not on the slides task.');

    $comments = get_comments($pdo, $task);
    check_same(['I started the slides.', 'Great, thanks!'], array_column($comments, 'body'), 'comments');
    check_same(['Alex Kim', 'Yuna Park'], array_column($comments, 'author_name'), 'authors');
});

db_test('FR-28  each card on the board shows its number of comments', function (PDO $pdo) {
    $team  = make_team($pdo);
    $busy  = add_task($pdo, $team['pid'], $team['alex'], 'Busy task', 'in_progress');
    $quiet = add_task($pdo, $team['pid'], $team['alex'], 'Quiet task', 'in_progress');
    add_comment($pdo, $busy, $team['alex'], 'One');
    add_comment($pdo, $busy, $team['minji'], 'Two');
    $counts = array_column(get_tasks_by_status($pdo, $team['pid'])['in_progress'], 'comment_count', 'title');
    check_same(2, (int) $counts['Busy task'], 'busy task');
    check_same(0, (int) $counts['Quiet task'], 'quiet task');
});

test('FR-28  only the author or the team leader may delete a comment', function () {
    $leader  = 1;
    $comment = ['user_id' => 5];
    check_same(true, can_delete_comment($comment, 5, $leader), 'author');
    check_same(true, can_delete_comment(['user_id' => '5'], 5, $leader), 'author id as text (MySQL)');
    check_same(true, can_delete_comment($comment, 1, $leader), 'leader');
    check_same(false, can_delete_comment($comment, 7, $leader), 'another member');
});

db_test('FR-28  a deleted comment is gone, and deleting a task deletes its comments', function (PDO $pdo) {
    $team = make_team($pdo);
    $task = add_task($pdo, $team['pid'], $team['alex'], 'Slides');
    $keep = add_comment($pdo, $task, $team['alex'], 'Keep me');
    $gone = add_comment($pdo, $task, $team['minji'], 'Delete me');
    delete_comment($pdo, $gone);
    check_same(null, get_comment($pdo, $gone), 'deleted comment');
    check_same('Keep me', get_comment($pdo, $keep)['body'], 'other comment');

    delete_task($pdo, $task);
    check_same(0, (int) value($pdo, 'SELECT COUNT(*) FROM task_comments'), 'comments after deleting the task');
});

test('FR-28  comment times read "Today, 4:05 PM", "Yesterday, 9:30 AM" or "Nov 12, 2:15 PM"', function () {
    check_same('Today, 4:05 PM', comment_time(day(0) . ' 16:05:00'));
    check_same('Yesterday, 9:30 AM', comment_time(day(-1) . ' 09:30:00'));
    $older = day(-3) . ' 14:15:00';
    check_same((new DateTime($older))->format('M j') . ', 2:15 PM', comment_time($older));
});

db_test('FR-28  the task page gets the task with its assignee', function (PDO $pdo) {
    $team = make_team($pdo);
    $mine = add_task($pdo, $team['pid'], $team['minji'], 'Draw UML', 'in_progress', day(3), 'high');
    $free = add_task($pdo, $team['pid'], null, 'Nobody yet');
    $task = get_task_details($pdo, $mine);
    check_same(['Draw UML', 'in_progress', 'high', day(3), 'Minji Lee'],
               [$task['title'], $task['status'], $task['priority'], $task['due_date'], $task['assignee_name']]);
    check_same(null, get_task_details($pdo, $free)['assignee_name'], 'unassigned');
    check_same(null, get_task_details($pdo, 999), 'missing task');
});


group('Login session cookie (#48)');

test('#48  the session cookie is HttpOnly and SameSite=Lax', function () {
    $options = session_cookie_options(false);
    check_same(true, $options['httponly'], 'HttpOnly');
    check_same('Lax', $options['samesite'], 'SameSite');
    check_same(0, $options['lifetime'], 'ends when the browser closes');
});

test('#48  the cookie is Secure on https only (XAMPP uses http)', function () {
    check_same(true, session_cookie_options(true)['secure'], 'https');
    check_same(false, session_cookie_options(false)['secure'], 'http');
});

test('#48  https is recognised directly and behind the host\'s proxy', function () {
    unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    check_same(false, request_is_https(), 'plain http');
    $_SERVER['HTTPS'] = 'on';
    check_same(true, request_is_https(), 'HTTPS=on');
    $_SERVER['HTTPS'] = 'off';
    check_same(false, request_is_https(), 'HTTPS=off (IIS)');
    unset($_SERVER['HTTPS']);
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    check_same(true, request_is_https(), 'X-Forwarded-Proto: https');
});

test('#48  no page starts a session without these settings', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_DIR, FilesystemIterator::SKIP_DOTS));
    $bad = [];
    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(APP_DIR) + 1));
        if ($file->getExtension() !== 'php' || preg_match('#^(lib|tests|\.git)/#', $relative) || $relative === 'includes/session.php') {
            continue;
        }
        if (preg_match('/\bsession_start\s*\(/', file_get_contents($file->getPathname()))) {
            $bad[] = $relative;
        }
    }
    check($bad === [], 'session_start() called directly in: ' . implode(', ', $bad));
});


group('Deleted or changed accounts (#56)');

db_test('#56  a session counts only while its account exists with the same role', function (PDO $pdo) {
    $alex = add_user($pdo, 'Alex Kim', 'alex@demo.test', 'leader');
    check_same(true, session_account_is_current($pdo, $alex, 'leader'), 'account as it was at login');
    check_same(false, session_account_is_current($pdo, $alex, 'member'), 'role changed since login');
    check_same(false, session_account_is_current($pdo, 9999, 'leader'), 'no such account');
});

db_test('#56  importing the demo data again logs out a browser still logged in as an old demo account', function (PDO $pdo) {
    $old = add_user($pdo, 'Alex Kim', 'alex@demo.test', 'leader');
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@demo.test'");   // what demo_data.sql does first
    $new = add_user($pdo, 'Alex Kim', 'alex@demo.test', 'leader');    // same person, new id
    check($new !== $old, 'the new demo account should get a new id');
    check_same(false, session_account_is_current($pdo, $old, 'leader'), 'old session');
    check_same(true, session_account_is_current($pdo, $new, 'leader'), 'after logging in again');
});

test('#56  logging out this way empties the session (the page then shows the login form)', function () {
    $_SESSION = ['user_id' => 7, 'name' => 'Alex Kim', 'role' => 'leader', 'csrf_token' => 'old'];
    forget_login();
    check_same([], $_SESSION);
});

test('#56  every page checks the account right after connecting to the database', function () {
    $auth = file_get_contents(APP_DIR . '/includes/auth.php');
    $connect = strpos($auth, 'require_once $configFile;');
    $check = strpos($auth, 'session_account_is_current($pdo');
    check($connect !== false && $check !== false, 'includes/auth.php must call session_account_is_current()');
    check($check > $connect, 'the check needs $pdo, so it must come after config/db.php is loaded');
    check(str_contains(substr($auth, $check, 200), 'forget_login()'), 'a session whose account is gone must be logged out');
});


group('Security headers (#50)');

// The Content-Security-Policy as ['script-src' => ["'self'"], ...]
function csp_directives(): array
{
    $directives = [];
    foreach (explode(';', security_headers()['Content-Security-Policy']) as $part) {
        $words = preg_split('/\s+/', trim($part));
        $directives[array_shift($words)] = $words;
    }
    return $directives;
}

// The pages and API files a browser can open: the .php files in the main folder and in api/
function app_pages(): array
{
    $pages = [];
    foreach (array_merge(glob(APP_DIR . '/*.php'), glob(APP_DIR . '/api/*.php')) as $path) {
        $pages[str_replace('\\', '/', substr($path, strlen(APP_DIR) + 1))] = $path;
    }
    return $pages;
}

test('#50  no other site may show Teamsync inside a frame (clickjacking)', function () {
    check_same(["'none'"], csp_directives()['frame-ancestors'] ?? null, 'frame-ancestors');
    check_same('DENY', security_headers()['X-Frame-Options'], 'X-Frame-Options');
});

test('#50  scripts run only from our own files: no inline scripts, no eval()', function () {
    $csp = csp_directives();
    check_same(["'self'"], $csp['default-src'] ?? null, 'default-src');
    check_same(["'self'"], $csp['script-src'] ?? null, 'script-src');
    check_same(["'none'"], $csp['object-src'] ?? null, 'object-src');
    check_same(["'self'"], $csp['form-action'] ?? null, 'form-action');
    check_same(["'self'"], $csp['base-uri'] ?? null, 'base-uri');
    foreach ($csp as $directive => $sources) {
        check(!in_array("'unsafe-eval'", $sources, true), "$directive allows eval()");
        if ($directive !== 'style-src-attr') {   // only style="width: N%" attributes are allowed inline
            check(!in_array("'unsafe-inline'", $sources, true), "$directive allows inline code");
        }
    }
});

test('#50  files keep the type the server gives, and other sites never get our page addresses', function () {
    $headers = security_headers();
    check_same('nosniff', $headers['X-Content-Type-Options'], 'X-Content-Type-Options');
    check_same('same-origin', $headers['Referrer-Policy'], 'Referrer-Policy (a reset link stays private)');
});

test('#50  every outside file the pages load is allowed by the policy (Google Fonts)', function () {
    $csp = csp_directives();
    $found = 0;
    foreach (app_pages() + ['includes/header.php' => APP_DIR . '/includes/header.php', 'includes/footer.php' => APP_DIR . '/includes/footer.php'] as $relative => $path) {
        $html = file_get_contents($path);
        preg_match_all('/<link\b[^>]*\brel="stylesheet"[^>]*\bhref="(https?:\/\/[^\/"]+)/i', $html, $styles);
        preg_match_all('/<script\b[^>]*\bsrc="(https?:\/\/[^\/"]+)/i', $html, $scripts);
        foreach ($styles[1] as $origin) {
            $found++;
            check(in_array($origin, $csp['style-src'], true), "$relative loads a style sheet from $origin");
            if ($origin === 'https://fonts.googleapis.com') {
                check(in_array('https://fonts.gstatic.com', $csp['font-src'], true), 'the Google Fonts files come from https://fonts.gstatic.com');
            }
        }
        foreach ($scripts[1] as $origin) {
            check(in_array($origin, $csp['script-src'], true), "$relative loads a script from $origin");
        }
    }
    check($found >= 1, 'the Google Fonts link in includes/header.php was not found');
});

test('#50  no page has inline <script> code or onclick="..." handlers (the policy would block them)', function () {
    $files = app_pages();
    foreach (glob(APP_DIR . '/includes/*.php') as $path) {
        $files['includes/' . basename($path)] = $path;
    }
    $bad = [];
    foreach ($files as $relative => $path) {
        $code = file_get_contents($path);
        if (preg_match('/<script\b(?![^>]*\bsrc=)/i', $code)) {
            $bad[] = "$relative: <script> without src";
        }
        if (preg_match('/<[a-z][^<>]*\son[a-z]+\s*=\s*["\']/i', $code)) {
            $bad[] = "$relative: an on...=\"\" handler";
        }
        if (stripos($code, 'javascript:') !== false) {
            $bad[] = "$relative: a javascript: link";
        }
    }
    check(count($files) >= 25, 'only ' . count($files) . ' files checked');
    check($bad === [], implode('; ', $bad) . ' (put the code in assets/js/app.js instead)');
});

test('#50  every page and API file loads includes/auth.php first, so every answer has these headers', function () {
    $bad = [];
    foreach (app_pages() as $relative => $path) {
        $code = file_get_contents($path);
        preg_match('/\brequire(?:_once)?\b([^;]+);/', $code, $first);
        if (!str_starts_with($code, '<?php') || !$first || !str_contains($first[1], '/includes/auth.php')) {
            $bad[] = $relative;
        }
    }
    check(count(app_pages()) >= 15, 'only ' . count(app_pages()) . ' pages found');
    check($bad === [], 'does not start by loading includes/auth.php: ' . implode(', ', $bad));
    $auth = file_get_contents(APP_DIR . '/includes/auth.php');
    $sends = strpos($auth, 'send_security_headers();');
    check($sends !== false && $sends < strpos($auth, 'start_secure_session();'), 'auth.php must send the headers before it starts the session');
});

test('#50  a page really sends them (asks PHP\'s own web server for index.php)', function () {
    $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    check((bool) $probe, "no free port: $error");
    $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);

    $null   = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', APP_DIR],
                        [['file', $null, 'r'], ['file', $null, 'w'], ['file', $null, 'w']], $pipes);
    check(is_resource($server), 'could not start php -S');
    try {
        $socket   = false;
        $deadline = microtime(true) + 5;
        while (!$socket && microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 1);
            if (!$socket) {
                usleep(100000);
            }
        }
        check((bool) $socket, "PHP's web server did not answer on port $port");
        stream_set_timeout($socket, 5);
        fwrite($socket, "GET /index.php HTTP/1.0\r\nHost: 127.0.0.1:$port\r\n\r\n");
        $response = (string) stream_get_contents($socket);
        fclose($socket);
    } finally {
        proc_terminate($server);
        proc_close($server);
    }

    $head = substr($response, 0, (int) strpos($response, "\r\n\r\n") + 2);
    check($head !== '', 'no answer from index.php');
    foreach (security_headers() as $name => $value) {
        check(stripos($head, "\r\n$name: $value\r\n") !== false, "index.php did not send $name");
    }
});


group('Email settings (FR-19, FR-26)');

test('FR-19  demo emails (.test, .example, .invalid, .localhost) are never mailed', function () {
    foreach (['alex@demo.test', 'PROF@DEMO.TEST', 'a@site.example', 'b@x.invalid', 'c@pc.localhost'] as $email) {
        check_same(true, is_undeliverable($email), $email);
    }
    foreach (['real.student@gmail.com', 'kim@office.du.ac.kr', 'test@testing.com'] as $email) {
        check_same(false, is_undeliverable($email), $email);
    }
});

test('FR-19  email is off until the Gmail address and App Password are set', function () {
    check_same(null, mail_settings(), 'empty settings');
    mail_on(['port' => '587']);
    $settings = mail_settings();
    check_same(587, $settings['port'], 'port');
    check_same('Teamsync', $settings['from_name'], 'sender name');
    $GLOBALS['MAIL_PASS'] = '';
    check_same(null, mail_settings(), 'without a password');
});

test('FR-26  links in emails use $SITE_URL, or the address of the running site', function () {
    $GLOBALS['SITE_URL'] = 'https://teamsync-du.fwh.is/';
    check_same('https://teamsync-du.fwh.is', site_url(1), 'with $SITE_URL');
    $GLOBALS['SITE_URL'] = '';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/Teamsync/api/send_reminders.php';
    unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    check_same('http://localhost/Teamsync', site_url(1), 'api file on XAMPP');
    $_SERVER['SCRIPT_NAME'] = '/Teamsync/forgot_password.php';
    check_same('http://localhost/Teamsync', site_url(0), 'page on XAMPP');
    $_SERVER['HTTP_HOST'] = 'teamsync-du.fwh.is';
    $_SERVER['SCRIPT_NAME'] = '/forgot_password.php';
    $_SERVER['HTTPS'] = 'on';
    check_same('https://teamsync-du.fwh.is', site_url(0), 'page at the top of an https site');
});


group('Project files');

test('tests/sqlite_schema.sql has the same tables and columns as database/schema.sql', function () {
    $mysql  = schema_columns(file_get_contents(APP_DIR . '/database/schema.sql'));
    $sqlite = schema_columns(file_get_contents(__DIR__ . '/sqlite_schema.sql'));
    check(count($mysql) >= 6, 'could not read database/schema.sql');
    check_same($mysql, $sqlite);
});

test('database/online.sql = schema.sql + demo_data.sql, without CREATE DATABASE or USE', function () {
    $online = file_get_contents(APP_DIR . '/database/online.sql');
    check_same(schema_columns(file_get_contents(APP_DIR . '/database/schema.sql')), schema_columns($online), 'tables');
    check(!preg_match('/^\\s*(CREATE\\s+DATABASE|USE)\\b/mi', $online), 'online.sql must not create or select a database');
    $demo = file_get_contents(APP_DIR . '/database/demo_data.sql');
    $demoBody = substr($demo, strpos($demo, '-- 1. Remove old demo data'));
    check(str_contains($online, $demoBody), 'the demo data differs from database/demo_data.sql');
});

test('the demo data uses the Korean date, so it can be imported at any time of day', function () {
    foreach (['database/demo_data.sql', 'database/online.sql'] as $file) {
        $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(APP_DIR . '/' . $file));   // without comments
        check(!preg_match('/\b(CURDATE|NOW|SYSDATE)\s*\(|\bCURRENT_DATE\b/i', $sql), "$file uses the database clock");
        $set = strpos($sql, 'SET @today = DATE(UTC_TIMESTAMP() + INTERVAL 9 HOUR);');
        check($set !== false, "$file does not set @today to the Korean date");
        check($set < strpos($sql, '@today -'), "$file uses @today before it is set");
    }
});

// "includes/auth.php" for a file inside the app folder, written with / on every system
function app_relative_path(string $path): string
{
    $clean = function (string $p): string {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $p)) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }
        return implode('/', $parts);
    };
    return substr($clean($path), strlen($clean(APP_DIR)) + 1);
}

test('tools/online-zip.sh packs every file the pages load (a missing file breaks the live site)', function () {
    $script = file_get_contents(APP_DIR . '/tools/online-zip.sh');
    check((bool) preg_match('/^APP_FILES="(.+)"$/m', $script, $m), 'APP_FILES not found in tools/online-zip.sh');
    $packed = preg_split('/\s+/', trim(preg_replace('/\$\([^)]*\)/', 'ROOT_PHP', $m[1])));
    check(str_contains($m[1], "git ls-files '*.php' | grep -v /"), 'the zip must take every .php file of the main folder');

    $files = app_pages();
    foreach (glob(APP_DIR . '/includes/*.php') as $path) {
        $files['includes/' . basename($path)] = $path;
    }
    $needed = [];
    foreach ($files as $relative => $path) {
        $code = file_get_contents($path);
        preg_match_all("/\\brequire(?:_once)?\\s+__DIR__\\s*\\.\\s*'([^']+)'/", $code, $requires);
        foreach ($requires[1] as $target) {
            $needed[app_relative_path(dirname($path) . $target)] = $relative;
        }
        preg_match_all('/\b(?:href|src)="([\w.\/-]+)["#?]/', $code, $links);   // files of this site the browser loads or opens
        foreach ($links[1] as $target) {
            $needed[$target] = $relative;
        }
    }
    check(isset($needed['assets/css/style.css'], $needed['includes/auth.php']), 'could not read what the pages load');

    $missing = [];
    foreach ($needed as $target => $usedBy) {
        $top = explode('/', $target)[0];
        $inZip = ($top === $target && str_ends_with($top, '.php')) ? in_array('ROOT_PHP', $packed, true) : in_array($top, $packed, true);
        if ($top !== 'config' && !$inZip) {   // config/db.php stays on the server
            $missing[] = "$target (used by $usedBy)";
        }
    }
    check($missing === [], 'not in the zip: ' . implode(', ', $missing) . '. Add it to APP_FILES in tools/online-zip.sh');
});

test('every PHP file of the app has no syntax errors', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_DIR, FilesystemIterator::SKIP_DOTS));
    $checked = 0;
    $bad = [];
    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(APP_DIR) + 1));
        if ($file->getExtension() !== 'php' || preg_match('#^(lib|\.git)/#', $relative)) {
            continue;   // PHPMailer is not our code
        }
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
        $checked++;
        if ($code !== 0) {
            $bad[] = $relative;
        }
    }
    check($checked >= 15, "only $checked PHP files found");
    check($bad === [], 'syntax errors in: ' . implode(', ', $bad));
});


// ======================================================================

echo "\n" . str_repeat('-', 60) . "\n";
printf("%d passed, %d failed%s\n", $results['passed'], $results['failed'],
       $results['skipped'] ? ", {$results['skipped']} skipped" : '');
exit($results['failed'] > 0 ? 1 : 0);
