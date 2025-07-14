<?php
require_once __DIR__.'/lib/db.php';
require_once __DIR__.'/lib/auth.php';

ensure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$id = intval($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';
if (!$id || !in_array($type, ['like','love'], true)) {
    http_response_code(400);
    exit('Invalid');
}

$pdo = get_pdo();
$isAdmin = isset($_SESSION['user_id']);
$col = ($type === 'like') ? ($isAdmin ? 'like_by_admin' : 'like_by_store')
                          : ($isAdmin ? 'love_by_admin' : 'love_by_store');

$stmt = $pdo->prepare("SELECT $col FROM store_messages WHERE id=?");
$stmt->execute([$id]);
$current = (int)$stmt->fetchColumn();
$new = $current ? 0 : 1;

$upd = $pdo->prepare("UPDATE store_messages SET $col=? WHERE id=?");
$upd->execute([$new, $id]);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'active' => $new]);

