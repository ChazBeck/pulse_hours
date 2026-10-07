<?php
/**
 * Draft hours sent in by other applications (see database/migrate_add_hours_drafts.php).
 *
 * Shared by the API (api/hours-drafts.php, which stores a send) and the hours
 * page (apps/hours.php, where the user confirms or discards it).
 *
 * The rules, in one place:
 *  - A send covers a date range. Sending again for an overlapping range
 *    REPLACES the earlier unconfirmed send from that source, so re-sending a
 *    week is idempotent and a block deleted at the source disappears.
 *  - Hours typed into Pulse by hand (hours.source IS NULL) are NEVER changed.
 *    A draft for the same user, task and day is a conflict: it is reported, and
 *    stays behind as a draft when the rest of its send is confirmed.
 *  - Confirming a send makes `hours` match it for that source and range: rows
 *    the source wrote earlier are updated, added, or removed (the block was
 *    deleted since) — but only rows carrying that same source.
 */

require_once __DIR__ . '/date_helpers.php';

/**
 * Of the given task ids, the ones a user may log hours against right now — the
 * same rule apps/hours.php and api/catalog.php apply when they offer choices:
 * the task is not completed, and its client (via its project, when it has one)
 * is active, as is that project. Keep the three in step.
 */
function pulse_loggable_task_ids(PDO $pdo, array $taskIds): array
{
    if (!$taskIds) return [];
    $in = implode(',', array_fill(0, count($taskIds), '?'));
    $stmt = $pdo->prepare("
        SELECT t.id FROM tasks t
        LEFT JOIN projects p ON p.id = t.project_id
        JOIN clients c ON c.id = CASE WHEN t.project_id IS NULL THEN t.client_id ELSE p.client_id END
        WHERE t.id IN ($in)
          AND t.status != 'completed'
          AND (t.project_id IS NULL OR p.active = 1)
          AND c.active = 1
    ");
    $stmt->execute(array_values($taskIds));
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Hand-entered `hours` rows that collide with the given entries, keyed "taskId|date". */
function pulse_drafts_manual_rows(PDO $pdo, int $userId, string $from, string $to): array
{
    $stmt = $pdo->prepare("
        SELECT task_id, date_worked, hours FROM hours
        WHERE user_id = ? AND source IS NULL AND date_worked BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $from, $to]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['task_id'] . '|' . $r['date_worked']] = (float) $r['hours'];
    }
    return $out;
}

/**
 * Store a send. $entries are already validated: [['date','taskId'(int),'hours'(float)]].
 * Returns ['batchId', 'written', 'conflicts' => [['date','taskId','existingHours']]].
 */
