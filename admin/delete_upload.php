<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid request']);
