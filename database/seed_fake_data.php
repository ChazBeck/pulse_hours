<?php
/**
 * Seed fake data for PluseHours
 *
 * - Creates 5 users
 * - Creates 3 clients, 2 projects per client, 3 tasks per project + 2 client-level tasks
 * - Inserts 3 months (~13 weeks) of hours per user, weekly totals between 20-45
 *
 * Usage: php database/seed_fake_data.php
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/date_helpers.php';

function random_name() {
    $first = ['Alex','Sam','Jamie','Taylor','Jordan','Casey','Riley','Morgan','Drew','Cameron'];
    $last = ['Smith','Johnson','Williams','Brown','Jones','Miller','Davis','Garcia','Rodriguez','Wilson'];
    return [$first[array_rand($first)], $last[array_rand($last)]];
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    echo "Seeding fake data...\n";

    // 1) Create users
    $userIds = [];
    for ($i = 1; $i <= 5; $i++) {
        $email = "user{$i}@example.local";
        $password = password_hash('password123', PASSWORD_DEFAULT);
        list($first, $last) = random_name();

        // Insert or get existing
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $userIds[] = $row['id'];
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, 'User', 1)");
        $stmt->execute([$email, $password, $first, $last]);
        $userIds[] = $pdo->lastInsertId();
    }

    // Create an admin user if not exists
    $adminEmail = 'admin@plusehours.com';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, 'Admin', 1)");
        $stmt->execute([$adminEmail, password_hash('admin123', PASSWORD_DEFAULT), 'Admin','User']);
    }

    echo "Created users: " . implode(', ', $userIds) . "\n";

    // 2) Create clients
    $clients = ['Acme Co', 'Globex Corporation', 'Initech'];
    $clientIds = [];
    foreach ($clients as $cname) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ?");
        $stmt->execute([$cname]);
        $row = $stmt->fetch();
        if ($row) { $clientIds[] = $row['id']; continue; }
        $stmt = $pdo->prepare("INSERT INTO clients (name, active) VALUES (?, 1)");
        $stmt->execute([$cname]);
        $clientIds[] = $pdo->lastInsertId();
    }

    // 3) Create projects and tasks
    $taskIds = [];
    foreach ($clientIds as $clientId) {
        for ($p = 1; $p <= 2; $p++) {
            $projName = "Project {$p} - Client {$clientId}";
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE name = ? AND client_id = ?");
            $stmt->execute([$projName, $clientId]);
            $row = $stmt->fetch();
            if ($row) { $projectId = $row['id']; }
            else {
                $stmt = $pdo->prepare("INSERT INTO projects (client_id, name, status, active) VALUES (?, ?, 'active', 1)");
                $stmt->execute([$clientId, $projName]);
                $projectId = $pdo->lastInsertId();
            }

            // Create tasks for this project
            for ($t = 1; $t <= 3; $t++) {
                $taskName = "Task {$t} - P{$projectId}";
                $stmt = $pdo->prepare("SELECT id FROM tasks WHERE name = ? AND project_id = ?");
                $stmt->execute([$taskName, $projectId]);
                $row = $stmt->fetch();
                if ($row) { $taskIds[] = ['id'=>$row['id'],'project_id'=>$projectId]; continue; }
                $stmt = $pdo->prepare("INSERT INTO tasks (client_id, project_id, name, status) VALUES (?, ?, ?, 'not-started')");
                $stmt->execute([$clientId, $projectId, $taskName]);
                $taskIds[] = ['id'=>$pdo->lastInsertId(),'project_id'=>$projectId];
            }
        }

        // Create 2 client-level tasks (no project)
        for ($ct = 1; $ct <= 2; $ct++) {
            $taskName = "Client Task {$ct} - C{$clientId}";
            $stmt = $pdo->prepare("SELECT id FROM tasks WHERE name = ? AND client_id = ? AND project_id IS NULL");
            $stmt->execute([$taskName, $clientId]);
            $row = $stmt->fetch();
            if ($row) { $taskIds[] = ['id'=>$row['id'],'project_id'=>null]; continue; }
            $stmt = $pdo->prepare("INSERT INTO tasks (client_id, project_id, name, status) VALUES (?, NULL, ?, 'not-started')");
            $stmt->execute([$clientId, $taskName]);
            $taskIds[] = ['id'=>$pdo->lastInsertId(),'project_id'=>null];
        }
    }

    echo "Created clients/projects/tasks. Total tasks: " . count($taskIds) . "\n";

    // 4) Seed hours for 3 months (~13 weeks)
    $weeks = 13;
    $today = new DateTime();
    $startDate = (clone $today)->modify('-' . (int)($weeks*7) . ' days');

    // Build list of ISO year-week strings for each week
    $yearWeeks = [];
    $dt = clone $startDate;
    for ($w = 0; $w < $weeks; $w++) {
        $isoYear = $dt->format('o');
        $isoWeek = $dt->format('W');
        $yw = sprintf('%s-%s', $isoYear, $isoWeek);
        $yearWeeks[] = $yw;
        $dt->modify('+7 days');
    }

    // For each user and each week, distribute hours
    foreach ($userIds as $userId) {
        foreach ($yearWeeks as $yearWeek) {
            // target total hours 20-45
            $total = rand(20,45);

            // get week start date
            $dates = get_week_dates($yearWeek);
            $day = $dates['start']; // DateTime Monday

            // split across 5 workdays (Mon-Fri)
            $workdays = [];
            for ($i=0;$i<5;$i++) {
                $workdays[] = clone $day;
                $day->modify('+1 day');
            }

            // generate random distribution using Dirichlet-like
            $parts = [];
            $remaining = $total;
            // assign random positive numbers then normalize
            $randParts = [];
            for ($i=0;$i<5;$i++) { $randParts[] = rand(1,100); }
            $sumRand = array_sum($randParts);
            for ($i=0;$i<5;$i++) {
                $val = round($total * ($randParts[$i] / $sumRand), 2);
                if ($i === 4) { // ensure exact total on last
                    $val = round($remaining, 2);
                }
                $parts[] = $val;
                $remaining -= $val;
            }

            // insert hours for each workday if > 0.1
            for ($i=0;$i<5;$i++) {
                $hoursVal = $parts[$i];
                if ($hoursVal <= 0.01) continue;
                $dateWorked = $workdays[$i]->format('Y-m-d');

                // choose random task
                $taskPick = $taskIds[array_rand($taskIds)];
                $taskId = $taskPick['id'];
                $projectId = $taskPick['project_id'];

                // Avoid duplicate unique constraint (user, task, date). If exists, add to hours value by updating.
                $stmt = $pdo->prepare("SELECT id, hours FROM hours WHERE user_id = ? AND task_id = ? AND date_worked = ?");
                $stmt->execute([$userId, $taskId, $dateWorked]);
                $exists = $stmt->fetch();
                if ($exists) {
                    $newHours = $exists['hours'] + $hoursVal;
                    $upd = $pdo->prepare("UPDATE hours SET hours = ? WHERE id = ?");
                    $upd->execute([$newHours, $exists['id']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins->execute([$userId, $projectId, $taskId, $dateWorked, $yearWeek, $hoursVal]);
                }
            }

            // Also insert a pulse entry for the week if not exists (pulse 3, workload random 3-8)
            $stmt = $pdo->prepare("SELECT id FROM pulse WHERE user_id = ? AND year_week = ?");
            $stmt->execute([$userId, $yearWeek]);
            if (!$stmt->fetch()) {
                $pulse = rand(2,5);
                $workload = rand(3,8);
                $ins = $pdo->prepare("INSERT INTO pulse (user_id, year_week, pulse, work_load, date_created) VALUES (?, ?, ?, ?, NOW())");
                $ins->execute([$userId, $yearWeek, $pulse, $workload]);
            }
        }
    }

    $pdo->commit();

    echo "Seeding complete.\n";

    // Summary counts
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users"); echo "Users: " . $stmt->fetch()['cnt'] . "\n";
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM clients"); echo "Clients: " . $stmt->fetch()['cnt'] . "\n";
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM projects"); echo "Projects: " . $stmt->fetch()['cnt'] . "\n";
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM tasks"); echo "Tasks: " . $stmt->fetch()['cnt'] . "\n";
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM hours"); echo "Hours entries: " . $stmt->fetch()['cnt'] . "\n";
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM pulse"); echo "Pulse entries: " . $stmt->fetch()['cnt'] . "\n";

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

?>