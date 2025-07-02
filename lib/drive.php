<?php
/** Google Drive upload helper using service account and cURL */

require_once __DIR__.'/config.php';

function drive_get_access_token() {
    $config = get_config();
    $creds = json_decode(file_get_contents($config['service_account_json']), true);
    $header = base64_encode(json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT'
    ]));
    $now = time();
    $claims = [
        'iss' => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'aud' => $creds['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now,
    ];
    $payload = base64_encode(json_encode($claims));
    $signature = ''; // We will sign using openssl.
    openssl_sign($header.'.'.$payload, $signature, $creds['private_key'], 'sha256');
    $jwt = $header.'.'.$payload.'.'.base64_encode($signature);

    $ch = curl_init($creds['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ])
    ]);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Curl error: '.curl_error($ch));
    }
    $data = json_decode($result, true);
    return $data['access_token'] ?? null;
}

function drive_upload($filepath, $mime, $name, $folderId) {
    $token = drive_get_access_token();
    $boundary = uniqid('boundary');

    $body = "--$boundary\r\n".
        "Content-Type: application/json; charset=UTF-8\r\n\r\n".
        json_encode(['name' => $name, 'parents' => [$folderId]])."\r\n".
        "--$boundary\r\n".
        "Content-Type: $mime\r\n\r\n".
        file_get_contents($filepath)."\r\n".
        "--$boundary--";

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: multipart/related; boundary=$boundary"
        ],
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $body
    ]);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Curl error: '.curl_error($ch));
    }
    return json_decode($result, true)['id'] ?? null;
}
