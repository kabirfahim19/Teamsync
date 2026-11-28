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
    initInstallApp();
});

// Install Teamsync as an app on a phone or computer (#68).
// sw.js lets the browser offer "Install" and shows offline.html when there is no connection.
// Chrome, Edge and Android also tell the page when Teamsync can be installed: then the dashboard
// shows its "Install the app" button. On an iPhone: Share, then "Add to Home Screen".
function initInstallApp() {
    if ('serviceWorker' in navigator && window.isSecureContext) {   // https, or localhost on XAMPP
        navigator.serviceWorker.register('sw.js').catch(() => {});   // without it the site works as before
    }
    const button = document.querySelector('[data-install-app]');
    if (!button) {
        return;   // other pages: the browser shows its own install offer
    }
    let offer = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();   // our button instead of the browser's bar
        offer = event;
        button.hidden = false;
    });
    window.addEventListener('appinstalled', () => {
        offer = null;
        button.hidden = true;
    });
    button.addEventListener('click', () => {
        if (!offer) {
            return;
        }
        button.hidden = true;   // an offer can be used once
        offer.prompt();
        offer = null;
    });
}

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
// A card going to Review first asks for a link to the work (FR-31): dropped there, or its Review button.

function initBoardDragAndDrop() {
    const board = document.querySelector('.board[data-csrf]');
    if (!board) {
        return;
    }
    const csrf = board.dataset.csrf;
    const openHandIn = initHandInDialog(board, csrf);
    let dragged = null;

    // The Review button is a link to the task page's hand-in form; with JavaScript the box opens instead.
    board.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-handin]');
        if (link && openHandIn) {
            event.preventDefault();
            openHandIn(link.closest('.task-card'));
        }
    });

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
            if (!card || card.dataset.status === column.dataset.status) {
                return;
            }
            if (column.dataset.status !== 'review') {
                moveCard(csrf, card, column).then(showBoardError);
            } else if (openHandIn) {
                openHandIn(card);   // the move happens when the link is given
            } else {
                window.location.href = `task.php?id=${card.dataset.taskId}#hand-in`;
            }
        });
    });
}

// FR-31: the box that asks for the link to the work when a card goes to Review.
// Returns a function that opens it for a card, or null when this page has no box (or the browser no <dialog>).
function initHandInDialog(board, csrf) {
    const dialog = document.getElementById('handin-dialog');
    const column = board.querySelector('.column[data-status="review"]');
    if (!dialog || !column || typeof dialog.showModal !== 'function') {
        return null;
    }
    const form = dialog.querySelector('form');
    const submit = form.querySelector('button[type="submit"]');
    const error = dialog.querySelector('[data-handin-error]');
    let card = null;

    const showError = (message) => {
        error.textContent = message;
        error.hidden = !message;
    };

    // The browser has already checked that the link is a web address; the server checks it again.
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!card) {
            return;
        }
        submit.disabled = true;
        const message = await moveCard(csrf, card, column, {
            link: form.elements.link.value,
            note: form.elements.note.value,
        });
        submit.disabled = false;
        if (message) {
            showError(message);   // the box stays open, with what was typed
            return;
        }
        dialog.close();
    });
    dialog.querySelector('[data-handin-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        card = null;
    });

    return (target) => {
        card = target;
        form.reset();
        showError('');
        showBoardError('');
        dialog.querySelector('[data-handin-title]').textContent = `“${target.dataset.title}”`;
        dialog.showModal();
        form.elements.link.focus();
    };
}

// Sends a move to the server; when it is saved, the card moves on the board.
// extra = more fields to send (a hand-in's link and note).
// Returns the error message to show, or '' when the move worked.
async function moveCard(csrf, card, column, extra = {}) {
    const status = column.dataset.status;
    const from = card.closest('.column');
    const body = new FormData();
    body.append('csrf_token', csrf);
    body.append('task_id', card.dataset.taskId);
    body.append('status', status);
    Object.entries(extra).forEach(([name, value]) => body.append(name, value));

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
        return 'Could not reach the server. Check that XAMPP is running and try again.';
    }

    if (!response.ok || !result.success) {
        return result.message || 'Could not move the task.'; // the card stays where it was
    }

    column.appendChild(card);
    card.dataset.status = status;
    refreshCard(csrf, card, result);
    refreshColumn(from);
    refreshColumn(column);
    refreshProgress(result);
    announce(`${card.dataset.title} moved to ${result.labels[status]}.`);
    return '';
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

    // A hand-in adds a comment: the count on the card follows.
    const count = card.querySelector('.comment-count');
    if (count && typeof result.comments === 'number') {
        count.lastChild.textContent = result.comments;
        count.setAttribute('aria-label',
            `${result.comments} ${result.comments === 1 ? 'comment' : 'comments'} on “${card.dataset.title}”`);
    }

    const actions = card.querySelector('.task-actions');
    if (!actions) {
        return;
    }
    actions.querySelectorAll('form, a[data-handin]').forEach((old) => old.remove());
    result.moves.forEach(([to, label]) => {
        if (to === 'review') {
            // Handing in needs a link: the button opens the hand-in box (see initBoardDragAndDrop)
            const link = document.createElement('a');
            link.className = 'btn-move';
            link.href = `task.php?id=${card.dataset.taskId}#hand-in`;
            link.dataset.handin = '';
            link.textContent = label;
            link.setAttribute('aria-label', `Hand in “${card.dataset.title}” for review`);
            actions.append(link);
            return;
        }
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
