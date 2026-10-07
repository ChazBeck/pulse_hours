<?php
// Test-only: run one confirm in its own process (its own DB connection), so the
// race test can interleave it with an edit held open on another connection.
require __DIR__ . '/../../config/db_config.php';
require __DIR__ . '/../../includes/hours_drafts.php';
echo json_encode(pulse_drafts_confirm(get_db_connection(), (int) $argv[1], (int) $argv[2]));
