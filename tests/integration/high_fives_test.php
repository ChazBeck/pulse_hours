<?php
/**
 * The High Fives step (apps/high-fives.php), rendered and posted to for real
 * through the test-only SSO stub, as charlie (user 101), whose latest check-in
 * is for week 2026-16. Run via tests/integration/run.sh.
 */
require __DIR__ . '/../../config/db_config.php';
require __DIR__ . '/../../config/app_config.php';
require __DIR__ . '/../../includes/high_fives_zoom.php';
$base = getenv('TEST_BASE');
$fail = 0;
function check(bool $ok, string $what): void { global $fail; echo ($ok ? "ok   " : "FAIL ") . $what . "\n"; if (!$ok) $fail++; }
$cookie = '';
/** One request, no redirect following: [status, Location header, body]. */
function req(string $method, string $path, array $form = []): array {
    global $base, $cookie;
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . ($cookie ? "Cookie: $cookie\r\n" : ''),
        'content' => http_build_query($form),
    ]]);
    $body = (string) file_get_contents($base . $path, false, $ctx);
    $status = 0; $location = '';
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $status = (int) $m[1];
        if (preg_match('/^Location:\s*(\S+)/i', $h, $m)) $location = $m[1];
        if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $h, $m)) $cookie = $m[1];
    }
    return [$status, $location, $body];
}
$pdo = get_db_connection();
$count = fn () => (int) $pdo->query("SELECT COUNT(*) FROM high_fives")->fetchColumn();

// --- the page
[$s, , $html] = req('GET', '/apps/high-fives.php');
check($s === 200 && str_contains($html, 'Any High Fives this week?'), 'the page is shown');
check(str_contains($html, 'Week of Apr 13 - Apr 19, 2026'), 'it names the Pulse week of the latest check-in');
check(str_contains($html, 'Ann Thomas') && str_contains($html, 'Katie Yoder'), 'active teammates are offered as pills');
check(!str_contains($html, 'Charlie Beck'), 'you cannot High Five yourself');
check(!str_contains($html, 'Gone User'), 'inactive users are not offered');
check(str_contains($html, 'value="another"') && str_contains($html, 'value="next"') && str_contains($html, '/apps/pulse.php'),
    'Back, Give another and Next are there');
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';
check($csrf !== '', 'the form carries a CSRF token');

// --- skipping
[$s, $loc] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'next', 'message' => '  ']);
check($s === 302 && str_ends_with($loc, '/apps/hours.php') && $count() === 0, 'Next with nothing filled in goes straight to hours');

// --- half-filled or bad forms save nothing and keep what was typed
[, , $html] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'another']);
check(str_contains($html, 'Pick at least one teammate') && $count() === 0, 'Give another with nothing filled in asks for a teammate');

[, , $html] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'next', 'recipients' => [103]]);
check(str_contains($html, 'Write a line about what they did') && $count() === 0, 'a teammate but no message is refused, not skipped');
check((bool) preg_match('/value="103"\s+checked/', $html), 'the picked teammate stays picked');

[, , $html] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'next', 'message' => 'Great deck']);
check(str_contains($html, 'Pick at least one teammate') && str_contains($html, 'Great deck') && $count() === 0,
    'a message but no teammate is refused, and the message is kept');

foreach (['yourself' => 101, 'an inactive user' => 102, 'an unknown id' => 999] as $who => $id) {
    [, , $html] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'next', 'recipients' => [103, $id], 'message' => 'x']);
    check(str_contains($html, 'Pick teammates from the list') && $count() === 0, "a High Five to $who is refused");
}

[, , $html] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'next', 'recipients' => [103], 'message' => str_repeat('a', 1001)]);
check(str_contains($html, 'Keep it under 1000 characters') && $count() === 0, 'an over-long message is refused');

req('POST', '/apps/high-fives.php', ['csrf_token' => 'forged', 'action' => 'next', 'recipients' => [103], 'message' => 'x']);
check($count() === 0, 'a forged CSRF token saves nothing');

// --- Give another: saves, comes back with a cleared form and a confirmation
[$s, $loc] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'another',
    'recipients' => [104, 103, 103], 'message' => "  Thanks for <the> Medica deck!\nIt landed.  "]);