function pulse_drafts_store(PDO $pdo, int $userId, string $source, string $from, string $to, array $entries, ?string $ref): array
{
    $pdo->beginTransaction();
    try {
        /* Replace any unconfirmed send from this source that overlaps. */
        $del = $pdo->prepare("
            DELETE FROM hours_draft_batches
            WHERE user_id = ? AND source = ? AND date_from <= ? AND date_to >= ?
        ");
        $del->execute([$userId, $source, $to, $from]);

        $ins = $pdo->prepare("
            INSERT INTO hours_draft_batches (user_id, source, date_from, date_to, source_ref)
            VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([$userId, $source, $from, $to, $ref]);
        $batchId = (int) $pdo->lastInsertId();

        $taskProject = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
        $draft = $pdo->prepare("
            INSERT INTO hours_drafts (batch_id, task_id, project_id, date_worked, year_week, hours)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($entries as $e) {
            $taskProject->execute([$e['taskId']]);
            $projectId = $taskProject->fetchColumn();
            $draft->execute([
                $batchId, $e['taskId'], $projectId === false ? null : $projectId,
                $e['date'], get_year_week(strtotime($e['date'])), $e['hours'],
            ]);
        }

        $manual = pulse_drafts_manual_rows($pdo, $userId, $from, $to);
        $conflicts = [];
        foreach ($entries as $e) {
            $key = $e['taskId'] . '|' . $e['date'];
            if (isset($manual[$key])) {
                $conflicts[] = ['date' => $e['date'], 'taskId' => (string) $e['taskId'], 'existingHours' => $manual[$key]];
            }
        }

        $pdo->commit();
        return ['batchId' => $batchId, 'written' => count($entries), 'conflicts' => $conflicts];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Every unconfirmed send for a user, newest first, with its drafts named and
 * conflicts marked — what the hours page shows.
 */
function pulse_drafts_pending(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT id, source, date_from, date_to, created_at, confirmed_at FROM hours_draft_batches
        WHERE user_id = ? ORDER BY date_from DESC, id DESC
    ");
    $stmt->execute([$userId]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$batches) return [];

    $rows = $pdo->prepare("
        SELECT d.task_id, d.date_worked, d.hours, t.name AS task_name,
               c.name AS client_name, p.name AS project_name
        FROM hours_drafts d
        JOIN tasks t ON t.id = d.task_id
        LEFT JOIN projects p ON p.id = d.project_id
        JOIN clients c ON c.id = CASE WHEN t.project_id IS NULL THEN t.client_id ELSE p.client_id END
        WHERE d.batch_id = ?
        ORDER BY d.date_worked ASC, c.name ASC, t.name ASC
    ");
    foreach ($batches as &$b) {
        $rows->execute([$b['id']]);
        $manual = pulse_drafts_manual_rows($pdo, $userId, $b['date_from'], $b['date_to']);
        $b['drafts'] = [];
        $b['total'] = 0.0;
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['task_id'] . '|' . $r['date_worked'];
            $r['conflict'] = $manual[$key] ?? null;
            $b['total'] += (float) $r['hours'];
            $b['drafts'][] = $r;
        }
    }
    unset($b);
    return $batches;
}

/**
 * Confirm one send into `hours`. Returns ['confirmed' => n, 'kept' => n]
 * where `kept` drafts stay behind because a hand-entered row holds that slot.
 */
function pulse_drafts_confirm(PDO $pdo, int $userId, int $batchId): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM hours_draft_batches WHERE id = ? AND user_id = ? FOR UPDATE");
        $stmt->execute([$batchId, $userId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            $pdo->rollBack();
            return ['confirmed' => 0, 'kept' => 0];
        }

        $drafts = $pdo->prepare("SELECT * FROM hours_drafts WHERE batch_id = ?");
        $drafts->execute([$batchId]);
        $drafts = $drafts->fetchAll(PDO::FETCH_ASSOC);
        $manual = pulse_drafts_manual_rows($pdo, $userId, $batch['date_from'], $batch['date_to']);

        /* Rows this source wrote earlier for the range and no longer sends: the
           block was deleted or untagged at the source since. Only on the FIRST
           confirm of a send — a send confirmed with conflicts left over keeps
           just those leftovers, and confirming them later must not read every
           other row as "no longer sent". */
        if ($batch['confirmed_at'] === null) {
            $keep = [];
            foreach ($drafts as $d) $keep[$d['task_id'] . '|' . $d['date_worked']] = true;
            /* Two guards against losing a row the user is editing by hand right
               now (a hand edit clears `source`, making the row theirs):
                 - FOR UPDATE locks these rows for the rest of the transaction, so
                   an edit on hours.php waits for the confirm instead of landing
                   between this read and the delete below;
                 - the delete only removes a row that STILL carries this source,
                   so an edit that committed first is never deleted either.
               A locking read sees the latest committed rows, not the snapshot. */
            $old = $pdo->prepare("
                SELECT id, task_id, date_worked FROM hours
                WHERE user_id = ? AND source = ? AND date_worked BETWEEN ? AND ?
                FOR UPDATE
            ");
            $old->execute([$userId, $batch['source'], $batch['date_from'], $batch['date_to']]);
            $remove = $pdo->prepare("DELETE FROM hours WHERE id = ? AND user_id = ? AND source = ?");
            foreach ($old->fetchAll(PDO::FETCH_ASSOC) as $o) {
                if (!isset($keep[$o['task_id'] . '|' . $o['date_worked']])) {
                    $remove->execute([$o['id'], $userId, $batch['source']]);
                }
            }
        }

        $upsert = $pdo->prepare("
            INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours, source)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                hours = IF(source <=> VALUES(source), VALUES(hours), hours),
                project_id = IF(source <=> VALUES(source), VALUES(project_id), project_id),
                year_week = IF(source <=> VALUES(source), VALUES(year_week), year_week)
        ");
        $dropDraft = $pdo->prepare("DELETE FROM hours_drafts WHERE id = ?");
        $confirmed = 0;
        $kept = 0;
        foreach ($drafts as $d) {
            if (isset($manual[$d['task_id'] . '|' . $d['date_worked']])) {
                $kept++;
                continue;
            }
            /* The IF(source <=> ...) guard is belt and braces: the manual check
               above already skipped hand-entered slots, but a row typed in by
               hand between that read and this write must still win. */
            $upsert->execute([
                $userId, $d['project_id'], $d['task_id'], $d['date_worked'],
                $d['year_week'], $d['hours'], $batch['source'],
            ]);
            $dropDraft->execute([$d['id']]);
            $confirmed++;
        }

        if ($kept === 0) {
            $pdo->prepare("DELETE FROM hours_draft_batches WHERE id = ?")->execute([$batchId]);
        } else {
            $pdo->prepare("UPDATE hours_draft_batches SET confirmed_at = COALESCE(confirmed_at, CURRENT_TIMESTAMP) WHERE id = ?")
                ->execute([$batchId]);
        }
        $pdo->commit();
        return ['confirmed' => $confirmed, 'kept' => $kept];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Throw away one unconfirmed send. */
function pulse_drafts_discard(PDO $pdo, int $userId, int $batchId): bool
{
    $stmt = $pdo->prepare("DELETE FROM hours_draft_batches WHERE id = ? AND user_id = ?");
    $stmt->execute([$batchId, $userId]);
    return $stmt->rowCount() > 0;
}
