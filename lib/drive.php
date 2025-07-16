<?php
/** Google Drive upload helper using service account and cURL */

require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';

function drive_get_access_token() {
    $config = get_config();
    $service_account_file = $config['service_account_json'];

    if (!file_exists($service_account_file)) {
        throw new Exception('Service account JSON file not found');
    }

    $creds = json_decode(file_get_contents($service_account_file), true);

    // Create JWT header
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
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'aud' => $creds['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now,
    ]);

    // Encode to base64url
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlClaims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claims));

    // Create signature
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

    // Create JWT
    $jwt = $base64UrlHeader . '.' . $base64UrlClaims . '.' . $base64UrlSignature;

    // Request access token
    $ch = curl_init($creds['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
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
        throw new Exception('No access token in response: ' . $result);
    }

    return $data['access_token'];
}

function drive_create_folder($name, $parentId = null) {
    $token = drive_get_access_token();

    $metadata = [
        'name' => $name,
        'mimeType' => 'application/vnd.google-apps.folder'
    ];

    if ($parentId) {
        $metadata['parents'] = [$parentId];
    }

    $ch = curl_init('https://www.googleapis.com/drive/v3/files');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($metadata)
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to create folder: ' . $result);
    }

    $data = json_decode($result, true);
    return $data['id'] ?? null;
}

function drive_upload($filepath, $mime, $name, $folderId) {
    $token = drive_get_access_token();

    // If no folder ID provided, use the base folder from settings
    if (!$folderId) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE name=?');
        $stmt->execute(['drive_base_folder']);
        $folderId = $stmt->fetchColumn();

        if (!$folderId) {
            throw new Exception('No Google Drive folder configured');
        }
    }

    $boundary = uniqid('boundary');

    // Create metadata
    $metadata = [
        'name' => $name,
        'parents' => [$folderId]
    ];

    // Build multipart body
    $body = "--$boundary\r\n";
    $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $body .= json_encode($metadata) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: $mime\r\n\r\n";
    $body .= file_get_contents($filepath) . "\r\n";
    $body .= "--$boundary--";

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to upload file: ' . $result);
    }

    $data = json_decode($result, true);
    if (!isset($data['id'])) {
        throw new Exception('No file ID in response');
    }

    return $data['id'];
}

function drive_delete($fileId) {
    $token = drive_get_access_token();

    $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . $fileId);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "DELETE",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token"
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 204 && $httpCode !== 200) {
        throw new Exception('Failed to delete file: ' . $result);
    }

    return true;
}

function get_or_create_store_folder($storeId) {
    $pdo = get_pdo();

    // Get store details
    $stmt = $pdo->prepare('SELECT * FROM stores WHERE id=?');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();

    if (!$store) {
        throw new Exception('Store not found');
    }

    // If store already has a folder, return it
    if ($store['drive_folder']) {
        return $store['drive_folder'];
    }

    // Get base folder from settings
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE name=?');
    $stmt->execute(['drive_base_folder']);
    $baseFolderId = $stmt->fetchColumn();

    if (!$baseFolderId) {
        throw new Exception('No base Google Drive folder configured');
    }

    // Create folder for this store
    $folderName = $store['name'] . ' (' . $store['pin'] . ')';
    $folderId = drive_create_folder($folderName, $baseFolderId);

    // Update store record with folder ID
    $stmt = $pdo->prepare('UPDATE stores SET drive_folder=? WHERE id=?');
    $stmt->execute([$folderId, $storeId]);

    return $folderId;
}