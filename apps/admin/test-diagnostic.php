<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. PHP is working<br>";

echo "2. Current directory: " . __DIR__ . "<br>";

echo "3. Checking if auth_include.php exists: ";
if (file_exists(__DIR__ . '/../../auth/include/auth_include.php')) {
    echo "YES<br>";
} else {
    echo "NO - FILE MISSING!<br>";
}

echo "4. Checking if app_config.php exists: ";
if (file_exists(__DIR__ . '/../../config/app_config.php')) {
    echo "YES<br>";
} else {
    echo "NO - FILE MISSING!<br>";
}

echo "5. Attempting to load auth_include.php...<br>";
require __DIR__ . '/../../auth/include/auth_include.php';
echo "6. Auth loaded successfully!<br>";

echo "7. Checking url() function: ";
if (function_exists('url')) {
    echo "EXISTS - " . url('/test') . "<br>";
} else {
    echo "MISSING!<br>";
}

echo "8. All checks passed!";
