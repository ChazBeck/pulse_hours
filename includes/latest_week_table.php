<?php
/**
 * Latest-week table shown under a Pulse & Workload chart.
 * Expects: $latestRows (name/value rows), $latestAvg, $latestMetric, $latestWeekLabel.
 */
if (!isset($latestRows, $latestMetric, $latestWeekLabel)) {
    return;
}
?>
<div class="latest-week table-container">
    <table>
        <caption><?= htmlspecialchars($latestWeekLabel) ?></caption>
        <thead>
            <tr>
                <th scope="col">Team Member</th>
                <th scope="col" class="rating"><?= htmlspecialchars($latestMetric) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($latestRows)): ?>
                <tr><td colspan="2">No check-ins in these weeks.</td></tr>
            <?php else: ?>
                <?php foreach ($latestRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td class="rating"><?= format_rating($row['value']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="team-average">
                <td>Team Average</td>
                <td class="rating"><?= format_rating($latestAvg) ?></td>
            </tr>
        </tbody>
    </table>
</div>
