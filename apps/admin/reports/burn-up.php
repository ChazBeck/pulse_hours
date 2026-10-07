<?php
/**
 * Reports - Burn-up chart
 *
 * Cumulative hours logged per week for a client, climbing toward the budgeted
 * (estimated) total. Shows weekly increments (bars), the cumulative burn-up line,
 * and a flat budget line, so you can see the pace and how close you are to the
 * budget. Marcy's cumulative burn + Marcy budget are included as toggleable lines.
 */

require __DIR__ . '/../../../sso/sso_include.php';
auth_init();
pulse_require_admin();

require_once __DIR__ . '/../../../includes/date_helpers.php';
require_once __DIR__ . '/../../../includes/rates.php';

$pdo = get_db_connection();
// $user provided by sso_include.php

// Clients that have a budget set (so the selector defaults somewhere meaningful).
$clients = $pdo->query("
    SELECT c.id, c.name, COALESCE(SUM(t.estimated_hours),0) AS budget
    FROM clients c
    LEFT JOIN tasks t ON t.client_id = c.id
    WHERE c.active = 1
    GROUP BY c.id, c.name
    ORDER BY budget DESC, c.name ASC
")->fetchAll();

// Selected client: querystring, else the first client with a budget, else first.
$selClientId = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? intval($_GET['client_id']) : null;
if ($selClientId === null) {
    foreach ($clients as $c) { if ($c['budget'] > 0) { $selClientId = (int) $c['id']; break; } }
    if ($selClientId === null && $clients) { $selClientId = (int) $clients[0]['id']; }
}

// Budget totals for the selected client.
$bud = $pdo->prepare("SELECT COALESCE(SUM(estimated_hours),0) AS total, COALESCE(SUM(estimated_hours_marcy),0) AS marcy FROM tasks WHERE client_id = ?");
$bud->execute([$selClientId]);
$budRow = $bud->fetch();
$budgetTotal = (float) $budRow['total'];
$budgetMarcy = (float) $budRow['marcy'];

// Stable per-person colors: assign a palette slot to every user who logs hours
// anywhere (ordered by id) so a person keeps the same color across clients.
// Medium saturation — present but not bright — and kept off blue/red/purple so
// they don't clash with the cumulative / budget / Marcy-budget lines.
$mutedPalette = ['#4f9d8a', '#8aa152', '#d08a54', '#b56f92', '#5f86b3', '#c2a53f', '#8f77b0', '#cf6f6f'];
$userColor = [];
$allUsers = $pdo->query("
    SELECT DISTINCT u.id FROM users u JOIN hours h ON h.user_id = u.id ORDER BY u.id ASC
")->fetchAll(PDO::FETCH_COLUMN);
foreach ($allUsers as $i => $uid) { $userColor[(int) $uid] = $mutedPalette[$i % count($mutedPalette)]; }

// Weekly logged hours broken down by team member for the selected client.
$wk = $pdo->prepare("
    SELECT h.year_week AS yw, u.id AS uid, u.first_name AS fname, u.email AS email,
           ROUND(SUM(h.hours), 2) AS hrs
    FROM hours h
    JOIN tasks t ON h.task_id = t.id
    LEFT JOIN users u ON h.user_id = u.id
    WHERE t.client_id = :cid
    GROUP BY h.year_week, u.id
    ORDER BY h.year_week ASC, u.id ASC
");
$wk->execute([':cid' => $selClientId]);
$weekly = [];           // yw => ['hrs'=>total, 'marcy'=>marcy total]
$weeklyByUser = [];     // uid => [yw => hrs]
$activeUsers = [];      // uid => first name (people who logged time for this client)
$marcyUid = null;
foreach ($wk->fetchAll() as $r) {
    $yw = $r['yw']; $uid = (int) $r['uid']; $hrs = (float) $r['hrs'];
    if (!isset($weekly[$yw])) { $weekly[$yw] = ['hrs' => 0.0, 'marcy' => 0.0]; }
    $weekly[$yw]['hrs'] += $hrs;
    if ($r['email'] === MARCY_EMAIL) { $weekly[$yw]['marcy'] += $hrs; $marcyUid = $uid; }
    $weeklyByUser[$uid][$yw] = $hrs;
    $activeUsers[$uid] = $r['fname'] ?: ('User ' . $uid);
}
ksort($activeUsers);
// Stack order for the burn-up: Marcy at the bottom so her band top reads directly
// against her budget line; everyone else stacks above.
$orderedUids = array_keys($activeUsers);
if ($marcyUid !== null) {
    $orderedUids = array_merge([$marcyUid], array_values(array_diff($orderedUids, [$marcyUid])));
}

// Engagement window from the client's budgeted projects (project-level dates):
// earliest start, latest end across projects that carry an estimate. Used to show
// the deadline/runway on the chart and weeks remaining. No ideal-pace line — work
// here is sprint-then-idle, so a linear even-burn assumption would mislead.
// NULL if the client's projects have no dates set.
$win = $pdo->prepare("
    SELECT MIN(p.start_date) AS start_date, MAX(p.end_date) AS end_date
    FROM projects p
    WHERE p.client_id = :cid
      AND p.start_date IS NOT NULL AND p.end_date IS NOT NULL
      AND EXISTS (SELECT 1 FROM tasks t WHERE t.project_id = p.id AND t.estimated_hours IS NOT NULL)
");
$win->execute([':cid' => $selClientId]);
$winRow = $win->fetch();
$engStart = $winRow['start_date'] ? new DateTime($winRow['start_date']) : null;
$engEnd   = $winRow['end_date']   ? new DateTime($winRow['end_date'])   : null;
$hasWindow = $engStart && $engEnd && $engEnd > $engStart;

// Build a continuous week axis. With an engagement window, span start-of-engagement
// to end-of-engagement (so the remaining runway to the deadline is visible); else
// first-activity week to the current week.
$labels = []; $incData = []; $cumData = []; $cumMarcy = [];
$budgetLine = []; $marcyBudgetLine = [];
$perUser = [];      // uid => weekly hours aligned to $labels (for the weekly chart)
$perUserCum = [];   // uid => cumulative hours aligned to $labels (for the burn-up stack)
$runUser = [];
foreach (array_keys($activeUsers) as $uid) { $perUser[$uid] = []; $perUserCum[$uid] = []; $runUser[$uid] = 0.0; }
$today = new DateTime('today');
if (!empty($weekly) || $hasWindow) {
    $firstYw = !empty($weekly) ? array_key_first($weekly) : date('o-W');
    $nowYw   = date('o-W');
    $startMon = get_week_dates($firstYw)['start'];
    if ($hasWindow) {
        $engStartMon = get_week_dates($engStart->format('o-W'))['start'];
        if ($engStartMon < $startMon) { $startMon = $engStartMon; }
    }
    $endMon = get_week_dates($nowYw)['start'];
    if ($hasWindow) {
        $engEndMon = get_week_dates($engEnd->format('o-W'))['start'];
        if ($engEndMon > $endMon) { $endMon = $engEndMon; }
    }
    if ($endMon < $startMon) { $endMon = clone $startMon; }

    $cursor = clone $startMon;
    $runTotal = 0.0; $runMarcy = 0.0;
    $guard = 0;
    while ($cursor <= $endMon && $guard < 520) {
        $yw  = $cursor->format('o-W');
        $inc = $weekly[$yw]['hrs']   ?? 0.0;
        $mh  = $weekly[$yw]['marcy'] ?? 0.0;
        $runTotal += $inc; $runMarcy += $mh;

        $labels[]     = $cursor->format('M j');
        $incData[]    = round($inc, 2);
        // Cumulative actual only through the current week; null afterwards (nothing logged yet).
        $isFuture = $cursor > $today;
        $cumData[]    = $isFuture ? null : round($runTotal, 2);
        $cumMarcy[]   = $isFuture ? null : round($runMarcy, 2);
        $budgetLine[] = $budgetTotal;
        $marcyBudgetLine[] = $budgetMarcy;
        foreach ($perUser as $uid => &$arr) {
            $wkHrs = round($weeklyByUser[$uid][$yw] ?? 0.0, 2);
            $arr[] = $wkHrs;
            $runUser[$uid] += $wkHrs;
            $perUserCum[$uid][] = $isFuture ? null : round($runUser[$uid], 2);
        }
        unset($arr);

        $cursor->modify('+7 days');
        $guard++;
    }
}

// Summary figures (no pace assumption — sprint-then-idle work).
$loggedTotal = 0.0;
foreach ($cumData as $v) { if ($v !== null) { $loggedTotal = $v; } }  // last non-null
$remaining   = max(0, $budgetTotal - $loggedTotal);
$pctComplete = $budgetTotal > 0 ? $loggedTotal / $budgetTotal * 100 : null;
$activeWeeks = count(array_filter($incData, function ($h) { return $h > 0; }));
$avgPerWeek  = $activeWeeks > 0 ? $loggedTotal / $activeWeeks : 0.0;

// Calendar time left to the deadline (not a pace projection — just weeks remaining).
$weeksRemaining = null; $endLabel = null;
if ($hasWindow) {
    $endLabel = $engEnd->format('M j, Y');
    $weeksRemaining = $today >= $engEnd ? 0 : (int) ceil(($engEnd->getTimestamp() - $today->getTimestamp()) / (7 * 86400));
}

$selClientName = '';
foreach ($clients as $c) { if ((int) $c['id'] === $selClientId) { $selClientName = $c['name']; break; } }

function fmt_h($n) { return rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Burn-up - Reports - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .bu-container { padding: 2rem; }
        .bu-header h1 { color: var(--text-primary); font-size: 2rem; margin-bottom: 0.5rem; }
        .bu-header p { color: var(--text-secondary); }
        .bu-controls { margin: 1.25rem 0; }
        .bu-controls label { display:block; font-size:0.8rem; color:var(--text-secondary); margin-bottom:0.25rem; }
        .bu-controls select { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; min-width: 240px; }
        .bu-summary { display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; }
        .bu-stat { background:white; border-radius:var(--border-radius); box-shadow:0 2px 4px rgba(0,0,0,0.08); padding:1.1rem 1.4rem; flex:1; min-width:150px; }
        .bu-stat .value { font-size:1.6rem; font-weight:700; color:var(--text-primary); }
        .bu-stat .label { font-size:0.75rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.03em; }
        .bu-chart-card { background:white; border-radius:var(--border-radius); box-shadow:0 2px 4px rgba(0,0,0,0.08); padding:1.5rem; }
        .bu-chart-title { font-size:1.05rem; color:var(--text-primary); margin:0 0 0.9rem; }
        .bu-legend { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:0.5rem; }
        .bu-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border:1px solid #d1d5db; border-radius:16px; background:white; font-size:0.8rem; color:#374151; cursor:pointer; user-select:none; }
        .bu-chip:hover { background:#f9fafb; }
        .bu-chip .sw { width:12px; height:12px; border-radius:3px; display:inline-block; }
        .bu-chip-off { opacity:0.4; text-decoration:line-through; }
        .bu-people { display:flex; flex-wrap:wrap; gap:12px; align-items:center; font-size:0.78rem; color:#6b7280; margin-bottom:0.9rem; }
        .bu-people .dot { width:9px; height:9px; border-radius:50%; display:inline-block; margin-right:4px; vertical-align:middle; }
        .bu-chart-wrap { position:relative; height:440px; }
        .empty-state { padding:3rem; text-align:center; color:#6b7280; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../_admin_nav.php'; ?>

    <main class="admin-content">
        <div class="bu-container">
            <div class="bu-header">
                <h1>Burn-up</h1>
                <p>Cumulative hours logged each week, climbing toward the budgeted total.</p>
            </div>

            <form method="GET" class="bu-controls">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" onchange="this.form.submit()">
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $selClientId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?><?= $c['budget'] > 0 ? ' — ' . fmt_h($c['budget']) . 'h budget' : ' (no budget)' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="bu-summary">
                <div class="bu-stat"><div class="value"><?= $budgetTotal > 0 ? fmt_h($budgetTotal) : '&mdash;' ?></div><div class="label">Budget hours</div></div>
                <div class="bu-stat"><div class="value"><?= fmt_h($loggedTotal) ?></div><div class="label">Logged to date</div></div>
                <div class="bu-stat"><div class="value"><?= $budgetTotal > 0 ? fmt_h($remaining) : '&mdash;' ?></div><div class="label">Remaining</div></div>
                <div class="bu-stat"><div class="value"><?= $pctComplete === null ? '&mdash;' : number_format($pctComplete, 0) . '%' ?></div><div class="label">Of budget</div></div>
                <?php if ($hasWindow): ?>
                <div class="bu-stat"><div class="value"><?= $weeksRemaining ?></div><div class="label">Weeks to deadline</div></div>
                <div class="bu-stat"><div class="value" style="font-size:1.15rem;"><?= htmlspecialchars($endLabel) ?></div><div class="label">Deadline</div></div>
                <?php else: ?>
                <div class="bu-stat"><div class="value"><?= fmt_h(round($avgPerWeek,1)) ?></div><div class="label">Avg hrs / active week</div></div>
                <?php endif; ?>
            </div>
            <?php if (!$hasWindow && $budgetTotal > 0): ?>
            <p style="font-size:0.85rem; color:#6b7280; margin:-0.75rem 0 1.25rem;">
                Tip: set start &amp; end dates on this client's projects in <a href="<?= url('/apps/admin/projects.php') ?>">Manage Projects</a> to show the deadline and weeks remaining on this chart.
            </p>
            <?php endif; ?>

            <?php if (empty($labels)): ?>
            <div class="bu-chart-card">
                <div class="empty-state">
                    <p>No hours logged yet for <?= htmlspecialchars($selClientName) ?>.</p>
                    <?php if ($budgetTotal <= 0): ?><p style="font-size:0.9rem;">This client also has no budget set — add estimates via <a href="<?= url('/apps/admin/budget-import.php') ?>">Budget Import</a>.</p><?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <?php
                // People color key markup, reused under each chart.
                $peopleKey = '<div class="bu-people">Team:';
                foreach ($activeUsers as $uid => $fname) {
                    $peopleKey .= '<span><span class="dot" style="background:' . ($userColor[$uid] ?? '#b4b2a9') . ';"></span>' . htmlspecialchars($fname) . '</span>';
                }
                $peopleKey .= '</div>';
                $marcySwatch = $userColor[$marcyUid] ?? '#9db9a4';
            ?>

            <!-- Chart 1: Burn-up (cumulative hours composed by person, single axis) -->
            <div class="bu-chart-card">
                <h3 class="bu-chart-title">Burn-up — cumulative hours toward budget</h3>
                <div id="bu-legend-cum" class="bu-legend">
                    <button type="button" class="bu-chip" data-role="cumulative"><span class="sw" style="background:#1e293b;"></span>Cumulative</button>
                    <button type="button" class="bu-chip" data-role="team"><span class="sw" style="background:#4f9d8a;"></span>Team hours</button>
                    <?php if ($marcyUid !== null): ?>
                    <button type="button" class="bu-chip" data-role="marcy"><span class="sw" style="background:<?= $marcySwatch ?>;"></span>Marcy</button>
                    <?php endif; ?>
                    <?php if ($budgetTotal > 0): ?>
                    <button type="button" class="bu-chip" data-role="budget"><span class="sw" style="background:#ef4444;"></span>Budget</button>
                    <?php endif; ?>
                    <?php if ($budgetMarcy > 0): ?>
                    <button type="button" class="bu-chip" data-role="marcyBudget"><span class="sw" style="background:#7c3aed;"></span>Marcy budget</button>
                    <?php endif; ?>
                </div>
                <?= $peopleKey ?>
                <div class="bu-chart-wrap"><canvas id="burnup"></canvas></div>
            </div>

            <!-- Chart 2: Weekly hours logged (single axis) -->
            <div class="bu-chart-card" style="margin-top:1.5rem;">
                <h3 class="bu-chart-title">Weekly hours logged</h3>
                <div id="bu-legend-wk" class="bu-legend">
                    <button type="button" class="bu-chip" data-role="team"><span class="sw" style="background:#94a3b8;"></span>Team hours</button>
                    <?php if ($marcyUid !== null): ?>
                    <button type="button" class="bu-chip" data-role="marcy"><span class="sw" style="background:<?= $marcySwatch ?>;"></span>Marcy</button>
                    <?php endif; ?>
                </div>
                <?= $peopleKey ?>
                <div class="bu-chart-wrap" style="height:300px;"><canvas id="weekly"></canvas></div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php if (!empty($labels)): ?>
    <script>
        // Chip toggles: each chip flips visibility of every dataset with its role,
        // so 'team' (the non-Marcy people) toggles as one group.
        function setupChips(sel, chart) {
            document.querySelectorAll(sel + ' .bu-chip').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    const role = chip.getAttribute('data-role');
                    const idxs = [];
                    chart.data.datasets.forEach(function (ds, i) { if (ds.role === role) idxs.push(i); });
                    if (!idxs.length) return;
                    const wasVisible = chart.isDatasetVisible(idxs[0]);
                    idxs.forEach(function (i) { chart.setDatasetVisibility(i, !wasVisible); });
                    chart.update();
                    chip.classList.toggle('bu-chip-off', wasVisible);
                });
            });
        }

        // ---- Chart 1: burn-up = stacked cumulative areas per person (Marcy at bottom) ----
        const burnChart = new Chart(document.getElementById('burnup').getContext('2d'), {
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    <?php foreach ($orderedUids as $uid): ?>
                    {
                        type: 'line',
                        label: <?= json_encode($activeUsers[$uid]) ?>,
                        role: '<?= $uid === $marcyUid ? 'marcy' : 'team' ?>',
                        data: <?= json_encode($perUserCum[$uid]) ?>,
                        borderColor: '<?= $userColor[$uid] ?? '#b4b2a9' ?>',
                        backgroundColor: '<?= $userColor[$uid] ?? '#b4b2a9' ?>D9',
                        fill: true,
                        stack: 'cum',
                        tension: 0.2,
                        pointRadius: 0,
                        borderWidth: 1,
                        spanGaps: false,
                        yAxisID: 'y'
                    },
                    <?php endforeach; ?>
                    {
                        type: 'line', label: 'Cumulative', role: 'cumulative',
                        data: <?= json_encode($cumData) ?>,
                        borderColor: '#1e293b', borderWidth: 3, pointRadius: 0,
                        fill: false, tension: 0.2, spanGaps: false, stack: 'cumTotal', yAxisID: 'y'
                    },
                    <?php if ($budgetTotal > 0): ?>
                    {
                        type: 'line', label: 'Budget (<?= fmt_h($budgetTotal) ?>h)', role: 'budget',
                        data: <?= json_encode($budgetLine) ?>,
                        borderColor: '#ef4444', borderDash: [6, 4], borderWidth: 2, pointRadius: 0,
                        fill: false, stack: 'budgetRef', yAxisID: 'y'
                    },
                    <?php endif; ?>
                    <?php if ($budgetMarcy > 0): ?>
                    {
                        type: 'line', label: 'Marcy budget (<?= fmt_h($budgetMarcy) ?>h)', role: 'marcyBudget',
                        data: <?= json_encode($marcyBudgetLine) ?>,
                        borderColor: '#7c3aed', borderDash: [6, 4], borderWidth: 2, pointRadius: 0,
                        fill: false, stack: 'marcyRef', yAxisID: 'y'
                    },
                    <?php endif; ?>
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { title: { display: true, text: 'Cumulative hours' }, beginAtZero: true, stacked: true,
                         suggestedMax: <?= $budgetTotal > 0 ? json_encode(ceil($budgetTotal * 1.05)) : 'undefined' ?> },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', callbacks: { title: (items) => 'Week of ' + items[0].label } }
                }
            }
        });
        setupChips('#bu-legend-cum', burnChart);

        // ---- Chart 2: weekly hours logged = stacked bars per person ----
        const weeklyChart = new Chart(document.getElementById('weekly').getContext('2d'), {
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    <?php foreach ($orderedUids as $uid): ?>
                    {
                        type: 'bar',
                        label: <?= json_encode($activeUsers[$uid]) ?>,
                        role: '<?= $uid === $marcyUid ? 'marcy' : 'team' ?>',
                        data: <?= json_encode($perUser[$uid]) ?>,
                        backgroundColor: '<?= $userColor[$uid] ?? '#b4b2a9' ?>D9',
                        stack: 'wk',
                        yAxisID: 'y'
                    },
                    <?php endforeach; ?>
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { title: { display: true, text: 'Hours logged that week' }, beginAtZero: true, stacked: true }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', callbacks: { title: (items) => 'Week of ' + items[0].label } }
                }
            }
        });
        setupChips('#bu-legend-wk', weeklyChart);
    </script>
    <?php endif; ?>
</body>
</html>
