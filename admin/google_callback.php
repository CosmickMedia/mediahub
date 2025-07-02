<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';

$config = require __DIR__.'/../config.php';

if (!isset($_GET['code'])) {
    exit('Missing code');
}
$code = $_GET['code'];

$tokenParams = [
    'code' => $code,
    'client_id' => $config['google_oauth']['client_id'],
    'client_secret' => $config['google_oauth']['client_secret'],
    'redirect_uri' => $config['google_oauth']['redirect_uri'],
    'grant_type' => 'authorization_code',
];
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($tokenParams),
]);
$resp = curl_exec($ch);
if (curl_errno($ch)) {
    exit('Curl error');
}
$data = json_decode($resp, true);
$accessToken = $data['access_token'] ?? null;
if (!$accessToken) {
    exit('Token error');
}

$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
]);
$userResp = curl_exec($ch);
if (curl_errno($ch)) {
    exit('Curl error');
}
$userInfo = json_decode($userResp, true);
$email = $userInfo['email'] ?? null;

if ($email && login_with_google_email($email)) {
    header('Location: index.php');
    exit;
} else {
    exit('No admin user for this Google account');
}
?>

