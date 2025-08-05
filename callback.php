<?php
// Include the config.php file to access Hootsuite credentials
include('config.php');

// Step 1: Capture the authorization code
if (isset($_GET['code'])) {
    // Authorization code sent by Hootsuite
    $authorization_code = $_GET['code'];

    // Step 2: Exchange Authorization Code for Access Token
    $token_url = "https://hootsuite.com/oauth/token";
    $data = array(
        'grant_type' => 'authorization_code',
        'code' => $authorization_code,
        'redirect_uri' => HOOTSUITE_REDIRECT_URI, // Using the redirect URI from config
        'client_id' => HOOTSUITE_CLIENT_ID,       // Using the client ID from config
        'client_secret' => HOOTSUITE_CLIENT_SECRET // Using the client secret from config
    );

    // Make POST request to get the access token
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded",
            'content' => http_build_query($data)
        )
    );
    $context = stream_context_create($options);
    $response = file_get_contents($token_url, false, $context);

    // Decode the response to get access token
    $token_data = json_decode($response, true);
    $access_token = $token_data['access_token'];

    // Step 3: Use Access Token to query content schedule
    $schedule_url = "https://api.hootsuite.com/v1/messages/schedule";
    $headers = array(
        "Authorization: Bearer $access_token"
    );

    // Initialize cURL to send request to the content schedule endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $schedule_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    // Step 4: Parse and display the result (scheduled posts)
    $schedule_data = json_decode($result, true);
    echo "<pre>";
    print_r($schedule_data); // This will show the scheduled posts in raw format
    echo "</pre>";
}
?>
