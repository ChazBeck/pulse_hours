<?php
/**
 * Migration: Add "Review and Revisions" to existing Annual Report projects
 *
 * The Sustainability Report Template already includes this task for newly-created
 * report projects. This migration backfills current active Annual Report projects
 * without creating duplicates if it is run more than once.
 */

require_once __DIR__ . '/../config/db_config.php';

$taskName = 'Review and Revisions';
$sortOrder = 7;

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $select = $pdo->prepare("
        SELECT c.id AS client_id, c.name AS client, p.id AS project_id, p.name AS project
        FROM projects p
        JOIN clients c ON c.id = p.client_id
        WHERE p.active = 1
          AND LOWER(p.name) LIKE ?
          AND NOT EXISTS (
              SELECT 1
              FROM tasks t
              WHERE t.project_id = p.id
                AND LOWER(t.name) = LOWER(?)
          )
        ORDER BY c.name, p.name
    ");
    $select->execute(['%annual%report%', $taskName]);
    $projects = $select->fetchAll(PDO::FETCH_ASSOC);

    $insert = $pdo->prepare("
        INSERT INTO tasks (client_id, project_id, name, description, status, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($projects as $project) {
        $insert->execute([
            (int) $project['client_id'],
            (int) $project['project_id'],
            $taskName,
            '',
            'not-started',
            $sortOrder,
        ]);
        echo "Added {$taskName} to {$project['client']} / {$project['project']}\n";
    }

    $pdo->commit();
    echo "\n✓ Migration completed. Inserted " . count($projects) . " task(s).\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
