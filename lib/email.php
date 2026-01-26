<?php
/**
 * Centralized Email Sending via Brevo (Sendinblue)
 * Falls back to PHP mail() if Brevo is not configured
 */

require_once __DIR__.'/settings.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

/**
 * Send email via Brevo API or fallback to PHP mail()
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Plain text email body
 * @param string|null $toName Recipient name (optional)
 * @param string|null $fromName Sender name (optional - uses logged-in admin name or default)
 * @return bool True if email was sent successfully
 */
function send_email(string $to, string $subject, string $body, ?string $toName = null, ?string $fromName = null): bool {
    $apiKey = get_setting('brevo_api_key');
    $brevoEnabled = get_setting('brevo_enabled') === '1';
    $fromAddress = get_setting('email_from_address') ?: 'noreply@cosmickmedia.com';
    $defaultFromName = get_setting('email_from_name') ?: 'Cosmick Media';

    // Determine sender name: provided > logged-in admin > default
    if (empty($fromName)) {
        $fromName = get_current_user_name() ?: $defaultFromName;
    }

    // If Brevo not configured or disabled, fall back to native mail()
    if (empty($apiKey) || !$brevoEnabled) {
        return send_email_via_mail($to, $subject, $body, $fromName, $fromAddress);
    }

    // Use Brevo API
    return send_email_via_brevo($to, $subject, $body, $toName, $fromName, $fromAddress, $apiKey);
}

/**
 * Send email using PHP's native mail() function
 */
function send_email_via_mail(string $to, string $subject, string $body, string $fromName, string $fromAddress): bool {
    $headers = "From: $fromName <$fromAddress>\r\n";
    $headers .= "Reply-To: $fromAddress\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return @mail($to, $subject, $body, $headers);
}

/**
 * Send email using Brevo API
 */
function send_email_via_brevo(string $to, string $subject, string $body, ?string $toName, string $fromName, string $fromAddress, string $apiKey): bool {
    $data = [
        'sender' => [
            'name' => $fromName,
            'email' => $fromAddress
        ],
        'to' => [[
            'email' => $to,
            'name' => $toName ?: $to
        ]],
        'subject' => $subject,
        'textContent' => $body
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log errors for debugging (optional)
    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Brevo API error: HTTP $httpCode - $response - $error");
    }

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Get current logged-in user's full name (for admin panel)
 * Returns null if no admin is logged in or name is not set
 */
function get_current_user_name(): ?string {
    ensure_session();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        return $name ?: null;
    }

    return null;
}

/**
 * Test Brevo connection by sending a test email
 *
 * @param string $testEmail Email address to send test to
 * @return array [bool success, string message]
 */
function test_brevo_connection(string $testEmail): array {
    $apiKey = get_setting('brevo_api_key');

    if (empty($apiKey)) {
        return [false, 'Brevo API key is not configured'];
    }

    $fromAddress = get_setting('email_from_address') ?: 'noreply@cosmickmedia.com';
    $fromName = get_setting('email_from_name') ?: 'Cosmick Media';

    $subject = 'Test Email - MediaHub Brevo Configuration';
    $body = "This is a test email from MediaHub.\n\n";
    $body .= "If you received this email, your Brevo configuration is working correctly.\n\n";
    $body .= "Sent via: Brevo API\n";
    $body .= "From: $fromName <$fromAddress>\n";
    $body .= "Time: " . date('Y-m-d H:i:s') . "\n";

    $success = send_email_via_brevo($testEmail, $subject, $body, null, $fromName, $fromAddress, $apiKey);

    if ($success) {
        return [true, "Test email sent to $testEmail via Brevo"];
    } else {
        return [false, "Failed to send test email via Brevo. Check your API key and sender email configuration."];
    }
}
