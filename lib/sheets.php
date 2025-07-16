<?php
// Google Sheets API helper using service account
require_once __DIR__.'/config.php';

function sheets_get_access_token() {
    $config = get_config();
    $service_account_file = $config['service_account_json'] ?? null;
    if (!$service_account_file || !file_exists($service_account_file)) {
        throw new Exception('Service account JSON file not found');
    }
    $creds = json_decode(file_get_contents($service_account_file), true);

    $headerArr = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];
    if (!empty($creds['private_key_id'])) {
        $headerArr['kid'] = $creds['private_key_id'];
    }
    $header = json_encode($headerArr);

    $now = time();
    $claims = json_encode([
        'iss' => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'aud' => $creds['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlClaims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claims));

    $signature = '';
    $pkey = openssl_pkey_get_private($creds['private_key']);
    if (!$pkey) {
        throw new Exception('Invalid private key');
    }
    $ok = openssl_sign($base64UrlHeader . '.' . $base64UrlClaims, $signature, $pkey, OPENSSL_ALGO_SHA256);
    openssl_free_key($pkey);
    if (!$ok) {
        throw new Exception('Failed to sign JWT');
    }
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = $base64UrlHeader . '.' . $base64UrlClaims . '.' . $base64UrlSignature;

    $ch = curl_init($creds['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ])
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        throw new Exception('Failed to get access token: ' . $result);
    }
    $data = json_decode($result, true);
    if (!isset($data['access_token'])) {
        throw new Exception('No access token in response');
    }
    return $data['access_token'];
}

function sheets_fetch_rows(string $spreadsheetId, string $range): array {
    $token = sheets_get_access_token();
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheetId . '/values/' . urlencode($range);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        throw new Exception('Failed to fetch sheet data: ' . $result);
    }
    $data = json_decode($result, true);
    return $data['values'] ?? [];
}
