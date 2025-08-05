<?php
// Include the config.php file to access Hootsuite credentials
include('config.php');

// Build the Hootsuite OAuth URL using the defined constants from config.php
$auth_url = "https://hootsuite.com/oauth/authorize?client_id=" . HOOTSUITE_CLIENT_ID . "&redirect_uri=" . urlencode(HOOTSUITE_REDIRECT_URI) . "&response_type=code&scope=offline";

// Redirect the user to Hootsuite's authorization page
header('Location: ' . $auth_url);
exit;
?>
