<?php
require_once __DIR__.'/lib/db.php';
require_once __DIR__.'/lib/helpers.php';
require_once __DIR__.'/lib/auth.php';
require_once __DIR__.'/lib/drive.php';

ensure_session();
$pdo = get_pdo();

$isAdmin = isset($_SESSION['user_id']);
$store_id = 0;
if ($isAdmin) {
    require_login();
    $store_id = intval($_POST['store_id'] ?? 0);
} else {
    $store_id = intval($_SESSION['store_id'] ?? 0);
}
if ($store_id <= 0 || empty($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$parent_id = intval($_POST['parent_id'] ?? 0) ?: null;
$tmp = $_FILES['file']['tmp_name'];
$orig = $_FILES['file']['name'];
$size = $_FILES['file']['size'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmp);
finfo_close($finfo);

try {
    $folderId = get_or_create_store_folder($store_id);
    $driveId = drive_upload($tmp, $mime, $orig, $folderId);
    $ins = $pdo->prepare('INSERT INTO uploads (store_id, filename, created_at, ip, mime, size, drive_id) VALUES (?, ?, NOW(), ?, ?, ?, ?)');
    $ins->execute([$store_id, $orig, $_SERVER['REMOTE_ADDR'] ?? '', $mime, $size, $driveId]);
    $upload_id = $pdo->lastInsertId();

    $sender = $isAdmin ? 'admin' : 'store';
    $read_admin = $isAdmin ? 1 : 0;
    $read_store = $isAdmin ? 0 : 1;
    $msg = sanitize_message($_POST['message'] ?? $orig);
    $stmt = $pdo->prepare('INSERT INTO store_messages (store_id, sender, message, parent_id, upload_id, created_at, read_by_admin, read_by_store) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)');
    $stmt->execute([$store_id, $sender, $msg, $parent_id, $upload_id, $read_admin, $read_store]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

