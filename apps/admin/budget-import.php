<?php
/**
 * Budget Import - load projected hours from a Veerless budget .xlsx into task estimates.
 *
 * Flow:
 *   1. Admin uploads a "Staff Rate Allocation" budget spreadsheet.
 *   2. We parse it (includes/BudgetParser.php), match the client, and show a
 *      preview where each budget PHASE is mapped to an existing task (best-guess
 *      auto-selected; admin confirms/changes).
 *   3. On commit, each mapped task's estimated_hours is set to the phase's
 *      projected hours. This "systematizes" item #1 (estimated hours per task).
 */

require __DIR__ . '/../../sso/sso_include.php';
pulse_require_admin();

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../includes/BudgetParser.php';

$pdo = get_db_connection();
$message = '';
$message_type = '';
$parsed = null;         // parsed budget for the preview step
$matchedClientId = null;

/** Normalize a name for fuzzy comparison: lowercase alphanumerics only. */
function bi_norm($s) {
    return preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
}

/** Pick the client whose name best matches the budget's client string. */
function bi_match_client($budgetClient, array $clients) {
    $target = bi_norm($budgetClient);
    if ($target === '') return null;
    $best = null; $bestScore = 0;
    foreach ($clients as $c) {
        $n = bi_norm($c['name']);
        if ($n === '') continue;
        // Containment either direction is a strong signal (e.g. "chrobinson" vs "crobinson").
        if (strpos($target, $n) !== false || strpos($n, $target) !== false) {
            return (int) $c['id'];
        }
        similar_text($target, $n, $pct);
        if ($pct > $bestScore) { $bestScore = $pct; $best = (int) $c['id']; }
    }
    return $bestScore >= 60 ? $best : null;
}

/** Best-guess task for a phase name; returns task id or null. */
function bi_suggest_task($phaseName, array $tasks) {
    $p = bi_norm($phaseName);
    if ($p === '') return null;
    $best = null; $bestScore = 0;
    foreach ($tasks as $t) {
        similar_text($p, bi_norm($t['name']), $pct);
        if ($pct > $bestScore) { $bestScore = $pct; $best = (int) $t['id']; }
    }
    return $bestScore >= 45 ? $best : null;
}

