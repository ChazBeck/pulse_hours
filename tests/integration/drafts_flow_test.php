<?php
/**
 * Integration test for the machine API + draft confirmation, against a real
 * MariaDB. Run via tests/integration/run.sh, never against a real database.
 */
require __DIR__ . '/../../config/db_config.php';
require __DIR__ . '/../../includes/hours_drafts.php';

$base = getenv('TEST_BASE');
$token = getenv('TEST_TOKEN');
$fail = 0;
function check(bool $ok, string $what): void { global $fail; echo ($ok ? "ok   " : "FAIL ") . $what . "\n"; if (!$ok) $fail++; }
function call(string $method, string $path, ?array $body = null, ?string $tok = null): array {
    global $base, $token;
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . ($tok ?? $token) . "\r\n",
        'content' => $body === null ? '' : json_encode($body),
    ]]);
    $raw = file_get_contents($base . $path, false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), json_decode((string) $raw, true)];
}
$pdo = get_db_connection();
$hours = fn () => $pdo->query("SELECT task_id, date_worked, hours, source FROM hours WHERE user_id = 101 ORDER BY date_worked, task_id")->fetchAll(PDO::FETCH_ASSOC);

// ---- auth
[$s] = call('GET', '/api/catalog.php', null, 'wrong');
check($s === 401, 'a wrong token is refused');
[$s] = call('GET', '/api/catalog.php', null, '');
check($s === 401, 'no token is refused');

// ---- the auth contract callers rely on (pinned: the Chief of Staff sends "Authorization: Bearer <token>")
function raw_get(string $path, string $header): int {
    global $base;
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'header' => $header . "\r\n"]]);
    file_get_contents($base . $path, false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return (int) ($m[1] ?? 0);
}
check(raw_get('/api/catalog.php', "Authorization: Bearer $token") === 200, 'Authorization: Bearer <token> is accepted');
check(raw_get('/api/catalog.php', "Authorization: bearer $token") === 200, 'the scheme is case-insensitive');
check(raw_get('/api/catalog.php', "X-Pulse-Token: $token") === 200, 'X-Pulse-Token is accepted');
check(raw_get('/api/catalog.php', "Authorization: Basic $token") === 401, 'another scheme is refused');
check(raw_get('/api/catalog.php', "Authorization: $token") === 401, 'a bare token with no scheme is refused');

// ---- catalog uses hours.php's filters, project tasks under the PROJECT's client
[$s, $c] = call('GET', '/api/catalog.php');
check($s === 200 && $c['ok'] === true, 'catalog answers');
$names = array_column($c['clients'], 'name');
check($names === ['Northwind', 'Veerless'], 'inactive clients left out, internal client present: ' . json_encode($names));
$nw = $c['clients'][0];
$taskIds = array_column($nw['tasks'], 'id');
sort($taskIds);
check($taskIds === ['10', '11', '12'], 'completed tasks and tasks under inactive projects left out; mismatched task grouped by project: ' . json_encode($taskIds));
check($c['clients'][1]['isInternal'] === true, 'Veerless is internal');

// ---- push drafts
$week = ['email' => 'charlie@veerless.com', 'from' => '2026-04-12', 'to' => '2026-04-18', 'ref' => 'charlie:2026-04-12'];
$entries = [
    ['date' => '2026-04-12', 'taskId' => '10', 'hours' => 1],     // a Sunday: ISO week 15
    ['date' => '2026-04-13', 'taskId' => '10', 'hours' => 2.5],   // Monday: ISO week 16
    ['date' => '2026-04-14', 'taskId' => 11, 'hours' => 0.25],
];
[$s, $r] = call('POST', '/api/hours-drafts.php', $week + ['entries' => $entries]);
check($s === 200 && $r['written'] === 3 && $r['conflicts'] === [], 'drafts accepted: ' . json_encode($r));
$yw = $pdo->query("SELECT date_worked, year_week FROM hours_drafts ORDER BY date_worked")->fetchAll(PDO::FETCH_KEY_PAIR);
check($yw['2026-04-12'] === '2026-15' && $yw['2026-04-13'] === '2026-16', 'year_week is the ISO week of each day: ' . json_encode($yw));
check($hours() === [], 'drafts touch no `hours` row before confirmation');

// re-send replaces (idempotent), and a dropped entry disappears
[$s, $r] = call('POST', '/api/hours-drafts.php', $week + ['entries' => array_slice($entries, 0, 2)]);
check($s === 200, 're-send accepted');
check((int) $pdo->query("SELECT COUNT(*) FROM hours_draft_batches")->fetchColumn() === 1, 're-send leaves one batch');
check((int) $pdo->query("SELECT COUNT(*) FROM hours_drafts")->fetchColumn() === 2, 're-send replaced the drafts (dropped entry gone)');

