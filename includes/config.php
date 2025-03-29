<?php
/**
 * Database Configuration File
 * Contains database connection settings and constants
 */

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'localconnect');

// Site URLs and paths
define('BASE_URL', 'http://localhost/LocalConnect/');
define('ASSETS_URL', BASE_URL . 'assets/');

// Other configuration constants
define('ITEMS_PER_PAGE', 12);
define('ENABLE_DEBUG', true);

// Error reporting
if (ENABLE_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 