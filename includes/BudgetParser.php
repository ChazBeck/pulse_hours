<?php
/**
 * Parses a Veerless "Staff Rate Allocation" / "Reporting" budget worksheet into
 * a normalized structure the importer can map onto Pulse Hours tasks.
 *
 * The template (see the INTERNAL ONLY *.xlsx budgets) lays out:
 *   - a header block (Project Name, Client, Main Client Contact, dates) in col A/B
 *   - a table whose header row has "Projected Hours" in column C
 *   - line items grouped under phase names that appear in column A
 *   - "Phase Subtotal" / "Total" / "Estimated Cost" marker rows (skipped)
 *
 * Output grain is the PHASE, because that is what maps cleanly onto Pulse tasks
 * (the per-line staff allocation in the sheets is inconsistent / blended).
 */
require_once __DIR__ . '/XlsxReader.php';

class BudgetParser
{
    /** Column A values that mark a non-line-item row (never a phase, never hours). */
    private static function isMarkerRow($a, $b)
    {
        $a = trim((string) $a);
        $b = trim((string) $b);
        if ($a !== '' && preg_match('/^(Phase Subtotal|Total\b|Project Estimates|Project Quote)/i', $a)) {
            return true;
        }
        if ($b !== '' && preg_match('/^(Estimated Cost|Quoted Cost)/i', $b)) {
            return true;
        }
        return false;
    }

    /** Section/group headers that organize phases but carry no hours themselves. */
    private static function isSectionRow($a)
    {
        $a = trim((string) $a);
        return $a !== '' && preg_match('/^(FOCUS AREA|Project \d+\s*:|PROJECT MANAGEMENT ESTIMATES\s*$)/i', $a);
    }

    /**
     * Is this line allocated SOLELY to Marcy? (For the Marcy-vs-everyone-else
     * split.) Blended / multi-person lines that merely include Marcy count as
     * "everyone else" so the totals still reconcile.
     */
    private static function isMarcySole($staff)
    {
        $n = preg_replace('/[^a-z]/', '', strtolower((string) $staff));
        return $n === 'marcytwete' || $n === 'marcy';
    }

    /**
     * @param string $path Path to a budget .xlsx
     * @return array {
     *   client, project_name, contact, launch_date,
     *   phases: [ {name, hours, line_items:[{detail,hours,staff}]} ],
     *   total_hours
     * }
     */
    public static function parse($path)
    {
        $rows = XlsxReader::read($path);

        $meta = ['client' => '', 'project_name' => '', 'contact' => '', 'launch_date' => ''];
        $labelMap = [
            'Project Name'        => 'project_name',
            'Client'              => 'client',
            'Main Client Contact' => 'contact',
            'Project Launch Date' => 'launch_date',
        ];

        // Locate header row (column C == "Projected Hours") and pull header-block meta.
        $headerRow = null;
        foreach ($rows as $rowNum => $cells) {
            $a = trim($cells['A'] ?? '');
            if (isset($labelMap[$a]) && isset($cells['B'])) {
                $meta[$labelMap[$a]] = trim($cells['B']);
            }
            if ($headerRow === null && isset($cells['C']) && stripos($cells['C'], 'Projected Hours') !== false) {
                $headerRow = $rowNum;
            }
        }
        if ($headerRow === null) {
            throw new RuntimeException('Could not find the budget table header ("Projected Hours" column). Is this a Staff Rate Allocation budget?');
        }

        // Walk data rows, grouping projected hours by phase.
        $phases = [];
        $current = null; // index into $phases
        $maxRow = max(array_keys($rows));

        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $cells = $rows[$r] ?? [];
            $a = trim($cells['A'] ?? '');
            $b = trim($cells['B'] ?? '');
            $c = $cells['C'] ?? '';

            if (self::isMarkerRow($a, $b)) {
                continue;
            }
            if (self::isSectionRow($a)) {
                $current = null; // a following numbered phase will open the next phase
                continue;
            }

            // A non-empty column A that isn't a marker/section starts a new phase.
            if ($a !== '') {
                $phases[] = ['name' => $a, 'hours' => 0.0, 'marcy_hours' => 0.0, 'line_items' => []];
                $current = count($phases) - 1;
            }

            // Numeric column C on a real row = projected hours for a line item.
            if (is_numeric($c)) {
                $hours = (float) $c;
                if ($current === null) {
                    // Hours before any phase header — bucket under an "Unassigned" phase.
                    $phases[] = ['name' => $a !== '' ? $a : 'Unassigned', 'hours' => 0.0, 'marcy_hours' => 0.0, 'line_items' => []];
                    $current = count($phases) - 1;
                }
                $staff = trim($cells['D'] ?? '');
                $phases[$current]['hours'] += $hours;
                if (self::isMarcySole($staff)) {
                    $phases[$current]['marcy_hours'] += $hours;
                }
                $phases[$current]['line_items'][] = [
                    'detail' => $b,
                    'hours'  => $hours,
                    'staff'  => $staff,
                ];
            }
        }

        // Keep only phases that actually carry projected hours.
        $phases = array_values(array_filter($phases, function ($p) {
            return $p['hours'] > 0;
        }));

        $total = array_sum(array_map(function ($p) { return $p['hours']; }, $phases));
        $totalMarcy = array_sum(array_map(function ($p) { return $p['marcy_hours']; }, $phases));

        return [
            'client'            => $meta['client'],
            'project_name'      => $meta['project_name'],
            'contact'           => $meta['contact'],
            'launch_date'       => $meta['launch_date'],
            'phases'            => $phases,
            'total_hours'       => $total,
            'total_marcy_hours' => $totalMarcy,
        ];
    }
}
