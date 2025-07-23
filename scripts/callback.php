<?php
/**
 * Hootsuite OAuth 2.0 Callback Handler
 *
 * This script handles the OAuth callback from Hootsuite and exchanges
 * the authorization code for an access token.
 */

// Your app credentials from Hootsuite Developer Dashboard
$client_id = '51d0a435-fb15-4ed7-90dd-90a690ed4b89';
$client_secret = 'LTLN46fg866k';
$redirect_uri = 'https://content.cosmickmedia.com/test-callback.php';

// IMPORTANT: This must EXACTLY match what you set in Hootsuite Developer Dashboard
// Common examples:
// $redirect_uri = 'http://localhost/hootsuite_callback.php';
// $redirect_uri = 'https://yoursite.com/hootsuite_callback.php';
// $redirect_uri = 'http://localhost:8080/hootsuite_callback.php';

// Auto-detect current URL (recommended for development)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script = $_SERVER['PHP_SELF'];
$redirect_uri = $protocol . '://' . $host . $script;

// OAuth endpoints
$auth_url = 'https://platform.hootsuite.com/oauth2/auth';
$token_url = 'https://platform.hootsuite.com/oauth2/token';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hootsuite OAuth Handler</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button, .button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; text-decoration: none; display: inline-block; }
        button:hover, .button:hover { background: #0056b3; }
        .token-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>

<h1>🔐 Hootsuite OAuth 2.0 Authentication</h1>

<?php

// Step 1: If no code parameter, show authorization link
if (!isset($_GET['code']) && !isset($_GET['error'])) {

    // Show current redirect URI for debugging
    echo '<div class="section info">';
    echo '<h2>🔍 Debug Information</h2>';
    echo '<p><strong>Current Redirect URI:</strong> <code>' . htmlentities($redirect_uri) . '</code></p>';
    echo '<p><strong>⚠️ Important:</strong> This URL must be EXACTLY registered in your Hootsuite app settings!</p>';
    echo '</div>';

    if ($client_id === 'YOUR_CLIENT_ID_HERE') {
        echo '<div class="section error">';
        echo '<h2>⚠️ Setup Required</h2>';
        echo '<p>Please update the <code>$client_id</code> and <code>$client_secret</code> variables in this script with your actual Hootsuite app credentials.</p>';
        echo '</div>';
    } else {
        $scope = 'offline_access,read_write_messages,read_social_profiles';
        $state = bin2hex(random_bytes(16)); // CSRF protection
        $_SESSION['oauth_state'] = $state;

        $auth_params = http_build_query([
            'response_type' => 'code',
            'client_id' => $client_id,
            'scope' => $scope,
            'redirect_uri' => $redirect_uri,
            'state' => $state
        ]);

        $authorization_url = "$auth_url?$auth_params";

        echo '<div class="section info">';
        echo '<h2>Step 1: Authorize Your App</h2>';
        echo '<p>Click the button below to authorize your application with Hootsuite:</p>';
        echo '<a href="' . htmlentities($authorization_url) . '" class="button">🚀 Authorize with Hootsuite</a>';
        echo '</div>';

        echo '<div class="section">';
        echo '<h3>What happens next?</h3>';
        echo '<ol>';
        echo '<li>You\'ll be redirected to Hootsuite to log in</li>';
        echo '<li>Hootsuite will ask you to authorize your app</li>';
        echo '<li>You\'ll be redirected back here with an authorization code</li>';
        echo '<li>This script will exchange the code for an access token</li>';
        echo '</ol>';
        echo '</div>';
    }

// Step 2: Handle authorization response
} elseif (isset($_GET['error'])) {

    echo '<div class="section error">';
    echo '<h2>❌ Authorization Error</h2>';
    echo '<p><strong>Error:</strong> ' . htmlentities($_GET['error']) . '</p>';
    if (isset($_GET['error_description'])) {
        echo '<p><strong>Description:</strong> ' . htmlentities($_GET['error_description']) . '</p>';
    }
    echo '<p><a href="?" class="button">Try Again</a></p>';
    echo '</div>';

// Step 3: Exchange code for token
} else {

    $authorization_code = $_GET['code'];

    // Prepare token request
    $token_data = [
        'grant_type' => 'authorization_code',
        'code' => $authorization_code,
        'redirect_uri' => $redirect_uri,
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ];

    // Make token request
    $ch = curl_init($token_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($token_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $token_data = json_decode($response, true);

    if ($http_code === 200 && isset($token_data['access_token'])) {
        echo '<div class="section success">';
        echo '<h2>✅ Success! Access Token Generated</h2>';
        echo '<div class="token-box">';
        echo '<h3>🔑 Your Access Token:</h3>';
        echo '<pre>' . htmlentities($token_data['access_token']) . '</pre>';
        echo '<p><strong>⚠️ Important:</strong> Copy this token and paste it into your main integration script where it says <code>PASTE_YOUR_ACCESS_TOKEN_HERE</code></p>';
        echo '</div>';
        echo '</div>';

        if (isset($token_data['refresh_token'])) {
            echo '<div class="section info">';
            echo '<h3>🔄 Refresh Token (for production use):</h3>';
            echo '<pre>' . htmlentities($token_data['refresh_token']) . '</pre>';
            echo '<p>This token can be used to get new access tokens when they expire.</p>';
            echo '</div>';
        }

        echo '<div class="section">';
        echo '<h3>Token Details:</h3>';
        echo '<pre>' . htmlentities(json_encode($token_data, JSON_PRETTY_PRINT)) . '</pre>';
        echo '</div>';

        // Now test the token by getting user info
        echo '<div class="section">';
        echo '<h3>🧪 Testing Your Token...</h3>';

        $test_ch = curl_init('https://platform.hootsuite.com/v1/me');
        curl_setopt_array($test_ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token_data['access_token'],
                'Accept: application/json'
            ]
        ]);

        $test_response = curl_exec($test_ch);
        $test_http_code = curl_getinfo($test_ch, CURLINFO_HTTP_CODE);
        curl_close($test_ch);

        if ($test_http_code === 200) {
            $user_info = json_decode($test_response, true);
            echo '<div class="success">✅ Token is working! Connected as: ' . htmlentities($user_info['username'] ?? 'Unknown') . '</div>';
        } else {
            echo '<div class="error">❌ Token test failed</div>';
            echo '<pre>' . htmlentities($test_response) . '</pre>';
        }
        echo '</div>';

    } else {
        echo '<div class="section error">';
        echo '<h2>❌ Token Exchange Failed</h2>';
        echo '<p><strong>HTTP Status:</strong> ' . $http_code . '</p>';
        echo '<pre>' . htmlentities($response) . '</pre>';
        echo '<p><a href="?" class="button">Try Again</a></p>';
        echo '</div>';
    }
}

?>

<div class="section info">
    <h2>📋 Next Steps</h2>
    <ol>
        <li><strong>Copy your access token</strong> from above</li>
        <li><strong>Paste it</strong> into your main integration script</li>
        <li><strong>Run your main script</strong> to see your social profiles and get their IDs</li>
        <li><strong>Start using the API!</strong> You can now schedule posts, retrieve messages, etc.</li>
    </ol>
</div>

<div class="section error">
    <h2>🚨 Troubleshooting Redirect URI Issues</h2>
    <h3>If you get "unknown_redirect_uri" error:</h3>
    <ol>
        <li><strong>Copy the exact URL</strong> shown in the "Debug Information" section above</li>
        <li><strong>Go to Hootsuite Developer Dashboard:</strong> <a href="https://developer.hootsuite.com/my-apps" target="_blank">My Apps</a></li>
        <li><strong>Edit your app</strong> and go to "Redirect URIs"</li>
        <li><strong>Add or update</strong> the redirect URI to match exactly (including http/https, port, etc.)</li>
        <li><strong>Save</strong> and try again</li>
    </ol>

    <h3>Common Issues:</h3>
    <ul>
        <li><strong>HTTP vs HTTPS:</strong> Make sure they match exactly</li>
        <li><strong>Port numbers:</strong> Include :8080, :3000, etc. if using them</li>
        <li><strong>Trailing slashes:</strong> /callback vs /callback/ - be consistent</li>
        <li><strong>Localhost vs domain:</strong> Use what you're actually running from</li>
    </ul>
</div>

<div class="section">
    <h2>🔗 Useful Links</h2>
    <ul>
        <li><a href="https://developer.hootsuite.com/docs" target="_blank">Hootsuite API Documentation</a></li>
        <li><a href="https://developer.hootsuite.com/docs/authentication" target="_blank">Authentication Guide</a></li>
        <li><a href="https://developer.hootsuite.com/my-apps" target="_blank">Manage Your Apps</a></li>
    </ul>
</div>

</body>
</html>