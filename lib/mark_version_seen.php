<?php
/**
 * AJAX endpoint to mark a version as seen
 * Called when user dismisses the "What's New" popup
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/version.php';

header('Content-Type: application/json');

// Detect which session exists by checking cookies BEFORE starting any session
// This prevents corrupting the user's session by starting the wrong one first
$hasAdminSession = isset($_COOKIE['cm_admin_session']);
$hasPublicSession = isset($_COOKIE['cm_public_session']);

$adminUserId = null;
$storeUserId = null;

if ($hasAdminSession) {
    session_name('cm_admin_session');
    session_start();
    $adminUserId = $_SESSION['user_id'] ?? null;
} elseif ($hasPublicSession) {
    session_name('cm_public_session');
    session_start();
    $storeUserId = $_SESSION['store_user_id'] ?? null;
}

try {
    $pdo = get_pdo();
    $version = getCurrentVersion();

    // Set cookie as backup (1 year expiry) - works even if DB update fails
    setcookie('whats_new_seen', $version, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    if (!empty($adminUserId)) {
        // Admin user
        markVersionSeen($pdo, $adminUserId, 'admin', $version);
        $_SESSION['last_seen_version'] = $version;
        echo json_encode(['success' => true, 'type' => 'admin', 'version' => $version]);
    } elseif (!empty($storeUserId)) {
        // Store user
        markVersionSeen($pdo, $storeUserId, 'store', $version);
        $_SESSION['last_seen_version'] = $version;
        echo json_encode(['success' => true, 'type' => 'store', 'version' => $version]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    }
} catch (Exception $e) {
    error_log("mark_version_seen error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