$clients = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !auth_verify_csrf($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // ---- STEP 2: parse an uploaded file and build the preview ----
        if ($action === 'preview') {
            $up = $_FILES['budget'] ?? null;
            if (!$up || $up['error'] !== UPLOAD_ERR_OK) {
                $message = 'Please choose a .xlsx budget file to upload.';
                $message_type = 'error';
            } elseif ($up['size'] > 5 * 1024 * 1024) {
                $message = 'File too large (max 5 MB).';
                $message_type = 'error';
            } elseif (strtolower(pathinfo($up['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
                $message = 'Please upload an .xlsx file.';
                $message_type = 'error';
            } else {
                try {
                    $parsed = BudgetParser::parse($up['tmp_name']);
                    $matchedClientId = bi_match_client($parsed['client'], $clients);
                } catch (Throwable $e) {
                    $message = 'Could not parse budget: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }

        // ---- STEP 3: commit the confirmed phase -> task mapping ----
        elseif ($action === 'commit') {
            $clientId = intval($_POST['client_id'] ?? 0);
            $names   = $_POST['phase_name']  ?? [];
            $hours   = $_POST['phase_hours'] ?? [];
            $marcy   = $_POST['phase_marcy'] ?? [];
            $taskMap = $_POST['task_map']    ?? [];

            if ($clientId <= 0) {
                $message = 'Please confirm which client this budget belongs to.';
                $message_type = 'error';
            } else {
                $updated = 0; $created = 0; $skipped = 0;
                try {
                    $pdo->beginTransaction();
                    foreach ($names as $i => $pname) {
                        $target = $taskMap[$i] ?? 'skip';
                        $h = (float) ($hours[$i] ?? 0);
                        $mh = (float) ($marcy[$i] ?? 0);   // Marcy's portion of this phase
                        if ($target === 'skip' || $h <= 0) { $skipped++; continue; }

                        if ($target === 'new') {
                            $stmt = $pdo->prepare("INSERT INTO tasks (client_id, project_id, name, status, estimated_hours, estimated_hours_marcy) VALUES (?, NULL, ?, 'not-started', ?, ?)");
                            $stmt->execute([$clientId, trim($pname), $h, $mh]);
                            $created++;
                        } else {
                            $taskId = intval($target);
                            // Guard: only write to a task that belongs to the confirmed client.
                            $chk = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND client_id = ?");
                            $chk->execute([$taskId, $clientId]);
                            if ($chk->fetch()) {
                                $upd = $pdo->prepare("UPDATE tasks SET estimated_hours = ?, estimated_hours_marcy = ? WHERE id = ?");
                                $upd->execute([$h, $mh, $taskId]);
                                $updated++;
                            } else {
                                $skipped++;
                            }
                        }
                    }
                    $pdo->commit();
                    $message = "Import complete: {$updated} task(s) updated, {$created} created, {$skipped} skipped.";
                    $message_type = 'success';
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $message = 'Import failed: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// For the preview: every active client's tasks, so the mapping dropdowns can be
// rebuilt in-browser when the admin corrects the client (no re-upload needed).
$allClientTasks = [];   // clientId => [ {id, name, project_name, estimated_hours}, ... ]
if ($parsed !== null) {
    $rowsT = $pdo->query("
        SELECT t.client_id, t.id, t.name, p.name AS project_name, t.estimated_hours
        FROM tasks t
        INNER JOIN clients c ON t.client_id = c.id AND c.active = 1
        LEFT JOIN projects p ON t.project_id = p.id
        ORDER BY p.name, t.name
    ")->fetchAll();
    foreach ($rowsT as $t) {
        $allClientTasks[(int) $t['client_id']][] = [
            'id'   => (int) $t['id'],
            'name' => $t['name'],
            'project_name' => $t['project_name'],
            'estimated_hours' => $t['estimated_hours'],
        ];
    }
}
// Tasks for the initially matched client (server-rendered first pass).
$previewTasks = $matchedClientId !== null ? ($allClientTasks[$matchedClientId] ?? []) : [];

function fmt_h($n) { return rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Import - Admin - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <style>
        .bi-container { padding: 2rem; max-width: 1000px; }
        .bi-header h1 { color: var(--text-primary); font-size: 2rem; margin-bottom: 0.5rem; }
        .bi-header p { color: var(--text-secondary); margin-bottom: 1.5rem; }
        .bi-meta { display: flex; flex-wrap: wrap; gap: 1.5rem; background: #f9fafb; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
        .bi-meta div { font-size: 0.9rem; }
        .bi-meta .k { color: #6b7280; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .bi-meta .v { font-weight: 600; color: var(--text-primary); }
        table.bi { width: 100%; border-collapse: collapse; background: white; }
        table.bi th, table.bi td { padding: 0.6rem 0.8rem; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: top; }
        table.bi th { background: #f9fafb; font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; }
        table.bi td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.bi select { width: 100%; padding: 0.4rem; border: 1px solid #d1d5db; border-radius: 6px; }
        .bi-lines { color: #9ca3af; font-size: 0.8rem; margin-top: 0.25rem; }
        .bi-warn { background: #fef3c7; color: #92400e; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/_admin_nav.php'; ?>

    <main class="admin-content">
        <div class="bi-container">
            <div class="bi-header">
                <h1>Budget Import</h1>
                <p>Upload a Veerless budget spreadsheet to load its projected hours onto task estimates.</p>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($parsed === null): ?>
            <!-- STEP 1: upload -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                        <input type="hidden" name="action" value="preview">
                        <div class="form-group">
                            <label for="budget">Budget spreadsheet (.xlsx)</label>
                            <input type="file" id="budget" name="budget" accept=".xlsx" required>
                            <small style="display:block;margin-top:0.25rem;color:#6b7280;">
                                Use a "Staff Rate Allocation" / "Reporting" budget. We read the <strong>Projected Hours</strong> per phase.
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary">Parse &amp; Preview</button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- STEP 2: preview & map -->
            <div class="bi-meta">
                <div><div class="k">Budget client</div><div class="v"><?= htmlspecialchars($parsed['client'] ?: '—') ?></div></div>
                <div><div class="k">Project</div><div class="v"><?= htmlspecialchars($parsed['project_name'] ?: '—') ?></div></div>
                <div><div class="k">Phases</div><div class="v"><?= count($parsed['phases']) ?></div></div>
                <div><div class="k">Total projected hours</div><div class="v"><?= fmt_h($parsed['total_hours']) ?></div></div>
            </div>

            <?php if ($matchedClientId === null): ?>
                <div class="bi-warn">Couldn't confidently match "<?= htmlspecialchars($parsed['client']) ?>" to a client — pick the correct one below.</div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= auth_csrf_token() ?>">
                <input type="hidden" name="action" value="commit">

                <div class="card">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="client_id">Apply to client</label>
                            <select id="client_id" name="client_id" required>
                                <option value="">-- Select client --</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $matchedClientId === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block;margin-top:0.25rem;color:#6b7280;">
                                Task dropdowns below list this client's tasks. Change the client and the lists refresh.
                            </small>
                        </div>

                        <table class="bi">
                            <thead>
                                <tr>
                                    <th style="width:40%;">Budget phase</th>
                                    <th class="num" style="width:10%;">Proj. hrs</th>
                                    <th class="num" style="width:10%;">of which Marcy</th>
                                    <th style="width:40%;">Map to task &rarr; set estimate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parsed['phases'] as $i => $phase): ?>
                                <?php $suggest = bi_suggest_task($phase['name'], $previewTasks); ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($phase['name']) ?></strong>
                                        <div class="bi-lines"><?= count($phase['line_items']) ?> line item(s)</div>
                                        <input type="hidden" name="phase_name[<?= $i ?>]" value="<?= htmlspecialchars($phase['name']) ?>">
                                        <input type="hidden" name="phase_hours[<?= $i ?>]" value="<?= htmlspecialchars($phase['hours']) ?>">
                                        <input type="hidden" name="phase_marcy[<?= $i ?>]" value="<?= htmlspecialchars($phase['marcy_hours'] ?? 0) ?>">
                                    </td>
                                    <td class="num"><?= fmt_h($phase['hours']) ?></td>
                                    <td class="num"><?= ($phase['marcy_hours'] ?? 0) > 0 ? fmt_h($phase['marcy_hours']) : '<span style="color:#9ca3af">—</span>' ?></td>
                                    <td>
                                        <select name="task_map[<?= $i ?>]" class="bi-taskmap" data-phase="<?= htmlspecialchars($phase['name']) ?>">
                                            <option value="skip">— Skip —</option>
                                            <option value="new">➕ Create new task "<?= htmlspecialchars($phase['name']) ?>"</option>
                                            <?php foreach ($previewTasks as $t): ?>
                                            <option value="<?= $t['id'] ?>" <?= $suggest === (int)$t['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($t['name']) ?><?= $t['project_name'] ? ' · ' . htmlspecialchars($t['project_name']) : '' ?><?= $t['estimated_hours'] !== null ? ' (now ' . fmt_h($t['estimated_hours']) . 'h)' : '' ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="btn-group" style="margin-top:1.25rem;">
                            <button type="submit" class="btn btn-primary">Apply Estimates</button>
                            <a href="budget-import.php" class="btn btn-secondary">Cancel / Upload Another</a>
                        </div>
                    </div>
                </div>
            </form>

            <script>
                // All active clients' tasks, so changing "Apply to client" can rebuild
                // the per-phase task dropdowns without re-uploading the spreadsheet.
                const BI_TASKS = <?= json_encode($allClientTasks) ?>;

                function fmtH(n) {
                    if (n === null || n === undefined || n === '') return null;
                    return parseFloat(n).toString();
                }

                function rebuildTaskMaps() {
                    const clientId = document.getElementById('client_id').value;
                    const tasks = BI_TASKS[clientId] || [];
                    document.querySelectorAll('select.bi-taskmap').forEach(function (sel) {
                        const phase = sel.getAttribute('data-phase') || '';
                        sel.innerHTML = '';
                        sel.appendChild(new Option('— Skip —', 'skip'));
                        sel.appendChild(new Option('➕ Create new task "' + phase + '"', 'new'));
                        tasks.forEach(function (t) {
                            let label = t.name;
                            if (t.project_name) label += ' · ' + t.project_name;
                            const est = fmtH(t.estimated_hours);
                            if (est !== null) label += ' (now ' + est + 'h)';
                            sel.appendChild(new Option(label, t.id));
                        });
                    });
                }

                document.getElementById('client_id').addEventListener('change', rebuildTaskMaps);
            </script>

            <?php endif; ?>
        </div>
    </main>
</body>
</html>
