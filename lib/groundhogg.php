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
            'groundhogg_public_key',
            'groundhogg_token',
            'groundhogg_secret_key',
            'groundhogg_debug',
            'groundhogg_contact_tags'
        )"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'site_url'   => $rows['groundhogg_site_url'] ?? '',
        'username'   => $rows['groundhogg_username'] ?? '',
        'public_key' => $rows['groundhogg_public_key'] ?? '',
        'token'      => $rows['groundhogg_token'] ?? '',
        'secret_key'    => $rows['groundhogg_secret_key'] ?? '',
        'debug'         => $rows['groundhogg_debug'] ?? '',
        'contact_tags'  => $rows['groundhogg_contact_tags'] ?? ''
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
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

function groundhogg_log(string $message, ?int $store_id = null, string $action = 'groundhogg'): void {
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO logs (store_id, action, message, created_at, ip) VALUES (?, ?, ?, NOW(), ?)');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt->execute([$store_id, $action, $message, $ip]);
    } catch (Exception $e) {
        groundhogg_debug_log('Failed to write to logs table: ' . $e->getMessage());
    }
}

/**
 * Get default tags for Groundhogg contacts from settings.
 *
 * @return array
 */
function groundhogg_get_default_tags(): array {
    $settings = groundhogg_get_settings();
    $tags = array_filter(array_map('trim', explode(',', $settings['contact_tags'] ?? '')));
    if (empty($tags)) {
        $tags = ['media-hub', 'store-onboarding'];
    }
    return $tags;
}

function groundhogg_send_contact(array $contactData): array {
    $settings = groundhogg_get_settings();

    // Validate settings
    if (!$settings['site_url']) {
        groundhogg_debug_log('Missing Groundhogg site URL');
        return [false, 'Groundhogg integration not configured: missing site URL'];
    }

    if (!$settings['username'] || !$settings['public_key'] || !$settings['token'] || !$settings['secret_key']) {
        groundhogg_debug_log('Missing Groundhogg API credentials');
        return [false, 'Groundhogg integration not configured: missing API credentials'];
    }

    // Ensure email is provided
    if (empty($contactData['email'])) {
        groundhogg_debug_log('Missing email in contact data');
        return [false, 'Email address is required'];
    }

    // Build the contact data in Groundhogg's expected format
    $groundhoggData = [
        'email' => $contactData['email'],
        'first_name' => $contactData['first_name'] ?? '',
        'last_name' => $contactData['last_name'] ?? '',
        'data' => []
    ];

    // Add optional fields to data array
    if (!empty($contactData['phone'])) {
        $groundhoggData['data']['mobile_phone'] = $contactData['phone'];
        $groundhoggData['data']['primary_phone'] = $contactData['phone'];
    }

    if (!empty($contactData['company_name'])) {
        $groundhoggData['data']['company'] = $contactData['company_name'];
    }

    if (!empty($contactData['user_role'])) {
        $groundhoggData['data']['user_role'] = $contactData['user_role'];
    }

    if (!empty($contactData['store_id'])) {
        $groundhoggData['data']['store_id'] = (string)$contactData['store_id'];
    }

    // Handle tags - Groundhogg expects an array of tag names or IDs
    if (!empty($contactData['tags']) && is_array($contactData['tags'])) {
        $groundhoggData['tags'] = $contactData['tags'];
    } else {
        $groundhoggData['tags'] = groundhogg_get_default_tags();
    }

    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/contacts';
    $payload = json_encode($groundhoggData);

    // Create signature for advanced authentication
    $signature = hash_hmac('sha256', $payload, $settings['secret_key']);

    $headers = [
        'Content-Type: application/json',
        'Gh-Token: ' . $settings['token'],
        'Gh-Public-Key: ' . $settings['public_key']
    ];

    groundhogg_debug_log('POST ' . $url);
    groundhogg_debug_log('Headers: ' . json_encode($headers));
    groundhogg_debug_log('Payload: ' . $payload);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        groundhogg_debug_log('cURL error: ' . $error);
        groundhogg_log("POST $url cURL error: $error", $contactData['store_id'] ?? null, 'groundhogg_contact');
        return [false, 'Connection error: ' . $error];
    }

    groundhogg_debug_log('Response Code: ' . $httpCode);
    groundhogg_debug_log('Response Body: ' . $response);
    groundhogg_log("POST $url HTTP $httpCode Response: $response", $contactData['store_id'] ?? null, 'groundhogg_contact');

    // Parse response
    $responseData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        if (isset($responseData['contact']['ID'])) {
            $contactId = $responseData['contact']['ID'];
            groundhogg_debug_log('Contact created/updated successfully with ID: ' . $contactId);
            return [true, 'Contact synchronized with Groundhogg (ID: ' . $contactId . ')'];
        } else {
            groundhogg_debug_log('Unexpected successful response format');
            return [true, 'Contact synchronized with Groundhogg'];
        }
    } else {
        // Handle error response
        $errorMessage = 'Unknown error';

        if (isset($responseData['message'])) {
            $errorMessage = $responseData['message'];
        } elseif (isset($responseData['error'])) {
            $errorMessage = $responseData['error'];
        } elseif (isset($responseData['code'])) {
            $errorMessage = 'Error code: ' . $responseData['code'];
        }

        groundhogg_debug_log('API Error: ' . $errorMessage);
        return [false, 'Groundhogg API error: ' . $errorMessage];
    }
}

