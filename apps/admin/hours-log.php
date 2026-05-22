<?php
/**
 * Hours Log - View and Edit All Hours
 *
 * Admin page listing all hours entries across all users, with filters,
 * inline edit modal, and delete. POST actions dispatch through HoursService.
 */

require __DIR__ . '/../../sso/sso_include.php';
pulse_require_admin();

require_once __DIR__ . '/../../src/Service/HoursService.php';

$service = new HoursService();
$pdo = get_db_connection();
$clientRepo = new ClientRepository($pdo);
$userRepo = new UserRepository($pdo);
$hoursRepo = new HoursRepository($pdo);

$successMessage = '';
$errorMessage = '';

// ----------------------------------------------------------------------
// Handle POST actions
// ----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid form submission (CSRF token mismatch).';
    } elseif (isset($_POST['delete_entry'])) {
        $result = $service->deleteEntryAsAdmin($_POST['entry_id'] ?? 0);
        $result['success'] ? $successMessage = $result['message'] : $errorMessage = $result['message'];
    } elseif (isset($_POST['update_entry'])) {
        $result = $service->updateEntry($_POST['entry_id'] ?? 0, $_POST);
        $result['success'] ? $successMessage = $result['message'] : $errorMessage = $result['message'];
    }
}

// ----------------------------------------------------------------------
// Fetch data for display
// ----------------------------------------------------------------------

$filterUser = $_GET['user'] ?? '';
$filterClient = $_GET['client'] ?? '';
$filterWeek = $_GET['week'] ?? '';

$filters = [];
if ($filterUser !== '')   $filters['user_id']   = $filterUser;
if ($filterClient !== '') $filters['client_id'] = $filterClient;
if ($filterWeek !== '')   $filters['year_week'] = $filterWeek;

$hoursEntries = $hoursRepo->getAllWithDetails($filters);
$users = $userRepo->getActive();
$clients = $clientRepo->getActive();
$weeks = $hoursRepo->getDistinctWeeks(20);

$totalHours = array_sum(array_column($hoursEntries, 'hours'));
$totalEntries = count($hoursEntries);
$uniqueUsers = count(array_unique(array_column($hoursEntries, 'email')));

$csrfToken = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hours Log - Pulse Hours</title>
    <?php include __DIR__ . '/../../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/hours-log.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>

    <main class="admin-content">
        <div class="admin-header">
            <h1>Hours Log</h1>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($totalHours, 2) ?></div>
                <div class="stat-label">Total Hours</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $totalEntries ?></div>
                <div class="stat-label">Entries</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $uniqueUsers ?></div>
                <div class="stat-label">Team Members</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label>User</label>
                    <select name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= ($filterUser == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Client</label>
                    <select name="client">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= ($filterClient == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Week</label>
                    <select name="week">
                        <option value="">All Weeks</option>
                        <?php foreach ($weeks as $w): ?>
                            <option value="<?= htmlspecialchars($w) ?>" <?= ($filterWeek === $w) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($w) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Hours Table -->
        <div class="hours-table">
            <?php if (empty($hoursEntries)): ?>
                <div class="empty-state">
                    <p>No hours entries found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Task</th>
                            <th>Hours</th>
                            <th>Week</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hoursEntries as $entry): ?>
                            <tr>
                                <td class="date-cell"><?= date('M j, Y', strtotime($entry['date_worked'])) ?></td>
                                <td class="user-cell"><?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?></td>
                                <td class="client-cell"><?= htmlspecialchars($entry['client_name']) ?></td>
                                <td>
                                    <?php if ($entry['project_name']): ?>
                                        <?= htmlspecialchars($entry['project_name']) ?>
                                    <?php else: ?>
                                        <em class="no-project">No Project</em>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($entry['task_name']) ?></td>
                                <td class="hours-cell"><?= number_format($entry['hours'], 2) ?>h</td>
                                <td><?= htmlspecialchars($entry['year_week']) ?></td>
                                <td class="actions-cell">
                                    <button type="button" class="btn-edit"
                                            data-edit-entry='<?= htmlspecialchars(json_encode($entry), ENT_QUOTES) ?>'>Edit</button>
                                    <button type="button" class="btn-delete"
                                            data-delete-entry="<?= (int) $entry['id'] ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Hours Entry</h3>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="entry_id" id="edit_entry_id">

                <div class="form-group">
                    <label>Date Worked</label>
                    <input type="date" name="date_worked" id="edit_date_worked" required>
                </div>

                <div class="form-group">
                    <label>Hours</label>
                    <input type="number" name="hours" id="edit_hours" step="0.25" min="0" max="75" value="0">
                    <small class="field-hint">Can be set to 0 if needed</small>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-edit-modal">Cancel</button>
                    <button type="submit" name="update_entry" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <input type="hidden" name="entry_id" id="delete_entry_id">
        <input type="hidden" name="delete_entry" value="1">
    </form>

    <script src="<?= url('/assets/hours-log.js') ?>"></script>
</body>
</html>
