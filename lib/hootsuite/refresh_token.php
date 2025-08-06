<?php
session_start();
include __DIR__ . '/../../config.php';

if (!isset($_SESSION['refresh_token'])) {
    die("No refresh token found. Please authenticate again.");
}

$refresh_token = $_SESSION['refresh_token'];

// Exchange refresh token for new access token
$token_url = "https://platform.hootsuite.com/oauth2/token";
$data = array(
    'grant_type' => 'refresh_token',
    'refresh_token' => $refresh_token,
    'client_id' => HOOTSUITE_CLIENT_ID,
    'client_secret' => HOOTSUITE_CLIENT_SECRET
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded'
));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $token_data = json_decode($response, true);

    // Update session with new tokens
    $_SESSION['access_token'] = $token_data['access_token'];
    if (isset($token_data['refresh_token'])) {
        $_SESSION['refresh_token'] = $token_data['refresh_token'];
    }

    echo "Token refreshed successfully!<br>";
    echo "New access token is valid for " . $token_data['expires_in'] . " seconds.<br>";
} else {
    echo "Error refreshing token. HTTP Code: " . $http_code . "<br>";
    echo "Response: " . $response;
}
?>