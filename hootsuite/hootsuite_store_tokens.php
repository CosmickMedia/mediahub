<?php
session_start();

require_once __DIR__.'/../lib/settings.php';

// Store the access token and refresh token in both session and persistent settings
if (isset($_GET['access_token']) && isset($_GET['refresh_token'])) {
    $access  = $_GET['access_token'];
    $refresh = $_GET['refresh_token'];

    // Store in session for immediate use
    $_SESSION['access_token'] = $access;
    $_SESSION['refresh_token'] = $refresh;

    // Persist tokens in settings table so they are available for cron jobs
    set_setting('hootsuite_access_token', $access);
    set_setting('hootsuite_refresh_token', $refresh);
    set_setting('hootsuite_token_last_refresh', date('Y-m-d H:i:s'));

    echo "Tokens stored successfully!";
} else {
    echo "No tokens received.";
}
?>
