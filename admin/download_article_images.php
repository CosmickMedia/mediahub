<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();

$article_id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

if (!$article_id) {
    http_response_code(400);
    exit('Invalid article ID');
}

$pdo = get_pdo();

// Get article with images
$stmt = $pdo->prepare('SELECT images FROM articles WHERE id = ?');
$stmt->execute([$article_id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    http_response_code(404);
    exit('Article not found');
}

// Parse images
$images = [];
if (!empty($article['images'])) {
    $decoded = json_decode($article['images'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $img) {
            $path = $img['local_path'] ?? '';
            if ($path) {
                $fullPath = __DIR__ . '/../public/' . ltrim($path, '/');
                if (file_exists($fullPath)) {
                    $images[] = [
                        'path' => $fullPath,
                        'filename' => $img['filename'] ?? basename($path)
                    ];
                }
            }
        }
    }
}

if (empty($images)) {
    http_response_code(404);
    exit('No images found');
}

// Handle download all as ZIP
if ($action === 'download_all') {
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('ZIP functionality not available');
    }

    $zip = new ZipArchive();
    $zipFilename = tempnam(sys_get_temp_dir(), 'article_images_') . '.zip';

    if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
        http_response_code(500);
        exit('Could not create ZIP file');
    }

    foreach ($images as $img) {
        $zip->addFile($img['path'], $img['filename']);
    }

    $zip->close();

    // Send ZIP file
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="article_' . $article_id . '_images.zip"');
    header('Content-Length: ' . filesize($zipFilename));

    readfile($zipFilename);
    unlink($zipFilename);
    exit;
}

// If no specific action, return error
http_response_code(400);
exit('Invalid action');
?>
