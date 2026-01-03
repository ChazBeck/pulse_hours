<?php
// Temporary error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Hours Log - View and Edit All Hours
 * 
 * Admin page to view and edit all hours entries across all users.
 */

require __DIR__ . '/../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

$pdo = get_db_connection();

// Success/error messages
$success_message = '';
$error_message = '';

// ============================================================================
// Handle Edit/Delete Actions
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!auth_verify_csrf($csrf_token)) {
        $error_message = 'Invalid form submission (CSRF token mismatch)';
    } elseif (isset($_POST['delete_entry'])) {
        try {
            $entry_id = $_POST['entry_id'];
            $stmt = $pdo->prepare("DELETE FROM hours WHERE id = ?");
            $stmt->execute([$entry_id]);
            $success_message = 'Entry deleted successfully';
        } catch (Exception $e) {
            $error_message = 'Error deleting entry: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_entry'])) {
        try {
            $entry_id = $_POST['entry_id'];
            $hours = $_POST['hours'];
            $date_worked = $_POST['date_worked'];
            
            // If hours is 0 or empty, delete the entry instead
            if ($hours === '' || $hours === '0' || $hours == 0) {
                $stmt = $pdo->prepare("DELETE FROM hours WHERE id = ?");
                $stmt->execute([$entry_id]);
                $success_message = 'Entry removed (hours set to 0)';
            } else {
                if (!is_numeric($hours) || $hours <= 0) {
                    throw new Exception('Hours must be a positive number');
                }
                
                // Calculate year_week from date_worked
                $year_week = date('o-W', strtotime($date_worked));
                
                $stmt = $pdo->prepare("
                    UPDATE hours 
                    SET hours = ?, date_worked = ?, year_week = ?
                    WHERE id = ?
                ");
                $stmt->execute([$hours, $date_worked, $year_week, $entry_id]);
                $success_message = 'Entry updated successfully';
            }
        } catch (Exception $e) {
            $error_message = 'Error updating entry: ' . $e->getMessage();
        }
    }
}

// ============================================================================
// Fetch All Hours Entries
// ============================================================================

$filter_user = $_GET['user'] ?? '';
$filter_client = $_GET['client'] ?? '';
$filter_week = $_GET['week'] ?? '';

$where_clauses = [];
$params = [];

if ($filter_user) {
    $where_clauses[] = "u.id = ?";
    $params[] = $filter_user;
}

if ($filter_client) {
    $where_clauses[] = "c.id = ?";
    $params[] = $filter_client;
}

if ($filter_week) {
    $where_clauses[] = "h.year_week = ?";
    $params[] = $filter_week;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$stmt = $pdo->prepare("
    SELECT 
        h.id,
        h.date_worked,
        h.hours,
        h.year_week,
        h.date_created,
        u.email,
        u.first_name,
        u.last_name,
        c.name as client_name,
        p.name as project_name,
        t.name as task_name
    FROM hours h
    JOIN users u ON h.user_id = u.id
    JOIN tasks t ON h.task_id = t.id
    JOIN projects p ON h.project_id = p.id
    JOIN clients c ON p.client_id = c.id
    $where_sql
    ORDER BY h.date_worked DESC, c.name, p.name, t.name
");
$stmt->execute($params);
$hours_entries = $stmt->fetchAll();

// Get filter options
$stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE is_active = 1 ORDER BY first_name, last_name");
$users = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name");
$clients = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT year_week FROM hours ORDER BY year_week DESC LIMIT 20");
$weeks = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hours Log - Pulse Hours</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin-nav-styles.css') ?>">
    <style>
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .filters form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .filter-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
        }

        .hours-table {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--gray-100);
        }

        th {
            padding: 1rem;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--gray-200);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.875rem;
        }

        tbody tr:hover {
            background: var(--gray-50);
        }

        .user-cell {
            font-weight: 600;
        }

        .client-cell {
            color: var(--primary-color);
            font-weight: 600;
        }

        .hours-cell {
            font-weight: 700;
            color: var(--primary-color);
            text-align: right;
        }

        .date-cell {
            white-space: nowrap;
        }

        .actions-cell {
            text-align: right;
            white-space: nowrap;
        }

        .btn-edit,
        .btn-delete {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit {
            background: var(--primary-color);
            color: white;
        }

        .btn-edit:hover {
            background: #d17520;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
            margin-left: 0.5rem;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            max-width: 500px;
            width: 90%;
        }

        .modal-content h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .summary-stats {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 2rem;
            justify-content: space-around;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>Hours Log</h1>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <?php
        $total_hours = array_sum(array_column($hours_entries, 'hours'));
        $total_entries = count($hours_entries);
        $unique_users = count(array_unique(array_column($hours_entries, 'email')));
        ?>
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($total_hours, 2) ?></div>
                <div class="stat-label">Total Hours</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $total_entries ?></div>
                <div class="stat-label">Entries</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $unique_users ?></div>
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
                            <option value="<?= $u['id'] ?>" <?= ($filter_user == $u['id']) ? 'selected' : '' ?>>
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
                            <option value="<?= $c['id'] ?>" <?= ($filter_client == $c['id']) ? 'selected' : '' ?>>
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
                            <option value="<?= $w['year_week'] ?>" <?= ($filter_week == $w['year_week']) ? 'selected' : '' ?>>
                                <?= $w['year_week'] ?>
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
            <?php if (empty($hours_entries)): ?>
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
                        <?php foreach ($hours_entries as $entry): ?>
                            <tr>
                                <td class="date-cell"><?= date('M j, Y', strtotime($entry['date_worked'])) ?></td>
                                <td class="user-cell"><?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?></td>
                                <td class="client-cell"><?= htmlspecialchars($entry['client_name']) ?></td>
                                <td><?= htmlspecialchars($entry['project_name']) ?></td>
                                <td><?= htmlspecialchars($entry['task_name']) ?></td>
                                <td class="hours-cell"><?= number_format($entry['hours'], 2) ?>h</td>
                                <td><?= htmlspecialchars($entry['year_week']) ?></td>
                                <td class="actions-cell">
                                    <button class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($entry)) ?>)">Edit</button>
                                    <button class="btn-delete" onclick="confirmDelete(<?= $entry['id'] ?>)">Delete</button>
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
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                <input type="hidden" name="entry_id" id="edit_entry_id">
                
                <div class="form-group">
                    <label>Date Worked</label>
                    <input type="date" name="date_worked" id="edit_date_worked" required>
                </div>
                
                <div class="form-group">
                    <label>Hours</label>
                    <input type="number" name="hours" id="edit_hours" step="0.25" min="0" max="24" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_entry" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
        <input type="hidden" name="entry_id" id="delete_entry_id">
        <input type="hidden" name="delete_entry" value="1">
    </form>

    <script>
        function openEditModal(entry) {
            document.getElementById('edit_entry_id').value = entry.id;
            document.getElementById('edit_date_worked').value = entry.date_worked;
            document.getElementById('edit_hours').value = entry.hours;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function confirmDelete(entryId) {
            if (confirm('Are you sure you want to delete this entry? This cannot be undone.')) {
                document.getElementById('delete_entry_id').value = entryId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Disable scroll on number inputs
        document.addEventListener('DOMContentLoaded', function() {
            const numberInputs = document.querySelectorAll('input[type="number"]');
            numberInputs.forEach(input => {
                input.addEventListener('wheel', function(e) {
                    e.preventDefault();
                });
            });
        });
    </script>
</body>
</html>
