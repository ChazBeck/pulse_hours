<?php
/**
 * Task Management - Add, Edit, Delete, and List Tasks
 *
 * Thin controller: dispatches POST actions to TaskService, then renders
 * the list view with an inline add/edit form.
 */

require __DIR__ . '/../../sso/sso_include.php';
pulse_require_admin();

require_once __DIR__ . '/../../src/Service/TaskService.php';

$service = new TaskService();
$message = '';
$messageType = '';

// ----------------------------------------------------------------------
// Handle POST actions
// ----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !auth_verify_csrf($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $result = null;

        switch ($action) {
            case 'add':
                $result = $service->createTask($_POST);
                break;
            case 'edit':
                $result = $service->updateTask($_POST);
                break;
            case 'delete':
                $result = $service->deleteTask($_POST['id'] ?? 0);
                break;
            default:
                $message = 'Unknown action.';
                $messageType = 'error';
        }

        if ($result !== null) {
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }
    }
}

// ----------------------------------------------------------------------
// Fetch data for display
// ----------------------------------------------------------------------

$editTask = isset($_GET['edit']) ? $service->getTaskById($_GET['edit']) : null;
$options = $service->getSelectOptions();
$clients = $options['clients'];
$projects = $options['projects'];
$tasks = $service->getAllWithRelations();
$csrfToken = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tasks - Admin - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/tasks.css') ?>">
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
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit Task Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $editTask ? 'Edit Task' : 'Add New Task' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <input type="hidden" name="action" value="<?= $editTask ? 'edit' : 'add' ?>">
                        <?php if ($editTask): ?>
                        <input type="hidden" name="id" value="<?= (int) $editTask['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="client_id">Client *</label>
                            <select id="client_id" name="client_id" required>
                                <option value="">-- Select a Client --</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>"
                                        <?= ($editTask && $editTask['client_id'] == $client['id']) ? 'selected' : '' ?>>
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
                                <option value="<?= (int) $project['id'] ?>"
                                        data-client-id="<?= (int) $project['client_id'] ?>"
                                        <?= ($editTask && $editTask['project_id'] == $project['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-hint">
                                Select a client first to filter projects, or leave empty for client-level task
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="name">Task Name *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?= $editTask ? htmlspecialchars($editTask['name']) : '' ?>"
                                   placeholder="Enter task name">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"
                                      placeholder="Enter task description (optional)"><?= $editTask ? htmlspecialchars($editTask['description']) : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <?php foreach (TaskStatus::all() as $statusValue): ?>
                                <option value="<?= htmlspecialchars($statusValue) ?>"
                                        <?= ($editTask && $editTask['status'] === $statusValue) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(TaskStatus::label($statusValue)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $editTask ? 'Update Task' : 'Add Task' ?>
                            </button>
                            <?php if ($editTask): ?>
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
                                    <td><?= (int) $task['id'] ?></td>
                                    <td>
                                        <span class="client-name">
                                            <span class="client-dot" style="background-color: <?= htmlspecialchars($task['client_color'] ?: '#6b7280') ?>;"
                                                  title="<?= htmlspecialchars($task['client_name']) ?>"></span>
                                            <strong><?= htmlspecialchars($task['client_name']) ?></strong>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($task['project_name']): ?>
                                            <?= htmlspecialchars($task['project_name']) ?>
                                        <?php else: ?>
                                            <em class="no-project">No Project</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($task['name']) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($task['status']) ?>">
                                            <?= htmlspecialchars(TaskStatus::label($task['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($task['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= (int) $task['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
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

    <script src="<?= url('assets/tasks.js') ?>"></script>
</body>
</html>
