<?php
/**
 * The hours page's "awaiting confirmation" panel, rendered and posted to for
 * real (through a test-only SSO stub). Run via tests/integration/run.sh.
 */
require __DIR__ . '/../../config/db_config.php';
require __DIR__ . '/../../config/app_config.php';
// hours.php stamps date('Y-m-d') in the APP's timezone; the DB's CURDATE() is UTC and
// disagrees for hours every day, so "today" must come from PHP, not SQL.
$today = date('Y-m-d');
$base = getenv('TEST_BASE');
$token = getenv('TEST_TOKEN');
$fail = 0;
function check(bool $ok, string $what): void { global $fail; echo ($ok ? "ok   " : "FAIL ") . $what . "\n"; if (!$ok) $fail++; }
$cookie = '';
function page(string $method, string $path, array $form = []): string {
    global $base, $cookie;
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . ($cookie ? "Cookie: $cookie\r\n" : ''),
        'content' => http_build_query($form),
    ]]);
    $html = (string) file_get_contents($base . $path, false, $ctx);
    foreach ($http_response_header as $h) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $h, $m)) $cookie = $m[1];
    }
    return $html;
}
function api(array $body): array {
    global $base, $token;
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer $token\r\n", 'content' => json_encode($body)]]);
    return json_decode((string) file_get_contents($base . '/api/hours-drafts.php', false, $ctx), true) ?? [];
}
$pdo = get_db_connection();
$row = fn ($date, $task) => $pdo->query("SELECT hours, source FROM hours WHERE user_id = 101 AND task_id = $task AND date_worked = '$date'")->fetch(PDO::FETCH_ASSOC);

api(['email' => 'charlie@veerless.com', 'from' => '2026-04-12', 'to' => '2026-04-18', 'entries' => [
    ['date' => '2026-04-13', 'taskId' => 10, 'hours' => 1],     // clashes with the hand-entered 0.75h
    ['date' => '2026-04-14', 'taskId' => 11, 'hours' => 2],
]]);

$html = page('GET', '/apps/hours.php');
check(str_contains($html, 'From 168 Hours — awaiting confirmation'), 'the panel is shown');
check(str_contains($html, 'Goes into Pulse week 16 (Apr 13 - Apr 19, 2026)'), 'the panel says which Pulse week the hours go into');
check(str_contains($html, 'Northwind') && str_contains($html, 'Reporting'), 'drafts are named by client and task');
check(str_contains($html, 'you already entered 0.75h for this task in week 16; yours is kept'), 'the clash is spelled out');
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
preg_match('/name="batch_id" value="(\d+)"/', $html, $b);
check(!empty($m[1]) && !empty($b[1]), 'the form carries a CSRF token and the batch');

page('POST', '/apps/hours.php', ['confirm_drafts' => '1', 'batch_id' => $b[1], 'csrf_token' => 'forged']);
check($row('2026-04-14', 11) === false, 'a forged CSRF token confirms nothing');

$html = page('POST', '/apps/hours.php', ['confirm_drafts' => '1', 'batch_id' => $b[1], 'csrf_token' => $m[1]]);
check(str_contains($html, '1 imported entry confirmed. 1 left as drafts'), 'confirm reports what it did');
check($row('2026-04-14', 11) == ['hours' => '2.00', 'source' => 'cos-168'], 'the draft became a 168 Hours row');
check($row('2026-04-13', 10) == ['hours' => '0.75', 'source' => null], 'the hand-entered row is untouched');
check(str_contains($html, 'awaiting confirmation'), 'the clashing draft is still waiting');
check(!str_contains($html, 'name="confirm_drafts"') && str_contains($html, 'Everything left here clashes'), 'only Discard is offered once just clashes are left');
check(str_contains($html, 'Apr 14: 2.00h <span class="hours-source">(168 Hours)</span>'), 'the confirmed row is listed under its task, labelled as from 168 Hours');

// The user types over the imported row by hand: it becomes theirs.
$pdo->exec("UPDATE hours SET date_worked = '$today' WHERE user_id = 101 AND task_id = 11 AND date_worked = '2026-04-14'");
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
page('POST', '/apps/hours.php', ['submit_hours' => '1', 'hours' => [11 => '3']]);
$mine = $pdo->query("SELECT hours, source FROM hours WHERE user_id = 101 AND task_id = 11 AND date_worked = '$today'")->fetch(PDO::FETCH_ASSOC);
check($mine == ['hours' => '3.00', 'source' => null], 'a hand edit of an imported row makes it hand-entered: ' . json_encode($mine));

$html = page('GET', '/apps/hours.php');
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
preg_match('/name="batch_id" value="(\d+)"/', $html, $b);
page('POST', '/apps/hours.php', ['discard_drafts' => '1', 'batch_id' => $b[1], 'csrf_token' => $m[1]]);
check((int) $pdo->query("SELECT COUNT(*) FROM hours_draft_batches WHERE user_id = 101")->fetchColumn() === 0, 'Discard clears the leftover');

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
