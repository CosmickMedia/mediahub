<?php
session_start();

require_once __DIR__.'/../lib/settings.php';

// Try to get the access token from session first, then fall back to settings
$token = $_SESSION['access_token'] ?? get_setting('hootsuite_access_token');

if ($token) {
    // Populate session so other scripts can reuse it
    $_SESSION['access_token'] = $token;
    echo "Access Token: " . $token;
} else {
    // If no access token, redirect to admin login to initiate OAuth
    header('Location: ../admin/hootsuite_login.php');
    exit;
}
?>
