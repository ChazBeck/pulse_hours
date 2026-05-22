/**
 * Hours Log admin page
 *
 * Edit modal + delete confirmation. Entry payloads come from the
 * data-edit-entry attribute so the JS file contains no PHP-interpolated
 * values.
 */
(function () {
    'use strict';

    function openEditModal(entry) {
        document.getElementById('edit_entry_id').value = entry.id;
        document.getElementById('edit_date_worked').value = entry.date_worked;
        document.getElementById('edit_hours').value = entry.hours;
        document.getElementById('editModal').classList.add('active');
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        if (modal) modal.classList.remove('active');
    }

    function confirmDelete(entryId) {
        if (!confirm('Are you sure you want to delete this entry? This cannot be undone.')) {
            return;
        }
        document.getElementById('delete_entry_id').value = entryId;
        document.getElementById('deleteForm').submit();
    }

    function bindEvents() {
        document.querySelectorAll('[data-edit-entry]').forEach((btn) => {
            btn.addEventListener('click', () => {
                try {
                    openEditModal(JSON.parse(btn.dataset.editEntry));
                } catch (e) {
                    console.error('Failed to parse entry payload', e);
                }
            });
        });

        document.querySelectorAll('[data-delete-entry]').forEach((btn) => {
            btn.addEventListener('click', () => confirmDelete(btn.dataset.deleteEntry));
        });

        document.querySelectorAll('[data-action="close-edit-modal"]').forEach((btn) => {
            btn.addEventListener('click', closeEditModal);
        });

        const modal = document.getElementById('editModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeEditModal();
            });
        }

        // Prevent accidental hour changes when scrolling the page
        document.querySelectorAll('input[type="number"]').forEach((input) => {
            input.addEventListener('wheel', (e) => e.preventDefault());
        });
    }

    document.addEventListener('DOMContentLoaded', bindEvents);
})();
