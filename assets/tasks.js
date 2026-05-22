/**
 * Tasks management page (apps/admin/tasks.php)
 *
 * Filters the project dropdown to only show projects belonging to the
 * currently-selected client.
 */
(function () {
    'use strict';

    function filterProjects() {
        const clientSelect = document.getElementById('client_id');
        const projectSelect = document.getElementById('project_id');
        if (!clientSelect || !projectSelect) return;

        const clientId = clientSelect.value;
        const currentlySelected = projectSelect.value;
        let resetSelection = false;

        projectSelect.querySelectorAll('option').forEach((option) => {
            if (option.value === '') {
                option.style.display = 'block';
                return;
            }
            const optClient = option.getAttribute('data-client-id');
            const shouldShow = !clientId || optClient === clientId;
            option.style.display = shouldShow ? 'block' : 'none';
            if (option.value === currentlySelected && !shouldShow) {
                resetSelection = true;
            }
        });

        if (resetSelection) {
            projectSelect.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const clientSelect = document.getElementById('client_id');
        if (clientSelect) {
            clientSelect.addEventListener('change', filterProjects);
            filterProjects();
        }
    });
})();
