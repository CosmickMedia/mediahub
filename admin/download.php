<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/drive.php';

// Require admin login
require_login();

// Get the upload ID
$id = $_GET['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

try {
    $pdo = get_pdo();

    // Get file details
    $stmt = $pdo->prepare('SELECT drive_id, filename FROM uploads WHERE id = ?');
    $stmt->execute([$id]);
    $upload = $stmt->fetch();

    if (!$upload || !$upload['drive_id']) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }

    // Get access token for Google Drive
    $token = drive_get_access_token();

    // Create the download URL with proper authentication
    $downloadUrl = "https://www.googleapis.com/drive/v3/files/{$upload['drive_id']}?alt=media";

    // Initialize cURL to fetch the file
    $ch = curl_init($downloadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token"
        ],
        CURLOPT_HEADER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    if ($httpCode !== 200) {
        // If authentication fails, try public download URL
        curl_close($ch);
        header('Location: https://drive.google.com/uc?id=' . $upload['drive_id'] . '&export=download');
        exit;
    }

    // Extract headers and body
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    // Parse content type from headers
    $contentType = 'application/octet-stream';
    if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $matches)) {
        $contentType = trim($matches[1]);
    }

    curl_close($ch);

    // Set appropriate headers for download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $upload['filename'] . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-cache, must-revalidate');

    // Output the file content
    echo $body;
    exit;

} catch (Exception $e) {
    // Log error and fallback to public URL
    error_log('Download error: ' . $e->getMessage());

    // Try fallback to public download URL
    if (isset($upload['drive_id'])) {
        header('Location: https://drive.google.com/uc?id=' . $upload['drive_id'] . '&export=download');
        exit;
    }

    http_response_code(500);
    echo 'Download failed';
}
?>