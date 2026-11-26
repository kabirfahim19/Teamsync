// Teamsync front-end code.

document.addEventListener('DOMContentLoaded', () => {
    // Ask before dangerous actions, e.g. <form data-confirm="Delete this task?">
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    initBoardDragAndDrop();
    initProfessorCodeField();
    sendDueReminders();
});

// Dashboard: ask the server to email reminders for tasks due soon (FR-19).
// Runs in the background; the page does not wait for it and shows nothing.
function sendDueReminders() {
    const main = document.querySelector('[data-send-reminders]');
    if (!main) {
        return;
    }
    const body = new FormData();
    body.append('csrf_token', main.dataset.sendReminders);
    fetch('api/send_reminders.php', { method: 'POST', headers: { Accept: 'application/json' }, body })
        .catch(() => {}); // no network: the next visit tries again
}

// Sign-up page: show the "Professor code" field only when the role Professor is chosen.
// Without JavaScript the field simply stays visible.
function initProfessorCodeField() {
    const role = document.getElementById('role');
    const row = document.getElementById('professor-code-row');
    if (!role || !row) {
        return;
    }
    const update = () => {
        row.hidden = role.value !== 'professor';
    };
    role.addEventListener('change', update);
    update();
}

// ---------- Kanban drag-and-drop (Issue #12) ----------
// Cards the user may move have draggable="true", and data-targets lists the columns they may be
// dropped in (the server decides: a member cannot drop a card in Done, FR-30).
// A drop sends the same request as the buttons, to api/update_task.php, and asks for JSON.
// The answer brings the card's new buttons and targets. Without JavaScript the buttons still work.

function initBoardDragAndDrop() {
    const board = document.querySelector('.board[data-csrf]');
    if (!board) {
        return;
    }
    const csrf = board.dataset.csrf;
    let dragged = null;

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('.task-card[draggable="true"]');
        if (!card) {
            return;
        }
        dragged = card;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.taskId);
    });

    board.addEventListener('dragend', () => {
        if (dragged) {
            dragged.classList.remove('dragging');
        }
        dragged = null;
        board.querySelectorAll('.drop-target').forEach((column) => column.classList.remove('drop-target'));
    });

    board.querySelectorAll('.column').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            const targets = dragged ? (dragged.dataset.targets || '').split(' ') : [];
            if (!targets.includes(column.dataset.status)) {
                return; // not a drop target: the card is already here, or this user may not put it here
            }
            event.preventDefault(); // this column accepts the drop
            event.dataTransfer.dropEffect = 'move';
            column.classList.add('drop-target');
        });

        column.addEventListener('dragleave', (event) => {
            if (!column.contains(event.relatedTarget)) {
                column.classList.remove('drop-target');
            }
        });

        column.addEventListener('drop', (event) => {
            event.preventDefault();
            column.classList.remove('drop-target');
            const card = dragged;
            if (card && card.dataset.status !== column.dataset.status) {
                moveCard(csrf, card, column);
            }
        });
    });
}

async function moveCard(csrf, card, column) {
    const status = column.dataset.status;
    const from = card.closest('.column');
    const body = new FormData();
    body.append('csrf_token', csrf);
    body.append('task_id', card.dataset.taskId);
    body.append('status', status);
    showBoardError('');

    let response;
    let result;
    try {
        response = await fetch('api/update_task.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body,
        });
        const text = await response.text();
        try {
            result = JSON.parse(text);
        } catch {
            // Not JSON: a plain-text error from PHP, or an HTML page (for example a web host's
            // security check). Show plain text as it is; never show raw HTML to the user.
            const plain = text.trim();
            result = {
                success: false,
                message: plain && !plain.startsWith('<')
                    ? plain
                    : 'Could not save the move. Reload the page and try again.',
            };
        }
    } catch {
        showBoardError('Could not reach the server. Check that XAMPP is running and try again.');
        return;
    }

    if (!response.ok || !result.success) {
        showBoardError(result.message || 'Could not move the task.');
        return; // the card stays where it was
    }

    column.appendChild(card);
    card.dataset.status = status;
    refreshCard(csrf, card, result);
    refreshColumn(from);
    refreshColumn(column);
    refreshProgress(result);
    announce(`${card.dataset.title} moved to ${result.labels[status]}.`);
}

// Badge, due date, buttons and drop targets of a card after it changed column.
// result = the server's answer: the card's moves, targets and the column labels.
function refreshCard(csrf, card, result) {
    const status = card.dataset.status;
    const handedIn = status === 'review' || status === 'done';

    card.dataset.targets = result.targets.join(' ');
    if (result.targets.length) {
        card.setAttribute('draggable', 'true');
    } else {
        card.removeAttribute('draggable');
    }

    const badge = card.querySelector('.task-badge');
    if (status === 'done') {
        badge.className = 'badge badge-done task-badge';
        badge.textContent = 'Completed';
    } else if (status === 'review') {
        badge.className = 'badge badge-review task-badge';
        badge.textContent = 'Waiting for approval';
    } else {
        badge.className = `badge badge-${card.dataset.priority} task-badge`;
        badge.textContent = card.dataset.priorityLabel;
    }

    const due = card.querySelector('.task-due');
    if (due) {
        const late = card.dataset.late === '1' && !handedIn;
        due.classList.toggle('overdue', late);
        due.textContent = (late ? 'Overdue · ' : 'Due ') + card.dataset.dueLabel;
    }

    const actions = card.querySelector('.task-actions');
    if (!actions) {
        return;
    }
    actions.querySelectorAll('form').forEach((form) => form.remove());
    result.moves.forEach(([to, label]) => {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'api/update_task.php';
        form.append(hiddenInput('csrf_token', csrf), hiddenInput('task_id', card.dataset.taskId), hiddenInput('status', to));

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = 'btn-move';
        button.textContent = label;
        button.setAttribute('aria-label', `Move “${card.dataset.title}” to ${result.labels[to]}`);
        form.append(button);
        actions.append(form);
    });
}

function hiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

// Card count and "No tasks here." of one column.
function refreshColumn(column) {
    const cards = column.querySelectorAll('.task-card').length;
    column.querySelector('.count').textContent = cards;
    column.querySelector('.column-empty').hidden = cards > 0;
}

function refreshProgress(result) {
    const text = document.getElementById('progress-text');
    const bar = document.getElementById('progress-bar');
    const fill = document.getElementById('progress-fill');
    if (text) {
        text.textContent = `${result.done} of ${result.total} tasks · ${result.progress}%`;
    }
    if (bar) {
        bar.setAttribute('aria-valuenow', result.progress);
    }
    if (fill) {
        fill.style.width = `${result.progress}%`;
    }
}

function showBoardError(message) {
    const box = document.getElementById('board-error');
    if (box) {
        box.textContent = message;
        box.hidden = !message;
    }
}

function announce(message) {
    const live = document.getElementById('board-status');
    if (live) {
        live.textContent = message;
    }
}
