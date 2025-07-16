<?php
// complete_auth_flow.php
$client_id = '51d0a435-fb15-4ed7-90dd-90a690ed4b89';
$client_secret = 'LTLN46fg866k';

// Step 1: Generate the authorization URL
$redirect_uri = 'https://www.getpostman.com/oauth2/callback';
$state = 'test123';

$auth_url = 'https://platform.hootsuite.com/oauth2/auth?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => 'offline',
        'state' => $state
    ]);

echo "=== STEP 1: AUTHORIZE ===\n";
echo "Open this URL in your browser:\n\n";
echo $auth_url . "\n\n";
echo "After logging in and authorizing:\n";
echo "1. You'll be redirected to a Postman page\n";
echo "2. Look at the URL - it will contain: ?code=XXXXXXX&state=test123\n";
echo "3. Copy the code value\n\n";

// Step 2: Exchange code for token
echo "=== STEP 2: EXCHANGE CODE ===\n";
echo "Enter the code from the URL: ";
$code = trim(fgets(STDIN));

if (!empty($code)) {
    echo "\nExchanging code for access token...\n";

    $ch = curl_init('https://platform.hootsuite.com/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri
    ]));
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $token_data = json_decode($response, true);
        $access_token = $token_data['access_token'];
        $refresh_token = $token_data['refresh_token'] ?? '';

        echo "\n✓ SUCCESS! Token obtained\n";
        echo "Access Token: " . substr($access_token, 0, 30) . "...\n";
        echo "Refresh Token: " . substr($refresh_token, 0, 30) . "...\n";
        echo "Expires in: " . $token_data['expires_in'] . " seconds\n\n";

        // Save tokens to file for later use
        file_put_contents('tokens.json', json_encode($token_data));
        echo "Tokens saved to tokens.json\n\n";

        // Step 3: Fetch scheduled posts
        echo "=== STEP 3: FETCHING SCHEDULED POSTS ===\n";

        $ch = curl_init('https://platform.hootsuite.com/v1/messages?state=scheduled');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json'
        ]);

        $posts_response = curl_exec($ch);
        $posts_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($posts_http_code == 200) {
            $posts = json_decode($posts_response, true);
            echo "Scheduled Posts Found: " . count($posts['data'] ?? []) . "\n";
            echo json_encode($posts, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Error fetching posts. HTTP Code: $posts_http_code\n";
            echo "Response: $posts_response\n";
        }

    } else {
        echo "\nError exchanging code. HTTP Code: $http_code\n";
        echo "Response: $response\n";

        $error_data = json_decode($response, true);
        if ($error_data['error'] == 'invalid_grant') {
            echo "\nThe code may have expired (they expire in 10 minutes) or already been used.\n";
            echo "Please run the script again to get a fresh code.\n";
        }
    }
}
?>