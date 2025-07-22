<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/drive.php';

// Require login for admin thumbnails
require_login();

// Get the upload ID
$id = intval($_GET['id'] ?? 0);
$size = $_GET['size'] ?? 'medium';

$cacheDir = __DIR__ . '/../assets/thumbs';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// Get upload details
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT drive_id, mime FROM uploads WHERE id = ?');
$stmt->execute([$id]);
$upload = $stmt->fetch();

if (!$upload) {
    // Return a 1x1 transparent pixel if not found
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    exit;
}

$isVideo = strpos($upload['mime'], 'video') !== false;
$ext = $isVideo ? 'svg' : 'jpg';
$cacheFile = "$cacheDir/{$id}_{$size}.{$ext}";

if (file_exists($cacheFile)) {
    header('Content-Type: ' . ($isVideo ? 'image/svg+xml' : 'image/jpeg'));
    readfile($cacheFile);
    exit;
}

// Check if it's a video
if ($isVideo) {
    // Generate video placeholder and cache it
    $width = $size === 'small' ? 50 : ($size === 'medium' ? 200 : 400);
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="'.$width.'" height="'.$width.'" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <rect width="100" height="100" fill="#000"/>
        <circle cx="50" cy="50" r="20" fill="none" stroke="#fff" stroke-width="3"/>
        <polygon points="45,40 45,60 60,50" fill="#fff"/>
    </svg>';
    file_put_contents($cacheFile, $svg);
    header('Content-Type: image/svg+xml');
    echo $svg;
    exit;
}

try {
    // Get access token
    $token = drive_get_access_token();

    // Determine thumbnail size
    $widths = [
        'small' => 100,
        'medium' => 400,
        'large' => 800
    ];
    $width = $widths[$size] ?? 400;

    // Try to get thumbnail from Google Drive
    $thumbnailUrl = "https://www.googleapis.com/drive/v3/files/{$upload['drive_id']}?alt=media&access_token={$token}";

    // For images, we can try to get a smaller version using the Drive API export
    $exportUrl = "https://www.googleapis.com/drive/v3/files/{$upload['drive_id']}/export?mimeType=image/jpeg&access_token={$token}";

    // First try the thumbnail metadata
    $metadataUrl = "https://www.googleapis.com/drive/v3/files/{$upload['drive_id']}?fields=thumbnailLink,hasThumbnail&access_token={$token}";

    $ch = curl_init($metadataUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]
    ]);

    $metadataResult = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $metadata = json_decode($metadataResult, true);
        if (!empty($metadata['thumbnailLink'])) {
            // Use the thumbnail link
            $thumbnailUrl = str_replace('=s220', '=s'.$width, $metadata['thumbnailLink']);

            $ch = curl_init($thumbnailUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]
            ]);

            $imageData = curl_exec($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $imageData) {
                file_put_contents($cacheFile, $imageData);
                header('Content-Type: ' . $contentType);
                header('Cache-Control: public, max-age=86400');
                echo $imageData;
                exit;
            }
        }
    }

    // If no thumbnail, return placeholder
    throw new Exception('No thumbnail available');

} catch (Exception $e) {
    $width = $size === 'small' ? 50 : ($size === 'medium' ? 200 : 400);
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="'.$width.'" height="'.$width.'" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <rect width="100" height="100" fill="#e9ecef"/>
        <path d="M30 70 L30 40 L45 25 L55 25 L70 40 L70 70 Z" fill="#6c757d"/>
        <circle cx="45" cy="45" r="8" fill="#e9ecef"/>
    </svg>';
    file_put_contents($cacheFile, $svg);
    header('Content-Type: image/svg+xml');
    echo $svg;
}
?>