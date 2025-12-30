<?php
/**
 * Project Templates Management
 * 
 * Allows administrators to manage project templates and associated task templates
 * for streamlined project setup and task assignment.
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
        
        // ADD NEW PROJECT TEMPLATE
        if ($action === 'add_template') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $active = isset($_POST['active']) ? 1 : 0;
            
            // Validate
            if (empty($name)) {
                $message = 'Template name is required.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO project_templates (name, description, active) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $description, $active]);
                    $message = 'Project template added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error adding template: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // EDIT PROJECT TEMPLATE
        elseif ($action === 'edit_template') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name)) {
                $message = 'Template name is required.';
                $message_type = 'error';
            } elseif ($id <= 0) {
                $message = 'Invalid template ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE project_templates SET name = ?, description = ?, active = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $active, $id]);
                    $message = 'Template updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating template: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // DELETE PROJECT TEMPLATE
        elseif ($action === 'delete_template') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $message = 'Invalid template ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM project_templates WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Template deleted successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting template: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // ADD TASK TEMPLATE
        elseif ($action === 'add_task') {
            $project_template_id = intval($_POST['project_template_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                $message = 'Task name is required.';
                $message_type = 'error';
            } elseif ($project_template_id <= 0) {
                $message = 'Invalid project template.';
                $message_type = 'error';
            } else {
                try {
                    // Get next sort order
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM task_templates WHERE project_template_id = ?");
                    $stmt->execute([$project_template_id]);
                    $next_order = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("INSERT INTO task_templates (project_template_id, name, description, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$project_template_id, $name, $description, $next_order]);
                    $message = 'Task added successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error adding task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // EDIT TASK TEMPLATE
        elseif ($action === 'edit_task') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                $message = 'Task name is required.';
                $message_type = 'error';
            } elseif ($id <= 0) {
                $message = 'Invalid task ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE task_templates SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    $message = 'Task updated successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // DELETE TASK TEMPLATE
        elseif ($action === 'delete_task') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $message = 'Invalid task ID.';
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM task_templates WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Task deleted successfully!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // REORDER TASK (MOVE UP/DOWN)
        elseif ($action === 'reorder_task') {
            $id = intval($_POST['id'] ?? 0);
            $direction = $_POST['direction'] ?? '';
            
            if ($id <= 0 || !in_array($direction, ['up', 'down'])) {
                $message = 'Invalid reorder request.';
                $message_type = 'error';
            } else {
                try {
                    // Get current task
                    $stmt = $pdo->prepare("SELECT * FROM task_templates WHERE id = ?");
                    $stmt->execute([$id]);
                    $task = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($task) {
                        // Get adjacent task
                        if ($direction === 'up') {
                            $stmt = $pdo->prepare("SELECT * FROM task_templates WHERE project_template_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1");
                        } else {
                            $stmt = $pdo->prepare("SELECT * FROM task_templates WHERE project_template_id = ? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1");
                        }
                        $stmt->execute([$task['project_template_id'], $task['sort_order']]);
                        $adjacent_task = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($adjacent_task) {
                            // Swap sort orders
                            $pdo->beginTransaction();
                            
                            $stmt = $pdo->prepare("UPDATE task_templates SET sort_order = ? WHERE id = ?");
                            $stmt->execute([$adjacent_task['sort_order'], $task['id']]);
                            
                            $stmt = $pdo->prepare("UPDATE task_templates SET sort_order = ? WHERE id = ?");
                            $stmt->execute([$task['sort_order'], $adjacent_task['id']]);
                            
                            $pdo->commit();
                            
                            $message = 'Task reordered successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Task is already at the ' . ($direction === 'up' ? 'top' : 'bottom') . '.';
                            $message_type = 'info';
                        }
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = 'Error reordering task: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// ============================================================================
// Fetch Data for Display
// ============================================================================

// Get all project templates
$stmt = $pdo->query("SELECT * FROM project_templates ORDER BY name ASC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all task templates organized by project template
$task_templates = [];
if (!empty($templates)) {
    $template_ids = array_column($templates, 'id');
    $placeholders = implode(',', array_fill(0, count($template_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM task_templates WHERE project_template_id IN ($placeholders) ORDER BY project_template_id, sort_order ASC");
    $stmt->execute($template_ids);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $task) {
        $task_templates[$task['project_template_id']][] = $task;
    }
}

// Generate CSRF token
$csrf_token = auth_csrf_token();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Templates - PluseHours Admin</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <style>
        /* Additional styles for project templates page */
        .template-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .template-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #055666 100%);
            color: white;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .template-header:hover {
            background: linear-gradient(135deg, #055666 0%, var(--primary-dark) 100%);
        }
        
        .template-header.inactive {
            background: linear-gradient(135deg, var(--gray-400) 0%, var(--gray-500) 100%);
        }
        
        .template-info {
            flex: 1;
        }
        
        .template-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .template-name .badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .template-description {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.25rem;
        }
        
        .template-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .template-content {
            padding: 1.5rem;
            display: none;
        }
        
        .template-content.active {
            display: block;
        }
        
        .tasks-list {
            margin-bottom: 1.5rem;
        }
        
        .task-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }
        
        .task-item:hover {
            background: var(--gray-100);
            box-shadow: var(--shadow-sm);
        }
        
        .task-order {
            background: var(--primary-color);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .task-info {
            flex: 1;
        }
        
        .task-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .task-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .task-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .btn-reorder {
            background: var(--gray-200);
            border: none;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s ease;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-reorder:hover {
            background: var(--gray-300);
        }
        
        .btn-reorder:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .add-task-form {
            background: linear-gradient(135deg, #FFF5E6 0%, #FFE9CC 100%);
            border: 2px dashed var(--primary-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }
        
        .form-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section h2 {
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state svg {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .btn-icon {
            padding: 0.5rem 0.75rem;
            min-width: auto;
        }
        
        .toggle-icon {
            margin-left: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .toggle-icon.active {
            transform: rotate(180deg);
        }
        
        .no-tasks {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>
    
    <main class="container" style="max-width: 1200px; padding: 2rem 1rem;">
        <div style="margin-bottom: 2rem;">
            <h1 style="color: var(--primary-dark); margin-bottom: 0.5rem;">Project Templates</h1>
            <p style="color: var(--text-secondary);">Manage reusable project templates with predefined tasks to streamline project creation.</p>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?= $message_type ?>" style="margin-bottom: 2rem;">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Add New Project Template Form -->
        <div class="form-section">
            <h2>Add New Project Template</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="add_template">
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="name">Template Name *</label>
                        <input type="text" id="name" name="name" required 
                               placeholder="e.g., Community Stakeholder Report">
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label for="active" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin-top: 1.85rem;">
                            <input type="checkbox" id="active" name="active" checked 
                                   style="width: auto; margin: 0;">
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
                    <span style="font-size: 1.2rem;">+</span> Add Project Template
                </button>
            </form>
        </div>
        
        <!-- Project Templates List -->
        <?php if (empty($templates)): ?>
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3>No Project Templates Yet</h3>
            <p>Create your first project template above to get started.</p>
        </div>
        <?php else: ?>
        
        <div id="templates-list">
            <?php foreach ($templates as $template): ?>
            <?php $tasks = $task_templates[$template['id']] ?? []; ?>
            <div class="template-section" data-template-id="<?= $template['id'] ?>">
                <!-- Template Header -->
                <div class="template-header <?= $template['active'] ? '' : 'inactive' ?>" 
                     onclick="toggleTemplate(<?= $template['id'] ?>)">
                    <div class="template-info">
                        <div class="template-name">
                            <span><?= htmlspecialchars($template['name']) ?></span>
                            <span class="badge"><?= count($tasks) ?> task<?= count($tasks) !== 1 ? 's' : '' ?></span>
                            <?php if (!$template['active']): ?>
                            <span class="badge" style="background: rgba(255, 255, 255, 0.4);">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($template['description']): ?>
                        <div class="template-description"><?= htmlspecialchars($template['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="template-actions" onclick="event.stopPropagation();">
                        <button type="button" class="btn btn-secondary btn-icon" 
                                onclick="editTemplate(<?= $template['id'] ?>)" title="Edit Template">
                            ✏️
                        </button>
                        <button type="button" class="btn btn-danger btn-icon" 
                                onclick="deleteTemplate(<?= $template['id'] ?>)" title="Delete Template">
                            🗑️
                        </button>
                        <span class="toggle-icon" style="font-size: 1.5rem;">▼</span>
                    </div>
                </div>
                
                <!-- Template Content (Tasks) -->
                <div class="template-content" id="template-content-<?= $template['id'] ?>">
                    <?php if (empty($tasks)): ?>
                    <div class="no-tasks">
                        No tasks defined yet. Add tasks below to build your template.
                    </div>
                    <?php else: ?>
                    <div class="tasks-list">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-dark);">Tasks</h3>
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
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="reorder_task">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="btn-reorder" title="Move Up"
                                            <?= $index === 0 ? 'disabled' : '' ?>>↑</button>
                                </form>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="reorder_task">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn-reorder" title="Move Down"
                                            <?= $index === count($tasks) - 1 ? 'disabled' : '' ?>>↓</button>
                                </form>
                                <button type="button" class="btn btn-secondary btn-icon" 
                                        onclick="editTask(<?= $task['id'] ?>)" title="Edit Task">
                                    ✏️
                                </button>
                                <button type="button" class="btn btn-danger btn-icon" 
                                        onclick="deleteTask(<?= $task['id'] ?>)" title="Delete Task">
                                    🗑️
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add Task Form -->
                    <div class="add-task-form">
                        <h4 style="margin-bottom: 1rem; color: var(--primary-dark);">Add Task to This Template</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="add_task">
                            <input type="hidden" name="project_template_id" value="<?= $template['id'] ?>">
                            
                            <div class="form-group">
                                <label for="task_name_<?= $template['id'] ?>">Task Name *</label>
                                <input type="text" id="task_name_<?= $template['id'] ?>" name="name" required 
                                       placeholder="e.g., Project Launch Meeting">
                            </div>
                            
                            <div class="form-group">
                                <label for="task_desc_<?= $template['id'] ?>">Task Description</label>
                                <textarea id="task_desc_<?= $template['id'] ?>" name="description" rows="2" 
                                          placeholder="Optional description of this task..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <span style="font-size: 1.2rem;">+</span> Add Task
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php endif; ?>
    </main>
    
    <script>
        // Toggle template content visibility
        function toggleTemplate(templateId) {
            const content = document.getElementById(`template-content-${templateId}`);
            const icon = content.previousElementSibling.querySelector('.toggle-icon');
            
            content.classList.toggle('active');
            icon.classList.toggle('active');
        }
        
        // Edit project template
        function editTemplate(id) {
            const section = document.querySelector(`[data-template-id="${id}"]`);
            const header = section.querySelector('.template-header');
            const nameEl = header.querySelector('.template-name span:first-child');
            const descEl = header.querySelector('.template-description');
            const isActive = !header.classList.contains('inactive');
            
            const name = nameEl.textContent.trim();
            const description = descEl ? descEl.textContent.trim() : '';
            
            const newName = prompt('Template Name:', name);
            if (newName === null) return;
            
            const newDesc = prompt('Description:', description);
            if (newDesc === null) return;
            
            const activeConfirm = confirm('Is this template active?');
            
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="edit_template">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="name" value="${newName}">
                <input type="hidden" name="description" value="${newDesc}">
                ${activeConfirm ? '<input type="hidden" name="active" value="1">' : ''}
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Delete project template
        function deleteTemplate(id) {
            if (!confirm('Are you sure you want to delete this template? All associated tasks will also be deleted.')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="delete_template">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Edit task template
        function editTask(id) {
            const taskItem = document.querySelector(`button[onclick*="editTask(${id})"]`).closest('.task-item');
            const nameEl = taskItem.querySelector('.task-name');
            const descEl = taskItem.querySelector('.task-description');
            
            const name = nameEl.textContent.trim();
            const description = descEl ? descEl.textContent.trim() : '';
            
            const newName = prompt('Task Name:', name);
            if (newName === null) return;
            
            const newDesc = prompt('Task Description:', description);
            if (newDesc === null) return;
            
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="edit_task">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="name" value="${newName}">
                <input type="hidden" name="description" value="${newDesc}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Delete task template
        function deleteTask(id) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Auto-dismiss success messages
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message.success');
            messages.forEach(msg => {
                setTimeout(() => {
                    msg.style.transition = 'opacity 0.5s ease';
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500);
                }, 3000);
            });
        });
    </script>
</body>
</html>
