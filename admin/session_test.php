<?php
require_once __DIR__.'/../lib/auth.php';

// Start session
ensure_session();

echo "<h2>Session Debug Information</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "Session Cookie Parameters:\n";
print_r(session_get_cookie_params());
echo "\n\nSession Data:\n";
print_r($_SESSION);
echo "\n\nServer Info:\n";
echo "Domain: " . $_SERVER['HTTP_HOST'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set') . "\n";
echo "\n\nCookies:\n";
print_r($_COOKIE);
echo "</pre>";

echo "<hr>";
echo "<a href='login.php'>Go to Login</a> | ";
echo "<a href='index.php'>Go to Dashboard</a> | ";
echo "<a href='session_test.php?action=set'>Set Test Session</a> | ";
echo "<a href='session_test.php?action=clear'>Clear Session</a>";

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'set') {
        $_SESSION['test'] = 'Session is working!';
        $_SESSION['time'] = date('Y-m-d H:i:s');
        echo "<p class='text-success'>Session test data set!</p>";
    } elseif ($_GET['action'] === 'clear') {
        session_destroy();
        echo "<p class='text-danger'>Session destroyed!</p>";
    }
}
?>