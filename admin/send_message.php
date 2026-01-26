<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/chat_notifications.php';

require_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$store_id = intval($_POST['store_id'] ?? 0);
$message  = sanitize_message($_POST['message'] ?? '');
$parent    = intval($_POST['parent_id'] ?? 0) ?: null;
$admin_user_id = $_SESSION['user_id'] ?? null;

if ($store_id <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO store_messages (store_id, admin_user_id, sender, message, parent_id, created_at, read_by_admin, read_by_store) VALUES (?, ?, 'admin', ?, ?, NOW(), 1, 0)");
$stmt->execute([$store_id, $admin_user_id, $message, $parent]);

// Send email notification to store users
send_chat_email_to_store($store_id, $message, $admin_user_id);

echo json_encode(['success' => true]);

