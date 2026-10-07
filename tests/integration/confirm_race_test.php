<?php
/**
 * A hand edit racing a confirm must never be lost.
 *
 * Confirming a send removes rows that 168 Hours wrote earlier and no longer
 * sends. If the user hand-edits one of those rows at the same moment (which
 * clears `source`, making it theirs), the confirm must keep it. This test
 * holds the hand edit open, uncommitted, on one connection while a confirm
 * runs in another process, then commits the edit and checks the row survived.
 * Run via tests/integration/run.sh.
 */
require __DIR__ . '/../../config/db_config.php';
require __DIR__ . '/../../includes/hours_drafts.php';
$fail = 0;
function check(bool $ok, string $what): void { global $fail; echo ($ok ? "ok   " : "FAIL ") . $what . "\n"; if (!$ok) $fail++; }

$pdo = get_db_connection();
$uid = 101;
$from = '2026-04-19'; $to = '2026-04-25';
$pdo->exec("DELETE FROM hours WHERE user_id = $uid AND date_worked BETWEEN '$from' AND '$to'");
$pdo->exec("DELETE FROM hours_draft_batches WHERE user_id = $uid");

// An earlier confirmed send left this 168 Hours row; the new send drops it.
$pdo->exec("INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours, source) VALUES ($uid, NULL, 11, '2026-04-20', '2026-17', 1.00, 'cos-168')");
$rowId = (int) $pdo->lastInsertId();
$batch = pulse_drafts_store($pdo, $uid, 'cos-168', $from, $to, [['date' => '2026-04-21', 'taskId' => 10, 'hours' => 2.0]], 'race-test');

// Connection B: the user hand-edits that row, and has not committed yet.
$b = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$b->beginTransaction();
$b->exec("UPDATE hours SET hours = 3.00, source = NULL WHERE id = $rowId");

// The confirm runs in its own process and must wait for, then respect, the edit.
$cmd = sprintf('php -d auto_prepend_file=%s %s %d %d', escapeshellarg(__DIR__ . '/prepend.php'), escapeshellarg(__DIR__ . '/confirm_child.php'), $uid, $batch['batchId']);
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
usleep(1500000);
$status = proc_get_status($proc);
check($status['running'], 'the confirm waits while the hand edit is open');
$b->commit();
$out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
proc_close($proc);
check(str_contains($out, '"confirmed":1'), 'the confirm completed: ' . trim($out . ' ' . $err));

$row = $pdo->query("SELECT hours, source FROM hours WHERE id = $rowId")->fetch(PDO::FETCH_ASSOC);
check($row !== false, 'the hand-edited row still exists');
check($row && $row['hours'] === '3.00' && $row['source'] === null, 'with the user\'s value, as theirs: ' . json_encode($row));
$new = $pdo->query("SELECT hours, source FROM hours WHERE user_id = $uid AND task_id = 10 AND date_worked = '2026-04-21'")->fetch(PDO::FETCH_ASSOC);
check($new && $new['source'] === 'cos-168', 'the rest of the send was confirmed');

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
