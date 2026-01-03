<?php
/**
 * Simple Autoloader for Repository Classes
 * 
 * Include this file to automatically load repository classes.
 */

spl_autoload_register(function ($className) {
    // Base directory for repository classes
    $baseDir = __DIR__ . '/';
    
    // Check if the class is in the Repository namespace
    if (strpos($className, 'Repository') !== false) {
        $file = $baseDir . $className . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Also require the base repository if not already loaded
if (!class_exists('BaseRepository')) {
    require_once __DIR__ . '/BaseRepository.php';
}