function test_groundhogg_connection(): array {
    $settings = groundhogg_get_settings();

    if (!$settings['site_url']) {
        return [false, 'Missing site URL configuration'];
    }

    if (!$settings['username'] || !$settings['public_key'] || !$settings['token'] || !$settings['secret_key']) {
        return [false, 'Missing API credentials'];
    }

    // Test with the contacts endpoint using GET to check authentication
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/contacts?limit=1';

    // For GET request, signature is based on empty string
    $signature = hash_hmac('sha256', '', $settings['secret_key']);

    $headers = [
        'X-GH-USER: ' . $settings['username'],
        'X-GH-PUBLIC-KEY: ' . $settings['public_key'],
        'X-GH-TOKEN: ' . $settings['token'],
        'X-GH-SIGNATURE: ' . $signature
    ];

    groundhogg_debug_log('GET ' . $url . ' (test connection)');
    groundhogg_debug_log('Test Headers: ' . json_encode($headers));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        groundhogg_debug_log('Test connection cURL error: ' . $error);
        return [false, 'Connection error: ' . $error];
    }

    groundhogg_log("GET $url HTTP $httpCode Response: $response", null, 'groundhogg_test');
    groundhogg_debug_log('Test Response Code: ' . $httpCode);
    groundhogg_debug_log('Test Response Body: ' . $response);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            return [true, 'Connection successful! Groundhogg API is working.'];
        } else {
            return [true, 'Connection successful! Response: ' . substr($response, 0, 100)];
        }
    } elseif ($httpCode === 401 || $httpCode === 403) {
        return [false, 'Authentication failed. Please check your API credentials.'];
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['message'] ?? $errorData['error'] ?? 'HTTP ' . $httpCode;
        return [false, 'API Error: ' . $errorMsg];
    }
}

// Helper function to sync existing store contacts
function groundhogg_sync_store_contacts(int $store_id): array {
    $pdo = get_pdo();

    // Get store details
    $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
    $stmt->execute([$store_id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        return [false, 'Store not found'];
    }

    $results = [];

    // Sync main store contact if email exists
    if (!empty($store['admin_email'])) {
        $contact = [
            'email'        => $store['admin_email'],
            'first_name'   => $store['first_name'] ?? '',
            'last_name'    => $store['last_name'] ?? '',
            'phone'        => $store['phone'] ?? '',
            'company_name' => $store['name'],
            'user_role'    => 'Store Admin',
            'tags'         => groundhogg_get_default_tags(),
            'store_id'     => $store_id
        ];

        [$success, $message] = groundhogg_send_contact($contact);
        $results[] = [
            'email' => $store['admin_email'],
            'success' => $success,
            'message' => $message
        ];
    }

    // Sync additional store users
    $stmt = $pdo->prepare('SELECT * FROM store_users WHERE store_id = ?');
    $stmt->execute([$store_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $contact = [
            'email'        => $user['email'],
            'first_name'   => $user['first_name'] ?? '',
            'last_name'    => $user['last_name'] ?? '',
            'phone'        => $store['phone'] ?? '',
            'company_name' => $store['name'],
            'user_role'    => 'Store Admin',
            'tags'         => groundhogg_get_default_tags(),
            'store_id'     => $store_id
        ];

        [$success, $message] = groundhogg_send_contact($contact);
        $results[] = [
            'email' => $user['email'],
            'success' => $success,
            'message' => $message
        ];
    }

    return [true, $results];
}

// Sync all admin users with Groundhogg
function groundhogg_sync_admin_users(): array {
    $pdo = get_pdo();
    $users = $pdo->query('SELECT first_name, last_name, email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $results = [];
    foreach ($users as $user) {
        $contact = [
            'email'       => $user['email'],
            'first_name'  => $user['first_name'] ?? '',
            'last_name'   => $user['last_name'] ?? '',
            'company_name'=> 'Cosmick Media',
            'user_role'   => 'Admin User',
            'tags'        => groundhogg_get_default_tags()
        ];
        [$success, $message] = groundhogg_send_contact($contact);
        $results[] = [
            'email' => $user['email'],
            'success' => $success,
            'message' => $message
        ];
    }
    return $results;
}
