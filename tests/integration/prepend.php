<?php
// Test-only: point db_config.php's constants at the throwaway database.
define('DB_HOST', getenv('TEST_DB_HOST'));
define('DB_USER', 'root');
define('DB_PASS', 'test');
define('DB_NAME', 'plusehours'); // setup_database.sql creates and USEs this name itself
