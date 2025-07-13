<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();

$article_id = $_GET['id'] ?? 0;

if (!$article_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid article ID']);
    exit;
}

$pdo = get_pdo();

// Get article with store info
$stmt = $pdo->prepare('
    SELECT a.*, s.name as store_name 
    FROM articles a 
    JOIN stores s ON a.store_id = s.id 
    WHERE a.id = ?
');
$stmt->execute([$article_id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo json_encode(['success' => false, 'error' => 'Article not found']);
    exit;
}

// Format dates
$article['created_at'] = format_ts($article['created_at']);
if ($article['updated_at']) {
    $article['updated_at'] = format_ts($article['updated_at']);
}

// Get status class for badge
$statusClass = [
    'draft' => 'secondary',
    'submitted' => 'info',
    'approved' => 'success',
    'rejected' => 'danger'
][$article['status']] ?? 'secondary';

echo json_encode([
    'success' => true,
    'article' => $article,
    'statusClass' => $statusClass
]);
?>