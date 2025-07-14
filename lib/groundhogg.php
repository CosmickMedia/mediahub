<?php
/**
 * Helper functions for Groundhogg CRM integration
 */
require_once __DIR__.'/db.php';

function groundhogg_get_settings(): array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        "SELECT name, value FROM settings WHERE name IN ('groundhogg_site_url','groundhogg_username','groundhogg_app_password','groundhogg_debug')"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'site_url'  => $rows['groundhogg_site_url'] ?? '',
        'username'  => $rows['groundhogg_username'] ?? '',
        'app_pass'  => $rows['groundhogg_app_password'] ?? '',
        'debug'     => $rows['groundhogg_debug'] ?? ''
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
    if (!$settings['site_url'] || !$settings['username'] || !$settings['app_pass']) {
        return false;
    }
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/contacts';
    $auth = base64_encode($settings['username'] . ':' . $settings['app_pass']);

    groundhogg_debug_log('POST ' . $url . ' Payload: ' . json_encode($contactData));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth
        ],
        CURLOPT_POSTFIELDS => json_encode($contactData)
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
    if (!$settings['site_url'] || !$settings['username'] || !$settings['app_pass']) {
        return [false, 'Missing configuration'];
    }
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/ping';
    $auth = base64_encode($settings['username'] . ':' . $settings['app_pass']);

    groundhogg_debug_log('GET ' . $url . ' (test connection)');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth
        ]
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
