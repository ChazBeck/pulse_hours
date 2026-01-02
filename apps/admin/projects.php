<?php
/**
 * Project Management - Add, Edit, Delete, and List Projects
 * 
 * Allows administrators to manage project records including
 * client assignments, templates, tasks, status, and dates.
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
        
        // ADD NEW PROJECT
        if ($action === 'add') {
            $client_id = intval($_POST['client_id'] ?? 0);
            $project_template_id = !empty($_POST['project_template_id']) ? intval($_POST['project_template_id']) : null;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $status = $_POST['status'] ?? 'active';
            $active = isset($_POST['active']) ? 1 : 0;
            
            // Validate
            if (empty($name)) {
                $message = 'Project name is required.';
                $message_type = 'error';
            } elseif ($client_id <= 0) {
                $message = 'Please select a valid client.';
                $message_type = 'error';
            } else {
                try {
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Create the project
                    $stmt = $pdo->prepare("INSERT INTO projects (client_id, project_template_id, name, description, start_date, end_date, status, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$client_id, $project_template_id, $name, $description, $start_date, $end_date, $status, $active]);
                    $project_id = $pdo->lastInsertId();
                    
                    // If a template was selected, create tasks from the template
                    if ($project_template_id) {
                        $stmt = $pdo->prepare("SELECT name, description FROM task_templates WHERE project_template_id = ? ORDER BY sort_order ASC");
                        $stmt->execute([$project_template_id]);
                        $task_templates = $stmt->fetchAll();
                        
                        $task_stmt = $pdo->prepare("INSERT INTO tasks (project_id, name, description, status) VALUES (?, ?, ?, 'not-started')");
                        
                        foreach ($task_templates as $task_template) {
                            $task_stmt->execute([
                                $project_id,
                                $task_template['name'],
                                $task_template['description']
                            ]);
                        }
                        
                        $task_count = count($task_templates);
                        $message = "Project created successfully with $task_count tasks from template!";
                    } else {
                        $message = 'Project created successfully!';
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = 'Error creating project: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // EDIT PROJECT
        elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $client_id = intval($_POST['client_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $status = $_POST['status'] ?? 'active';
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name)) {
                $message = 'Project name is required.';
                $message_type = 'error';
            } elseif ($id <= 0) {
                $message = 'Invalid project ID.';
                $message_type = 'error';
            } elseif ($client_id <= 0) {
                $message = 'Please select a valid client.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE projects SET client_id = ?, name = ?, description = ?, start_date = ?, end_date = ?, status = ?, active = ? WHERE id = ?");
                    $stmt->execute([$client_id, $name, $description, $start_date, $end_date, $status, $active, $id]);
                    $message = 'Project updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating project: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // DELETE PROJECT
        elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $message = 'Invalid project ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Project deleted successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting project: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // UPDATE TASK STATUS
        elseif ($action === 'update_task_status') {
            $task_id = intval($_POST['task_id'] ?? 0);
            $task_status = $_POST['task_status'] ?? 'not-started';
            
            if ($task_id <= 0) {
                $message = 'Invalid task ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
                    $stmt->execute([$task_status, $task_id]);
                    $message = 'Task status updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // ADD TASK TO PROJECT
        elseif ($action === 'add_task') {
            $project_id = intval($_POST['project_id'] ?? 0);
            $task_name = trim($_POST['task_name'] ?? '');
            $task_description = trim($_POST['task_description'] ?? '');
            
            if ($project_id <= 0) {
                $message = 'Invalid project ID.';
                $message_type = 'error';
            } elseif (empty($task_name)) {
                $message = 'Task name is required.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO tasks (project_id, name, description, status) VALUES (?, ?, ?, 'not-started')");
                    $stmt->execute([$project_id, $task_name, $task_description]);
                    $message = 'Task added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error adding task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // DELETE TASK
        elseif ($action === 'delete_task') {
            $task_id = intval($_POST['task_id'] ?? 0);
            
            if ($task_id <= 0) {
                $message = 'Invalid task ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                    $stmt->execute([$task_id]);
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
// Load Data for Display
// ============================================================================

// Check if editing a project
$edit_project = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_project = $stmt->fetch();
}

// Get view parameter for task details
$view_project_id = isset($_GET['view']) ? intval($_GET['view']) : null;

// Fetch all projects with client information, template info, and task counts
$query = "
    SELECT 
        p.*,
        c.name AS client_name,
        c.client_color,
        c.client_logo,
        pt.name AS template_name,
        COUNT(DISTINCT t.id) AS task_count,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks
    FROM projects p
    INNER JOIN clients c ON p.client_id = c.id
    LEFT JOIN project_templates pt ON p.project_template_id = pt.id
    LEFT JOIN tasks t ON p.id = t.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
";
$stmt = $pdo->query($query);
$projects = $stmt->fetchAll();

// Fetch active clients for dropdown
$stmt = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name ASC");
$clients = $stmt->fetchAll();

// Fetch active project templates for dropdown
$stmt = $pdo->query("SELECT id, name, description FROM project_templates WHERE active = 1 ORDER BY name ASC");
$templates = $stmt->fetchAll();

// If viewing a specific project, fetch its tasks
$project_tasks = [];
if ($view_project_id) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY created_at ASC");
    $stmt->execute([$view_project_id]);
    $project_tasks = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects - PluseHours Admin</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <style>
        .client-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .client-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-active { background-color: #d1fae5; color: #065f46; }
        .status-completed { background-color: #dbeafe; color: #1e40af; }
        .status-on-hold { background-color: #fef3c7; color: #92400e; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        
        .progress-bar-container {
            width: 100%;
            height: 20px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .progress-bar {
            height: 100%;
            background-color: #10b981;
            transition: width 0.3s ease;
        }
        .progress-text {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
        }
        
        .task-list {
            margin-top: 1rem;
        }
        .task-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background-color: #f9fafb;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        .task-status-selector {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            font-size: 0.875rem;
        }
        .task-name {
            flex: 1;
            font-weight: 500;
        }
        .task-status-not-started { color: #6b7280; }
        .task-status-in-progress { color: #3b82f6; }
        .task-status-completed { color: #10b981; text-decoration: line-through; }
        .task-status-blocked { color: #ef4444; }
        
        .expand-tasks-btn {
            cursor: pointer;
            color: #E58325;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .expand-tasks-btn:hover {
            text-decoration: underline;
        }
    </style>
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
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit Project Form -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $edit_project ? 'Edit Project' : 'Add New Project' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                        <input type="hidden" name="action" value="<?= $edit_project ? 'edit' : 'add' ?>">
                        <?php if ($edit_project): ?>
                        <input type="hidden" name="id" value="<?= $edit_project['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="client_id">Client *</label>
                            <select id="client_id" name="client_id" required>
                                <option value="">-- Select a client --</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" 
                                        <?= ($edit_project && $edit_project['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($clients)): ?>
                            <small style="display: block; margin-top: 0.25rem; color: #ef4444;">
                                No active clients found. <a href="clients.php">Add a client first</a>.
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$edit_project): ?>
                        <div class="form-group">
                            <label for="project_template_id">Project Template (Optional)</label>
                            <select id="project_template_id" name="project_template_id">
                                <option value="">-- No template (Custom project) --</option>
                                <?php foreach ($templates as $template): ?>
                                <option value="<?= $template['id'] ?>" 
                                        data-description="<?= htmlspecialchars($template['description'] ?? '') ?>">
                                    <?= htmlspecialchars($template['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                Select a template to automatically create tasks for this project.
                            </small>
                            <div id="template-description" style="margin-top: 0.5rem; padding: 0.75rem; background-color: #f3f4f6; border-radius: 4px; display: none;">
                                <strong>Template Description:</strong>
                                <p id="template-description-text" style="margin-top: 0.25rem;"></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="name">Project Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?= $edit_project ? htmlspecialchars($edit_project['name']) : '' ?>"
                                   placeholder="e.g., 2025 Annual Report, Website Redesign">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4" 
                                      placeholder="Describe the project goals and scope..."><?= $edit_project ? htmlspecialchars($edit_project['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" 
                                       value="<?= $edit_project ? htmlspecialchars($edit_project['start_date'] ?? '') : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" 
                                       value="<?= $edit_project ? htmlspecialchars($edit_project['end_date'] ?? '') : '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="active" <?= ($edit_project && $edit_project['status'] == 'active') || !$edit_project ? 'selected' : '' ?>>Active</option>
                                    <option value="completed" <?= ($edit_project && $edit_project['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                                    <option value="on-hold" <?= ($edit_project && $edit_project['status'] == 'on-hold') ? 'selected' : '' ?>>On Hold</option>
                                    <option value="cancelled" <?= ($edit_project && $edit_project['status'] == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="active" name="active" 
                                           <?= ($edit_project && $edit_project['active']) || !$edit_project ? 'checked' : '' ?>>
                                    <label for="active">Active</label>
                                </div>
                                <small style="display: block; margin-top: 0.25rem; color: #6b7280;">
                                    Inactive projects are hidden from time tracking
                                </small>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?= $edit_project ? 'Update Project' : 'Create Project' ?>
                            </button>
                            <?php if ($edit_project): ?>
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
                                        <br><small style="color: #6b7280;"><?= htmlspecialchars(substr($project['description'], 0, 60)) ?><?= strlen($project['description']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($project['template_name']): ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($project['template_name']) ?></span>
                                        <?php else: ?>
                                        <span style="color: #9ca3af;">Custom</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $project['status'] ?>">
                                            <?= ucfirst(str_replace('-', ' ', $project['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($project['task_count'] > 0): ?>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?= ($project['completed_tasks'] / $project['task_count']) * 100 ?>%"></div>
                                            <div class="progress-text">
                                                <?= $project['completed_tasks'] ?>/<?= $project['task_count'] ?> tasks
                                            </div>
                                        </div>
                                        <div style="margin-top: 0.5rem;">
                                            <a href="?view=<?= $project['id'] ?>" class="expand-tasks-btn">
                                                <?= $view_project_id == $project['id'] ? '▼ Hide Tasks' : '▶ View Tasks' ?>
                                            </a>
                                        </div>
                                        <?php else: ?>
                                        <span style="color: #9ca3af;">No tasks</span>
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
                                        <span style="color: #9ca3af;">No dates</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= $project['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this project? All associated tasks will also be deleted.');">
                                                <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Task Details Row (if viewing this project) -->
                                <?php if ($view_project_id == $project['id'] && !empty($project_tasks)): ?>
                                <tr>
                                    <td colspan="7" style="background-color: #f9fafb; padding: 1.5rem;">
                                        <h4 style="margin-bottom: 1rem;">Tasks for: <?= htmlspecialchars($project['name']) ?></h4>
                                        <div class="task-list">
                                            <?php foreach ($project_tasks as $task): ?>
                                            <div class="task-item">
                                                <form method="POST" style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                                    <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="update_task_status">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    
                                                    <select name="task_status" class="task-status-selector" onchange="this.form.submit()">
                                                        <option value="not-started" <?= $task['status'] == 'not-started' ? 'selected' : '' ?>>Not Started</option>
                                                        <option value="in-progress" <?= $task['status'] == 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="completed" <?= $task['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        <option value="blocked" <?= $task['status'] == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                                    </select>
                                                    
                                                    <div class="task-name task-status-<?= $task['status'] ?>">
                                                        <strong><?= htmlspecialchars($task['name']) ?></strong>
                                                        <?php if (!empty($task['description'])): ?>
                                                        <br><small style="color: #6b7280;"><?= htmlspecialchars($task['description']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                                
                                                <!-- Delete Task Button -->
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                    <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="delete_task">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-small" style="margin-left: 0.5rem;">Delete</button>
                                                </form>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                            <!-- Add Task Form -->
                                            <div class="add-task-form" style="margin-top: 1.5rem; padding: 1rem; background: white; border: 2px dashed var(--primary-color); border-radius: 6px;">
                                                <h5 style="margin-bottom: 1rem; color: var(--primary-color);">Add Custom Task</h5>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="add_task">
                                                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                                    
                                                    <div class="form-group">
                                                        <label for="task_name_<?= $project['id'] ?>">Task Name *</label>
                                                        <input type="text" id="task_name_<?= $project['id'] ?>" name="task_name" required>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="task_description_<?= $project['id'] ?>">Description</label>
                                                        <textarea id="task_description_<?= $project['id'] ?>" name="task_description" rows="2"></textarea>
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
    
    <script>
        // Show template description when template is selected
        document.getElementById('project_template_id')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const description = selectedOption.getAttribute('data-description');
            const descriptionDiv = document.getElementById('template-description');
            const descriptionText = document.getElementById('template-description-text');
            
            if (description && description.trim() !== '') {
                descriptionText.textContent = description;
                descriptionDiv.style.display = 'block';
            } else {
                descriptionDiv.style.display = 'none';
            }
        });
    </script>
</body>
</html>
