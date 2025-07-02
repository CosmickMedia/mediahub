<?php
$config = require __DIR__.'/../config.php';
$params = [
    'client_id' => $config['google_oauth']['client_id'],
    'redirect_uri' => $config['google_oauth']['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email',
    'include_granted_scopes' => 'true',
    'prompt' => 'select_account',
];
$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $url);
exit;
?>

