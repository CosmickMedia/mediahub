<?php
/**
 * Helper functions for Groundhogg CRM integration
 */
require_once __DIR__.'/db.php';

function groundhogg_get_settings(): array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        "SELECT name, value FROM settings WHERE name IN (
            'groundhogg_site_url',
            'groundhogg_username',
            'groundhogg_app_password',
            'groundhogg_public_key',
            'groundhogg_token',
            'groundhogg_secret_key',
            'groundhogg_debug'
        )"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'site_url'   => $rows['groundhogg_site_url'] ?? '',
        'username'   => $rows['groundhogg_username'] ?? '',
        'app_pass'   => $rows['groundhogg_app_password'] ?? '',
        'public_key' => $rows['groundhogg_public_key'] ?? '',
        'token'      => $rows['groundhogg_token'] ?? '',
        'secret_key' => $rows['groundhogg_secret_key'] ?? '',
        'debug'      => $rows['groundhogg_debug'] ?? ''
    ];
}

function groundhogg_debug_enabled(): bool {
    $settings = groundhogg_get_settings();
    return $settings['debug'] === '1';
}

function groundhogg_debug_log(string $message): void {
    if (!groundhogg_debug_enabled()) {
        return;
    }
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $file = $logDir . '/groundhogg.log';
    $entry = '[' . date('Y-m-d H:i:s') . "] " . $message . "\n";
    file_put_contents($file, $entry, FILE_APPEND);
}

function groundhogg_log(string $message, ?int $store_id = null, string $action = 'groundhogg'): void {
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO logs (store_id, action, message, created_at, ip) VALUES (?, ?, ?, NOW(), ?)');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->execute([$store_id, $action, $message, $ip]);
    } catch (Exception $e) {
        // ignore logging errors
    }
}

function groundhogg_send_contact(array $contactData): bool {
    $settings = groundhogg_get_settings();
    if (!$settings['site_url']) {
        return false;
    }

    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/contacts';
    $payload = json_encode($contactData);
    $headers = ['Content-Type: application/json'];

    if ($settings['public_key'] && $settings['token'] && $settings['secret_key']) {
        $signature = hash_hmac('sha256', $payload, $settings['secret_key']);
        $headers[] = 'X-GH-USER: ' . $settings['username'];
        $headers[] = 'X-GH-PUBLIC-KEY: ' . $settings['public_key'];
        $headers[] = 'X-GH-TOKEN: ' . $settings['token'];
        $headers[] = 'X-GH-SIGNATURE: ' . $signature;
    } elseif ($settings['username'] && $settings['app_pass']) {
        $auth = base64_encode($settings['username'] . ':' . $settings['app_pass']);
        $headers[] = 'Authorization: Basic ' . $auth;
    } else {
        return false;
    }

    groundhogg_debug_log('POST ' . $url . ' Payload: ' . $payload);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        groundhogg_debug_log('cURL error: ' . curl_error($ch));
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    groundhogg_log("POST $url HTTP $httpCode Response: $response", $contactData['store_id'] ?? null, 'groundhogg_contact');
    groundhogg_debug_log('Response Code: ' . $httpCode . ' Body: ' . $response);

    return $httpCode >= 200 && $httpCode < 300;
}

function test_groundhogg_connection(): array {
    $settings = groundhogg_get_settings();
    if (!$settings['site_url']) {
        return [false, 'Missing configuration'];
    }
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/ping';
    $headers = [];

    if ($settings['public_key'] && $settings['token'] && $settings['secret_key']) {
        $signature = hash_hmac('sha256', 'ping', $settings['secret_key']);
        $headers[] = 'X-GH-USER: ' . $settings['username'];
        $headers[] = 'X-GH-PUBLIC-KEY: ' . $settings['public_key'];
        $headers[] = 'X-GH-TOKEN: ' . $settings['token'];
        $headers[] = 'X-GH-SIGNATURE: ' . $signature;
    } elseif ($settings['username'] && $settings['app_pass']) {
        $auth = base64_encode($settings['username'] . ':' . $settings['app_pass']);
        $headers[] = 'Authorization: Basic ' . $auth;
    } else {
        return [false, 'Missing configuration'];
    }

    groundhogg_debug_log('GET ' . $url . ' (test connection)');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        groundhogg_debug_log('cURL error: ' . curl_error($ch));
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $success = $httpCode >= 200 && $httpCode < 300;
    groundhogg_log("GET $url HTTP $httpCode Response: $response", null, 'groundhogg_test');
    groundhogg_debug_log('Response Code: ' . $httpCode . ' Body: ' . $response);

    return [$success, $response ?: ''];
}
