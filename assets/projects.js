/**
 * Projects admin page (apps/admin/projects.php)
 *
 * Shows the selected template's description when the user picks a
 * template from the dropdown.
 */
(function () {
    'use strict';

    function bindTemplateDescription() {
        const select = document.getElementById('project_template_id');
        if (!select) return;

        const box = document.getElementById('template-description');
        const text = document.getElementById('template-description-text');
        if (!box || !text) return;

        select.addEventListener('change', () => {
            const option = select.options[select.selectedIndex];
            const description = (option && option.getAttribute('data-description')) || '';
            if (description.trim() !== '') {
                text.textContent = description;
                box.classList.add('visible');
            } else {
                box.classList.remove('visible');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', bindTemplateDescription);
})();
