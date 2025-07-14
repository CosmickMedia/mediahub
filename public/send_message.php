<?php
require_once __DIR__.'/../lib/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!isset($_SESSION['store_id'])) {
    http_response_code(403);
    exit('Not logged in');
}

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    http_response_code(400);
    exit('Message cannot be empty');
}

$pdo = get_pdo();
$stmt = $pdo->prepare("INSERT INTO store_messages (store_id, sender, message, created_at) VALUES (?, 'store', ?, NOW())");
$stmt->execute([$_SESSION['store_id'], $message]);

if (!empty($_POST['ajax'])) {
    echo json_encode(['success' => true]);
} else {
    header('Location: messages.php');
}
