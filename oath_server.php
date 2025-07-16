<?php
$client_id = '51d0a435-fb15-4ed7-90dd-90a690ed4b89';
$client_secret = 'LTLN46fg866k';

$possible_redirects = [
    'https://www.getpostman.com/oauth2/callback',
    'https://oauth.pstmn.io/v1/callback',
    'https://oauth.pstmn.io/v1/browser-callback',
    'urn:ietf:wg:oauth:2.0:oob',
    'http://localhost',
    'http://localhost:3000/callback'
];

echo "Testing possible redirect URIs...\n\n";

foreach ($possible_redirects as $redirect_uri) {
    $auth_url = 'https://platform.hootsuite.com/oauth2/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => 'offline',
            'state' => 'test123'
        ]);

    echo "Try this URL with redirect_uri: $redirect_uri\n";
    echo $auth_url . "\n\n";
}
?>