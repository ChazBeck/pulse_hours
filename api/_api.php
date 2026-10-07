<?php
/**
 * Shared plumbing for the machine-to-machine JSON API.
 *
 * These endpoints are called by other applications on the same server (the
 * Chief-of-Staff "168 Hours" planner first), never by a browser, so they do NOT
 * go through SSO. A caller presents a bearer token instead. Tokens live in a
 * file outside the docroot named by PULSE_API_TOKENS_FILE, one per line:
 *
 *     <source> <sha256-of-token> [allowed-email,allowed-email|*]
 *
 * Only the SHA-256 of each token is stored, so the file leaking does not leak a
 * usable credential. <source> is what rows written by that caller are stamped
 * with — it comes from the server's own file, never from the request, so a
 * caller cannot write rows that look like someone else's. The optional third
 * column limits which users that token may write for; omit it or use * for any.
 *
 * nginx additionally restricts /pulse/api/ to the server's own addresses.
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/date_helpers.php';

const PULSE_API_MAX_BODY = 65536;

function api_respond(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function api_fail(int $status, string $error, array $extra = []): never
{
    api_respond($status, ['ok' => false, 'error' => $error] + $extra);
}

function api_presented_token(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        $value = $_SERVER[$key] ?? '';
        if (is_string($value) && preg_match('/^Bearer\s+(\S+)$/i', $value, $m)) {
            return $m[1];
        }
    }
    $header = $_SERVER['HTTP_X_PULSE_TOKEN'] ?? '';
    return is_string($header) ? trim($header) : '';
}

/**
 * The calling client, as ['source' => string, 'emails' => string[]|null].
 * emails === null means the token may write for any user.
 */
function api_require_client(): array
{
    $presented = api_presented_token();
    $file = getenv('PULSE_API_TOKENS_FILE') ?: '';
    if ($presented === '' || $file === '' || !is_readable($file)) {
        api_fail(401, 'unauthorized');
    }
    $hash = hash('sha256', $presented);
    $match = null;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 2 || !preg_match('/^[a-z0-9][a-z0-9._-]{0,31}$/', $parts[0])) continue;
        /* Compare every line rather than stopping at the first hit, so timing
           does not reveal which line matched. */
        if (hash_equals(strtolower($parts[1]), $hash) && $match === null) {
            $emails = null;
            if (isset($parts[2]) && $parts[2] !== '*') {
                $emails = array_values(array_filter(array_map(
                    static fn ($e) => strtolower(trim($e)),
                    explode(',', $parts[2])
                )));
            }
            $match = ['source' => $parts[0], 'emails' => $emails];
        }
    }
    if ($match === null) {
        api_fail(401, 'unauthorized');
    }
    return $match;
}

function api_json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, PULSE_API_MAX_BODY + 1);
    if ($raw === false || strlen($raw) > PULSE_API_MAX_BODY) {
        api_fail(413, 'body_too_large');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_fail(400, 'invalid_json');
    }
    return $decoded;
}

/** A strict YYYY-MM-DD that is a real calendar date, or null. */
function api_date(mixed $value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

/** Does `clients.is_internal` exist? It is added by a migration, not the base schema. */
function api_has_is_internal(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'is_internal'");
    return (bool) $stmt->fetch();
}
