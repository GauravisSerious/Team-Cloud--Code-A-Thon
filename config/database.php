<?php
/**
 * Database Configuration
 * This file contains database connection settings for LocalConnect
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Default XAMPP username
define('DB_PASS', '');            // Default XAMPP password (empty)
define('DB_NAME', 'localconnect');

// Create database connection
function connect_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Close database connection
function close_db($conn) {
    $conn->close();
}

// Sanitize user input to prevent SQL injection
function sanitize_input($conn, $data) {
    return $conn->real_escape_string($data);
} 