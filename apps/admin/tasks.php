<?php
/**
 * Task Management - Add, Edit, Delete, and List Tasks
 * 
 * Allows administrators to manage tasks linked to projects and clients
 * with status tracking and CRUD operations.
 */

require __DIR__ . '/../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

require_once __DIR__ . '/../../config/db_config.php';
$pdo = get_db_connection();

$user = auth_get_user();
$message = '';
$message_type = '';

// ============================================================================
// Handle Form Submissions
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !auth_verify_csrf($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        // ADD NEW TASK
        if ($action === 'add') {
            $client_id = intval($_POST['client_id'] ?? 0);
            $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'not-started';
            
            // Validate
            if (empty($name)) {
                $message = 'Task name is required.';
                $message_type = 'error';
            } elseif ($client_id <= 0) {
                $message = 'Please select a valid client.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO tasks (client_id, project_id, name, description, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$client_id, $project_id, $name, $description, $status]);
                    $message = 'Task added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error adding task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // EDIT TASK
        elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $client_id = intval($_POST['client_id'] ?? 0);
            $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'not-started';
            
            if (empty($name)) {
                $message = 'Task name is required.';
                $message_type = 'error';
            } elseif ($id <= 0) {
                $message = 'Invalid task ID.';
                $message_type = 'error';
            } elseif ($client_id <= 0) {
                $message = 'Please select a valid client.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE tasks SET client_id = ?, project_id = ?, name = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$client_id, $project_id, $name, $description, $status, $id]);
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
// Get Task for Editing
// ============================================================================

$edit_task = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("
        SELECT t.*
        FROM tasks t
        WHERE t.id = ?
    ");
    $stmt->execute([$edit_id]);
    $edit_task = $stmt->fetch();
}

// ============================================================================
// Fetch All Active Clients for Dropdown
// ============================================================================

$stmt = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name ASC");
$clients = $stmt->fetchAll();

// ============================================================================
// Fetch All Projects for Dropdown (will be filtered by JavaScript)
// ============================================================================

$stmt = $pdo->query("SELECT id, name, client_id FROM projects WHERE active = 1 ORDER BY name ASC");
$projects = $stmt->fetchAll();

// ============================================================================
// Fetch All Tasks with Client and Project Info
// ============================================================================

$stmt = $pdo->query("
    SELECT 
        t.*,
        p.name as project_name,
        c.name as client_name,
        c.client_color
    FROM tasks t
    INNER JOIN clients c ON t.client_id = c.id
    LEFT JOIN projects p ON t.project_id = p.id
    ORDER BY t.created_at DESC
");
$tasks = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tasks - Admin - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }
        .status-not-started {
            background-color: #f3f4f6;
            color: #374151;
        }
        .status-in-progress {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-blocked {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>

    <main class="admin-content">
        <div class="container">
            <div class="page-header">
                <h2>Manage Tasks</h2>
                <p>Add, edit, or remove tasks for your projects.</p>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit Task Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $edit_task ? 'Edit Task' : 'Add New Task' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                        <input type="hidden" name="action" value="<?= $edit_task ? 'edit' : 'add' ?>">
                        <?php if ($edit_task): ?>
                        <input type="hidden" name="id" value="<?= $edit_task['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="client_id">Client *</label>
                            <select id="client_id" name="client_id" required onchange="filterProjects()">
                                <option value="">-- Select a Client --</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" 
                                        <?= ($edit_task && $edit_task['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="project_id">Project (Optional)</label>
                            <select id="project_id" name="project_id">
                                <option value="">-- No Project --</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" 
                                        data-client-id="<?= $project['client_id'] ?>"
                                        <?= ($edit_task && $edit_task['project_id'] == $project['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                Select a client first to filter projects, or leave empty for client-level task
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Task Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?= $edit_task ? htmlspecialchars($edit_task['name']) : '' ?>"
                                   placeholder="Enter task name">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4" 
                                      placeholder="Enter task description (optional)"><?= $edit_task ? htmlspecialchars($edit_task['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="not-started" <?= ($edit_task && $edit_task['status'] == 'not-started') ? 'selected' : '' ?>>Not Started</option>
                                <option value="in-progress" <?= ($edit_task && $edit_task['status'] == 'in-progress') ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= ($edit_task && $edit_task['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                                <option value="blocked" <?= ($edit_task && $edit_task['status'] == 'blocked') ? 'selected' : '' ?>>Blocked</option>
                            </select>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $edit_task ? 'Update Task' : 'Add Task' ?>
                            </button>
                            <?php if ($edit_task): ?>
                            <a href="tasks.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tasks List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Tasks (<?= count($tasks) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <p>No tasks found. Add your first task above!</p>
                    </div>
                    <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Project</th>
                                    <th>Task Name</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?= $task['id'] ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: <?= htmlspecialchars($task['client_color']) ?>;" 
                                                 title="<?= htmlspecialchars($task['client_name']) ?>"></div>
                                            <strong><?= htmlspecialchars($task['client_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($task['project_name']): ?>
                                            <?= htmlspecialchars($task['project_name']) ?>
                                        <?php else: ?>
                                            <em style="color: #9ca3af;">No Project</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($task['name']) ?></strong></td>
                                    <td>
                                        <?php
                                        $status_class = 'status-' . $task['status'];
                                        $status_label = ucwords(str_replace('-', ' ', $task['status']));
                                        ?>
                                        <span class="status-badge <?= $status_class ?>"><?= $status_label ?></span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($task['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= $task['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Filter projects based on selected client
        function filterProjects() {
            const clientId = document.getElementById('client_id').value;
            const projectSelect = document.getElementById('project_id');
            const options = projectSelect.querySelectorAll('option');
            
            // Reset project selection
            projectSelect.value = '';
            
            // Show/hide options based on client
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const optionClientId = option.getAttribute('data-client-id');
                if (!clientId || optionClientId === clientId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }
        
        // Initialize filter on page load
        document.addEventListener('DOMContentLoaded', function() {
            filterProjects();
        });
    </script>
</body>
</html>
