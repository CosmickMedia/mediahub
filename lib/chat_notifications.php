<?php
/**
 * Chat Notification Functions
 * Handles email notifications for chat messages between admins and store users
 */

require_once __DIR__.'/db.php';
require_once __DIR__.'/settings.php';
require_once __DIR__.'/email.php';

/**
 * Check if chat emails are enabled globally
 */
function are_chat_emails_enabled(): bool {
    return get_setting('enable_chat_emails') !== '0';
}

/**
 * Check if we should send an email based on cooldown period
 * @param string $direction 'to_admin' or 'to_store'
 * @param int $store_id The store ID for this conversation
 * @return bool True if email should be sent
 */
function should_send_chat_email(string $direction, int $store_id): bool {
    if (!are_chat_emails_enabled()) {
        return false;
    }

    $cooldownMinutes = (int)(get_setting('chat_email_cooldown_minutes') ?? 5);
    if ($cooldownMinutes <= 0) {
        return true; // No cooldown
    }

    $settingKey = "chat_email_last_{$direction}_{$store_id}";
    $lastSent = get_setting($settingKey);

    if (!$lastSent) {
        return true;
    }

    $lastSentTime = strtotime($lastSent);
    $cooldownSeconds = $cooldownMinutes * 60;

    return (time() - $lastSentTime) >= $cooldownSeconds;
}

/**
 * Record that a chat email was sent
 * @param string $direction 'to_admin' or 'to_store'
 * @param int $store_id The store ID for this conversation
 */
function record_chat_email_sent(string $direction, int $store_id): void {
    $settingKey = "chat_email_last_{$direction}_{$store_id}";
    set_setting($settingKey, date('Y-m-d H:i:s'));
}

/**
 * Get the last admin who responded to this store's chat
 * @param int $store_id The store ID
 * @return array|null Admin info (id, email, first_name, last_name) or null
 */
function get_last_responding_admin(int $store_id): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.first_name, u.last_name
        FROM store_messages m
        JOIN users u ON m.admin_user_id = u.id
        WHERE m.store_id = ? AND m.sender = 'admin' AND m.admin_user_id IS NOT NULL
        ORDER BY m.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$store_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Get store information
 * @param int $store_id The store ID
 * @return array|null Store info or null
 */
function get_store_for_notification(int $store_id): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Get store user information
 * @param int $store_user_id The store user ID
 * @return array|null Store user info or null
 */
