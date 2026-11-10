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

    // Later: Kanban drag-and-drop, sending requests to api/update_task.php
});
