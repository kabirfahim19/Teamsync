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
});

// ---------- Kanban drag-and-drop (Issue #12) ----------
// Cards the user may move have draggable="true" (the server decides).
// A drop sends the same request as the buttons, to api/update_task.php, and asks for JSON.
// Without JavaScript the Start / Done / Back buttons still work.

const STATUS_LABELS = { todo: 'To Do', in_progress: 'In Progress', done: 'Done' };
const MOVES = {
    todo: [['in_progress', 'Start →']],
    in_progress: [['todo', '← Back'], ['done', 'Done →']],
    done: [['in_progress', '← Reopen']],
};

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
            if (!dragged || dragged.dataset.status === column.dataset.status) {
                return; // not a drop target: the card is already in this column
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
    refreshCard(csrf, card);
    refreshColumn(from);
    refreshColumn(column);
    refreshProgress(result);
    announce(`${card.dataset.title} moved to ${STATUS_LABELS[status]}.`);
}

// Badge, due date and buttons of a card after it changed column.
function refreshCard(csrf, card) {
    const status = card.dataset.status;

    const badge = card.querySelector('.task-badge');
    if (status === 'done') {
        badge.className = 'badge badge-done task-badge';
        badge.textContent = 'Completed';
    } else {
        badge.className = `badge badge-${card.dataset.priority} task-badge`;
        badge.textContent = card.dataset.priorityLabel;
    }

    const due = card.querySelector('.task-due');
    if (due) {
        const late = card.dataset.late === '1' && status !== 'done';
        due.classList.toggle('overdue', late);
        due.textContent = (late ? 'Overdue · ' : 'Due ') + card.dataset.dueLabel;
    }

    const actions = card.querySelector('.task-actions');
    if (!actions) {
        return;
    }
    actions.querySelectorAll('form').forEach((form) => form.remove());
    MOVES[status].forEach(([to, label]) => {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'api/update_task.php';
        form.append(hiddenInput('csrf_token', csrf), hiddenInput('task_id', card.dataset.taskId), hiddenInput('status', to));

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = 'btn-move';
        button.textContent = label;
        button.setAttribute('aria-label', `Move “${card.dataset.title}” to ${STATUS_LABELS[to]}`);
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
