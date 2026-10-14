<?php
/**
 * Post any High Fives still waiting for the team's Zoom channel: ones whose
 * immediate post failed (Zoom down, timeout), or that were left mid-send.
 * Run from cron every 15 minutes, as the web user:
 *
 *   php scripts/post_high_fives.php /etc/pulse/zoom-high-fives.env
 *
 * The credentials file is the one nginx names in PULSE_ZOOM_HIGH_FIVES_FILE;
 * with no argument that variable is used. No file, nothing posted.
 * Exit status: 0 = nothing failed this run, 1 = something did (cron mails it).
 */

require_once __DIR__ . '/cli_only.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/high_fives_zoom.php';

$zoom = high_fives_zoom_config($argv[1] ?? null);
if ($zoom === null) {
    fwrite(STDERR, "Zoom is not set up (no readable credentials file); nothing posted.\n");
    exit(0);
}

$pdo = get_db_connection();
$failed = 0;
foreach (high_fives_zoom_due($pdo) as $id) {
    $result = high_fives_zoom_post($pdo, $id, $zoom);
    echo "High Five $id: $result\n";
    if ($result === 'failed') $failed++;
}
exit($failed ? 1 : 0);
