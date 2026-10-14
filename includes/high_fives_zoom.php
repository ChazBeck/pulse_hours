<?php
/**
 * Posting High Fives to the team's Zoom Team Chat channel.
 *
 * Delivery is Zoom's pre-built Incoming Webhook app, the same route the client
 * news digest uses: `/inc connect <name>` inside the channel gives an endpoint
 * and a token. No OAuth, no bot. The two values live in a KEY=value file
 * outside the docroot, named by PULSE_ZOOM_HIGH_FIVES_FILE (an nginx
 * fastcgi_param for the web, an argument for scripts/post_high_fives.php):
 *
 *     ZOOM_WEBHOOK_ENDPOINT=https://integrations.zoom.us/chat/webhooks/incomingwebhook/...
 *     ZOOM_WEBHOOK_TOKEN=...
 *
 * No file means Zoom is off: High Fives are saved as 'off' and never posted.
 *
 * Every attempt first CLAIMS the row (pending/failed -> sending in one UPDATE),
 * so the page's immediate post and the retry script can never both post the
 * same High Five. Zoom answers a bare "OK" with no message id; HTTP 200 is the
 * only confirmation there is.
 *
 * Card quirks found by the client news digest against the live channel:
 * Markdown is not rendered, and head.sub_head is overwritten by Zoom with
 * "Connection: <name>", so everything goes in head.text and body.
 */

require_once __DIR__ . '/high_fives.php';

/** Attempts after which a High Five is left as 'failed' and no longer retried. */
const HIGH_FIVE_ZOOM_MAX_ATTEMPTS = 10;
/** A 'sending' claim older than this many minutes is assumed dead and retried. */
const HIGH_FIVE_ZOOM_STUCK_MINUTES = 10;

/**
 * ['endpoint' => ..., 'token' => ...] from the credentials file, or null when
 * Zoom is not set up (no file named, file missing/unreadable, or incomplete).
 */
function high_fives_zoom_config(?string $file = null): ?array
{
    $file = $file ?? (getenv('PULSE_ZOOM_HIGH_FIVES_FILE') ?: '');
    if ($file === '' || !is_readable($file)) {
        return null;
    }
    $values = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), "'\"");
    }
    if (empty($values['ZOOM_WEBHOOK_ENDPOINT']) || empty($values['ZOOM_WEBHOOK_TOKEN'])) {
        return null;
    }
    return ['endpoint' => $values['ZOOM_WEBHOOK_ENDPOINT'], 'token' => $values['ZOOM_WEBHOOK_TOKEN']];
}

/** The Zoom card for one High Five. */
function high_fives_zoom_card(string $giver, string $recipients, string $message): array
{
    return [
        'head' => ['text' => '🙌 High Five'],
        'body' => [
            ['type' => 'message', 'text' => $giver . ' → ' . $recipients, 'style' => ['bold' => true]],
            ['type' => 'message', 'text' => $message],
        ],
    ];
}

/** POST a card; returns [http status (0 = no response), response text]. */
function high_fives_zoom_send(array $config, array $card): array
{
    $ch = curl_init($config['endpoint'] . '?format=full');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($card, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Authorization: ' . $config['token'], 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $text = $body === false ? curl_error($ch) : (string) $body;
    curl_close($ch);
    return [$status, mb_substr($text, 0, 200)];
}

/**
 * Post one High Five if it is waiting (pending or failed, under the attempt
 * limit) or stuck in 'sending'. Returns 'posted', 'failed', or 'skipped' (not
 * claimable: already posted, off, in flight elsewhere, or out of attempts).
 * $send is swappable for tests; it defaults to high_fives_zoom_send().
 */
function high_fives_zoom_post(PDO $pdo, int $id, array $config, ?callable $send = null): string
{
    $claim = $pdo->prepare("
        UPDATE high_fives
        SET zoom_status = 'sending', zoom_attempts = zoom_attempts + 1, zoom_attempted_at = CURRENT_TIMESTAMP
        WHERE id = ?
          AND zoom_attempts < ?
          AND (zoom_status IN ('pending', 'failed')
               OR (zoom_status = 'sending' AND zoom_attempted_at < CURRENT_TIMESTAMP - INTERVAL ? MINUTE))
    ");
    $claim->execute([$id, HIGH_FIVE_ZOOM_MAX_ATTEMPTS, HIGH_FIVE_ZOOM_STUCK_MINUTES]);
    if ($claim->rowCount() !== 1) {
        return 'skipped';
    }

    $stmt = $pdo->prepare("
        SELECT hf.message, CONCAT(g.first_name, ' ', g.last_name) AS giver,
               GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name)
                            ORDER BY u.first_name, u.last_name SEPARATOR '|') AS names
        FROM high_fives hf
        JOIN users g ON g.id = hf.giver_user_id
        JOIN high_five_recipients r ON r.high_five_id = hf.id
        JOIN users u ON u.id = r.user_id
        WHERE hf.id = ?
        GROUP BY hf.id
    ");
    $stmt->execute([$id]);
    $hf = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hf) {
        // No recipients left (users deleted since): nothing sensible to post
        $pdo->prepare("UPDATE high_fives SET zoom_status = 'failed', zoom_attempts = ?, zoom_error = 'no recipients' WHERE id = ?")
            ->execute([HIGH_FIVE_ZOOM_MAX_ATTEMPTS, $id]);
        return 'failed';
    }

    $card =high_fives_zoom_card($hf['giver'], high_fives_join_names(explode('|', $hf['names'])), $hf['message']);
    try {
        [$status, $text] = ($send ?? 'high_fives_zoom_send')($config, $card);
    } catch (Throwable $e) {
        [$status, $text] = [0, get_class($e) . ': ' . $e->getMessage()];
    }

    if ($status === 200) {
        $pdo->prepare("UPDATE high_fives SET zoom_status = 'posted', zoom_posted_at = CURRENT_TIMESTAMP, zoom_error = NULL WHERE id = ?")
            ->execute([$id]);
        return 'posted';
    }
    $error = mb_substr('HTTP ' . $status . ': ' . $text, 0, 255);
    $pdo->prepare("UPDATE high_fives SET zoom_status = 'failed', zoom_error = ? WHERE id = ?")->execute([$error, $id]);
    error_log('High Five ' . $id . ' not posted to Zoom: ' . $error);
    return 'failed';
}

/** Ids of every High Five the retry script should try now, oldest first. */
function high_fives_zoom_due(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT id FROM high_fives
        WHERE zoom_attempts < ?
          AND (zoom_status IN ('pending', 'failed')
               OR (zoom_status = 'sending' AND zoom_attempted_at < CURRENT_TIMESTAMP - INTERVAL ? MINUTE))
        ORDER BY id
    ");
    $stmt->execute([HIGH_FIVE_ZOOM_MAX_ATTEMPTS, HIGH_FIVE_ZOOM_STUCK_MINUTES]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
