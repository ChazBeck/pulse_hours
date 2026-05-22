<?php
/**
 * Tasks View - Client, Project, and Task Overview
 *
 * Admin page listing tasks grouped by client and project, with filters,
 * stats, and modal-based add/edit. POST actions are delegated to TaskService.
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

$filterClient  = $_GET['client'] ?? '';
$filterProject = $_GET['project'] ?? '';
$filterStatus  = $_GET['status'] ?? '';

$tasks = $service->getFilteredWithRelations([
    'client_id'  => $filterClient,
    'project_id' => $filterProject,
    'status'     => $filterStatus,
]);

$options = $service->getSelectOptions();
$clients = $options['clients'];
$projects = $options['projects'];
$csrfToken = auth_csrf_token();

// Stats
$totalTasks      = count($tasks);
$completedTasks  = count(array_filter($tasks, fn($t) => $t['task_status'] === TaskStatus::COMPLETED));
$inProgressTasks = count(array_filter($tasks, fn($t) => $t['task_status'] === TaskStatus::IN_PROGRESS));
$uniqueClients   = count(array_unique(array_column($tasks, 'client_id')));
$uniqueProjects  = count(array_unique(array_filter(array_column($tasks, 'project_id'))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks View - Pulse Hours</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/tasks-view.css') ?>">
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
            <button type="button" class="btn btn-primary" data-action="open-add-modal">+ Add Task</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?= $totalTasks ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $completedTasks ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $inProgressTasks ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $uniqueClients ?></div>
                <div class="stat-label">Clients</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $uniqueProjects ?></div>
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
                            <option value="<?= (int) $c['id'] ?>" <?= ($filterClient == $c['id']) ? 'selected' : '' ?>>
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
                            <option value="<?= (int) $proj['id'] ?>" <?= ($filterProject == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <?php foreach (TaskStatus::all() as $statusValue): ?>
                            <option value="<?= htmlspecialchars($statusValue) ?>" <?= ($filterStatus === $statusValue) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(TaskStatus::label($statusValue)) ?>
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
                                <td>
                                    <?php if ($task['project_name']): ?>
                                        <?= htmlspecialchars($task['project_name']) ?>
                                    <?php else: ?>
                                        <em class="no-project">No Project</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($task['task_name']) ?></strong>
                                    <?php if ($task['task_description']): ?>
                                        <div class="task-description"><?= htmlspecialchars($task['task_description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($task['task_status']) ?>">
                                        <?= htmlspecialchars(TaskStatus::label($task['task_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-icon btn-edit"
                                                data-edit-task='<?= htmlspecialchars(json_encode($task), ENT_QUOTES) ?>'>Edit</button>
                                        <button type="button" class="btn-icon btn-delete"
                                                data-delete-task="<?= (int) $task['task_id'] ?>"
                                                data-delete-task-name="<?= htmlspecialchars($task['task_name'], ENT_QUOTES) ?>">Delete</button>
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
                <button type="button" class="btn-close" data-action="close-modal" data-modal="addModal">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Client *</label>
                    <select name="client_id" id="add_client_id" required data-filter-client="add">
                        <option value="">Select a client...</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Project (Optional)</label>
                    <select name="project_id" id="add_project_id">
                        <option value="">No project (client-level task)</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= (int) $proj['id'] ?>" data-client="<?= (int) $proj['client_id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
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
                        <?php foreach (TaskStatus::all() as $statusValue): ?>
                            <option value="<?= htmlspecialchars($statusValue) ?>"><?= htmlspecialchars(TaskStatus::label($statusValue)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="addModal">Cancel</button>
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
                <button type="button" class="btn-close" data-action="close-modal" data-modal="editModal">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>Client *</label>
                    <select name="client_id" id="edit_client_id" required data-filter-client="edit">
                        <option value="">Select a client...</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Project (Optional)</label>
                    <select name="project_id" id="edit_project_id">
                        <option value="">No project (client-level task)</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= (int) $proj['id'] ?>" data-client="<?= (int) $proj['client_id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
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
                        <?php foreach (TaskStatus::all() as $statusValue): ?>
                            <option value="<?= htmlspecialchars($statusValue) ?>"><?= htmlspecialchars(TaskStatus::label($statusValue)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal="editModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form (submitted via JS after confirm) -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script src="<?= url('/assets/tasks-view.js') ?>"></script>
</body>
</html>