check($s === 302 && str_contains($loc, '/apps/high-fives.php?sent='), 'Give another saves and returns to the page');
$hf = $pdo->query("SELECT * FROM high_fives ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check($hf && $hf['giver_user_id'] == 101 && $hf['year_week'] === '2026-16', "it is charlie's, for the check-in's week");
check($hf && $hf['message'] === "Thanks for <the> Medica deck!\nIt landed.", 'the message is stored trimmed');
$to = $pdo->query("SELECT user_id FROM high_five_recipients WHERE high_five_id = " . (int) $hf['id'] . " ORDER BY user_id")->fetchAll(PDO::FETCH_COLUMN);
check($to == [103, 104], 'both teammates are recipients, once each');

[, , $html] = req('GET', parse_url($loc, PHP_URL_PATH) . '?' . parse_url($loc, PHP_URL_QUERY));
check(str_contains($html, 'High Five sent to Ann Thomas and Katie Yoder!'), 'the confirmation names who it went to');
check(str_contains($html, 'Your High Fives this week') && str_contains($html, 'Thanks for &lt;the&gt; Medica deck!<br />'),
    'it is listed below, escaped');
check(!preg_match('/value="10[34]"\s+checked/', $html) && !str_contains($html, '>  Thanks'), 'the form is cleared for the next one');
check(str_contains($html, '<form method="POST" action="/apps/high-fives.php"'), 'the form posts to the plain page, not back to ?sent=');
[, , $html] = req('POST', parse_url($loc, PHP_URL_PATH) . '?' . parse_url($loc, PHP_URL_QUERY), ['csrf_token' => $csrf, 'action' => 'next', 'recipients' => [103]]);
check(str_contains($html, 'Write a line about what they did') && !str_contains($html, 'High Five sent to'),
    'a mistake on the next one does not show the previous one\'s "sent" banner');

// --- Next with a filled form: saves, then on to hours
[$s, $loc] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'next', 'recipients' => [103], 'message' => 'Covered my call']);
check($s === 302 && str_ends_with($loc, '/apps/hours.php') && $count() === 2, 'Next with a High Five filled in saves it, then goes to hours');
[, , $html] = req('GET', '/apps/high-fives.php');
check(substr_count($html, '🙌 ') === 2, 'both are listed as this week\'s');

// --- the weekly summary shows the week's High Fives, given and received
[, , $html] = req('GET', '/apps/summary.php');
check(str_contains($html, '<h2>High Fives</h2>') && str_contains($html, 'No High Fives given or received') === false,
    'the summary has a High Fives card');
check(str_contains($html, 'You gave') && str_contains($html, 'To Ann Thomas and Katie Yoder') && str_contains($html, 'Covered my call'),
    "it lists what charlie gave this week");
check(!str_contains($html, 'You received'), 'nothing received yet, so no received list');
$give = function (int $from, array $to, string $week, string $msg) use ($pdo) {
    $pdo->prepare("INSERT INTO high_fives (giver_user_id, year_week, message) VALUES (?, ?, ?)")->execute([$from, $week, $msg]);
    $id = (int) $pdo->lastInsertId();
    foreach ($to as $u) $pdo->prepare("INSERT INTO high_five_recipients VALUES (?, ?)")->execute([$id, $u]);
};
$give(103, [101, 104], '2026-16', 'Great client call, both of you');
$give(104, [101], '2026-15', 'Last week thing');
[, , $html] = req('GET', '/apps/summary.php');
check(str_contains($html, 'You received') && str_contains($html, 'From Ann Thomas (also to Katie Yoder)')
    && str_contains($html, 'Great client call, both of you'), 'it lists what charlie received, and who else got it');
check(!str_contains($html, 'Last week thing'), "another week's High Five is not shown");
check(str_contains($html, '/apps/high-fives.php'), 'it links to give a High Five');

