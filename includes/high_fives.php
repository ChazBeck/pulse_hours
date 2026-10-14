<?php
/**
 * High Fives: public kudos from one teammate to one or more others
 * (see database/migrate_add_high_fives.php). Given on apps/high-fives.php,
 * the step between the Pulse check-in and the hours page.
 *
 * The rules, in one place:
 *  - A High Five has a giver, one or more recipients and a message, and
 *    belongs to a Pulse week: the week of the giver's latest check-in, the
 *    same week apps/hours.php files their hours under.
 *  - Recipients must be active users other than the giver.
 *  - The message is required, trimmed, and at most HIGH_FIVE_MAX_LENGTH
 *    characters.
 */

const HIGH_FIVE_MAX_LENGTH = 1000;

/** True once the migration has run. Until then the High Fives step is skipped. */
function high_fives_enabled(PDO $pdo): bool
{
    return (bool) $pdo->query("SHOW TABLES LIKE 'high_fives'")->fetch();
}

/** Active users other than $selfId, as [id, first_name, last_name], by name. */
function high_fives_teammates(PDO $pdo, int $selfId): array
{
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name
        FROM users
        WHERE is_active = 1 AND id <> ?
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$selfId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Save a High Five and return its id.
 *
 * @throws InvalidArgumentException with a message fit to show the user
 */
function high_fives_give(PDO $pdo, int $giverId, string $yearWeek, array $recipientIds, string $message): int
{
    $message = trim($message);
    $ids = array_values(array_unique(array_map('intval', $recipientIds)));

    if (!$ids) {
        throw new InvalidArgumentException('Pick at least one teammate for your High Five.');
    }
    if ($message === '') {
        throw new InvalidArgumentException('Write a line about what they did.');
    }
    if (mb_strlen($message) > HIGH_FIVE_MAX_LENGTH) {
        throw new InvalidArgumentException('Keep it under ' . HIGH_FIVE_MAX_LENGTH . ' characters.');
    }
    $allowed = array_map('intval', array_column(high_fives_teammates($pdo, $giverId), 'id'));
    if (array_diff($ids, $allowed)) {
        throw new InvalidArgumentException('Pick teammates from the list.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO high_fives (giver_user_id, year_week, message) VALUES (?, ?, ?)")
            ->execute([$giverId, $yearWeek, $message]);
        $id = (int) $pdo->lastInsertId();
        $add = $pdo->prepare("INSERT INTO high_five_recipients (high_five_id, user_id) VALUES (?, ?)");
        foreach ($ids as $uid) {
            $add->execute([$id, $uid]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $id;
}

/**
 * The High Fives a user gave for a Pulse week, newest first, each with
 * 'recipients' (names joined for display).
 */
function high_fives_given(PDO $pdo, int $giverId, string $yearWeek): array
{
    $stmt = $pdo->prepare("
        SELECT hf.id, hf.message, hf.created_at,
               GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name)
                            ORDER BY u.first_name, u.last_name SEPARATOR '|') AS names
        FROM high_fives hf
        JOIN high_five_recipients r ON r.high_five_id = hf.id
        JOIN users u ON u.id = r.user_id
        WHERE hf.giver_user_id = ? AND hf.year_week = ?
        GROUP BY hf.id
        ORDER BY hf.created_at DESC, hf.id DESC
    ");
    $stmt->execute([$giverId, $yearWeek]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['recipients'] = high_fives_join_names(explode('|', $row['names']));
        unset($row['names']);
    }
    return $rows;
}

/**
 * The High Fives a user received for a Pulse week, newest first, each with
 * 'giver' (name) and 'others' (the other recipients' names, joined; '' if none).
 */
function high_fives_received(PDO $pdo, int $userId, string $yearWeek): array
{
    $stmt = $pdo->prepare("
        SELECT hf.id, hf.message, hf.created_at,
               CONCAT(g.first_name, ' ', g.last_name) AS giver,
               GROUP_CONCAT(CASE WHEN o.id <> ? THEN CONCAT(o.first_name, ' ', o.last_name) END
                            ORDER BY o.first_name, o.last_name SEPARATOR '|') AS others
        FROM high_fives hf
        JOIN high_five_recipients me ON me.high_five_id = hf.id AND me.user_id = ?
        JOIN users g ON g.id = hf.giver_user_id
        JOIN high_five_recipients r ON r.high_five_id = hf.id
        JOIN users o ON o.id = r.user_id
        WHERE hf.year_week = ?
        GROUP BY hf.id
        ORDER BY hf.created_at DESC, hf.id DESC
    ");
    $stmt->execute([$userId, $userId, $yearWeek]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['others'] = $row['others'] === null ? '' : high_fives_join_names(explode('|', $row['others']));
    }
    return $rows;
}

/** "Ann", "Ann and Katie", "Ann, Katie and Kristen". */
function high_fives_join_names(array $names): string
{
    if (count($names) < 2) {
        return (string) ($names[0] ?? '');
    }
    $last = array_pop($names);
    return implode(', ', $names) . ' and ' . $last;
}
