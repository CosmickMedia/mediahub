<?php
require_once __DIR__.'/../lib/settings.php';

$client_id = get_setting('hootsuite_client_id');
$redirect_uri = get_setting('hootsuite_redirect_uri');

if (!$client_id || !$redirect_uri) {
    exit('Hootsuite client ID or redirect URI not configured.');
}

$params = [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'offline',
];

header('Location: https://platform.hootsuite.com/oauth2/auth?' . http_build_query($params));
exit;
