<?php
session_start();

// Step 1: Store the access token and refresh token
if (isset($_GET['access_token']) && isset($_GET['refresh_token'])) {
    $_SESSION['access_token'] = $_GET['access_token']; // Store the access token
    $_SESSION['refresh_token'] = $_GET['refresh_token']; // Store the refresh token

    echo "Tokens stored successfully!";
} else {
    echo "No tokens received.";
}
?>
