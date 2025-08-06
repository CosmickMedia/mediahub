<?php
// Launches the OAuth flow for Hootsuite using credentials stored in the settings table.

require_once __DIR__ . '/../settings.php';

// Retrieve stored client details
$client_id    = get_setting('hootsuite_client_id');
$redirect_uri = get_setting('hootsuite_redirect_uri');

if (!$client_id || !$redirect_uri) {
    die('Error: Hootsuite client ID or redirect URI not configured.');
}

// Build the Hootsuite authorization URL
$auth_url = 'https://platform.hootsuite.com/oauth2/auth?client_id=' . urlencode($client_id)
    . '&redirect_uri=' . urlencode($redirect_uri)
    . '&response_type=code&scope=offline';

// Redirect user to Hootsuite for authorization
header('Location: ' . $auth_url);
exit;
?>

