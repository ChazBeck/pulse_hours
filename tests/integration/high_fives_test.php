<?php
/**
 * The High Fives step (apps/high-fives.php), rendered and posted to for real
 * through the test-only SSO stub, as charlie (user 101), whose latest check-in
 * is for week 2026-16. Run via tests/integration/run.sh.
 */
require __DIR__ . '/../../config/db_config.php';
require __DIR__ . '/../../config/app_config.php';
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

// --- the check-in now leads here (last: it adds a check-in for the real current week)
[, , $html] = req('GET', '/apps/pulse.php');
[$s, $loc] = req('POST', '/apps/pulse.php', ['week_selection' => 'this_week', 'pulse' => 4, 'work_load' => 5]);
check($s === 302 && str_ends_with($loc, '/apps/high-fives.php'), 'saving the check-in goes to High Fives');
$pdo->exec("DELETE FROM pulse WHERE user_id = 101 AND year_week <> '2026-16'");

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
