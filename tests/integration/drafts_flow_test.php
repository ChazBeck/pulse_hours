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
    ['duplicate entry', $week + ['entries' => [['date' => '2026-04-13', 'taskId' => 10, 'hours' => 1], ['date' => '2026-04-13', 'taskId' => '10', 'hours' => 2]]], 400],
] as [$what, $body, $want]) {
    [$s] = call('POST', '/api/hours-drafts.php', $body);
    check($s === $want, "$what → $want (got $s)");
}
check((int) $pdo->query("SELECT COUNT(*) FROM hours_drafts")->fetchColumn() === 2, 'refused sends changed nothing');

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
