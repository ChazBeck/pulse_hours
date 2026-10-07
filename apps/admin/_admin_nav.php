<nav class="admin-nav">
    <div class="admin-nav-container">
        <a href="<?= url('/apps/admin/index.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            Dashboard
        </a>
        <a href="<?= url('/apps/admin/clients.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : '' ?>">
            Clients
        </a>
        <a href="<?= url('/apps/admin/users.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
            Users
        </a>
        <a href="<?= url('/apps/admin/projects.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : '' ?>">
            Projects
        </a>
        <a href="<?= url('/apps/admin/project-templates.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'project-templates.php' ? 'active' : '' ?>">
            Templates
        </a>
        <a href="<?= url('/apps/admin/tasks.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : '' ?>">
            Tasks
        </a>
        <a href="<?= url('/apps/admin/budget-import.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'budget-import.php' ? 'active' : '' ?>">
            Budget Import
        </a>
        <a href="<?= url('/apps/admin/tasks-view.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'tasks-view.php' ? 'active' : '' ?>">
            Tasks View
        </a>
        <a href="<?= url('/apps/admin/hours-log.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'hours-log.php' ? 'active' : '' ?>">
            Hours Log
        </a>
        <div class="admin-nav-dropdown" id="reportsDropdown">
            <button type="button" class="<?= strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'active' : '' ?>" id="reportsBtn">
                Reports ▾
            </button>
            <div class="admin-nav-dropdown-content" id="reportsMenu">
                <a href="<?= url('/apps/admin/reports/pulse-workload.php') ?>">Pulse & Workload</a>
                <a href="<?= url('/apps/admin/reports/client-hours.php') ?>">Client Hours</a>
                <a href="<?= url('/apps/admin/reports/budget-vs-actual.php') ?>">Budget vs Actual</a>
                <a href="<?= url('/apps/admin/reports/burn-up.php') ?>">Burn-up</a>
            </div>
        </div>
    </div>
</nav>

<script>
const btn = document.getElementById('reportsBtn');
const dropdown = document.getElementById('reportsDropdown');

if (btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (dropdown && !dropdown.contains(event.target)) {
        dropdown.classList.remove('open');
    }
});
</script>
