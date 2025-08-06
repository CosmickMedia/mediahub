<?php
require_once __DIR__.'/../settings.php';

/**
 * Refresh the Hootsuite access token using the stored refresh token.
 *
 * @param bool $debug When true, includes HTTP details in the response message.
 * @return array [success:boolean, message:string]
 */
function hootsuite_refresh_token(bool $debug = false): array {
    $refresh_token = get_setting('hootsuite_refresh_token');
    $client_id = get_setting('hootsuite_client_id');
    $client_secret = get_setting('hootsuite_client_secret');
    if (!$refresh_token || !$client_id || !$client_secret) {
        return [false, 'Missing refresh token or OAuth credentials'];
    }

    $token_url = 'https://platform.hootsuite.com/oauth2/token';
    $data = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ];

    $ch = curl_init($token_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return [false, 'cURL error: ' . $curl_error];
    }

    if ($http_code === 200) {
        $token_data = json_decode($response, true);
        if (!isset($token_data['access_token'])) {
            return [false, 'Invalid token response'];
        }
        set_setting('hootsuite_access_token', $token_data['access_token']);
        if (isset($token_data['refresh_token'])) {
            set_setting('hootsuite_refresh_token', $token_data['refresh_token']);
        }
        set_setting('hootsuite_token_last_refresh', date('Y-m-d H:i:s'));
        $msg = 'Token refreshed successfully';
        if ($debug) {
            $msg .= " | HTTP $http_code | Response: " . $response;
        }
        return [true, $msg];
    }

    $msg = 'Error refreshing token. HTTP ' . $http_code;
    if ($debug) {
        $msg .= ' | Response: ' . $response;
    }
    return [false, $msg];
}
