<?php
/**
 * Project Management - Add, Edit, Delete, and List Projects
 *
 * Thin controller: dispatches POST actions to ProjectService / TaskService
 * and renders the projects list with optional inline task drilldown.
 */

require __DIR__ . '/../../sso/sso_include.php';
pulse_require_admin();

require_once __DIR__ . '/../../src/Service/ProjectService.php';
require_once __DIR__ . '/../../src/Service/TaskService.php';
require_once __DIR__ . '/../../src/Service/ProjectTemplateService.php';

$projectService = new ProjectService();
$taskService = new TaskService();
$templateService = new ProjectTemplateService();

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

        // Normalize the boolean `active` checkbox once so update paths
        // can persist both checked and unchecked states.
        $normalizedPost = $_POST;
        if (in_array($action, ['add', 'edit'], true)) {
            $normalizedPost['active'] = isset($_POST['active']) ? 1 : 0;
        }

        switch ($action) {
            case 'add':
                $result = $projectService->createProject($normalizedPost);
                break;
            case 'edit':
                $id = (int) ($_POST['id'] ?? 0);
                $result = $projectService->updateProject($id, $normalizedPost);
                break;
            case 'delete':
                $result = $projectService->deleteProjectCascade($_POST['id'] ?? 0);
                break;
            case 'update_task_status':
                $result = $taskService->updateStatus(
                    $_POST['task_id'] ?? 0,
                    $_POST['task_status'] ?? ''
                );
                break;
            case 'add_task':
                $result = $taskService->createForProject(
                    $_POST['project_id'] ?? 0,
                    [
                        'name'        => $_POST['task_name'] ?? '',
                        'description' => $_POST['task_description'] ?? '',
                    ]
                );
                break;
            case 'delete_task':
                $result = $taskService->deleteTask($_POST['task_id'] ?? 0);
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

$editProject = isset($_GET['edit']) ? $projectService->getProjectById($_GET['edit']) : null;
$viewProjectId = isset($_GET['view']) ? (int) $_GET['view'] : null;

$projects = $projectService->getAllWithStats();
$templateData = $templateService->getAllWithTasks();
$templates = array_filter($templateData['templates'], fn($t) => (int) $t['active'] === 1);

// Active clients for the dropdown (matches prior behavior of `active = 1`)
$pdo = get_db_connection();
$clientRepo = new ClientRepository($pdo);
$clients = $clientRepo->getActive();

$projectTasks = $viewProjectId ? $projectService->getTasksForProject($viewProjectId) : [];
$csrfToken = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects - PluseHours Admin</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/projects.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h2>Manage Projects</h2>
                <p>Create projects from templates, manage tasks, and track progress.</p>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit Project Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $editProject ? 'Edit Project' : 'Add New Project' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <input type="hidden" name="action" value="<?= $editProject ? 'edit' : 'add' ?>">
                        <?php if ($editProject): ?>
                        <input type="hidden" name="id" value="<?= (int) $editProject['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="client_id">Client *</label>
                            <select id="client_id" name="client_id" required>
                                <option value="">-- Select a client --</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>"
                                        <?= ($editProject && $editProject['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($clients)): ?>
                            <small class="field-error">
                                No active clients found. <a href="clients.php">Add a client first</a>.
                            </small>
                            <?php endif; ?>
                        </div>

                        <?php if (!$editProject): ?>
                        <div class="form-group">
                            <label for="project_template_id">Project Template (Optional)</label>
                            <select id="project_template_id" name="project_template_id">
                                <option value="">-- No template (Custom project) --</option>
                                <?php foreach ($templates as $template): ?>
                                <option value="<?= (int) $template['id'] ?>"
                                        data-description="<?= htmlspecialchars($template['description'] ?? '', ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($template['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-hint">
                                Select a template to automatically create tasks for this project.
                            </small>
                            <div id="template-description" class="template-description-box">
                                <strong>Template Description:</strong>
                                <p id="template-description-text"></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Project Name *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?= $editProject ? htmlspecialchars($editProject['name']) : '' ?>"
                                   placeholder="e.g., 2025 Annual Report, Website Redesign">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"
                                      placeholder="Describe the project goals and scope..."><?= $editProject ? htmlspecialchars($editProject['description']) : '' ?></textarea>
                        </div>

                        <div class="form-row form-row-two">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date"
                                       value="<?= $editProject ? htmlspecialchars($editProject['start_date'] ?? '') : '' ?>">
                            </div>

                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date"
                                       value="<?= $editProject ? htmlspecialchars($editProject['end_date'] ?? '') : '' ?>">
                            </div>
                        </div>

                        <div class="form-row form-row-two">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <?php foreach (ProjectStatus::all() as $statusValue): ?>
                                    <option value="<?= htmlspecialchars($statusValue) ?>"
                                            <?php
                                                $isSelected = $editProject
                                                    ? ($editProject['status'] === $statusValue)
                                                    : ($statusValue === ProjectStatus::ACTIVE);
                                            ?>
                                            <?= $isSelected ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ProjectStatus::label($statusValue)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="active" name="active"
                                           <?= ($editProject && $editProject['active']) || !$editProject ? 'checked' : '' ?>>
                                    <label for="active">Active</label>
                                </div>
                                <small class="field-hint">
                                    Inactive projects are hidden from time tracking
                                </small>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $editProject ? 'Update Project' : 'Create Project' ?>
                            </button>
                            <?php if ($editProject): ?>
                            <a href="projects.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Projects List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Projects (<?= count($projects) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($projects)): ?>
                    <div class="empty-state">
                        <p>No projects found. Create your first project above!</p>
                    </div>
                    <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Project Name</th>
                                    <th>Template</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Dates</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td>
                                        <div class="client-badge" style="background-color: <?= htmlspecialchars($project['client_color']) ?>22;">
                                            <span class="client-color-dot" style="background-color: <?= htmlspecialchars($project['client_color']) ?>"></span>
                                            <span><?= htmlspecialchars($project['client_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($project['name']) ?></strong>
                                        <?php if (!empty($project['description'])): ?>
                                        <br><small class="task-meta"><?= htmlspecialchars(substr($project['description'], 0, 60)) ?><?= strlen($project['description']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($project['template_name']): ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($project['template_name']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">Custom</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($project['status']) ?>">
                                            <?= htmlspecialchars(ProjectStatus::label($project['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($project['task_count'] > 0): ?>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?= ($project['completed_tasks'] / $project['task_count']) * 100 ?>%"></div>
                                            <div class="progress-text">
                                                <?= (int) $project['completed_tasks'] ?>/<?= (int) $project['task_count'] ?> tasks
                                            </div>
                                        </div>
                                        <div style="margin-top: 0.5rem;">
                                            <a href="?view=<?= (int) $project['id'] ?>" class="expand-tasks-btn">
                                                <?= $viewProjectId == $project['id'] ? '▼ Hide Tasks' : '▶ View Tasks' ?>
                                            </a>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">No tasks</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($project['start_date']): ?>
                                        <small>Start: <?= date('M j, Y', strtotime($project['start_date'])) ?></small><br>
                                        <?php endif; ?>
                                        <?php if ($project['end_date']): ?>
                                        <small>End: <?= date('M j, Y', strtotime($project['end_date'])) ?></small>
                                        <?php endif; ?>
                                        <?php if (!$project['start_date'] && !$project['end_date']): ?>
                                        <span class="text-muted">No dates</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= (int) $project['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this project? All associated tasks will also be deleted.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <?php if ($viewProjectId == $project['id'] && !empty($projectTasks)): ?>
                                <tr class="task-details-row">
                                    <td colspan="7">
                                        <h4 style="margin-bottom: 1rem;">Tasks for: <?= htmlspecialchars($project['name']) ?></h4>
                                        <div class="task-list">
                                            <?php foreach ($projectTasks as $task): ?>
                                            <div class="task-item">
                                                <form method="POST" class="task-status-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                    <input type="hidden" name="action" value="update_task_status">
                                                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">

                                                    <select name="task_status" class="task-status-selector" onchange="this.form.submit()">
                                                        <?php foreach (TaskStatus::all() as $statusValue): ?>
                                                        <option value="<?= htmlspecialchars($statusValue) ?>" <?= $task['status'] === $statusValue ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars(TaskStatus::label($statusValue)) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <div class="task-name task-status-<?= htmlspecialchars($task['status']) ?>">
                                                        <strong><?= htmlspecialchars($task['name']) ?></strong>
                                                        <?php if (!empty($task['description'])): ?>
                                                        <br><small class="task-meta"><?= htmlspecialchars($task['description']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>

                                                <form method="POST" class="delete-task-form"
                                                      onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                    <input type="hidden" name="action" value="delete_task">
                                                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-small delete-task-btn">Delete</button>
                                                </form>
                                            </div>
                                            <?php endforeach; ?>

                                            <div class="add-task-card">
                                                <h5>Add Custom Task</h5>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                    <input type="hidden" name="action" value="add_task">
                                                    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">

                                                    <div class="form-group">
                                                        <label for="task_name_<?= (int) $project['id'] ?>">Task Name *</label>
                                                        <input type="text" id="task_name_<?= (int) $project['id'] ?>" name="task_name" required>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="task_description_<?= (int) $project['id'] ?>">Description</label>
                                                        <textarea id="task_description_<?= (int) $project['id'] ?>" name="task_description" rows="2"></textarea>
                                                    </div>

                                                    <button type="submit" class="btn btn-primary">Add Task</button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= url('assets/projects.js') ?>"></script>
</body>
</html>
