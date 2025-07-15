<?php
/**
 * Helper functions for Groundhogg CRM integration
 */
require_once __DIR__.'/db.php';
require_once __DIR__.'/helpers.php';

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

function groundhogg_get_location(): array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        "SELECT name, value FROM settings WHERE name IN ('company_address','company_city','company_state','company_zip','company_country')"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'address' => $rows['company_address'] ?? '',
        'city'    => $rows['company_city'] ?? '',
        'state'   => $rows['company_state'] ?? '',
        'zip'     => $rows['company_zip'] ?? '',
        'country' => $rows['company_country'] ?? ''
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

/**
 * Build the payload structure expected by Groundhogg.
 */
function groundhogg_build_contact_structure(array $contactData): array {
    $data = [
        'email'      => $contactData['email'],
        'first_name' => $contactData['first_name'] ?? '',
        'last_name'  => $contactData['last_name'] ?? '',
        'meta'       => []
    ];

    // Phone numbers in multiple fields and formats
    $phoneRaw = $contactData['mobile_phone'] ?? ($contactData['phone'] ?? '');
    $formats = phone_number_variations($phoneRaw);
    if (!empty($formats)) {
        [$digits, $intl, $dash] = $formats;
        $phones = [
            'phone'         => $digits,
            'mobile_phone'  => $intl,
            // Store primary phone using the same format as mobile
            'primary_phone' => $intl,
            'phone_number'  => $digits
        ];
        foreach ($phones as $field => $value) {
            $data[$field] = $value;
            $data['meta'][$field] = $value;
        }
    }

    // Address/location fields
    if (!empty($contactData['address'])) {
        foreach (['street_address_1', 'address_line_1', 'address'] as $field) {
            $data[$field] = $contactData['address'];
            $data['meta'][$field] = $contactData['address'];
        }
    }
    if (!empty($contactData['city'])) {
        foreach (['city', 'locality'] as $field) {
            $data[$field] = $contactData['city'];
            $data['meta'][$field] = $contactData['city'];
        }
    }
    if (!empty($contactData['state'])) {
        foreach (['region', 'state'] as $field) {
            $data[$field] = $contactData['state'];
            $data['meta'][$field] = $contactData['state'];
        }
    }
    if (!empty($contactData['zip'])) {
        foreach (['postal_zip', 'zip', 'zip_code'] as $field) {
            $data[$field] = $contactData['zip'];
            $data['meta'][$field] = $contactData['zip'];
        }
    }
    if (!empty($contactData['country'])) {
        $data['country'] = $contactData['country'];
        $data['meta']['country'] = $contactData['country'];
    }

    if (!empty($contactData['lead_source'])) {
        $data['lead_source'] = $contactData['lead_source'];
        $data['meta']['lead_source'] = $contactData['lead_source'];
    }

    if (!empty($contactData['opt_in_status'])) {
        $data['optin_status'] = $contactData['opt_in_status'];
    }

    if (!empty($contactData['company_name'])) {
        $data['company_name'] = $contactData['company_name'];
        $data['meta']['company'] = $contactData['company_name'];
    }

    if (!empty($contactData['user_role'])) {
        $data['user_role'] = $contactData['user_role'];
        $data['meta']['user_role'] = $contactData['user_role'];
    }

    if (!empty($contactData['store_id'])) {
        $data['store_id'] = (string)$contactData['store_id'];
        $data['meta']['store_id'] = (string)$contactData['store_id'];
    }

    if (!empty($contactData['tags']) && is_array($contactData['tags'])) {
        $data['tags'] = $contactData['tags'];
    } else {
        $data['tags'] = groundhogg_get_default_tags();
    }

    return $data;
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

    // Build payload using helper
    $groundhoggData = groundhogg_build_contact_structure($contactData);

    groundhogg_debug_log('Raw Contact: ' . json_encode($contactData));
    groundhogg_debug_log('Built Data: ' . json_encode($groundhoggData));

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

function groundhogg_delete_contact(string $email): array {
    $settings = groundhogg_get_settings();

    if (!$settings['site_url']) {
        return [false, 'Groundhogg integration not configured: missing site URL'];
    }

    if (!$settings['username'] || !$settings['public_key'] || !$settings['token'] || !$settings['secret_key']) {
        return [false, 'Groundhogg integration not configured: missing API credentials'];
    }

    if ($email === '') {
        return [false, 'Email address is required'];
    }

    $query = http_build_query(['search' => $email]);
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/contacts?' . $query;

    $signature = hash_hmac('sha256', '', $settings['secret_key']);

    $headers = [
        'X-GH-USER: ' . $settings['username'],
        'X-GH-PUBLIC-KEY: ' . $settings['public_key'],
        'X-GH-TOKEN: ' . $settings['token'],
        'X-GH-SIGNATURE: ' . $signature
    ];

    groundhogg_debug_log('DELETE ' . $url);
    groundhogg_debug_log('Headers: ' . json_encode($headers));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
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

    groundhogg_log("DELETE $url HTTP $httpCode Response: $response", null, 'groundhogg_contact');

    if ($error) {
        return [false, 'Connection error: ' . $error];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return [true, 'Contact removed from Groundhogg'];
    }

    $data = json_decode($response, true);
    $message = $data['message'] ?? $data['error'] ?? ('HTTP ' . $httpCode);
    return [false, 'Groundhogg API error: ' . $message];
}

/**
 * Retrieve a contact from Groundhogg by email.
 */
function groundhogg_get_contact(string $email): array {
    $settings = groundhogg_get_settings();

    if (!$settings['site_url']) {
        return [false, 'Groundhogg integration not configured: missing site URL'];
    }

    if (!$settings['username'] || !$settings['public_key'] || !$settings['token'] || !$settings['secret_key']) {
        return [false, 'Groundhogg integration not configured: missing API credentials'];
    }

    if ($email === '') {
        return [false, 'Email address is required'];
    }

    $query = http_build_query(['search' => $email]);
    $url = rtrim($settings['site_url'], '/') . '/wp-json/gh/v4/contacts?' . $query;

    $signature = hash_hmac('sha256', '', $settings['secret_key']);

    $headers = [
        'X-GH-USER: ' . $settings['username'],
        'X-GH-PUBLIC-KEY: ' . $settings['public_key'],
        'X-GH-TOKEN: ' . $settings['token'],
        'X-GH-SIGNATURE: ' . $signature
    ];

    groundhogg_debug_log('GET ' . $url . ' (fetch contact)');
    groundhogg_debug_log('Fetch Headers: ' . json_encode($headers));

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

    groundhogg_debug_log('Fetch Response Code: ' . $httpCode);
    groundhogg_debug_log('Fetch Response Body: ' . $response);

    if ($error) {
        return [false, $error];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['contacts'][0])) {
        return [true, $data['contacts'][0]];
    }

    $message = $data['message'] ?? $data['error'] ?? ('HTTP ' . $httpCode);
    return [false, $message];
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
            'mobile_phone' => $store['phone'] ?? '',
            'address'      => $store['address'] ?? '',
            'city'         => $store['city'] ?? '',
            'state'        => $store['state'] ?? '',
            'zip'          => $store['zip_code'] ?? '',
            'country'      => $store['country'] ?? '',
            'company_name' => $store['name'],
            'user_role'    => 'Store Admin',
            'lead_source'  => 'mediahub',
            'opt_in_status'=> 'confirmed',
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
            'mobile_phone' => $user['mobile_phone'] ?? ($store['phone'] ?? ''),
            'address'      => $store['address'] ?? '',
            'city'         => $store['city'] ?? '',
            'state'        => $store['state'] ?? '',
            'zip'          => $store['zip_code'] ?? '',
            'country'      => $store['country'] ?? '',
            'company_name' => $store['name'],
            'user_role'    => 'Store Admin',
            'lead_source'  => 'mediahub',
            'opt_in_status'=> $user['opt_in_status'] ?? 'confirmed',
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
    $users = $pdo->query('SELECT first_name, last_name, email, mobile_phone, opt_in_status FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $location = groundhogg_get_location();
    $results = [];
    foreach ($users as $user) {
        $contact = [
            'email'       => $user['email'],
            'first_name'  => $user['first_name'] ?? '',
            'last_name'   => $user['last_name'] ?? '',
            'mobile_phone'=> format_mobile_number($user['mobile_phone'] ?? ''),
            'address'     => $location['address'],
            'city'        => $location['city'],
            'state'       => $location['state'],
            'zip'         => $location['zip'],
            'country'     => $location['country'],
            'company_name'=> 'Cosmick Media',
            'user_role'   => 'Admin User',
            'lead_source' => 'cosmick-employee',
            'opt_in_status'=> $user['opt_in_status'] ?? 'confirmed',
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
