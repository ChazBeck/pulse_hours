<?php
/**
 * GET /pulse/api/catalog.php
 *
 * The client → task list a user can log hours against, for other apps to offer
 * as choices. It uses exactly the filters apps/hours.php uses (active clients,
 * active projects, tasks not completed, clients with no tasks left out), so a
 * task chosen elsewhere is one the hours page would also have offered.
 *
 * Response:
 *   {ok:true, generatedAt, clients:[{id, name, isInternal,
 *     tasks:[{id, name, projectId|null, projectName|null}]}]}
 */

require __DIR__ . '/_api.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    api_fail(405, 'method_not_allowed');
}
api_require_client();

try {
    $pdo = get_db_connection();
    $internal = api_has_is_internal($pdo) ? 'c.is_internal' : '0';

    $clients = $pdo->query("
        SELECT c.id, c.name, {$internal} AS is_internal
        FROM clients c
        WHERE c.active = 1
        ORDER BY c.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* One query for every offerable task; a task under a project needs that
       project active, a client-level task (no project) needs nothing more.
       A project task is grouped under its PROJECT's client, as hours.php does
       — nothing keeps tasks.client_id in step with projects.client_id. */
    $tasks = $pdo->query("
        SELECT t.id, t.name,
               CASE WHEN t.project_id IS NULL THEN t.client_id ELSE p.client_id END AS client_id,
               t.project_id, p.name AS project_name
        FROM tasks t
        LEFT JOIN projects p ON p.id = t.project_id
        WHERE t.status != 'completed'
          AND (t.project_id IS NULL OR p.active = 1)
        ORDER BY (t.project_id IS NULL) ASC, p.name ASC, t.sort_order ASC, t.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('pulse api catalog: ' . $e->getMessage());
    api_fail(500, 'server_error');
}

$byClient = [];
foreach ($tasks as $t) {
    $byClient[(int) $t['client_id']][] = [
        'id'          => (string) $t['id'],
        'name'        => (string) $t['name'],
        'projectId'   => $t['project_id'] === null ? null : (string) $t['project_id'],
        'projectName' => $t['project_name'] === null ? null : (string) $t['project_name'],
    ];
}

$out = [];
foreach ($clients as $c) {
    $list = $byClient[(int) $c['id']] ?? [];
    if (!$list) continue;
    $out[] = [
        'id'         => (string) $c['id'],
        'name'       => (string) $c['name'],
        'isInternal' => (bool) $c['is_internal'],
        'tasks'      => $list,
    ];
}

api_respond(200, ['ok' => true, 'generatedAt' => gmdate('c'), 'clients' => $out]);
