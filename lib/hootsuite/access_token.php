<?php
session_start();

include __DIR__ . '/../../config.php'; // Include config to get credentials

// Step 1: Check if the access token is already available in the session
if (isset($_SESSION['access_token'])) {
    echo "Access Token: " . $_SESSION['access_token'];
} else {
    // If no access token, redirect to authentication flow
    header('Location: test_auth.php');
}
?>