// ---- validation (overrides go on the LEFT of +: PHP's array union keeps the left key)
foreach ([
    ['unknown user', ['email' => 'nobody@veerless.com', 'entries' => []] + $week, 404],
    ['inactive user', ['email' => 'gone@veerless.com', 'entries' => []] + $week, 404],
    ['range over 7 days', ['to' => '2026-04-19'] + $week + ['entries' => []], 400],
    ['date outside range', $week + ['entries' => [['date' => '2026-04-19', 'taskId' => 10, 'hours' => 1]]], 400],
    ['not a quarter hour', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 10, 'hours' => 1.1]]], 400],
    ['over 24h', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 10, 'hours' => 25]]], 400],
    ['unknown task', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 999, 'hours' => 1]]], 400],
    ['completed task', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 13, 'hours' => 1]]], 400],
    ['task under an inactive project', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 14, 'hours' => 1]]], 400],
    ['task of an inactive client', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 15, 'hours' => 1]]], 400],
    ['duplicate entry', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 10, 'hours' => 1], ['date' => '2026-04-13', 'taskId' => '10', 'hours' => 2]]], 400],
] as [$what, $body, $want]) {
    [$s] = call('POST', '/api/hours-drafts.php', $body);
    check($s === $want, "$what → $want (got $s)");
}
check((int) $pdo->query("SELECT COUNT(*) FROM hours_drafts")->fetchColumn() === 2, 'refused sends changed nothing');
[$s, $r] = call('POST', '/api/hours-drafts.php', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 13, 'hours' => 1], ['date' => '2026-04-13', 'taskId' => 10, 'hours' => 1]]]);
check($r['error'] === 'task_not_loggable' && $r['taskIds'] === ['13'], 'a closed task is named with its own error code: ' . json_encode($r));
[$s, $r] = call('POST', '/api/hours-drafts.php', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 999, 'hours' => 1]]]);
check($r['error'] === 'unknown_task', 'a task that does not exist is still unknown_task');

// ---- a hand-entered row is a conflict and is never overwritten
$pdo->exec("INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours) VALUES (101, 601, 10, '2026-04-13', '2026-16', 0.75)");
[$s, $r] = call('POST', '/api/hours-drafts.php', $week + ['entries' => [
    ['date' => '2026-04-12', 'taskId' => 10, 'hours' => 1],
    ['date' => '2026-04-13', 'taskId' => 10, 'hours' => 2.5],
    ['date' => '2026-04-15', 'taskId' => 11, 'hours' => 3],
]]);
check(count($r['conflicts']) === 1 && $r['conflicts'][0]['existingHours'] == 0.75, 'conflict with the hand-entered row reported: ' . json_encode($r['conflicts']));

$batch = (int) $pdo->query("SELECT id FROM hours_draft_batches")->fetchColumn();
$pending = pulse_drafts_pending($pdo, 101);
check(count($pending) === 1 && count($pending[0]['drafts']) === 3, 'pending view lists the send');
check($pending[0]['drafts'][1]['conflict'] == 0.75, 'pending view marks the conflict');

$res = pulse_drafts_confirm($pdo, 101, $batch);
check($res === ['confirmed' => 2, 'kept' => 1], 'confirm: 2 confirmed, 1 kept back: ' . json_encode($res));
$h = $hours();
check(count($h) === 3, 'three hours rows now');
check($h[1]['hours'] === '0.75' && $h[1]['source'] === null, 'hand-entered 0.75h untouched');
check($h[0]['source'] === 'cos-168' && $h[2]['source'] === 'cos-168', 'confirmed rows carry the source');

// confirming the leftover again must not delete the rows just confirmed
$res = pulse_drafts_confirm($pdo, 101, $batch);
check($res === ['confirmed' => 0, 'kept' => 1] && count($hours()) === 3, 'second confirm of the leftover deletes nothing');

// ---- next send for the week: a block was removed, another changed
[$s] = call('POST', '/api/hours-drafts.php', $week + ['entries' => [
    ['date' => '2026-04-12', 'taskId' => 10, 'hours' => 1.5],
]]);
check((int) $pdo->query("SELECT COUNT(*) FROM hours_draft_batches")->fetchColumn() === 1, 'the new send replaced the partly-confirmed one');
$batch = (int) $pdo->query("SELECT id FROM hours_draft_batches")->fetchColumn();
pulse_drafts_confirm($pdo, 101, $batch);
$h = $hours();
$byKey = [];
foreach ($h as $row) $byKey[$row['date_worked'] . '|' . $row['task_id']] = $row;
check(($byKey['2026-04-12|10']['hours'] ?? null) === '1.50', 'changed hours updated');
check(!isset($byKey['2026-04-15|11']), 'a block no longer sent is removed from hours');
check(($byKey['2026-04-13|10']['hours'] ?? null) === '0.75', 'the hand-entered row survives every confirm');
check((int) $pdo->query("SELECT COUNT(*) FROM hours_draft_batches")->fetchColumn() === 0, 'a fully confirmed send is cleared');

