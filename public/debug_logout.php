<?php
/**
 * Debug logout - shows exactly what's happening with session/cookies
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='font-family: monospace; background: #1a1a2e; color: #0f0; padding: 20px; margin: 0; min-height: 100vh;'>";
echo "=== LOGOUT DIAGNOSTIC ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Show ALL cookies the browser is sending
echo "=== ALL COOKIES FROM BROWSER ===\n";
if (empty($_COOKIE)) {
    echo "(No cookies sent)\n";
} else {
    foreach ($_COOKIE as $name => $value) {
        echo "  $name = " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . "\n";
    }
}

echo "\n=== SESSION STATUS BEFORE START ===\n";
echo "session_status(): " . session_status() . " (0=disabled, 1=none, 2=active)\n";

// Start session the same way as the app does
echo "\n=== STARTING SESSION ===\n";
session_name('cm_public_session');
echo "Session name set to: cm_public_session\n";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "session_start() called\n";
}

echo "session_id(): " . session_id() . "\n";
echo "session_status(): " . session_status() . "\n";

echo "\n=== SESSION DATA ===\n";
if (empty($_SESSION)) {
    echo "(Session is empty)\n";
} else {
    foreach ($_SESSION as $key => $value) {
        if (is_array($value)) {
            echo "  $key = [array]\n";
        } else {
            echo "  $key = " . substr((string)$value, 0, 50) . "\n";
        }
    }
}

$isLoggedIn = isset($_SESSION['store_id']) && !empty($_SESSION['store_id']);
echo "\n=== LOGIN STATUS ===\n";
echo "store_id in session: " . ($isLoggedIn ? "YES ({$_SESSION['store_id']})" : "NO") . "\n";

// If action=logout, perform the logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    echo "\n=== PERFORMING LOGOUT ===\n";

    $sessionName = session_name();
    $sessionId = session_id();
    echo "Destroying session: $sessionName (ID: $sessionId)\n";

    // Clear session
    $_SESSION = array();
    session_unset();
    echo "Session variables cleared\n";

    // Destroy session
    session_destroy();
    echo "session_destroy() called\n";

    // Delete cookies
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    // Delete session cookie
    setcookie($sessionName, '', 1, '/');
    setcookie($sessionName, '', 1, '/public/');
    setcookie($sessionName, '', 1, '/public');
    echo "Session cookie deletion headers sent for: $sessionName\n";

    // Delete remember cookie
    setcookie('cm_public_remember', '', 1, '/');
    echo "Remember cookie deletion header sent\n";

    echo "\n=== RESPONSE HEADERS ===\n";
    foreach (headers_list() as $header) {
        echo "  $header\n";
    }

    echo "\n=== NEXT STEPS ===\n";
    echo "<a href='debug_logout.php' style='color: #0ff;'>Click here to check if logout worked</a>\n";
    echo "<a href='index.php' style='color: #0ff;'>Go to index.php</a>\n";
} else {
    echo "\n=== ACTIONS ===\n";
    if ($isLoggedIn) {
        echo "<a href='debug_logout.php?action=logout' style='color: #ff0;'>CLICK HERE TO TEST LOGOUT</a>\n";
    } else {
        echo "Not logged in!\n";
        echo "<a href='index.php' style='color: #0ff;'>Go to index.php to login first</a>\n";
    }
    echo "\n<a href='debug_logout.php' style='color: #0ff;'>Refresh this page</a>\n";
}

echo "</pre>";
