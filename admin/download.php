<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare('SELECT drive_id FROM uploads WHERE id=?');
$stmt->execute([$id]);
$driveId = $stmt->fetchColumn();
if (!$driveId) {
    http_response_code(404);
    echo 'File not found';
    exit;
}
header('Location: https://drive.google.com/uc?id='.$driveId.'&export=download');