function get_store_user_for_notification(int $store_user_id): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM store_users WHERE id = ?");
    $stmt->execute([$store_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Get admin user information
 * @param int $admin_user_id The admin user ID
 * @return array|null Admin user info or null
 */
function get_admin_user_for_notification(int $admin_user_id): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$admin_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Send email notification to store users when admin sends a message
 * @param int $store_id The store ID
 * @param string $message The message content
 * @param int|null $admin_user_id The admin user ID who sent the message
 * @return bool True if email was sent
 */
function send_chat_email_to_store(int $store_id, string $message, ?int $admin_user_id = null): bool {
    // Check if this direction is enabled
    if (get_setting('enable_chat_email_to_store') === '0') {
        return false;
    }

    // Check cooldown
    if (!should_send_chat_email('to_store', $store_id)) {
        return false;
    }

    // Get store info
    $store = get_store_for_notification($store_id);
    if (!$store) {
        return false;
    }

    // Get admin info for personalization
    $adminName = get_setting('email_from_name') ?: 'Cosmick Media';
    if ($admin_user_id) {
        $admin = get_admin_user_for_notification($admin_user_id);
        if ($admin) {
            $name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
            if (!empty($name)) {
                $adminName = $name;
            }
        }
    }

    // Get all store users for this store
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM store_users WHERE store_id = ? AND email IS NOT NULL AND email != ''");
    $stmt->execute([$store_id]);
    $storeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no store users, fall back to admin_email
    if (empty($storeUsers) && !empty($store['admin_email'])) {
        $storeUsers = [['email' => $store['admin_email'], 'first_name' => $store['name']]];
    }

    if (empty($storeUsers)) {
        return false;
    }

    // Get subject template
    $subjectTemplate = get_setting('chat_store_notification_subject') ?: 'New message from {admin_name}';
    $subject = str_replace('{admin_name}', $adminName, $subjectTemplate);

    // Get login URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $loginUrl = $protocol . '://' . $host . '/public/';

    $emailsSent = false;
    $defaultFromName = get_setting('email_from_name') ?: 'Cosmick Media';

    foreach ($storeUsers as $user) {
        $firstName = $user['first_name'] ?? $store['name'];
        $recipientName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $store['name'];

        $emailBody = "Dear {$firstName},\n\n";
        $emailBody .= "You have a new message from {$adminName}:\n\n";
        $emailBody .= "=====================================\n";
        $emailBody .= $message . "\n";
        $emailBody .= "=====================================\n\n";
        $emailBody .= "Log in to MediaHub to view and reply:\n";
        $emailBody .= $loginUrl . "\n\n";
        $emailBody .= "Best regards,\n" . $defaultFromName;

        // Use admin's name as sender for chat messages
        if (send_email($user['email'], $subject, $emailBody, $recipientName, $adminName)) {
            $emailsSent = true;
        }
    }

    if ($emailsSent) {
        record_chat_email_sent('to_store', $store_id);
    }

    return $emailsSent;
}

/**
 * Send email notification to admin when store user sends a message
 * @param int $store_id The store ID
 * @param string $message The message content
 * @param int|null $store_user_id The store user ID who sent the message
 * @return bool True if email was sent
 */
function send_chat_email_to_admin(int $store_id, string $message, ?int $store_user_id = null): bool {
    // Check if this direction is enabled
    if (get_setting('enable_chat_email_to_admin') === '0') {
        return false;
    }

    // Check cooldown
    if (!should_send_chat_email('to_admin', $store_id)) {
        return false;
    }

    // Get store info
    $store = get_store_for_notification($store_id);
    if (!$store) {
        return false;
    }

    // Get the sender's name
    $senderName = $store['name'];
    if ($store_user_id) {
        $storeUser = get_store_user_for_notification($store_user_id);
        if ($storeUser) {
            $userName = trim(($storeUser['first_name'] ?? '') . ' ' . ($storeUser['last_name'] ?? ''));
            if (!empty($userName)) {
                $senderName = $userName;
            }
        }
    }

    // Determine recipient email(s)
    $recipientEmails = [];

    // First, check for override notification email
    $chatNotificationEmail = get_setting('chat_notification_email');
    if (!empty($chatNotificationEmail)) {
        // Support comma-separated emails
        $recipientEmails = array_map('trim', explode(',', $chatNotificationEmail));
    } else {
        // Find the last admin who responded to this store
        $lastAdmin = get_last_responding_admin($store_id);
        if ($lastAdmin && !empty($lastAdmin['email'])) {
            $recipientEmails[] = $lastAdmin['email'];
        } else {
            // Fall back to general notification email
            $notificationEmail = get_setting('notification_email');
            if (!empty($notificationEmail)) {
                $recipientEmails = array_map('trim', explode(',', $notificationEmail));
            }
        }
    }

    if (empty($recipientEmails)) {
        return false;
    }

    // Get subject template
    $subjectTemplate = get_setting('chat_admin_notification_subject') ?: 'New chat message from {store_name}';
    $subject = str_replace('{store_name}', $store['name'], $subjectTemplate);

    // Get admin panel URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $adminUrl = $protocol . '://' . $host . '/admin/chat.php?store_id=' . $store_id;

    // Get admin first name (for last responding admin)
    $adminFirstName = 'Admin';
    $lastAdmin = get_last_responding_admin($store_id);
    if ($lastAdmin && !empty($lastAdmin['first_name'])) {
        $adminFirstName = $lastAdmin['first_name'];
    }

    $defaultFromName = get_setting('email_from_name') ?: 'Cosmick Media';

    $emailBody = "Hi {$adminFirstName},\n\n";
    $emailBody .= "You have a new chat message from {$store['name']}:\n\n";
    $emailBody .= "From: {$senderName}\n";
    $emailBody .= "=====================================\n";
    $emailBody .= $message . "\n";
    $emailBody .= "=====================================\n\n";
    $emailBody .= "Log in to the admin panel to respond:\n";
    $emailBody .= $adminUrl . "\n\n";
    $emailBody .= "Best regards,\n" . $defaultFromName;

    $emailsSent = false;

    foreach ($recipientEmails as $email) {
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Use company name as sender for system notifications
            if (send_email($email, $subject, $emailBody, null, $defaultFromName)) {
                $emailsSent = true;
            }
        }
    }

    if ($emailsSent) {
        record_chat_email_sent('to_admin', $store_id);
    }

    return $emailsSent;
}
