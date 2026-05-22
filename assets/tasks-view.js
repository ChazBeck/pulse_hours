/**
 * Tasks View admin page
 *
 * Modal-based add/edit, client→project filtering, delete confirmation.
 */
(function () {
    'use strict';

    function filterProjectsByClient(clientId, mode) {
        const projectSelect = document.getElementById(mode + '_project_id');
        if (!projectSelect) return;

        projectSelect.querySelectorAll('option').forEach((option) => {
            if (option.value === '') {
                option.style.display = 'block';
                return;
            }
            const optClient = option.dataset.client;
            option.style.display = (!clientId || optClient === clientId) ? 'block' : 'none';
        });

        // Reset selection if the current project no longer belongs to the chosen client
        if (projectSelect.value) {
            const selected = projectSelect.querySelector('option[value="' + projectSelect.value + '"]');
            if (selected && selected.dataset.client !== clientId) {
                projectSelect.value = '';
            }
        }
    }

    function openAddModal() {
        const modal = document.getElementById('addModal');
        if (!modal) return;
        modal.classList.add('active');
        document.getElementById('add_client_id').value = '';
        document.getElementById('add_project_id').value = '';
        filterProjectsByClient('', 'add');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('active');
    }

    function openEditModal(task) {
        document.getElementById('edit_id').value = task.task_id;
        document.getElementById('edit_client_id').value = task.client_id;
        filterProjectsByClient(String(task.client_id), 'edit');
        document.getElementById('edit_project_id').value = task.project_id || '';
        document.getElementById('edit_name').value = task.task_name;
        document.getElementById('edit_description').value = task.task_description || '';
        document.getElementById('edit_status').value = task.task_status;
        document.getElementById('editModal').classList.add('active');
    }

    function confirmDelete(taskId, taskName) {
        if (!confirm('Are you sure you want to delete "' + taskName + '"? This action cannot be undone.')) {
            return;
        }
        document.getElementById('delete_id').value = taskId;
        document.getElementById('deleteForm').submit();
    }

    function bindEvents() {
        const addBtn = document.querySelector('[data-action="open-add-modal"]');
        if (addBtn) addBtn.addEventListener('click', openAddModal);

        document.querySelectorAll('[data-action="close-modal"]').forEach((btn) => {
            btn.addEventListener('click', () => closeModal(btn.dataset.modal));
        });

        document.querySelectorAll('[data-filter-client]').forEach((select) => {
            select.addEventListener('change', () => {
                filterProjectsByClient(select.value, select.dataset.filterClient);
            });
        });

        document.querySelectorAll('[data-edit-task]').forEach((btn) => {
            btn.addEventListener('click', () => {
                try {
                    const task = JSON.parse(btn.dataset.editTask);
                    openEditModal(task);
                } catch (e) {
                    console.error('Failed to parse task payload', e);
                }
            });
        });

        document.querySelectorAll('[data-delete-task]').forEach((btn) => {
            btn.addEventListener('click', () => {
                confirmDelete(btn.dataset.deleteTask, btn.dataset.deleteTaskName);
            });
        });

        // Close modal when clicking the backdrop
        document.querySelectorAll('.modal').forEach((modal) => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.remove('active');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', bindEvents);
})();
