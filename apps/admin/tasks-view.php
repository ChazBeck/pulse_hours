<?php
/**
 * Tasks View - Client, Project, and Task Overview
 * 
 * Admin page to view all tasks organized by client and project
 */

require __DIR__ . '/../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

$pdo = get_db_connection();

$message = '';
$message_type = '';

// ============================================================================
// Handle Form Submissions
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !auth_verify_csrf($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        // ADD NEW TASK
        if ($action === 'add') {
            $project_id = intval($_POST['project_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'not-started';
            
            if (empty($name)) {
                $message = 'Task name is required.';
                $message_type = 'error';
            } elseif ($project_id <= 0) {
                $message = 'Please select a valid project.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO tasks (project_id, name, description, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$project_id, $name, $description, $status]);
                    $message = 'Task created successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error creating task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // EDIT TASK
        elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $project_id = intval($_POST['project_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'not-started';
            
            if (empty($name)) {
                $message = 'Task name is required.';
                $message_type = 'error';
            } elseif ($id <= 0) {
                $message = 'Invalid task ID.';
                $message_type = 'error';
            } elseif ($project_id <= 0) {
                $message = 'Please select a valid project.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE tasks SET project_id = ?, name = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$project_id, $name, $description, $status, $id]);
                    $message = 'Task updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // DELETE TASK
        elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $message = 'Invalid task ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Task deleted successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// ============================================================================
// Fetch All Tasks with Client and Project Info
// ============================================================================

$filter_client = $_GET['client'] ?? '';
$filter_project = $_GET['project'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where_clauses = [];
$params = [];

if ($filter_client) {
    $where_clauses[] = "c.id = ?";
    $params[] = $filter_client;
}

if ($filter_project) {
    $where_clauses[] = "p.id = ?";
    $params[] = $filter_project;
}

if ($filter_status) {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$stmt = $pdo->prepare("
    SELECT 
        c.id as client_id,
        c.name as client_name,
        c.client_color,
        p.id as project_id,
        p.name as project_name,
        p.status as project_status,
        t.id as task_id,
        t.name as task_name,
        t.status as task_status,
        t.description as task_description,
        t.created_at as task_created
    FROM tasks t
    INNER JOIN projects p ON t.project_id = p.id
    INNER JOIN clients c ON p.client_id = c.id
    $where_sql
    ORDER BY c.name ASC, p.name ASC, t.name ASC
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Get filter options
$stmt = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name");
$clients = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, name, client_id FROM projects WHERE active = 1 ORDER BY name");
$projects = $stmt->fetchAll();

// Get statistics
$total_tasks = count($tasks);
$completed_tasks = count(array_filter($tasks, fn($t) => $t['task_status'] === 'completed'));
$in_progress_tasks = count(array_filter($tasks, fn($t) => $t['task_status'] === 'in-progress'));
$unique_clients = count(array_unique(array_column($tasks, 'client_id')));
$unique_projects = count(array_unique(array_column($tasks, 'project_id')));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks View - Pulse Hours</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin-nav-styles.css') ?>">
    <style>
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
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
        }

        .filter-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
        }

        .tasks-table {
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

        .client-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-not-started {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .status-in-progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .task-description {
            color: var(--text-secondary);
            font-size: 0.813rem;
            margin-top: 0.25rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            padding: 0.25rem 0.5rem;
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
        }

        .btn-delete:hover {
            background: #dc2626;
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
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .page-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>
    
    <main class="admin-content">
        <div class="page-actions">
            <div>
                <h1>Tasks View</h1>
                <p>Overview of all tasks by client and project</p>
            </div>
            <button onclick="openAddModal()" class="btn btn-primary">+ Add Task</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?= $total_tasks ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $completed_tasks ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $in_progress_tasks ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $unique_clients ?></div>
                <div class="stat-label">Clients</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $unique_projects ?></div>
                <div class="stat-label">Projects</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
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
                    <label>Project</label>
                    <select name="project">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= ($filter_project == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="not-started" <?= ($filter_status == 'not-started') ? 'selected' : '' ?>>Not Started</option>
                        <option value="in-progress" <?= ($filter_status == 'in-progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= ($filter_status == 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="blocked" <?= ($filter_status == 'blocked') ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Tasks Table -->
        <div class="tasks-table">
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <p>No tasks found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <span class="client-badge" style="background-color: <?= htmlspecialchars($task['client_color'] ?: '#6b7280') ?>">
                                        <?= htmlspecialchars($task['client_name']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($task['project_name']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($task['task_name']) ?></strong>
                                    <?php if ($task['task_description']): ?>
                                        <div class="task-description"><?= htmlspecialchars($task['task_description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $task['task_status'] ?>">
                                        <?= ucwords(str_replace('-', ' ', $task['task_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon btn-edit" onclick='editTask(<?= json_encode($task) ?>)'>Edit</button>
                                        <button class="btn-icon btn-delete" onclick="deleteTask(<?= $task['task_id'] ?>, '<?= htmlspecialchars(addslashes($task['task_name'])) ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Task Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Task</h3>
                <button class="btn-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Project *</label>
                    <select name="project_id" required>
                        <option value="">Select a project...</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Task Name *</label>
                    <input type="text" name="name" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="not-started">Not Started</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Task</h3>
                <button class="btn-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Project *</label>
                    <select name="project_id" id="edit_project_id" required>
                        <option value="">Select a project...</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Task Name *</label>
                    <input type="text" name="name" id="edit_name" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="not-started">Not Started</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function editTask(task) {
            document.getElementById('edit_id').value = task.task_id;
            document.getElementById('edit_project_id').value = task.project_id;
            document.getElementById('edit_name').value = task.task_name;
            document.getElementById('edit_description').value = task.task_description || '';
            document.getElementById('edit_status').value = task.task_status;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function deleteTask(taskId, taskName) {
            if (confirm('Are you sure you want to delete "' + taskName + '"? This action cannot be undone.')) {
                document.getElementById('delete_id').value = taskId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
