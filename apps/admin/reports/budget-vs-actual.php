<?php
/**
 * Reports - Budget vs Actual (Estimated vs Logged hours + cost)
 *
 * Layers each task's estimated hours (from the client budgets) against hours
 * actually logged, and splits both into Marcy (senior, MARCY_RATE) vs everyone
 * else (STANDARD_RATE) so you can see budgeted cost vs actual cost — not just
 * hours. Rolled up by client -> project -> task.
 */

require __DIR__ . '/../../../sso/sso_include.php';
auth_init();
pulse_require_admin();

require_once __DIR__ . '/../../../includes/rates.php';

$pdo = get_db_connection();
// $user provided by sso_include.php

$view = ($_GET['view'] ?? 'estimated') === 'all' ? 'all' : 'estimated';
$filterClientId = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? intval($_GET['client_id']) : null;

// Estimated vs actual per task, each split into Marcy vs everyone else.
$sql = "
    SELECT
        t.id                    AS task_id,
        t.name                  AS task_name,
        t.estimated_hours       AS est_hours,
        t.estimated_hours_marcy AS est_marcy,
        c.id                    AS client_id,
        c.name                  AS client_name,
        c.client_color          AS client_color,
        p.name                  AS project_name,
        COALESCE(SUM(h.hours), 0) AS act_hours,
        COALESCE(SUM(CASE WHEN u.email = :marcy THEN h.hours ELSE 0 END), 0) AS act_marcy
    FROM tasks t
    INNER JOIN clients c ON t.client_id = c.id
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN hours h ON h.task_id = t.id
    LEFT JOIN users u ON h.user_id = u.id
    GROUP BY t.id, t.name, t.estimated_hours, t.estimated_hours_marcy,
             c.id, c.name, c.client_color, p.name
    HAVING t.estimated_hours IS NOT NULL
        " . ($view === 'all' ? "OR act_hours > 0" : "") . "
    ORDER BY c.name ASC, p.name ASC, t.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':marcy' => MARCY_EMAIL]);
$rows = $stmt->fetchAll();

$clients = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name ASC")->fetchAll();

// Group by client, compute per-row and subtotal figures.
$byClient = [];
$G = ['est_h' => 0.0, 'est_mh' => 0.0, 'act_h' => 0.0, 'act_mh' => 0.0]; // grand totals
foreach ($rows as $r) {
    if ($filterClientId !== null && (int) $r['client_id'] !== $filterClientId) continue;
    $cid = $r['client_id'];
    if (!isset($byClient[$cid])) {
        $byClient[$cid] = ['name' => $r['client_name'], 'color' => $r['client_color'], 'tasks' => [],
                           'est_h' => 0.0, 'est_mh' => 0.0, 'act_h' => 0.0, 'act_mh' => 0.0];
    }
    $estH  = (float) $r['est_hours'];       // NULL -> 0
    $estMH = (float) $r['est_marcy'];
    $actH  = (float) $r['act_hours'];
    $actMH = (float) $r['act_marcy'];
    $byClient[$cid]['tasks'][] = $r;
    $byClient[$cid]['est_h']  += $estH;  $byClient[$cid]['est_mh'] += $estMH;
    $byClient[$cid]['act_h']  += $actH;  $byClient[$cid]['act_mh'] += $actMH;
    $G['est_h'] += $estH; $G['est_mh'] += $estMH; $G['act_h'] += $actH; $G['act_mh'] += $actMH;
}

/** cost from a {est_h,est_mh} or {act_h,act_mh} bucket */
function est_cost($b) { return rate_cost($b['est_mh'], $b['est_h'] - $b['est_mh']); }
function act_cost($b) { return rate_cost($b['act_mh'], $b['act_h'] - $b['act_mh']); }

function fmt_hours($n) { if ($n === null) return '&mdash;'; return rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); }

/** "total (marcy)" cell — total hours with Marcy's portion noted. */
function hrs_split($total, $marcy) {
    $out = fmt_hours($total);
    if ((float) $marcy > 0) {
        $out .= ' <span class="bva-marcy">(' . fmt_hours($marcy) . ')</span>';
    }
    return $out;
}

function variance_state($est, $act) {
    if ($est === null) return ['No estimate', 'bva-none'];
    $est = (float) $est; $act = (float) $act;
    if ($est == 0) return [$act > 0 ? 'Over' : 'On track', $act > 0 ? 'bva-over' : 'bva-ok'];
    $pct = $act / $est * 100;
    if ($pct > 105) return ['Over', 'bva-over'];
    if ($pct >= 90) return ['On track', 'bva-ok'];
    return ['Under', 'bva-under'];
}

