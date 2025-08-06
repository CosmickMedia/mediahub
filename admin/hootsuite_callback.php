<?php
session_start();
require_once __DIR__.'/../lib/settings.php';

$client_id = get_setting('hootsuite_client_id');
$client_secret = get_setting('hootsuite_client_secret');
$redirect_uri = get_setting('hootsuite_redirect_uri');

if (!isset($_GET['code'])) {
    exit('Missing authorization code');
}

$code = $_GET['code'];

$ch = curl_init('https://platform.hootsuite.com/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
    ]),
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $err = curl_error($ch);
    curl_close($ch);
    header('Location: settings.php?hootsuite_token_error=' . urlencode($err) . '&active_tab=calendar');
    exit;
}
curl_close($ch);
$data = json_decode($response, true);
$access = $data['access_token'] ?? null;
$refresh = $data['refresh_token'] ?? null;
if ($access) {
    set_setting('hootsuite_access_token', $access);
    if ($refresh) {
        set_setting('hootsuite_refresh_token', $refresh);
    }
    set_setting('hootsuite_token_last_refresh', date('Y-m-d H:i:s'));
    $_SESSION['access_token'] = $access;
    if ($refresh) {
        $_SESSION['refresh_token'] = $refresh;
    }
    header('Location: settings.php?hootsuite_token_saved=1&active_tab=calendar');
    exit;
} else {
    $err = $data['error'] ?? 'Token error';
    header('Location: settings.php?hootsuite_token_error=' . urlencode($err) . '&active_tab=calendar');
    exit;
}
