<?php
// START SESSION AT THE VERY BEGINNING
session_start();

// Load Hootsuite credentials from database settings
require_once __DIR__.'/lib/settings.php';
$client_id = get_setting('hootsuite_client_id');
$client_secret = get_setting('hootsuite_client_secret');
$redirect_uri = get_setting('hootsuite_redirect_uri');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Step 1: Capture the authorization code
if (isset($_GET['code'])) {
    // Authorization code sent by Hootsuite
    $authorization_code = $_GET['code'];

    echo "Authorization code received: " . htmlspecialchars($authorization_code) . "<br><br>";

    // Step 2: Exchange Authorization Code for Access Token
    $token_url = "https://platform.hootsuite.com/oauth2/token";
    $data = array(
        'grant_type' => 'authorization_code',
        'code' => $authorization_code,
        'redirect_uri' => $redirect_uri,
        'client_id' => $client_id,
        'client_secret' => $client_secret
    );

    // Make POST request to get the access token using cURL
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
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        die("cURL Error: " . $curl_error);
    }

    echo "Token Exchange HTTP Response Code: " . $http_code . "<br>";
    echo "Token Exchange Response: <pre>" . htmlspecialchars($response) . "</pre><br>";

    // Decode the response to get access token
    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        $access_token = $token_data['access_token'];

        // Store in session for immediate use
        $_SESSION['access_token'] = $access_token;
        if (isset($token_data['refresh_token'])) {
            $_SESSION['refresh_token'] = $token_data['refresh_token'];
        }

        // Persist tokens in settings for long term storage
        set_setting('hootsuite_access_token', $access_token);
        if (isset($token_data['refresh_token'])) {
            set_setting('hootsuite_refresh_token', $token_data['refresh_token']);
        }
        set_setting('hootsuite_token_last_refresh', date('Y-m-d H:i:s'));

        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✅ Access token received and stored!</strong><br>";
        echo "Session ID: " . session_id() . "<br>";
        echo "Access Token (first 20 chars): " . substr($access_token, 0, 20) . "...<br>";
        if (isset($_SESSION['refresh_token'])) {
            echo "Refresh Token (first 20 chars): " . substr($_SESSION['refresh_token'], 0, 20) . "...<br>";
        }
        echo "</div>";

        // Step 3: Test the access token with a simple API call
        $test_url = "https://platform.hootsuite.com/v1/me";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $access_token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "API Test HTTP Response Code: " . $http_code . "<br>";
        echo "API Test Response: <pre>" . htmlspecialchars($result) . "</pre>";

        // Add links to other pages
        echo "<div style='background: #cfe2ff; border: 1px solid #b6d4fe; padding: 10px; margin: 20px 0;'>";
        echo "<h3>Next Steps:</h3>";
        echo "<a href='lib/hootsuite/get_scheduled_posts.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; margin: 5px;'>View Scheduled Posts</a>";
        echo "<a href='lib/hootsuite/access_token.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; margin: 5px;'>Check Access Token</a>";
        echo "</div>";

    } else {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px;'>";
        echo "Error: No access token received<br>";
        echo "Full response: <pre>" . print_r($token_data, true) . "</pre>";
        echo "</div>";
    }
} elseif (isset($_GET['error'])) {
    // Handle error response from Hootsuite
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px;'>";
    echo "Error from Hootsuite: " . htmlspecialchars($_GET['error']) . "<br>";
    if (isset($_GET['error_description'])) {
        echo "Error Description: " . htmlspecialchars($_GET['error_description']) . "<br>";
    }
    echo "</div>";
} else {
    echo "No authorization code or error received. Please start from <a href='lib/hootsuite/test_auth.php'>test_auth.php</a>";
}

// Debug: Show current session contents
echo "<div style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin-top: 20px;'>";
echo "<h4>Debug - Current Session Contents:</h4>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "</div>";
?>