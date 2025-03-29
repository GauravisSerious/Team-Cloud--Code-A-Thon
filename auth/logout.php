<?php
/**
 * Logout
 * Logs out the user and destroys the session
 */

// Include required files
require_once('../utils/helpers.php');

// Start session
start_session_if_not_exists();

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Destroy the session
session_unset();
session_destroy();

// Redirect to home page
redirect('../index.php');
?> 