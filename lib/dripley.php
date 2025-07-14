<?php
/**
 * Helper functions for Dripley CRM integration
 */
require_once __DIR__.'/db.php';

function dripley_get_settings(): array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT name, value FROM settings WHERE name IN ('dripley_site_url','dripley_username','dripley_app_password')");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'site_url'  => $rows['dripley_site_url'] ?? '',
        'username'  => $rows['dripley_username'] ?? '',
        'app_pass'  => $rows['dripley_app_password'] ?? ''
    ];
}

function dripley_log(string $message, ?int $store_id = null, string $action = 'dripley'): void {
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO logs (store_id, action, message, created_at, ip) VALUES (?, ?, ?, NOW(), ?)');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->execute([$store_id, $action, $message, $ip]);
    } catch (Exception $e) {
        // ignore logging errors
    }
}

function send_contact_to_dripley(array $contactData): bool {
    $settings = dripley_get_settings();
    if (!$settings['site_url'] || !$settings['username'] || !$settings['app_pass']) {
        return false;
    }
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/contacts';
    $auth = base64_encode($settings['username'] . ':' . $settings['app_pass']);

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    dripley_log("POST $url HTTP $httpCode Response: $response", $contactData['store_id'] ?? null, 'dripley_contact');

    return $httpCode >= 200 && $httpCode < 300;
}

function test_dripley_connection(): array {
    $settings = dripley_get_settings();
    if (!$settings['site_url'] || !$settings['username'] || !$settings['app_pass']) {
        return [false, 'Missing configuration'];
    }
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/ping';
    $auth = base64_encode($settings['username'] . ':' . $settings['app_pass']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $success = $httpCode >= 200 && $httpCode < 300;
    dripley_log("GET $url HTTP $httpCode Response: $response", null, 'dripley_test');

    return [$success, $response ?: ''];
}
