/**
 * Project Templates admin page
 *
 * Reads the CSRF token from the data attribute on <body data-csrf="...">
 * so we don't need PHP interpolation inside this file.
 */
(function () {
    'use strict';

    const csrfToken = document.body.dataset.csrf || '';

    function buildHiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }

    function submitAction(fields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.appendChild(buildHiddenInput('csrf_token', csrfToken));
        for (const [name, value] of Object.entries(fields)) {
            if (value !== null && value !== undefined) {
                form.appendChild(buildHiddenInput(name, value));
            }
        }
        document.body.appendChild(form);
        form.submit();
    }

    function toggleTemplate(templateId) {
        const content = document.getElementById('template-content-' + templateId);
        if (!content) return;
        const icon = content.previousElementSibling.querySelector('.toggle-icon');
        content.classList.toggle('active');
        if (icon) icon.classList.toggle('active');
    }

    function editTemplate(id) {
        const section = document.querySelector('[data-template-id="' + id + '"]');
        if (!section) return;

        const header = section.querySelector('.template-header');
        const nameEl = header.querySelector('.template-name span:first-child');
        const descEl = header.querySelector('.template-description');

        const name = nameEl ? nameEl.textContent.trim() : '';
        const description = descEl ? descEl.textContent.trim() : '';

        const newName = prompt('Template Name:', name);
        if (newName === null) return;

        const newDesc = prompt('Description:', description);
        if (newDesc === null) return;

        const activeConfirm = confirm('Is this template active?');

        submitAction({
            action: 'edit_template',
            id: id,
            name: newName,
            description: newDesc,
            active: activeConfirm ? '1' : null,
        });
    }

    function deleteTemplate(id) {
        if (!confirm('Are you sure you want to delete this template? All associated tasks will also be deleted.')) {
            return;
        }
        submitAction({ action: 'delete_template', id: id });
    }

    function editTask(id) {
        const trigger = document.querySelector('button[data-task-edit="' + id + '"]');
        if (!trigger) return;
        const taskItem = trigger.closest('.task-item');
        if (!taskItem) return;

        const nameEl = taskItem.querySelector('.task-name');
        const descEl = taskItem.querySelector('.task-description');
        const name = nameEl ? nameEl.textContent.trim() : '';
        const description = descEl ? descEl.textContent.trim() : '';

        const newName = prompt('Task Name:', name);
        if (newName === null) return;
        const newDesc = prompt('Task Description:', description);
        if (newDesc === null) return;

        submitAction({
            action: 'edit_task',
            id: id,
            name: newName,
            description: newDesc,
        });
    }

    function deleteTask(id) {
        if (!confirm('Are you sure you want to delete this task?')) {
            return;
        }
        submitAction({ action: 'delete_task', id: id });
    }

    function bindEvents() {
        document.querySelectorAll('[data-template-toggle]').forEach((el) => {
            el.addEventListener('click', (e) => {
                if (e.target.closest('.template-actions')) return;
                toggleTemplate(el.dataset.templateToggle);
            });
        });

        document.querySelectorAll('[data-template-edit]').forEach((btn) => {
            btn.addEventListener('click', () => editTemplate(btn.dataset.templateEdit));
        });

        document.querySelectorAll('[data-template-delete]').forEach((btn) => {
            btn.addEventListener('click', () => deleteTemplate(btn.dataset.templateDelete));
        });

        document.querySelectorAll('[data-task-edit]').forEach((btn) => {
            btn.addEventListener('click', () => editTask(btn.dataset.taskEdit));
        });

        document.querySelectorAll('[data-task-delete]').forEach((btn) => {
            btn.addEventListener('click', () => deleteTask(btn.dataset.taskDelete));
        });
    }

    function autoDismissSuccess() {
        document.querySelectorAll('.message.success').forEach((msg) => {
            setTimeout(() => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }, 3000);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindEvents();
        autoDismissSuccess();
    });
})();