// --- Zoom: every High Five given while Zoom is set up is posted, once
$zoomRequests = fn () => array_map(fn ($l) => json_decode($l, true), file('/t/zoom-requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$status = fn (int $id) => $pdo->query("SELECT zoom_status, zoom_attempts, zoom_error FROM high_fives WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
$reqs = $zoomRequests();
check(count($reqs) === 2, 'the two High Fives given on the page were each posted to Zoom once: ' . count($reqs));
$first = $reqs[0] ?? [];
check(($first['uri'] ?? '') === '/inc/hook?format=full' && ($first['authorization'] ?? '') === 'test-zoom-token'
    && ($first['content_type'] ?? '') === 'application/json', 'it posts a full card to the endpoint with the token');
check(($first['body']['head']['text'] ?? '') === '🙌 High Five'
    && ($first['body']['body'][0]['text'] ?? '') === 'Charlie Beck → Ann Thomas and Katie Yoder'
    && ($first['body']['body'][1]['text'] ?? '') === "Thanks for <the> Medica deck!\nIt landed.",
    'the card says who gave it, to whom, and the message: ' . json_encode($first['body'] ?? null, JSON_UNESCAPED_UNICODE));
$posted = $pdo->query("SELECT COUNT(*) FROM high_fives WHERE zoom_status = 'posted' AND zoom_attempts = 1 AND zoom_posted_at IS NOT NULL")->fetchColumn();
check($posted == 2, 'both are recorded as posted, on the first attempt');
check($pdo->query("SELECT COUNT(*) FROM high_fives WHERE giver_user_id <> 101 AND zoom_status = 'off'")->fetchColumn() == 2,
    'High Fives saved without Zoom (here, straight into the table) are off');

$retry = fn () => shell_exec(PHP_BINARY . ' -d auto_prepend_file=/t/app/tests/integration/prepend.php /t/app/scripts/post_high_fives.php /t/zoom.env 2>&1; echo "exit=$?"');
$out = $retry();
check(count($zoomRequests()) === 2 && str_contains($out, 'exit=0'), 'the retry script posts nothing already posted, nor anything off: ' . trim($out));

// Zoom down: the High Five is still saved and the user still moves on; the retry script posts it later
file_put_contents('/t/zoom-mode', 'down');
[$s, $loc] = req('POST', '/apps/high-fives.php', ['csrf_token' => $csrf, 'action' => 'next', 'recipients' => [104], 'message' => 'Posted later']);
$downId = (int) $pdo->query("SELECT MAX(id) FROM high_fives")->fetchColumn();
$st = $status($downId);
check($s === 302 && str_ends_with($loc, '/apps/hours.php'), 'with Zoom down, Next still goes on to hours');
check($st['zoom_status'] === 'failed' && $st['zoom_attempts'] == 1 && str_starts_with((string) $st['zoom_error'], 'HTTP 500'),
    'the High Five is saved and marked failed, with the reason: ' . json_encode($st));
$out = $retry();
check(str_contains($out, "High Five $downId: failed") && str_contains($out, 'exit=1'), 'while Zoom is still down the retry fails, and says so');
file_put_contents('/t/zoom-mode', 'ok');
$before = count($zoomRequests());
$out = $retry();
$st = $status($downId);
check(str_contains($out, "High Five $downId: posted") && str_contains($out, 'exit=0') && $st['zoom_status'] === 'posted' && $st['zoom_attempts'] == 3,
    'once Zoom is back the retry script posts it: ' . json_encode($st));
check(count($zoomRequests()) === $before + 1, 'exactly one more post');
check(high_fives_zoom_post($pdo, $downId, high_fives_zoom_config('/t/zoom.env')) === 'skipped' && count($zoomRequests()) === $before + 1,
    'a posted High Five cannot be posted again');

// A post left mid-send is retried once it is clearly stuck, but not while it may still be in flight
$pdo->exec("UPDATE high_fives SET zoom_status = 'sending', zoom_attempted_at = CURRENT_TIMESTAMP WHERE id = $downId");
$retry();
check(count($zoomRequests()) === $before + 1, 'a fresh in-flight post is left alone');
$pdo->exec("UPDATE high_fives SET zoom_attempted_at = CURRENT_TIMESTAMP - INTERVAL 11 MINUTE WHERE id = $downId");
$retry();
check(count($zoomRequests()) === $before + 2 && $status($downId)['zoom_status'] === 'posted', 'a post stuck for over 10 minutes is retried');

// Out of attempts: left as failed, never retried again
$pdo->exec("UPDATE high_fives SET zoom_status = 'failed', zoom_attempts = 10 WHERE id = $downId");
$retry();
check(count($zoomRequests()) === $before + 2 && $status($downId)['zoom_status'] === 'failed', 'after 10 attempts it is no longer retried');

// Config: no file, or an incomplete one, means Zoom is off
file_put_contents('/t/zoom-incomplete.env', "ZOOM_WEBHOOK_ENDPOINT=http://x\n");
check(high_fives_zoom_config('/t/nope.env') === null && high_fives_zoom_config('/t/zoom-incomplete.env') === null
    && high_fives_zoom_config('') === null, 'no or incomplete credentials file means Zoom is off');
$offId = high_fives_give($pdo, 101, '2026-16', [103], 'Given with Zoom off');
check($status($offId)['zoom_status'] === 'off', 'a High Five given with Zoom off is saved as off');
$out = shell_exec(PHP_BINARY . ' -d auto_prepend_file=/t/app/tests/integration/prepend.php /t/app/scripts/post_high_fives.php /t/nope.env 2>&1; echo "exit=$?"');
check(str_contains($out, 'Zoom is not set up') && str_contains($out, 'exit=0'), 'the retry script does nothing without credentials');

// --- the check-in now leads here (last: it adds a check-in for the real current week)
[, , $html] = req('GET', '/apps/pulse.php');
[$s, $loc] = req('POST', '/apps/pulse.php', ['week_selection' => 'this_week', 'pulse' => 4, 'work_load' => 5]);
check($s === 302 && str_ends_with($loc, '/apps/high-fives.php'), 'saving the check-in goes to High Fives');
$pdo->exec("DELETE FROM pulse WHERE user_id = 101 AND year_week <> '2026-16'");

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
