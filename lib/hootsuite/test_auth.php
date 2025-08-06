<?php
// Debug: Check if config.php exists and is readable
$configPath = __DIR__ . '/../../config.php';
if (!file_exists($configPath)) {
    die('Error: config.php file not found');
}

// Include the config.php file to access Hootsuite credentials
include $configPath;

// Debug: Check if constants are defined
if (!defined('HOOTSUITE_CLIENT_ID')) {
    die('Error: HOOTSUITE_CLIENT_ID not defined in config.php');
}

if (!defined('HOOTSUITE_REDIRECT_URI')) {
    die('Error: HOOTSUITE_REDIRECT_URI not defined in config.php');
}

// Debug: Display the values (remove this in production!)
echo "Client ID: " . HOOTSUITE_CLIENT_ID . "<br>";
echo "Redirect URI: " . HOOTSUITE_REDIRECT_URI . "<br>";

// Build the Hootsuite OAuth URL using the defined constants from config.php
$auth_url = "https://platform.hootsuite.com/oauth2/auth?client_id=" . HOOTSUITE_CLIENT_ID . "&redirect_uri=" . urlencode(HOOTSUITE_REDIRECT_URI) . "&response_type=code&scope=offline";

// Debug: Show the constructed URL
echo "Redirecting to: " . $auth_url . "<br>";
echo "<a href='" . $auth_url . "'>Click here if not redirected</a>";

// Comment out the redirect temporarily for debugging
// header('Location: ' . $auth_url);
// exit;
?>