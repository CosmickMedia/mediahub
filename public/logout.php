<?php
/**
 * Public side logout - handles store user logout
 */

// Prevent caching of this page
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start session with the correct name for public side
if (session_status() === PHP_SESSION_NONE) {
    session_name('cm_public_session');
    session_start();
}

// Get the session name for cookie deletion
$sessionName = session_name();

// Clear all session data
$_SESSION = array();
session_unset();

// Destroy the session on the server
session_destroy();

// Delete the session cookie - must match how it was originally set
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

// Try deleting with multiple parameter combinations to ensure it works
setcookie($sessionName, '', [
    'expires' => 1,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Also try without secure flag in case cookie was set differently
setcookie($sessionName, '', 1, '/');

// Also delete the remember cookie
setcookie('cm_public_remember', '', [
    'expires' => 1,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
setcookie('cm_public_remember', '', 1, '/');

// Redirect to login page
header('Location: index.php');
exit;
