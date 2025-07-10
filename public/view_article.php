<?php
require_once __DIR__.'/../lib/db.php';

session_start();

// Check if logged in
if (!isset($_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$store_id = $_SESSION['store_id'];
$article_id = $_GET['id'] ?? 0;

if (!$article_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid article ID']);
    exit;
}

$pdo = get_pdo();

// Get article - verify it belongs to this store
$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ? AND store_id = ?');
$stmt->execute([$article_id, $store_id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo json_encode(['success' => false, 'error' => 'Article not found']);
    exit;
}

// Format the response
$article['created_at'] = date('F j, Y g:i A', strtotime($article['created_at']));
if ($article['updated_at']) {
    $article['updated_at'] = date('F j, Y g:i A', strtotime($article['updated_at']));
}

echo json_encode([
    'success' => true,
    'article' => $article
]);
?>