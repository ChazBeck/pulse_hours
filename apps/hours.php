<?php
/**
 * Hours Entry Page
 * 
 * Allows users to log hours worked on tasks grouped by client and project.
 */

require __DIR__ . '/../sso/sso_include.php';
/* SSO enforced in sso_include.php */

require_once __DIR__ . '/../includes/date_helpers.php';

// $user provided by sso_include.php
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
// Draft hours sent in from other apps (e.g. the 168 Hours planner)
// ============================================================================

require_once __DIR__ . '/../includes/hours_drafts.php';

// The draft tables come from database/migrate_add_hours_drafts.php; until it
// has run, this page behaves exactly as it did before.
$drafts_enabled = (bool) $pdo->query("SHOW TABLES LIKE 'hours_draft_batches'")->fetch();

if ($drafts_enabled && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['confirm_drafts']) || isset($_POST['discard_drafts']))) {
    $batch_id = (int) ($_POST['batch_id'] ?? 0);
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid form submission (CSRF token mismatch)';
    } elseif (isset($_POST['confirm_drafts'])) {
        try {
            $r = pulse_drafts_confirm($pdo, (int) $user['id'], $batch_id);
            $success_message = $r['confirmed'] . ' imported ' . ($r['confirmed'] === 1 ? 'entry' : 'entries') . ' confirmed.'
                . ($r['kept'] ? ' ' . $r['kept'] . ' left as drafts because you already entered hours for that task on that day.' : '');
        } catch (Throwable $e) {
            error_log('hours.php confirm drafts: ' . $e->getMessage());
            $error_message = 'Could not confirm the imported hours. Nothing was changed.';
        }
    } else {
        pulse_drafts_discard($pdo, (int) $user['id'], $batch_id);
        $success_message = 'Imported hours discarded.';
    }
}

$pending_batches = $drafts_enabled ? pulse_drafts_pending($pdo, (int) $user['id']) : [];
$source_labels = ['cos-168' => '168 Hours'];
$hours_source_sql = $drafts_enabled ? 'source' : 'NULL AS source';

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
                // A row typed over by hand becomes the user's own: a later
                // send from another app must never overwrite it.
                $stmt = $pdo->prepare("
                    UPDATE hours 
                    SET hours = ?, year_week = ?" . ($drafts_enabled ? ", source = NULL" : "") . "
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
            ORDER BY t.sort_order ASC, t.name ASC
        ");
        $stmt->execute([$project['project_id']]);
        $tasks = $stmt->fetchAll();
        
        // Get existing hours for this week/user/task
        $project_tasks = [];
        foreach ($tasks as $task) {
            $stmt = $pdo->prepare("
                SELECT date_worked, hours, {$hours_source_sql}
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
        ORDER BY t.sort_order ASC, t.name ASC
    ");
    $stmt->execute([$client['client_id']]);
    $client_level_tasks = $stmt->fetchAll();
    
    // Get existing hours for client-level tasks
    $client_tasks = [];
    foreach ($client_level_tasks as $task) {
        $stmt = $pdo->prepare("
            SELECT date_worked, hours, {$hours_source_sql}
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
    <?php require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/hours-styles.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../_header.php'; ?>
    
    <?php if ($user && $user['role'] === 'Admin'): ?>
        <?php include __DIR__ . '/admin/_admin_nav.php'; ?>
    <?php endif; ?>
    
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

            <?php foreach ($pending_batches as $batch): ?>
                <div class="hours-card drafts-card">
                    <h2 class="drafts-title">
                        From <?= htmlspecialchars($source_labels[$batch['source']] ?? $batch['source']) ?> — awaiting confirmation
                    </h2>
                    <p class="drafts-meta">
                        <?= date('M j', strtotime($batch['date_from'])) ?> – <?= date('M j, Y', strtotime($batch['date_to'])) ?>
                        · <?= rtrim(rtrim(number_format($batch['total'], 2), '0'), '.') ?>h
                        · sent <?= date('M j, g:ia', strtotime($batch['created_at'])) ?>
                    </p>
                    <?php if (empty($batch['drafts'])): ?>
                        <p>No client hours in this week. Confirming clears anything sent for it before.</p>
                    <?php else: ?>
                        <table class="drafts-table">
                            <thead><tr><th>Day</th><th>Client</th><th>Task</th><th>Hours</th></tr></thead>
                            <tbody>
                            <?php foreach ($batch['drafts'] as $d): ?>
                                <tr<?= $d['conflict'] !== null ? ' class="draft-conflict"' : '' ?>>
                                    <td><?= date('D M j', strtotime($d['date_worked'])) ?></td>
                                    <td><?= htmlspecialchars($d['client_name']) ?></td>
                                    <td><?= htmlspecialchars(($d['project_name'] ? $d['project_name'] . ' › ' : '') . $d['task_name']) ?></td>
                                    <td>
                                        <?= rtrim(rtrim($d['hours'], '0'), '.') ?>h
                                        <?php if ($d['conflict'] !== null): ?>
                                            <small>— you already entered <?= rtrim(rtrim(number_format($d['conflict'], 2), '0'), '.') ?>h here; yours is kept</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <form method="POST" action="" class="drafts-actions">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">
                        <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
                        <button type="submit" name="confirm_drafts" class="btn btn-primary">Confirm all</button>
                        <button type="submit" name="discard_drafts" class="btn btn-secondary"
                                onclick="return confirm('Discard these imported hours?');">Discard</button>
                    </form>
                </div>
            <?php endforeach; ?>

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
                                                                            <?= date('M j', strtotime($h['date_worked'])) ?>: <?= $h['hours'] ?>h<?php if (!empty($h['source'])): ?> <span class="hours-source">(<?= htmlspecialchars($source_labels[$h['source']] ?? $h['source']) ?>)</span><?php endif; ?>
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
                                                                    max="75"
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
                                                                        <?= date('M j', strtotime($h['date_worked'])) ?>: <?= $h['hours'] ?>h<?php if (!empty($h['source'])): ?> <span class="hours-source">(<?= htmlspecialchars($source_labels[$h['source']] ?? $h['source']) ?>)</span><?php endif; ?>
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
                                                                max="75"
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
