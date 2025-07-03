<?php
// Debug file to check authentication issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Authentication Debug</h2>";
echo "<pre>";

// Test 1: Raw session check
session_start();
echo "1. Raw Session Check:\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: ";
print_r($_SESSION);
echo "\n";

// Test 2: Include auth and check
echo "\n2. After including auth.php:\n";
require_once __DIR__.'/../lib/auth.php';
echo "is_logged_in(): " . (is_logged_in() ? 'true' : 'false') . "\n";
echo "Session after ensure_session():\n";
ensure_session();
print_r($_SESSION);

// Test 3: Check file paths
echo "\n3. File Paths:\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "Auth file exists: " . (file_exists(__DIR__.'/../lib/auth.php') ? 'Yes' : 'No') . "\n";
echo "DB file exists: " . (file_exists(__DIR__.'/../lib/db.php') ? 'Yes' : 'No') . "\n";

// Test 4: Try require_login
echo "\n4. Testing require_login (this should NOT redirect if logged in):\n";
echo "About to call require_login()...\n";

// Temporarily override header to prevent redirect
$headers_sent = headers_sent();
if (!$headers_sent) {
    // This will show us what require_login is trying to do
    require_login();
    echo "require_login() passed - user is logged in!\n";
} else {
    echo "Headers already sent, cannot test require_login\n";
}

echo "</pre>";

echo "<hr>";
echo "<p>Navigation: ";
echo "<a href='index.php'>Dashboard</a> | ";
echo "<a href='uploads.php'>Uploads</a> | ";
echo "<a href='stores.php'>Stores</a> | ";
echo "<a href='login.php'>Login</a>";
echo "</p>";
?>