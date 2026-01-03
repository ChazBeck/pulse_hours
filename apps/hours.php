<?php
/**
 * Hours Entry Page
 * 
 * Allows users to log hours worked on tasks grouped by client and project.
 */

require __DIR__ . '/../auth/include/auth_include.php';
auth_init();
auth_require_login();

require_once __DIR__ . '/../includes/date_helpers.php';

$user = auth_get_user();
$pdo = get_db_connection();

// Success/error messages
$success_message = '';
$error_message = '';

// ============================================================================
// Get Year-Week from User's Pulse Submission
// ============================================================================

// Get the year_week from the user's most recent pulse entry
$stmt = $pdo->prepare("SELECT year_week FROM pulse WHERE user_id = ? ORDER BY date_created DESC LIMIT 1");
$stmt->execute([$user['id']]);
$pulse_entry = $stmt->fetch();

if (!$pulse_entry) {
    // If no pulse entry found, redirect back to pulse page
    header('Location: ' . url('/apps/pulse.php'));
    exit();
}

$target_year_week = $pulse_entry['year_week'];

// ============================================================================
// Handle Hours Submission
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_hours'])) {
    try {
        $pdo->beginTransaction();
        
        $hours_data = $_POST['hours'] ?? [];
        $date_worked = date('Y-m-d'); // Use today's date
        
        $saved_count = 0;
        
        foreach ($hours_data as $task_id => $hours) {
            $hours = trim($hours);
            
            // Skip empty entries
            if ($hours === '' || $hours === '0' || $hours === '0.00') {
                continue;
            }
            
            // Validate hours
            if (!is_numeric($hours) || $hours <= 0) {
                throw new Exception('Hours must be a positive number');
            }
            
            // Get project_id for this task
            $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            if (!$task) {
                throw new Exception('Invalid task ID: ' . $task_id);
            }
            
            // Check if entry already exists for this user/task/date
            $stmt = $pdo->prepare("
                SELECT id FROM hours 
                WHERE user_id = ? AND task_id = ? AND date_worked = ?
            ");
            $stmt->execute([$user['id'], $task_id, $date_worked]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing entry
                $stmt = $pdo->prepare("
                    UPDATE hours 
                    SET hours = ?, year_week = ?
                    WHERE id = ?
                ");
                $stmt->execute([$hours, $target_year_week, $existing['id']]);
            } else {
                // Insert new entry
                $stmt = $pdo->prepare("
                    INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user['id'],
                    $task['project_id'],
                    $task_id,
                    $date_worked,
                    $target_year_week,
                    $hours
                ]);
            }
            
            $saved_count++;
        }
        
        $pdo->commit();
        
        // Redirect to summary page after successful save
        header('Location: ' . url('/apps/summary.php'));
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// ============================================================================
// Fetch Active Clients with Projects and Tasks
// ============================================================================

$stmt = $pdo->prepare("
    SELECT 
        c.id as client_id,
        c.name as client_name,
        c.client_logo
    FROM clients c
    WHERE c.active = 1
    ORDER BY c.name ASC
");
$stmt->execute();
$clients = $stmt->fetchAll();

// For each client, get active projects with active tasks AND client-level tasks
$client_data = [];
foreach ($clients as $client) {
    // Get active projects
    $stmt = $pdo->prepare("
        SELECT 
            p.id as project_id,
            p.name as project_name,
            p.active as project_active
        FROM projects p
        WHERE p.client_id = ? AND p.active = 1
        ORDER BY p.name ASC
    ");
    $stmt->execute([$client['client_id']]);
    $projects = $stmt->fetchAll();
    
    // For each project, get active tasks
    $client_projects = [];
    foreach ($projects as $project) {
        $stmt = $pdo->prepare("
            SELECT 
                t.id as task_id,
                t.name as task_name,
                t.status as task_status
            FROM tasks t
            WHERE t.project_id = ? AND t.status != 'completed'
            ORDER BY t.name ASC
        ");
        $stmt->execute([$project['project_id']]);
        $tasks = $stmt->fetchAll();
        
        // Get existing hours for this week/user/task
        $project_tasks = [];
        foreach ($tasks as $task) {
            $stmt = $pdo->prepare("
                SELECT date_worked, hours 
                FROM hours 
                WHERE user_id = ? AND task_id = ? AND year_week = ?
                ORDER BY date_worked DESC
            ");
            $stmt->execute([$user['id'], $task['task_id'], $target_year_week]);
            $task['existing_hours'] = $stmt->fetchAll();
            $project_tasks[] = $task;
        }
        
        $project['tasks'] = $project_tasks;
        $client_projects[] = $project;
    }
    
    // Also get client-level tasks (tasks with no project)
    $stmt = $pdo->prepare("
        SELECT 
            t.id as task_id,
            t.name as task_name,
            t.status as task_status
        FROM tasks t
        WHERE t.client_id = ? AND t.project_id IS NULL AND t.status != 'completed'
        ORDER BY t.name ASC
    ");
    $stmt->execute([$client['client_id']]);
    $client_level_tasks = $stmt->fetchAll();
    
    // Get existing hours for client-level tasks
    $client_tasks = [];
    foreach ($client_level_tasks as $task) {
        $stmt = $pdo->prepare("
            SELECT date_worked, hours 
            FROM hours 
            WHERE user_id = ? AND task_id = ? AND year_week = ?
            ORDER BY date_worked DESC
        ");
        $stmt->execute([$user['id'], $task['task_id'], $target_year_week]);
        $task['existing_hours'] = $stmt->fetchAll();
        $client_tasks[] = $task;
    }
    
    // Include clients that have either projects with tasks OR client-level tasks
    $has_tasks = false;
    
    // Check if any project has tasks
    foreach ($client_projects as $p) {
        if (!empty($p['tasks'])) {
            $has_tasks = true;
            break;
        }
    }
    
    // Also check if there are client-level tasks
    if (!empty($client_tasks)) {
        $has_tasks = true;
    }
    
    if ($has_tasks) {
        $client['projects'] = $client_projects;
        $client['client_tasks'] = $client_tasks;
        $client_data[] = $client;
    }
}

// DEBUG: Uncomment to see data structure
// echo '<pre>'; print_r($client_data); echo '</pre>'; exit;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Hours - Pulse Hours</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/hours-styles.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../_header.php'; ?>
    
    <main class="admin-content">
        <div class="hours-container">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Hours Entry Card -->
            <div class="hours-card">
                <form method="POST" action="" id="hoursForm">
                    <!-- Clients/Projects/Tasks -->
                    <?php if (empty($client_data)): ?>
                        <div class="empty-state">
                            <p>No active projects or tasks available.</p>
                            <small>Contact your administrator to set up projects.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($client_data as $client): ?>
                            <div class="client-section">
                                <div class="client-header" onclick="toggleClient(this)">
                                    <div class="client-header-content">
                                        <?php if ($client['client_logo']): ?>
                                            <img src="<?= url('/' . htmlspecialchars($client['client_logo'])) ?>" 
                                                 alt="<?= htmlspecialchars($client['client_name']) ?>" 
                                                 class="client-logo">
                                        <?php endif; ?>
                                        <span class="client-name"><?= htmlspecialchars($client['client_name']) ?></span>
                                    </div>
                                    <span class="client-toggle">▼</span>
                                </div>
                                <div class="client-content">
                                    <?php foreach ($client['projects'] as $project): ?>
                                        <?php if (!empty($project['tasks'])): ?>
                                            <div class="project-section">
                                                <div class="project-header" onclick="toggleProject(this)">
                                                    <span class="project-name"><?= htmlspecialchars($project['project_name']) ?></span>
                                                    <span class="project-toggle">▼</span>
                                                </div>
                                                <div class="project-content">
                                                    <?php foreach ($project['tasks'] as $task): ?>
                                                        <div class="task-row">
                                                            <div class="task-name">
                                                                <?= htmlspecialchars($task['task_name']) ?>
                                                                <?php if (!empty($task['existing_hours'])): ?>
                                                                    <div class="existing-hours">
                                                                        <?php foreach ($task['existing_hours'] as $h): ?>
                                                                            <?= date('M j', strtotime($h['date_worked'])) ?>: <?= $h['hours'] ?>h
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="task-hours-input">
                                                                <input 
                                                                    type="number" 
                                                                    name="hours[<?= $task['task_id'] ?>]" 
                                                                    class="hours-input"
                                                                    step="0.25"
                                                                    min="0"
                                                                    max="24"
                                                                >
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    
                                    <?php if (!empty($client['client_tasks'])): ?>
                                        <div class="project-section">
                                            <div class="project-header" onclick="toggleProject(this)">
                                                <span class="project-name"><em>General Tasks</em></span>
                                                <span class="project-toggle">▼</span>
                                            </div>
                                            <div class="project-content">
                                                <?php foreach ($client['client_tasks'] as $task): ?>
                                                    <div class="task-row">
                                                        <div class="task-name">
                                                            <?= htmlspecialchars($task['task_name']) ?>
                                                            <?php if (!empty($task['existing_hours'])): ?>
                                                                <div class="existing-hours">
                                                                    <?php foreach ($task['existing_hours'] as $h): ?>
                                                                        <?= date('M j', strtotime($h['date_worked'])) ?>: <?= $h['hours'] ?>h
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="task-hours-input">
                                                            <input 
                                                                type="number" 
                                                                name="hours[<?= $task['task_id'] ?>]" 
                                                                class="hours-input"
                                                                step="0.25"
                                                                min="0"
                                                                max="24"
                                                            >
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Submit Button -->
                        <div class="submit-section">
                            <button type="submit" name="submit_hours" class="btn btn-primary btn-lg">
                                Save Hours
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </main>

    <script>
        function toggleClient(header) {
            const section = header.parentElement;
            const isExpanding = !section.classList.contains('expanded');
            section.classList.toggle('expanded');
            
            // Auto-expand all projects when client is expanded
            if (isExpanding) {
                const projects = section.querySelectorAll('.project-section');
                projects.forEach(project => {
                    project.classList.add('expanded');
                });
            }
        }

        function toggleProject(header) {
            const section = header.parentElement;
            section.classList.toggle('expanded');
        }

        // Disable scroll wheel on number inputs
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