// ---- weeks, not days (what happened on 2026-10-09)
// People type LAST week's hours early the next week; hours.php files them under
// that week but dates them the day they were typed. A hand entry typed Monday
// Apr 20 for week 16 must neither clash with, nor block, 168 Hours work actually
// done on Apr 20 (week 17).
$pdo->exec("DELETE FROM hours_draft_batches WHERE user_id = 101");
$pdo->exec("INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours) VALUES (101, 601, 10, '2026-04-20', '2026-16', 20.00)");
$wk = ['email' => 'charlie@veerless.com', 'from' => '2026-04-19', 'to' => '2026-04-25'];
[$s, $r] = call('POST', '/api/hours-drafts.php', $wk + ['entries' => [
    ['date' => '2026-04-20', 'taskId' => 10, 'hours' => 1.5],   // week 17: last week's hand entry is NOT a clash
    ['date' => '2026-04-21', 'taskId' => 11, 'hours' => 2],
]]);
check($s === 200 && $r['conflicts'] === [], 'last week\'s hours typed on the same day are not a clash: ' . json_encode($r['conflicts'] ?? $r));
$batch = (int) $pdo->query("SELECT id FROM hours_draft_batches WHERE user_id = 101 AND date_from = '2026-04-19'")->fetchColumn();
$res = pulse_drafts_confirm($pdo, 101, $batch);
check($res === ['confirmed' => 2, 'kept' => 0], 'both drafts confirm: ' . json_encode($res));
$rows = $pdo->query("SELECT year_week, hours, source FROM hours WHERE user_id = 101 AND task_id = 10 AND date_worked = '2026-04-20' ORDER BY year_week")->fetchAll(PDO::FETCH_ASSOC);
check($rows === [['year_week' => '2026-16', 'hours' => '20.00', 'source' => null], ['year_week' => '2026-17', 'hours' => '1.50', 'source' => 'cos-168']],
    'the hand entry (week 16) and the 168 Hours row (week 17) sit side by side on the same day: ' . json_encode($rows));

// A hand entry for the SAME task in the SAME week is still a clash, whichever day it was typed.
$pdo->exec("INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours) VALUES (101, NULL, 11, '2026-04-27', '2026-17', 4.00)");
[$s, $r] = call('POST', '/api/hours-drafts.php', $wk + ['entries' => [['date' => '2026-04-21', 'taskId' => 11, 'hours' => 2]]]);
check(count($r['conflicts']) === 1 && $r['conflicts'][0]['yearWeek'] === '2026-17' && $r['conflicts'][0]['existingHours'] == 4,
    'same task, same week, typed on another day: a clash, with the week named: ' . json_encode($r['conflicts']));
$pending = pulse_drafts_pending($pdo, 101);
check(($pending[0]['weeks'] ?? null) === ['2026-17'], 'the confirm panel knows which Pulse week the send goes into');
$pdo->exec("DELETE FROM hours_draft_batches WHERE user_id = 101");
$pdo->exec("DELETE FROM hours WHERE user_id = 101 AND date_worked >= '2026-04-19'");

// ---- discard
call('POST', '/api/hours-drafts.php', $week + ['entries' => [['date' => '2026-04-16', 'taskId' => 10, 'hours' => 2]]]);
$batch = (int) $pdo->query("SELECT id FROM hours_draft_batches")->fetchColumn();
check(pulse_drafts_discard($pdo, 102, $batch) === false, 'another user cannot discard my send');
check(pulse_drafts_discard($pdo, 101, $batch) === true, 'discard removes the send');
check(count($hours()) === 2 && (int) $pdo->query("SELECT COUNT(*) FROM hours_drafts")->fetchColumn() === 0, 'discard touched no hours');

// ---- a token limited to one user
[$s] = call('POST', '/api/hours-drafts.php', ['entries' => []] + $week, 'scoped-token');
check($s === 200, 'scoped token may write for its own user');
[$s] = call('POST', '/api/hours-drafts.php', ['email' => 'gone@veerless.com', 'entries' => []] + $week, 'scoped-token');
check($s === 403, 'scoped token may not write for anyone else');

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
