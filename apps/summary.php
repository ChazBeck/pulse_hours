<?php
/**
 * Weekly Summary Page
 * 
 * Shows user's pulse check-in and hours logged for the week.
 */

require __DIR__ . '/../auth/include/auth_include.php';
auth_init();
auth_require_login();

require_once __DIR__ . '/../includes/date_helpers.php';

$user = auth_get_user();
$pdo = get_db_connection();

// Get the year_week from the user's most recent pulse entry
$stmt = $pdo->prepare("SELECT year_week, pulse, work_load, date_created FROM pulse WHERE user_id = ? ORDER BY date_created DESC LIMIT 1");
$stmt->execute([$user['id']]);
$pulse_entry = $stmt->fetch();

if (!$pulse_entry) {
    // If no pulse entry found, redirect back to pulse page
    header('Location: ' . url('/apps/pulse.php'));
    exit();
}

$target_year_week = $pulse_entry['year_week'];

$week_label = format_week_range($target_year_week);

// Get all hours logged for this week
$stmt = $pdo->prepare("
    SELECT 
        h.id,
        h.date_worked,
        h.hours,
        t.name as task_name,
        p.name as project_name,
        c.name as client_name,
        c.client_logo
    FROM hours h
    JOIN tasks t ON h.task_id = t.id
    LEFT JOIN projects p ON h.project_id = p.id
    JOIN clients c ON t.client_id = c.id
    WHERE h.user_id = ? AND h.year_week = ?
    ORDER BY c.name, p.name, t.name, h.date_worked
");
$stmt->execute([$user['id'], $target_year_week]);
$hours_entries = $stmt->fetchAll();

// Group hours by client > project > task
$grouped_hours = [];
$total_hours = 0;

foreach ($hours_entries as $entry) {
    $client = $entry['client_name'];
    $project = $entry['project_name'] ?? 'General Tasks';
    $task = $entry['task_name'];
    
    if (!isset($grouped_hours[$client])) {
        $grouped_hours[$client] = [
            'logo' => $entry['client_logo'],
            'projects' => []
        ];
    }
    
    if (!isset($grouped_hours[$client]['projects'][$project])) {
        $grouped_hours[$client]['projects'][$project] = [];
    }
    
    if (!isset($grouped_hours[$client]['projects'][$project][$task])) {
        $grouped_hours[$client]['projects'][$project][$task] = 0;
    }
    
    $grouped_hours[$client]['projects'][$project][$task] += $entry['hours'];
    $total_hours += $entry['hours'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Summary - Pulse Hours</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/summary-styles.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../_header.php'; ?>
    
    <main class="admin-content">
        <div class="summary-container">
            <div class="summary-header">
                <h1>Weekly Summary</h1>
                <div class="week-info"><?= htmlspecialchars($week_label) ?></div>
            </div>

            <!-- Pulse Summary -->
            <div class="summary-card">
                <h2>Your Pulse Check-in</h2>
                <div class="pulse-summary">
                    <div class="pulse-item">
                        <div class="label">How You're Doing</div>
                        <div class="value"><?= $pulse_entry['pulse'] ?></div>
                        <div class="value-label">out of 5</div>
                    </div>
                    <div class="pulse-item">
                        <div class="label">Workload</div>
                        <div class="value"><?= $pulse_entry['work_load'] ?></div>
                        <div class="value-label">out of 10</div>
                    </div>
                </div>
            </div>

            <!-- Hours Summary -->
            <div class="summary-card">
                <h2>Hours Logged</h2>
                
                <?php if (empty($grouped_hours)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">⏱️</div>
                        <p>No hours logged for this week yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_hours as $client_name => $client_data): ?>
                        <div class="client-section">
                            <div class="client-header">
                                <?php if ($client_data['logo']): ?>
                                    <img src="<?= url('/' . htmlspecialchars($client_data['logo'])) ?>" 
                                         alt="<?= htmlspecialchars($client_name) ?>" 
                                         class="client-logo">
                                <?php endif; ?>
                                <span class="client-name"><?= htmlspecialchars($client_name) ?></span>
                            </div>
                            
                            <?php foreach ($client_data['projects'] as $project_name => $tasks): ?>
                                <div class="project-section">
                                    <div class="project-name"><?= htmlspecialchars($project_name) ?></div>
                                    
                                    <?php foreach ($tasks as $task_name => $task_hours): ?>
                                        <div class="task-row">
                                            <span class="task-name"><?= htmlspecialchars($task_name) ?></span>
                                            <span class="task-hours"><?= number_format($task_hours, 2) ?>h</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="total-section">
                        <span class="total-label">Total Hours:</span>
                        <span class="total-hours"><?= number_format($total_hours, 2) ?>h</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="actions">
                <a href="<?= url('/apps/hours.php') ?>">Edit Hours</a>
            </div>
        </div>
    </main>
</body>
</html>
