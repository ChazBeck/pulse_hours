<?php
/**
 * Refuse to run outside the command line.
 *
 * Every maintenance script (scripts/, database/) requires this first. nginx also
 * denies those directories (deploy/nginx/pulse-hardening.conf); this is the
 * second lock, for a server where that include is missing.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