$grandEstCost = est_cost($G);
$grandActCost = act_cost($G);
$grandCostPct = $grandEstCost > 0 ? $grandActCost / $grandEstCost * 100 : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget vs Actual - Reports - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <style>
        .bva-container { padding: 2rem; }
        .bva-header h1 { color: var(--text-primary); font-size: 2rem; margin-bottom: 0.5rem; }
        .bva-header p { color: var(--text-secondary); }
        .bva-controls { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin: 1.25rem 0; }
        .bva-controls label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
        .bva-controls select { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; min-width: 200px; }

        .bva-summary { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
        .bva-stat { background: white; border-radius: var(--border-radius); box-shadow: 0 2px 4px rgba(0,0,0,0.08); padding: 1.1rem 1.4rem; flex: 1; min-width: 150px; }
        .bva-stat .value { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); }
        .bva-stat .label { font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.03em; }

        .bva-split { width: auto; border-collapse: collapse; background: white; border-radius: var(--border-radius); box-shadow: 0 2px 4px rgba(0,0,0,0.08); margin-bottom: 1.5rem; overflow: hidden; }
        .bva-split th, .bva-split td { padding: 0.55rem 1.1rem; border-bottom: 1px solid #f0f0f0; text-align: right; font-variant-numeric: tabular-nums; }
        .bva-split th { background: #f9fafb; font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; }
        .bva-split td:first-child, .bva-split th:first-child { text-align: left; font-weight: 600; }
        .bva-split tr.marcy td:first-child { color: #7c3aed; }
        .bva-split tr.total td { font-weight: 700; background: #f3f4f6; }

        .bva-table-wrap { background: white; border-radius: var(--border-radius); box-shadow: 0 2px 4px rgba(0,0,0,0.08); overflow-x: auto; }
        table.bva { width: 100%; border-collapse: collapse; min-width: 820px; }
        table.bva th, table.bva td { padding: 0.6rem 0.9rem; text-align: left; border-bottom: 1px solid #f0f0f0; }
        table.bva th { background: #f9fafb; font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; white-space: nowrap; }
        table.bva td.num, table.bva th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tr.bva-client-row td { background: #f3f4f6; font-weight: 700; }
        tr.bva-client-row .dot { display: inline-block; width: 11px; height: 11px; border-radius: 50%; margin-right: 0.5rem; vertical-align: middle; }
        tr.bva-grand td { background: #111827; color: white; font-weight: 700; }
        .bva-project { color: #9ca3af; font-size: 0.85rem; }
        .bva-marcy { color: #7c3aed; font-weight: 600; }
        .bva-badge { padding: 0.15rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .bva-under { background: #dbeafe; color: #1e40af; }
        .bva-ok { background: #d1fae5; color: #065f46; }
        .bva-over { background: #fee2e2; color: #991b1b; }
        .bva-none { background: #f3f4f6; color: #6b7280; }
        .bva-note { font-size: 0.8rem; color: #6b7280; margin: 0.5rem 0 1.25rem; }
        .empty-state { padding: 3rem; text-align: center; color: #6b7280; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../_admin_nav.php'; ?>

    <main class="admin-content">
        <div class="bva-container">
            <div class="bva-header">
                <h1>Budget vs Actual</h1>
                <p>Estimated vs logged hours and cost, split by Marcy (senior) vs everyone else.</p>
            </div>

            <form method="GET" class="bva-controls">
                <div>
                    <label for="client_id">Client</label>
                    <select id="client_id" name="client_id" onchange="this.form.submit()">
                        <option value="">All clients</option>
                        <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $filterClientId === (int)$cl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="view">Show</label>
                    <select id="view" name="view" onchange="this.form.submit()">
                        <option value="estimated" <?= $view === 'estimated' ? 'selected' : '' ?>>Budgeted tasks only</option>
                        <option value="all" <?= $view === 'all' ? 'selected' : '' ?>>Include un-budgeted tasks with logged time</option>
                    </select>
                </div>
            </form>

            <div class="bva-summary">
                <div class="bva-stat"><div class="value"><?= fmt_hours($G['est_h']) ?></div><div class="label">Est. hours</div></div>
                <div class="bva-stat"><div class="value"><?= fmt_hours($G['act_h']) ?></div><div class="label">Actual hours</div></div>
                <div class="bva-stat"><div class="value"><?= fmt_money($grandEstCost) ?></div><div class="label">Est. cost</div></div>
                <div class="bva-stat"><div class="value"><?= fmt_money($grandActCost) ?></div><div class="label">Actual cost</div></div>
                <div class="bva-stat"><div class="value"><?= $grandCostPct === null ? '&mdash;' : number_format($grandCostPct, 0) . '%' ?></div><div class="label">Cost consumed</div></div>
            </div>

            <!-- Marcy vs everyone-else split -->
            <table class="bva-split">
                <thead>
                    <tr><th>Who</th><th>Est. hrs</th><th>Actual hrs</th><th>Est. cost</th><th>Actual cost</th></tr>
                </thead>
                <tbody>
                    <tr class="marcy">
                        <td>Marcy (@ <?= fmt_money(MARCY_RATE) ?>/hr)</td>
                        <td><?= fmt_hours($G['est_mh']) ?></td>
                        <td><?= fmt_hours($G['act_mh']) ?></td>
                        <td><?= fmt_money($G['est_mh'] * MARCY_RATE) ?></td>
                        <td><?= fmt_money($G['act_mh'] * MARCY_RATE) ?></td>
                    </tr>
                    <tr>
                        <td>Everyone else (@ <?= fmt_money(STANDARD_RATE) ?>/hr)</td>
                        <td><?= fmt_hours($G['est_h'] - $G['est_mh']) ?></td>
                        <td><?= fmt_hours($G['act_h'] - $G['act_mh']) ?></td>
                        <td><?= fmt_money(($G['est_h'] - $G['est_mh']) * STANDARD_RATE) ?></td>
                        <td><?= fmt_money(($G['act_h'] - $G['act_mh']) * STANDARD_RATE) ?></td>
                    </tr>
                    <tr class="total">
                        <td>Total</td>
                        <td><?= fmt_hours($G['est_h']) ?></td>
                        <td><?= fmt_hours($G['act_h']) ?></td>
                        <td><?= fmt_money($grandEstCost) ?></td>
                        <td><?= fmt_money($grandActCost) ?></td>
                    </tr>
                </tbody>
            </table>
            <p class="bva-note">Hours columns below read <strong>total (Marcy)</strong> — the number in purple is Marcy's portion. Cost uses <?= fmt_money(MARCY_RATE) ?>/hr for Marcy, <?= fmt_money(STANDARD_RATE) ?>/hr for everyone else.</p>

            <div class="bva-table-wrap">
                <?php if (empty($byClient)): ?>
                    <div class="empty-state">
                        <p>No budgeted tasks yet.</p>
                        <p style="font-size:0.9rem;">Set estimates via <a href="<?= url('/apps/admin/budget-import.php') ?>">Budget Import</a> or <a href="<?= url('/apps/admin/tasks.php') ?>">Manage Tasks</a>.</p>
                    </div>
                <?php else: ?>
                <table class="bva">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th class="num">Est. hrs (Marcy)</th>
                            <th class="num">Actual hrs (Marcy)</th>
                            <th class="num">Var</th>
                            <th class="num">Est. $</th>
                            <th class="num">Actual $</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byClient as $cid => $c): ?>
                            <?php
                                $cVar = $c['act_h'] - $c['est_h'];
                            ?>
                            <tr class="bva-client-row">
                                <td><span class="dot" style="background: <?= htmlspecialchars($c['color'] ?: '#9ca3af') ?>;"></span><?= htmlspecialchars($c['name']) ?></td>
                                <td class="num"><?= hrs_split($c['est_h'], $c['est_mh']) ?></td>
                                <td class="num"><?= hrs_split($c['act_h'], $c['act_mh']) ?></td>
                                <td class="num"><?= ($cVar > 0 ? '+' : '') . fmt_hours($cVar) ?></td>
                                <td class="num"><?= fmt_money(est_cost($c)) ?></td>
                                <td class="num"><?= fmt_money(act_cost($c)) ?></td>
                                <td></td>
                            </tr>
                            <?php foreach ($c['tasks'] as $t): ?>
                                <?php
                                    $est = $t['est_hours'];
                                    $estMH = (float) $t['est_marcy'];
                                    $act = (float) $t['act_hours'];
                                    $actMH = (float) $t['act_marcy'];
                                    $var = $act - (float) $est;
                                    [$stLabel, $stClass] = variance_state($est, $act);
                                    $rowEstCost = $est === null ? null : rate_cost($estMH, (float)$est - $estMH);
                                    $rowActCost = rate_cost($actMH, $act - $actMH);
                                ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($t['task_name']) ?>
                                        <?php if ($t['project_name']): ?><div class="bva-project"><?= htmlspecialchars($t['project_name']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="num"><?= $est === null ? '<em style="color:#9ca3af">&mdash;</em>' : hrs_split($est, $estMH) ?></td>
                                    <td class="num"><?= hrs_split($act, $actMH) ?></td>
                                    <td class="num"><?= $est === null ? '&mdash;' : (($var > 0 ? '+' : '') . fmt_hours($var)) ?></td>
                                    <td class="num"><?= $rowEstCost === null ? '&mdash;' : fmt_money($rowEstCost) ?></td>
                                    <td class="num"><?= fmt_money($rowActCost) ?></td>
                                    <td><span class="bva-badge <?= $stClass ?>"><?= $stLabel ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <tr class="bva-grand">
                            <td>All clients<?= $filterClientId !== null ? ' (filtered)' : '' ?></td>
                            <td class="num"><?= hrs_split($G['est_h'], $G['est_mh']) ?></td>
                            <td class="num"><?= hrs_split($G['act_h'], $G['act_mh']) ?></td>
                            <td class="num"><?= (($G['act_h'] - $G['est_h']) > 0 ? '+' : '') . fmt_hours($G['act_h'] - $G['est_h']) ?></td>
                            <td class="num"><?= fmt_money($grandEstCost) ?></td>
                            <td class="num"><?= fmt_money($grandActCost) ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
