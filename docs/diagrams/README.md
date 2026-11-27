# Teamsync diagrams

UML and ER diagrams of Teamsync, matching the code on `main` (Sprint 3, Nov 2026). Requirements, use case descriptions and test cases are in the SRS: [`docs/Teamsync-Requirements-v1.5.pdf`](../Teamsync-Requirements-v1.5.pdf).

The class, sequence and ER diagrams are written in [Mermaid](https://mermaid.js.org/), so GitHub draws them from the text below, and a change to a diagram goes through a pull request like a change to the code. PNG copies of every diagram are in this folder for slides and documents.

| Diagram | What it shows | Files |
|---|---|---|
| Use case | The 11 use cases, the three user roles and the email service | `use-case.svg`, `use-case.png` |
| Class | The 7 core classes, their attributes, operations and associations | `class.png` |
| Sequence | UC-11: a member hands in a task with a link, and the leader approves it | `sequence.png` |
| ER | The 9 database tables, their keys and relationships | `er.png` |

## Use case diagram

![Teamsync use case diagram](use-case.svg)

A Team Leader can do everything a Team Member can (the generalization arrow), plus create projects, assign tasks and approve finished work. The Professor only reads. Gmail SMTP is the outside system that delivers reminder and password reset emails.

## Class diagram

Each class is one database table, and its operations are PHP functions in `includes/` (for example `Task.moveTo()` is `move_task()` in `includes/tasks.php`). A filled diamond means the part is deleted with its whole.

```mermaid
classDiagram
    class User {
        int user_id
        string name
        string email
        string password_hash
        Role role
        register()
        logIn()
    }
    class Project {
        int project_id
        string title
        string invite_code
        datetime created_at
        create(title)
        join(inviteCode)
    }
    class Task {
        int task_id
        string title
        Status status
        Priority priority
        date due_date
        datetime reminder_sent_at
        moveTo(status)
        handIn(link, note)
        isOverdue()
    }
    class Comment {
        string body
        datetime created_at
    }
    class TaskEvent {
        Status from_status
        Status to_status
        datetime created_at
    }
    class Handin {
        string link
        datetime created_at
    }
    class PasswordReset {
        string token_hash
        datetime expires_at
        datetime created_at
        isValid()
    }
    class Role {
        <<enumeration>>
        leader
        member
        professor
    }
    class Status {
        <<enumeration>>
        todo
        in_progress
        review
        done
    }
    class Priority {
        <<enumeration>>
        low
        medium
        high
    }
    User "1" -- "0..*" Project : leads
    User "*" -- "*" Project : member of
    Project "1" *-- "0..*" Task : has
    Task "0..*" -- "0..1" User : assigned to
    Task "1" *-- "0..*" Comment
    Task "1" *-- "0..*" TaskEvent
    Task "1" *-- "0..*" Handin
    User "1" -- "0..*" PasswordReset : requests
    User ..> Role
    Task ..> Status
    Task ..> Priority
```

Comment, TaskEvent and Handin also each belong to the User who wrote or made them; those three links are left out to keep the diagram readable. LoginAttempt (wrong-password tries per email, FR-27) has no links and is not drawn.

## Sequence diagram: UC-11 hand in and approve a task

Every check runs on the server before anything is saved, and the move, its history line, the comment and the link are saved in one transaction (FR-29 to FR-31).

```mermaid
sequenceDiagram
    actor M as Team Member
    participant B as Board page (app.js)
    participant S as PHP server
    participant D as MySQL
    actor L as Team Leader
    M->>B: drop card on Review
    B-->>M: hand-in box opens
    M->>B: link + note, Move to Review
    B->>S: POST status=review, link, note
    Note over S: check CSRF, membership,<br/>move rule and link
    S->>D: one transaction: status, history line, comment, link
    D-->>S: saved together
    S-->>B: JSON: new moves and progress
    B-->>M: card waits in Review
    Note over M,L: later: the leader checks the work
    L->>S: open dashboard
    S->>D: tasks in Review + newest link
    S-->>L: "Waiting for your approval" + link to the work
    L->>S: Approve (status=done)
    S->>D: status done + history line
    S-->>L: card in Done, progress goes up
```

## ER diagram

The tables as created by [`database/schema.sql`](../../database/schema.sql). `login_attempts` has no foreign key: it is looked up by email (FR-27).

```mermaid
erDiagram
    users ||--o{ project_members : "is a member"
    projects ||--o{ project_members : "has"
    users ||--o{ projects : "leads"
    projects ||--o{ tasks : "contains"
    users |o--o{ tasks : "assigned to"
    tasks ||--o{ task_comments : "has"
    users ||--o{ task_comments : "writes"
    tasks ||--o{ task_events : "history"
    users |o--o{ task_events : "made"
    tasks ||--o{ task_handins : "handed in"
    users |o--o{ task_handins : "gave"
    users ||--o{ password_resets : "requests"

    users {
        int user_id PK
        varchar name
        varchar email UK
        varchar password_hash
        enum role "leader, member, professor"
        datetime created_at
    }
    projects {
        int project_id PK
        varchar title
        char invite_code UK "TS-XXXX"
        int leader_id FK
        datetime created_at
    }
    project_members {
        int project_id PK, FK
        int user_id PK, FK
        datetime joined_at
    }
    tasks {
        int task_id PK
        int project_id FK
        int assignee_id FK "NULL = unassigned"
        varchar title
        enum status "todo, in_progress, review, done"
        enum priority "low, medium, high"
        date due_date
        datetime reminder_sent_at
    }
    task_comments {
        int comment_id PK
        int task_id FK
        int user_id FK
        text body
        datetime created_at
    }
    task_events {
        int event_id PK
        int task_id FK
        int user_id FK "NULL if account deleted"
        varchar from_status "NULL when added"
        varchar to_status
        datetime created_at
    }
    task_handins {
        int handin_id PK
        int task_id FK
        int user_id FK
        varchar link
        datetime created_at
    }
    password_resets {
        int reset_id PK
        int user_id FK
        char token_hash UK "SHA-256"
        datetime expires_at
        datetime created_at
    }
    login_attempts {
        int attempt_id PK
        varchar email
        datetime attempted_at
    }
```
