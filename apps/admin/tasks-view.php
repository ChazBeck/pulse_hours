<?php
/**
 * Tasks View - Client, Project, and Task Overview
 * 
 * Admin page to view all tasks organized by client and project
 */

require __DIR__ . '/../../auth/include/auth_include.php';
auth_init();
auth_require_admin();

$pdo = get_db_connection();

// ============================================================================
// Fetch All Tasks with Client and Project Info
// ============================================================================

$filter_client = $_GET['client'] ?? '';
$filter_project = $_GET['project'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where_clauses = [];
$params = [];

if ($filter_client) {
    $where_clauses[] = "c.id = ?";
    $params[] = $filter_client;
}

if ($filter_project) {
    $where_clauses[] = "p.id = ?";
    $params[] = $filter_project;
}

if ($filter_status) {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$stmt = $pdo->prepare("
    SELECT 
        c.id as client_id,
        c.name as client_name,
        c.client_color,
        p.id as project_id,
        p.name as project_name,
        p.status as project_status,
        t.id as task_id,
        t.name as task_name,
        t.status as task_status,
        t.description as task_description,
        t.created_at as task_created
    FROM tasks t
    INNER JOIN projects p ON t.project_id = p.id
    INNER JOIN clients c ON p.client_id = c.id
    $where_sql
    ORDER BY c.name ASC, p.name ASC, t.name ASC
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Get filter options
$stmt = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name");
$clients = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, name, client_id FROM projects WHERE active = 1 ORDER BY name");
$projects = $stmt->fetchAll();

// Get statistics
$total_tasks = count($tasks);
$completed_tasks = count(array_filter($tasks, fn($t) => $t['task_status'] === 'completed'));
$in_progress_tasks = count(array_filter($tasks, fn($t) => $t['task_status'] === 'in-progress'));
$unique_clients = count(array_unique(array_column($tasks, 'client_id')));
$unique_projects = count(array_unique(array_column($tasks, 'project_id')));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks View - Pulse Hours</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/admin-nav-styles.css') ?>">
    <style>
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .filters form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .filter-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
        }

        .tasks-table {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--gray-100);
        }

        th {
            padding: 1rem;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            border-bottom: 2px solid var(--gray-200);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.875rem;
        }

        tbody tr:hover {
            background: var(--gray-50);
        }

        .client-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-not-started {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .status-in-progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .task-description {
            color: var(--text-secondary);
            font-size: 0.813rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <?php include __DIR__ . '/_admin_nav.php'; ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>Tasks View</h1>
            <p>Overview of all tasks by client and project</p>
        </div>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?= $total_tasks ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $completed_tasks ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $in_progress_tasks ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $unique_clients ?></div>
                <div class="stat-label">Clients</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $unique_projects ?></div>
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
                            <option value="<?= $c['id'] ?>" <?= ($filter_client == $c['id']) ? 'selected' : '' ?>>
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
                            <option value="<?= $proj['id'] ?>" <?= ($filter_project == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="not-started" <?= ($filter_status == 'not-started') ? 'selected' : '' ?>>Not Started</option>
                        <option value="in-progress" <?= ($filter_status == 'in-progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= ($filter_status == 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="blocked" <?= ($filter_status == 'blocked') ? 'selected' : '' ?>>Blocked</option>
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
                                <td><?= htmlspecialchars($task['project_name']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($task['task_name']) ?></strong>
                                    <?php if ($task['task_description']): ?>
                                        <div class="task-description"><?= htmlspecialchars($task['task_description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $task['task_status'] ?>">
                                        <?= ucwords(str_replace('-', ' ', $task['task_status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
