<?php
/**
 * Project Templates Management
 *
 * Thin controller: handles POST dispatch via ProjectTemplateService,
 * then renders the list of templates and their task templates.
 */

require __DIR__ . '/../../sso/sso_include.php';
pulse_require_admin();

require_once __DIR__ . '/../../src/Service/ProjectTemplateService.php';

$service = new ProjectTemplateService();
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
            case 'add_template':
                $result = $service->createTemplate($_POST);
                break;
            case 'edit_template':
                $result = $service->updateTemplate($_POST);
                break;
            case 'delete_template':
                $result = $service->deleteTemplate($_POST['id'] ?? 0);
                break;
            case 'add_task':
                $result = $service->addTask($_POST);
                break;
            case 'edit_task':
                $result = $service->updateTask($_POST);
                break;
            case 'delete_task':
                $result = $service->deleteTask($_POST['id'] ?? 0);
                break;
            case 'reorder_task':
                $result = $service->reorderTask(
                    $_POST['id'] ?? 0,
                    $_POST['direction'] ?? ''
                );
                break;
            default:
                $message = 'Unknown action.';
                $messageType = 'error';
        }

        if ($result !== null) {
            $message = $result['message'];
            $messageType = $result['type'] ?? ($result['success'] ? 'success' : 'error');
        }
    }
}

// ----------------------------------------------------------------------
// Fetch data for display
// ----------------------------------------------------------------------

$data = $service->getAllWithTasks();
$templates = $data['templates'];
$tasksByTemplate = $data['tasksByTemplate'];
$csrfToken = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Templates - PluseHours Admin</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/project-templates.css') ?>">
</head>
<body data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>

    <main class="container template-container">
        <div class="page-intro">
            <h1>Project Templates</h1>
            <p>Manage reusable project templates with predefined tasks to streamline project creation.</p>
        </div>

        <?php if ($message): ?>
        <div class="message <?= htmlspecialchars($messageType) ?> message-spaced">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Add New Project Template Form -->
        <div class="form-section">
            <h2>Add New Project Template</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="action" value="add_template">

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="name">Template Name *</label>
                        <input type="text" id="name" name="name" required
                               placeholder="e.g., Community Stakeholder Report">
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label for="active" class="checkbox-label">
                            <input type="checkbox" id="active" name="active" checked class="checkbox-inline">
                            <span>Active</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Brief description of this project template..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="btn-plus-icon">+</span> Add Project Template
                </button>
            </form>
        </div>

        <!-- Project Templates List -->
        <?php if (empty($templates)): ?>
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3>No Project Templates Yet</h3>
            <p>Create your first project template above to get started.</p>
        </div>
        <?php else: ?>

        <div id="templates-list">
            <?php foreach ($templates as $template): ?>
            <?php
                $tasks = $tasksByTemplate[$template['id']] ?? [];
                $taskCount = count($tasks);
            ?>
            <div class="template-section" data-template-id="<?= (int) $template['id'] ?>">
                <div class="template-header <?= $template['active'] ? '' : 'inactive' ?>"
                     data-template-toggle="<?= (int) $template['id'] ?>">
                    <div class="template-info">
                        <div class="template-name">
                            <span><?= htmlspecialchars($template['name']) ?></span>
                            <span class="badge"><?= $taskCount ?> task<?= $taskCount !== 1 ? 's' : '' ?></span>
                            <?php if (!$template['active']): ?>
                            <span class="badge badge-muted">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($template['description']): ?>
                        <div class="template-description"><?= htmlspecialchars($template['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="template-actions">
                        <button type="button" class="btn btn-secondary btn-icon"
                                data-template-edit="<?= (int) $template['id'] ?>" title="Edit Template">
                            &#9998;
                        </button>
                        <button type="button" class="btn btn-danger btn-icon"
                                data-template-delete="<?= (int) $template['id'] ?>" title="Delete Template">
                            &#128465;
                        </button>
                        <span class="toggle-icon">&#9660;</span>
                    </div>
                </div>

                <div class="template-content" id="template-content-<?= (int) $template['id'] ?>">
                    <?php if (empty($tasks)): ?>
                    <div class="no-tasks">
                        No tasks defined yet. Add tasks below to build your template.
                    </div>
                    <?php else: ?>
                    <div class="tasks-list">
                        <h3 class="section-heading">Tasks</h3>
                        <?php foreach ($tasks as $index => $task): ?>
                        <div class="task-item">
                            <div class="task-order"><?= $index + 1 ?></div>
                            <div class="task-info">
                                <div class="task-name"><?= htmlspecialchars($task['name']) ?></div>
                                <?php if ($task['description']): ?>
                                <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="task-actions">
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                    <input type="hidden" name="action" value="reorder_task">
                                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="btn-reorder" title="Move Up"
                                            <?= $index === 0 ? 'disabled' : '' ?>>&#8593;</button>
                                </form>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                    <input type="hidden" name="action" value="reorder_task">
                                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn-reorder" title="Move Down"
                                            <?= $index === $taskCount - 1 ? 'disabled' : '' ?>>&#8595;</button>
                                </form>
                                <button type="button" class="btn btn-secondary btn-icon"
                                        data-task-edit="<?= (int) $task['id'] ?>" title="Edit Task">
                                    &#9998;
                                </button>
                                <button type="button" class="btn btn-danger btn-icon"
                                        data-task-delete="<?= (int) $task['id'] ?>" title="Delete Task">
                                    &#128465;
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="add-task-form">
                        <h4 class="add-task-heading">Add Task to This Template</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                            <input type="hidden" name="action" value="add_task">
                            <input type="hidden" name="project_template_id" value="<?= (int) $template['id'] ?>">

                            <div class="form-group">
                                <label for="task_name_<?= (int) $template['id'] ?>">Task Name *</label>
                                <input type="text" id="task_name_<?= (int) $template['id'] ?>" name="name" required
                                       placeholder="e.g., Project Launch Meeting">
                            </div>

                            <div class="form-group">
                                <label for="task_desc_<?= (int) $template['id'] ?>">Task Description</label>
                                <textarea id="task_desc_<?= (int) $template['id'] ?>" name="description" rows="2"
                                          placeholder="Optional description of this task..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <span class="btn-plus-icon">+</span> Add Task
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </main>

    <script src="<?= url('assets/project-templates.js') ?>"></script>
</body>
</html>
