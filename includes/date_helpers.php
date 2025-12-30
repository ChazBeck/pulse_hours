<?php
/**
 * Date Helper Functions
 * 
 * Centralized date and week calculation utilities for the PluseHours application.
 * Used for pulse check-ins, hours tracking, and weekly summaries.
 */

// Prevent redeclaration if this file is included multiple times
if (!function_exists('get_year_week')) {

/**
 * Get the year-week format for a given timestamp
 * Uses ISO-8601 week date system
 * 
 * @param int $timestamp Unix timestamp
 * @return string Format: "YYYY-WW" (e.g., "2024-52")
 */
function get_year_week($timestamp) {
    return date('o-W', $timestamp); // 'o' is ISO-8601 year, 'W' is ISO-8601 week number
}

}

if (!function_exists('get_week_dates')) {

/**
 * Get the start and end date of a week
 * 
 * @param string $year_week Format: "YYYY-WW"
 * @return array ['start' => DateTime, 'end' => DateTime]
 */
function get_week_dates($year_week) {
    list($year, $week) = explode('-', $year_week);
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $start = clone $dto;
    $end = clone $dto;
    $end->modify('+6 days');
    return ['start' => $start, 'end' => $end];
}

}

if (!function_exists('format_week_range')) {

/**
 * Format week date range for display
 * 
 * @param string $year_week Format: "YYYY-WW"
 * @return string Format: "Mon DD - Mon DD, YYYY" (e.g., "Dec 23 - Dec 29, 2024")
 */
function format_week_range($year_week) {
    $dates = get_week_dates($year_week);
    return $dates['start']->format('M j') . ' - ' . $dates['end']->format('M j, Y');
}

}
