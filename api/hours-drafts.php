<?php
/**
 * POST /pulse/api/hours-drafts.php
 *
 * Send a user's hours for a date range as DRAFTS. They appear on that user's
 * hours page under "awaiting confirmation" and touch no report until confirmed
 * there. Sending again for the same range replaces the earlier unconfirmed
 * send, so a caller can send a week as often as it likes.
 *
 * Request:  {email, from:"YYYY-MM-DD", to:"YYYY-MM-DD", ref?:string,
 *            entries:[{date:"YYYY-MM-DD", taskId:string|int, hours:number}]}
 *           - the range is at most 7 days; every entry's date is inside it
 *           - one entry per (date, taskId); 0 < hours <= 24, quarter-hour steps
 *           - an empty entries list is allowed: it says "nothing for this range"
 * Response: {ok:true, written, conflicts:[{date, taskId, existingHours}]}
 *           a conflict is an hour row the user typed into Pulse by hand for the
 *           same task and day; it is never overwritten.
 *
 * Errors: 401 unauthorized · 403 user_not_allowed · 404 unknown_user ·
 *         400 invalid_range|invalid_entry|unknown_task|duplicate_entry ·
 *         413 body_too_large · 500 server_error
 */

require __DIR__ . '/_api.php';
require_once __DIR__ . '/../includes/hours_drafts.php';

const PULSE_API_MAX_ENTRIES = 500;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    api_fail(405, 'method_not_allowed');
}
$client = api_require_client();
$body = api_json_body();

$email = strtolower(trim((string) ($body['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_fail(400, 'invalid_email');
}
if ($client['emails'] !== null && !in_array($email, $client['emails'], true)) {
    api_fail(403, 'user_not_allowed');
}

$from = api_date($body['from'] ?? null);
$to = api_date($body['to'] ?? null);
if ($from === null || $to === null || $from > $to
    || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 6) {
    api_fail(400, 'invalid_range');
}

$ref = $body['ref'] ?? null;
$ref = is_string($ref) && preg_match('/^[A-Za-z0-9._:-]{1,80}$/', $ref) ? $ref : null;

$raw = $body['entries'] ?? null;
if (!is_array($raw) || !array_is_list($raw) || count($raw) > PULSE_API_MAX_ENTRIES) {
    api_fail(400, 'invalid_entry');
}

$entries = [];
$seen = [];
foreach ($raw as $i => $e) {
    $date = is_array($e) ? api_date($e['date'] ?? null) : null;
    $taskId = is_array($e) ? ($e['taskId'] ?? null) : null;
    $hours = is_array($e) ? ($e['hours'] ?? null) : null;
    $taskOk = (is_int($taskId) && $taskId > 0)
        || (is_string($taskId) && preg_match('/^[1-9]\d{0,9}$/', $taskId));
    $hoursOk = (is_int($hours) || is_float($hours)) && $hours > 0 && $hours <= 24
        && abs($hours * 4 - round($hours * 4)) < 1e-9;
    if ($date === null || $date < $from || $date > $to || !$taskOk || !$hoursOk) {
        api_fail(400, 'invalid_entry', ['index' => $i]);
    }
    $taskId = (int) $taskId;
    $key = $taskId . '|' . $date;
    if (isset($seen[$key])) {
        api_fail(400, 'duplicate_entry', ['index' => $i]);
    }
    $seen[$key] = true;
    $entries[] = ['date' => $date, 'taskId' => $taskId, 'hours' => round((float) $hours, 2)];
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("SELECT id, is_active FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !(int) $user['is_active']) {
        api_fail(404, 'unknown_user');
    }

    $taskIds = array_values(array_unique(array_column($entries, 'taskId')));
    if ($taskIds) {
        $in = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id IN ($in)");
        $stmt->execute($taskIds);
        $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($taskIds, $found));
        if ($missing) {
            api_fail(400, 'unknown_task', ['taskIds' => array_map('strval', $missing)]);
        }
    }

    $result = pulse_drafts_store($pdo, (int) $user['id'], $client['source'], $from, $to, $entries, $ref);
} catch (Throwable $e) {
    error_log('pulse api hours-drafts: ' . $e->getMessage());
    api_fail(500, 'server_error');
}

api_respond(200, [
    'ok'        => true,
    'written'   => $result['written'],
    'conflicts' => $result['conflicts'],
]);
