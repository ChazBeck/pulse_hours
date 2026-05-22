<?php
/**
 * Hours Entry Page
 *
 * Lets users log hours worked on their assigned tasks for the week
 * they last submitted a pulse for. All business logic lives in
 * HoursService; this file dispatches and renders.
 */

require __DIR__ . '/../sso/sso_include.php';
/* SSO enforced in sso_include.php */

require_once __DIR__ . '/../includes/date_helpers.php';
require_once __DIR__ . '/../src/Service/HoursService.php';

$service = new HoursService();

$successMessage = '';
$errorMessage = '';

// Anchor the entry form to the week the user last pulsed for.
$targetYearWeek = $service->getCurrentYearWeekForUser($user['id']);
if (!$targetYearWeek) {
    header('Location: ' . url('/apps/pulse.php'));
    exit();
}

// ----------------------------------------------------------------------
// Handle submission
// ----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_hours'])) {
    $result = $service->submitTodayHours(
        $user['id'],
        $targetYearWeek,
        $_POST['hours'] ?? []
    );

    if ($result['success']) {
        header('Location: ' . url('/apps/summary.php'));
        exit();
    }
    $errorMessage = $result['message'];
}

$clientData = $service->getEntryFormData($user['id'], $targetYearWeek);
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
            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <div class="hours-card">
                <form method="POST" action="" id="hoursForm">
                    <?php if (empty($clientData)): ?>
                        <div class="empty-state">
                            <p>No active projects or tasks available.</p>
                            <small>Contact your administrator to set up projects.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clientData as $client): ?>
                            <div class="client-section">
                                <div class="client-header">
                                    <div class="client-header-content">
                                        <?php if ($client['client_logo']): ?>
                                            <img src="<?= url('/' . htmlspecialchars($client['client_logo'])) ?>"
                                                 alt="<?= htmlspecialchars($client['client_name']) ?>"
                                                 class="client-logo">
                                        <?php endif; ?>
                                        <span class="client-name"><?= htmlspecialchars($client['client_name']) ?></span>
                                    </div>
                                    <span class="client-toggle">&#9660;</span>
                                </div>
                                <div class="client-content">
                                    <?php foreach ($client['projects'] as $project): ?>
                                        <?php if (!empty($project['tasks'])): ?>
                                            <div class="project-section">
                                                <div class="project-header">
                                                    <span class="project-name"><?= htmlspecialchars($project['project_name']) ?></span>
                                                    <span class="project-toggle">&#9660;</span>
                                                </div>
                                                <div class="project-content">
                                                    <?php foreach ($project['tasks'] as $task): ?>
                                                        <div class="task-row">
                                                            <div class="task-name">
                                                                <?= htmlspecialchars($task['task_name']) ?>
                                                                <?php if (!empty($task['existing_hours'])): ?>
                                                                    <div class="existing-hours">
                                                                        <?php foreach ($task['existing_hours'] as $h): ?>
                                                                            <?= date('M j', strtotime($h['date_worked'])) ?>: <?= htmlspecialchars($h['hours']) ?>h
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="task-hours-input">
                                                                <input type="number"
                                                                       name="hours[<?= (int) $task['task_id'] ?>]"
                                                                       class="hours-input"
                                                                       step="0.25" min="0" max="75">
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <?php if (!empty($client['client_tasks'])): ?>
                                        <div class="project-section">
                                            <div class="project-header">
                                                <span class="project-name"><em>General Tasks</em></span>
                                                <span class="project-toggle">&#9660;</span>
                                            </div>
                                            <div class="project-content">
                                                <?php foreach ($client['client_tasks'] as $task): ?>
                                                    <div class="task-row">
                                                        <div class="task-name">
                                                            <?= htmlspecialchars($task['task_name']) ?>
                                                            <?php if (!empty($task['existing_hours'])): ?>
                                                                <div class="existing-hours">
                                                                    <?php foreach ($task['existing_hours'] as $h): ?>
                                                                        <?= date('M j', strtotime($h['date_worked'])) ?>: <?= htmlspecialchars($h['hours']) ?>h
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="task-hours-input">
                                                            <input type="number"
                                                                   name="hours[<?= (int) $task['task_id'] ?>]"
                                                                   class="hours-input"
                                                                   step="0.25" min="0" max="75">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

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

    <script src="<?= url('/assets/hours.js') ?>"></script>
</body>
</html>
