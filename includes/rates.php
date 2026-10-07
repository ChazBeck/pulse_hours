<?php
/**
 * Billing rate constants + cost helpers for the budget/cost views.
 *
 * Simplification: Marcy Twete is the senior/principal rate; everyone else is the
 * standard rate. Real client budgets sometimes use other/blended rates, but for
 * the "Marcy vs everyone else" cost view these two tiers are what we apply.
 * Change the numbers here to re-price every report.
 */

if (!defined('MARCY_EMAIL'))    define('MARCY_EMAIL', 'marcy@veerless.com');
if (!defined('MARCY_RATE'))     define('MARCY_RATE', 300);   // $/hr, senior
if (!defined('STANDARD_RATE'))  define('STANDARD_RATE', 150); // $/hr, everyone else

/**
 * Cost of a chunk of work given how many hours are Marcy's vs everyone else's.
 *
 * @param float $marcyHours  hours billed at the senior rate
 * @param float $otherHours  hours billed at the standard rate
 * @return float dollars
 */
function rate_cost($marcyHours, $otherHours) {
    return ((float) $marcyHours) * MARCY_RATE + ((float) $otherHours) * STANDARD_RATE;
}

/** Format a dollar amount as $#,### (no cents). */
function fmt_money($n) {
    return '$' . number_format((float) $n, 0);
}
