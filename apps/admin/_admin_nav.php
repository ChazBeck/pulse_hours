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
        <a href="<?= url('/apps/admin/hours-log.php') ?>" class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'hours-log.php' ? 'active' : '' ?>">
            Hours Log
        </a>
    </div>
</nav>
