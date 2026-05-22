/**
 * Hours entry page (apps/hours.php)
 *
 * Collapsible client/project sections and scroll-wheel guard on the
 * number inputs (so a stray wheel scroll doesn't bump a value).
 */
(function () {
    'use strict';

    function toggleClient(header) {
        const section = header.parentElement;
        const isExpanding = !section.classList.contains('expanded');
        section.classList.toggle('expanded');

        if (isExpanding) {
            section.querySelectorAll('.project-section').forEach((p) => p.classList.add('expanded'));
        }
    }

    function toggleProject(header) {
        header.parentElement.classList.toggle('expanded');
    }

    function bindEvents() {
        document.querySelectorAll('.client-header').forEach((el) => {
            el.addEventListener('click', () => toggleClient(el));
        });
        document.querySelectorAll('.project-header').forEach((el) => {
            el.addEventListener('click', () => toggleProject(el));
        });
        document.querySelectorAll('input[type="number"]').forEach((input) => {
            input.addEventListener('wheel', (e) => e.preventDefault());
        });
    }

    document.addEventListener('DOMContentLoaded', bindEvents);
})();
