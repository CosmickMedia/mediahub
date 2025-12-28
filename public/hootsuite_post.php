<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/settings.php';

/**
 * Hootsuite Post Handler
 *
 * Image Requirements by Platform (via Hootsuite API):
 * - Facebook: Most formats accepted, max 10MB
 * - Twitter/X: JPG, PNG, GIF, max 5MB
 * - LinkedIn: Most formats accepted, max 10MB
 * - Instagram: JPG, PNG only, aspect ratios 1.91:1 to 4:5, min 320px, max 1080px width
 * - Pinterest: JPG, PNG only, 2:3 aspect ratio preferred, max 20MB
 * - Threads: Similar to Instagram requirements
 *
 * Note: Instagram and Pinterest often have API restrictions for media uploads
 * and may require special handling or may not support media via API at all.
 */

ensure_session();
header('Content-Type: application/json');

// Allow admin to perform certain actions (like delete) by passing
// `admin_delete=1` and providing a store_id via POST. Admin sessions use
// `user_id` rather than store-specific session variables.
$is_admin_action = !empty($_POST['admin_delete']) && !empty($_SESSION['user_id']);

if ($is_admin_action) {
    // For admin actions, store_id must be supplied explicitly
    $store_id = (int)($_POST['store_id'] ?? 0);
    if ($store_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing store id']);
        exit;
    }
    // Use admin user id for logging if needed, but skip store user checks
    $user_id = (int)$_SESSION['user_id'];
} else {
    // Regular store user action requires store_id and store_user_id in session
    if (!isset($_SESSION['store_id']) || !isset($_SESSION['store_user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $store_id = (int)$_SESSION['store_id'];
    $user_id  = (int)$_SESSION['store_user_id'];
}

$action = $_POST['action'] ?? 'create';

$token = get_setting('hootsuite_access_token');
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Missing Hootsuite token']);
    exit;
}

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT hootsuite_profile_ids FROM stores WHERE id=?');
$stmt->execute([$store_id]);
$allowed_profiles = array_filter(array_map('trim', explode(',', (string)$stmt->fetchColumn())));

// Fetch the name of the store user for "posted by" info
$user_name = '';
$stmt = $pdo->prepare('SELECT first_name, last_name, email FROM store_users WHERE id=? AND store_id=?');
$stmt->execute([$user_id, $store_id]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $user_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if ($user_name === '') {
        $user_name = $row['email'] ?? '';
    }
}

/**
 * Compress image if needed to ensure fast Hootsuite processing
 * Target: <100KB for optimal speed (2-4 sec), max 1MB
 * Returns: [compressed_path, new_size, was_compressed] or false on error
 */
function compressImageIfNeeded($filePath, $mimeType) {
    $originalSize = filesize($filePath);

    // Get compression settings from admin settings
    $targetSize = (int)(get_setting('hootsuite_target_file_size') ?: 100) * 1024; // Default 100KB
    $maxSize = (int)(get_setting('hootsuite_max_file_size') ?: 800) * 1024; // Default 800KB
    $initialQuality = (int)(get_setting('hootsuite_compression_quality') ?: 85); // Default 85

    // Only compress if needed
    if ($originalSize <= $targetSize) {
        error_log("Image is small enough ($originalSize bytes), no compression needed");
        return [$filePath, $originalSize, false];
    }

    error_log("Image needs compression: $originalSize bytes → target $targetSize bytes");

    // Check if GD is available
    if (!function_exists('imagecreatefromjpeg')) {
        error_log("GD library not available, cannot compress images");
        return false;
    }

    // Load image based on type
    $image = null;
    $imageType = null;

    if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
        $image = @imagecreatefromjpeg($filePath);
        $imageType = 'jpeg';
    } elseif ($mimeType === 'image/png') {
        $image = @imagecreatefrompng($filePath);
        $imageType = 'png';
    } elseif ($mimeType === 'image/webp') {
        $image = @imagecreatefromwebp($filePath);
        $imageType = 'webp';
    } else {
        error_log("Unsupported image type for compression: $mimeType");
        return false;
    }

    if (!$image) {
        error_log("Failed to load image for compression");
        return false;
    }

    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    error_log("Original dimensions: {$width}x{$height}");

    // Try compression with quality reduction first
    $quality = $initialQuality; // Use admin setting
    $tempPath = $filePath . '.compressed.jpg';
    $compressed = false;

    while ($quality >= 50 && !$compressed) {
        // Convert to JPEG for better compression
        imagejpeg($image, $tempPath, $quality);
        $newSize = filesize($tempPath);

        error_log("Compression attempt: quality=$quality, size=$newSize bytes");

        if ($newSize <= $maxSize) {
            $compressed = true;
            error_log("SUCCESS: Compressed to $newSize bytes at quality $quality");
        } else {
            $quality -= 10;
        }
    }

    // If quality reduction isn't enough, try resizing
    if (!$compressed) {
        error_log("Quality reduction not enough, trying resize...");

        // Calculate new dimensions (reduce by 20% each iteration)
        $scale = 0.8;
        while ($scale >= 0.3 && !$compressed) {
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            imagejpeg($resized, $tempPath, 75);
            $newSize = filesize($tempPath);

            error_log("Resize attempt: {$newWidth}x{$newHeight}, size=$newSize bytes");

            if ($newSize <= $maxSize) {
                $compressed = true;
                error_log("SUCCESS: Resized to {$newWidth}x{$newHeight}, $newSize bytes");
            }

            imagedestroy($resized);
            $scale -= 0.1;
        }
    }

    imagedestroy($image);

    if (!$compressed) {
        error_log("Failed to compress image under $maxSize bytes");
        @unlink($tempPath);
        return false;
    }

    $finalSize = filesize($tempPath);
    $reduction = round((1 - $finalSize / $originalSize) * 100);
    error_log("Image compressed: $originalSize → $finalSize bytes ($reduction% reduction)");

    return [$tempPath, $finalSize, true];
}

/**
 * Upload media to Hootsuite using their 3-step process
 * Step 1: Request upload URL (POST /v1/media)
 * Step 2: Upload to S3
 * Step 3: Poll until media is READY (downloadUrl is not null)
 */
function uploadMediaToHootsuite($token, $filePath, $fileName, $mimeType) {
    error_log("Starting Hootsuite media upload for: $fileName");

    $fileSize = filesize($filePath);
    error_log("File size: $fileSize bytes, MIME type: $mimeType");

    // Step 1: Request upload URL from Hootsuite
    $uploadRequestPayload = [
        'sizeBytes' => $fileSize,
        'mimeType' => $mimeType
    ];

    $ch = curl_init('https://platform.hootsuite.com/v1/media');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($uploadRequestPayload)
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 && $code !== 201) {
        error_log("Failed to get upload URL (code $code): $response");
        return null;
    }

    $uploadData = json_decode($response, true);
    $uploadUrl = $uploadData['data']['uploadUrl'] ?? null;
    $mediaId = $uploadData['data']['id'] ?? null;

    if (!$uploadUrl || !$mediaId) {
        error_log("Missing upload URL or media ID");
        return null;
    }

    error_log("Got media ID: $mediaId");

    // Step 2: Upload file to S3
    $fileContent = file_get_contents($filePath);

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $mimeType,
            'Content-Length: ' . strlen($fileContent)
        ]
    ]);

    $s3Response = curl_exec($ch);
    $s3Code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($s3Code !== 200) {
        error_log("S3 upload failed (code $s3Code): $s3Response");
        return null;
    }

    error_log("S3 upload successful");

    // Step 3: CRITICAL - Must wait for READY state
    // Media attached in QUEUED state will silently fail (post created but no media)
    // Based on Hootsuite support findings:
    // - Files <100KB: Process to READY in 2-4 seconds
    // - Files >1MB: Can take 30+ seconds or get stuck indefinitely

    $maxAttempts = 20;  // Up to 60 seconds with exponential backoff
    $attempt = 0;
    $mediaReady = false;

    error_log("Polling for media READY state (file size: $fileSize bytes)...");

    while ($attempt < $maxAttempts && !$mediaReady) {
        $attempt++;

        // Exponential backoff: 1s, 2s, 3s, 3s, 3s...
        if ($attempt > 1) {
            $waitTime = min($attempt, 3);
            sleep($waitTime);
        }

        $ch = curl_init('https://platform.hootsuite.com/v1/media/' . urlencode($mediaId));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);

        $verifyResponse = curl_exec($ch);
        $verifyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($verifyCode === 200) {
            $verifyData = json_decode($verifyResponse, true);
            $state = $verifyData['data']['state'] ?? null;

            error_log("Attempt $attempt: Media state = $state");

            if ($state === 'READY') {
                error_log("SUCCESS: Media is READY after $attempt attempts!");
                $mediaReady = true;
                break;
            } else if ($state === 'FAILED' || $state === 'ERROR') {
                error_log("FAILED: Media processing failed (state: $state)");
                return null;
            }
            // Continue polling if QUEUED or PROCESSING
        } else {
            error_log("Attempt $attempt: Failed to check status (HTTP $verifyCode)");
        }
    }

    if (!$mediaReady) {
        error_log("TIMEOUT: Media did not reach READY state after $maxAttempts attempts");
        error_log("File size was $fileSize bytes - large files may need manual upload");
        return null;  // Return null to post without media
    }

    return $mediaId;
}

/**
 * Check which profiles support media
 */
function getProfileCapabilities($token, $profileId) {
    $ch = curl_init('https://platform.hootsuite.com/v1/socialProfiles/' . urlencode($profileId));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($response, true);
        // Log profile capabilities for debugging
        error_log("Profile $profileId capabilities: " . json_encode($data['data'] ?? []));
        return $data['data'] ?? [];
    }

    return null;
}

/**
 * Determine if a profile supports media based on network type
 * Instagram and Pinterest often have specific requirements or restrictions
 */
function profileSupportsMedia($pdo, $profileId) {
    $stmt = $pdo->prepare('SELECT network FROM hootsuite_profiles WHERE id=?');
    $stmt->execute([$profileId]);
    $network = strtolower($stmt->fetchColumn() ?: '');

    // Check if platform has auto-cropping enabled
    $platformSettings = getPlatformImageSettings($pdo, $network);

    // If auto-cropping is enabled for this platform, media is supported
    if ($platformSettings && !empty($platformSettings['enabled'])) {
        error_log("Profile $profileId is $network - auto-crop enabled, media supported");
        return true;
    }

    // Networks that commonly have media issues via API (when auto-crop is disabled)
    $restrictedNetworks = ['instagram', 'pinterest'];

    if (in_array($network, $restrictedNetworks)) {
        error_log("Profile $profileId is $network - auto-crop disabled, skipping media");
        return false; // Conservative approach - skip media for these networks without auto-crop
    }

    return true;
}

/**
 * Get platform-specific image settings (from database or defaults)
 */
function getPlatformImageSettings($pdo, $network) {
    $network = strtolower(trim($network));

    // Try to get custom settings from database first
    $stmt = $pdo->prepare('SELECT * FROM social_network_image_settings WHERE LOWER(network_name) = ?');
    $stmt->execute([$network]);

    if ($settings = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return $settings;
    }

    // Return hardcoded defaults if table doesn't exist or no custom settings
    return getDefaultPlatformSettings($network);
}

/**
 * Get default platform image settings
 */
function getDefaultPlatformSettings($network) {
    $defaults = [
        'instagram' => [
            'enabled' => 1,
            'aspect_ratio' => '1:1',
            'target_width' => 1080,
            'target_height' => 1080,
            'min_width' => 320,
            'max_file_size_kb' => 5120
        ],
        'facebook' => [
            'enabled' => 1,
            'aspect_ratio' => '1.91:1',
            'target_width' => 1200,
            'target_height' => 630,
            'min_width' => 200,
            'max_file_size_kb' => 10240
        ],
        'linkedin' => [
            'enabled' => 1,
            'aspect_ratio' => '1.91:1',
            'target_width' => 1200,
            'target_height' => 627,
            'min_width' => 200,
            'max_file_size_kb' => 10240
        ],
        'x' => [
            'enabled' => 1,
            'aspect_ratio' => '1:1',
            'target_width' => 1080,
            'target_height' => 1080,
            'min_width' => 200,
            'max_file_size_kb' => 5120
        ],
        'twitter' => [
            'enabled' => 1,
            'aspect_ratio' => '1:1',
            'target_width' => 1080,
            'target_height' => 1080,
            'min_width' => 200,
            'max_file_size_kb' => 5120
        ],
        'threads' => [
            'enabled' => 1,
            'aspect_ratio' => '9:16',
            'target_width' => 1080,
            'target_height' => 1920,
            'min_width' => 320,
            'max_file_size_kb' => 10240
        ],
        'pinterest' => [
            'enabled' => 1,
            'aspect_ratio' => '2:3',
            'target_width' => 1000,
            'target_height' => 1500,
            'min_width' => 200,
            'max_file_size_kb' => 20480
        ]
    ];

    return $defaults[$network] ?? [
        'enabled' => 1,
        'aspect_ratio' => '1:1',
        'target_width' => 1080,
        'target_height' => 1080,
        'min_width' => 200,
        'max_file_size_kb' => 5120
    ];
}

/**
 * Platform character limits for text truncation
 * Used to auto-truncate posts that exceed platform limits
 */
$GLOBALS['platformCharLimits'] = [
    'TWITTER' => 280,
    'PINTEREST' => 500,
    'INSTAGRAM' => 2200,
    'INSTAGRAMBUSINESS' => 2200,
    'TIKTOK' => 2200,
    'LINKEDIN' => 3000,
    'LINKEDINCOMPANY' => 3000,
    'YOUTUBE' => 5000,
    'FACEBOOK' => 63206,
    'FACEBOOKPAGE' => 63206
];

/**
 * Truncate text for a specific platform if it exceeds the character limit
 * Adds "..." at the end if truncated
 */
function truncateForPlatform($text, $platformType) {
    $limits = $GLOBALS['platformCharLimits'];
    $limit = $limits[strtoupper($platformType)] ?? 3000;

    if (mb_strlen($text) > $limit) {
        // Truncate and add ellipsis (leave room for "...")
        return mb_substr($text, 0, $limit - 3) . '...';
    }
    return $text;
}

/**
 * Get the minimum character limit from a list of profile IDs
 */
function getMinCharLimitForProfiles($pdo, $profileIds) {
    if (empty($profileIds)) {
        return 3000;
    }

    $limits = $GLOBALS['platformCharLimits'];
    $minLimit = 3000;

    $placeholders = implode(',', array_fill(0, count($profileIds), '?'));
    $stmt = $pdo->prepare("SELECT UPPER(type) as type FROM hootsuite_profiles WHERE id IN ($placeholders)");
    $stmt->execute($profileIds);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['type'] ?? '';
        $limit = $limits[$type] ?? 3000;
        $minLimit = min($minLimit, $limit);
    }

    return $minLimit;
}

/**
 * Crop and resize image to specific aspect ratio from center
 * Returns path to cropped image or false on error
 */
function cropImageToAspectRatio($sourcePath, $targetWidth, $targetHeight, $outputPath = null) {
    if (!file_exists($sourcePath)) {
        error_log("Source image not found: $sourcePath");
        return false;
    }

    // Get source image info
    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) {
        error_log("Could not get image info for: $sourcePath");
        return false;
    }

    list($srcWidth, $srcHeight, $imageType) = $imageInfo;
    error_log("Source image: {$srcWidth}x{$srcHeight}, Target: {$targetWidth}x{$targetHeight}");

    // Load source image based on type
    $srcImage = null;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $srcImage = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $srcImage = @imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $srcImage = @imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $srcImage = @imagecreatefromwebp($sourcePath);
            break;
        default:
            error_log("Unsupported image type: $imageType");
            return false;
    }

    if (!$srcImage) {
        error_log("Failed to create image resource from: $sourcePath");
        return false;
    }

    // Calculate target aspect ratio
    $targetRatio = $targetWidth / $targetHeight;
    $srcRatio = $srcWidth / $srcHeight;

    // Calculate crop dimensions (crop from center)
    if ($srcRatio > $targetRatio) {
        // Source is wider - crop width
        $cropHeight = $srcHeight;
        $cropWidth = (int)($srcHeight * $targetRatio);
        $cropX = (int)(($srcWidth - $cropWidth) / 2);
        $cropY = 0;
    } else {
        // Source is taller - crop height
        $cropWidth = $srcWidth;
        $cropHeight = (int)($srcWidth / $targetRatio);
        $cropX = 0;
        $cropY = (int)(($srcHeight - $cropHeight) / 2);
    }

    error_log("Crop area: {$cropWidth}x{$cropHeight} from ({$cropX},{$cropY})");

    // Create destination image
    $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

    // Preserve transparency for PNG and WebP
    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_WEBP) {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
        imagefilledrectangle($dstImage, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    // Copy and resize
    imagecopyresampled(
        $dstImage, $srcImage,
        0, 0, $cropX, $cropY,
        $targetWidth, $targetHeight,
        $cropWidth, $cropHeight
    );

    // Generate output path if not provided
    if (!$outputPath) {
        $pathInfo = pathinfo($sourcePath);
        $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_cropped_' . $targetWidth . 'x' . $targetHeight . '.jpg';
    }

    // Save as JPEG for best compression
    $success = imagejpeg($dstImage, $outputPath, 90);

    // Clean up
    imagedestroy($srcImage);
    imagedestroy($dstImage);

    if ($success) {
        error_log("Cropped image saved to: $outputPath");
        return $outputPath;
    } else {
        error_log("Failed to save cropped image to: $outputPath");
        return false;
    }
}

if ($action === 'update') {
    // Hootsuite API does not support editing scheduled posts via PUT/PATCH
    // We must delete and recreate the post with updated content
    $post_id = $_POST['post_id'] ?? '';

    if ($post_id === '') {
        echo json_encode(['success' => false, 'error' => 'Missing post id']);
        exit;
    }

    // Fetch existing post data BEFORE deleting (to preserve media if not uploading new files)
    $stmt = $pdo->prepare('SELECT created_by_user_id, media_urls FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);
    $existingPost = $stmt->fetch(PDO::FETCH_ASSOC);
    $owner = $existingPost['created_by_user_id'] ?? null;
    $existingMediaUrls = $existingPost['media_urls'] ?? null;

    if (!$is_admin_action && $owner && $owner != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Not authorized to update this post']);
        exit;
    }

    // Store existing media URLs for use if no new media is uploaded
    $_SESSION['existing_media_urls_for_update'] = $existingMediaUrls;
    error_log("Preserved existing media URLs: " . ($existingMediaUrls ?: 'none'));

    // Delete the existing post from Hootsuite
    error_log("Deleting existing post $post_id before recreating with updates");
    $ch = curl_init('https://platform.hootsuite.com/v1/messages/' . urlencode($post_id));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE'
    ]);
    $deleteResponse = curl_exec($ch);
    $deleteCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the delete attempt (don't fail if delete fails - post might already be sent/deleted)
    error_log("Delete attempt for post $post_id: HTTP $deleteCode - Response: $deleteResponse");

    // Delete from database
    $stmt = $pdo->prepare('DELETE FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);

    // Now change action to 'create' to recreate the post with updated content
    $action = 'create';
    error_log("Post deleted, now recreating with updated content");
}

if ($action === 'create') {
    $text = trim($_POST['text'] ?? '');
    $scheduled = $_POST['scheduled_time'] ?? '';
    $profile_ids = $_POST['profile_ids'] ?? ($_POST['profile_id'] ?? []);
    if (!is_array($profile_ids)) $profile_ids = [$profile_ids];
    $profile_ids = array_values(array_filter(array_map('trim', $profile_ids)));
    $hashtags = trim($_POST['hashtags'] ?? '');
    $post_id = $_POST['post_id'] ?? null;

    if ($text === '' || $scheduled === '' || empty($profile_ids)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    $ts = strtotime($scheduled);
    if ($ts === false || $ts <= time()) {
        echo json_encode(['success' => false, 'error' => 'Schedule time must be in the future']);
        exit;
    }

    $invalid = array_diff($profile_ids, $allowed_profiles);
    if (!empty($invalid)) {
        echo json_encode(['success' => false, 'error' => 'Invalid profile selected']);
        exit;
    }

    $tagsArr = [];
    if ($hashtags !== '') {
        $tagsArr = array_values(array_filter(array_map('trim', explode(',', $hashtags))));
    }

    // Save media files locally first
    $localMediaPaths = [];
    $mediaUrls = [];

    if (!empty($_FILES['media'])) {
        // New media uploaded - clear any existing media from update
        unset($_SESSION['existing_media_urls_for_update']);
        // Check if it's a single file or multiple files
        $isSingleFile = !is_array($_FILES['media']['name']);

        $uploadDir = __DIR__ . '/calendar_media/' . date('Y/m/', $ts);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if ($isSingleFile && !empty($_FILES['media']['tmp_name'])) {
            // Single file upload
            $fileName = $_FILES['media']['name'];
            $tmpName = $_FILES['media']['tmp_name'];
            $mimeType = $_FILES['media']['type'];

            error_log("Processing single media upload: $fileName");

            $savedFileName = time() . '_0_' . basename($fileName);
            $localPath = $uploadDir . $savedFileName;

            if (move_uploaded_file($tmpName, $localPath)) {
                error_log("File saved locally: $localPath");
                $localMediaPaths[] = [
                    'path' => $localPath,
                    'name' => $fileName,
                    'mime' => $mimeType
                ];
                $mediaUrls[] = '/public/calendar_media/' . date('Y/m/', $ts) . $savedFileName;
            } else {
                error_log("Failed to save file locally");
            }
        } elseif (!$isSingleFile) {
            // Multiple files upload
            $fileCount = count($_FILES['media']['name']);
            error_log("Processing multiple files upload: $fileCount files");

            for ($i = 0; $i < $fileCount; $i++) {
                if (!empty($_FILES['media']['tmp_name'][$i])) {
                    $fileName = $_FILES['media']['name'][$i];
                    $tmpName = $_FILES['media']['tmp_name'][$i];
                    $mimeType = $_FILES['media']['type'][$i];

                    error_log("Processing file $i: $fileName");

                    $savedFileName = time() . '_' . $i . '_' . basename($fileName);
                    $localPath = $uploadDir . $savedFileName;

                    if (move_uploaded_file($tmpName, $localPath)) {
                        error_log("File $i saved locally: $localPath");

                        // Add all files to localMediaPaths (not just the first file)
                        $localMediaPaths[] = [
                            'path' => $localPath,
                            'name' => $fileName,
                            'mime' => $mimeType
                        ];

                        $mediaUrls[] = '/public/calendar_media/' . date('Y/m/', $ts) . $savedFileName;
                    } else {
                        error_log("Failed to save file $i locally");
                    }
                }
            }
        }
    }

    // If this was an update and no new media was uploaded, use existing media from the original post
    if (empty($localMediaPaths) && !empty($_SESSION['existing_media_urls_for_update'])) {
        error_log("No new media uploaded, using existing media from original post");

        // Decode the existing media URLs
        $existingUrls = json_decode($_SESSION['existing_media_urls_for_update'], true);
        if (is_array($existingUrls)) {
            foreach ($existingUrls as $url) {
                // Convert URL to file path
                $localPath = __DIR__ . str_replace('/public', '', $url);

                if (file_exists($localPath)) {
                    error_log("Found existing media file: $localPath");

                    // Get file info
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $localPath);
                    finfo_close($finfo);

                    $localMediaPaths[] = [
                        'path' => $localPath,
                        'name' => basename($localPath),
                        'mime' => $mimeType
                    ];
                    $mediaUrls[] = $url;
                } else {
                    error_log("WARNING: Existing media file not found: $localPath");
                }
            }
        }

        // Clear the session variable after use
        unset($_SESSION['existing_media_urls_for_update']);
    }

    if ($action === 'create') {
        $events = [];
        $utc_time = gmdate('Y-m-d\TH:i:s\Z', $ts);

        // Strategy change: Try single call first with all profiles
        // If it fails with media, then try without media
        $hasMedia = !empty($localMediaPaths);
        $mediaPayload = [];
        $mediaTimedOut = false;

        if ($hasMedia) {
            // Upload all media files (with automatic compression)
            foreach ($localMediaPaths as $mediaFile) {
                $uploadPath = $mediaFile['path'];
                $uploadMime = $mediaFile['mime'];
                $wasCompressed = false;
                $compressionInfo = '';

                // Compress image if needed (only for images)
                if (strpos($uploadMime, 'image/') === 0) {
                    $compressionResult = compressImageIfNeeded($mediaFile['path'], $mediaFile['mime']);

                    if ($compressionResult && $compressionResult !== false) {
                        list($compressedPath, $compressedSize, $wasCompressed) = $compressionResult;

                        if ($wasCompressed) {
                            $uploadPath = $compressedPath;
                            $uploadMime = 'image/jpeg'; // Always JPEG after compression
                            $originalSize = filesize($mediaFile['path']);
                            $reduction = round((1 - $compressedSize / $originalSize) * 100);
                            $compressionInfo = "Compressed: " . round($originalSize/1024) . "KB → " . round($compressedSize/1024) . "KB ($reduction% reduction)";
                            error_log($compressionInfo);
                        }
                    } elseif ($compressionResult === false) {
                        error_log("Compression failed for: " . $mediaFile['name'] . " - file may be too large");
                        $mediaTimedOut = true;
                        continue; // Skip this file
                    }
                }

                $mediaId = uploadMediaToHootsuite(
                    $token,
                    $uploadPath,
                    $mediaFile['name'],
                    $uploadMime
                );

                // Clean up compressed file if created
                if ($wasCompressed && file_exists($uploadPath)) {
                    @unlink($uploadPath);
                }

                if ($mediaId) {
                    $mediaPayload[] = ['id' => $mediaId];
                    error_log("Media upload complete with ID: $mediaId");
                } else {
                    error_log("Media upload failed/timed out for: " . $mediaFile['name']);
                    $mediaTimedOut = true;
                }
            }
        }

        // Try creating with all profiles together first
        // Truncate text to the minimum limit among all selected profiles
        $minLimit = getMinCharLimitForProfiles($pdo, $profile_ids);
        $batchText = mb_strlen($text) > $minLimit ? mb_substr($text, 0, $minLimit - 3) . '...' : $text;

        $payload = [
            'text' => $batchText,
            'socialProfileIds' => $profile_ids,
            'scheduledSendTime' => $utc_time
        ];

        if ($tagsArr) {
            $payload['tags'] = $tagsArr;
        }

        if ($mediaPayload && !empty($mediaPayload[0]['id'])) {
            $payload['media'] = $mediaPayload; // Attach all media files
        }

        error_log("Attempting to create post for all profiles together: " . implode(',', $profile_ids));
        error_log("Payload: " . json_encode($payload));

        $ch = curl_init('https://platform.hootsuite.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        // Log the response
        $logFile = __DIR__ . '/hootsuite_api_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " - All Profiles: " . implode(',', $profile_ids) . " - Code: $code\n";
        $logEntry .= "Payload sent:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        $logEntry .= "Response received:\n" . $response . "\n";
        $logEntry .= "----------------------------------------\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        if ($code >= 200 && $code < 300) {
            // Success with all profiles
            $data = json_decode($response, true);
            $messages = $data['data'] ?? [];

            foreach ($messages as $msg) {
                $profile_id = $msg['socialProfile']['id'] ?? null;
                if (!$profile_id) continue;

                $postId = $msg['id'] ?? uniqid('post_');
                $state = $msg['state'] ?? 'SCHEDULED';
                $scheduledSendTime = $msg['scheduledSendTime'] ?? date('c', $ts);
                $scheduledSendTime = date('Y-m-d H:i:s', strtotime($scheduledSendTime));

                // Get network info for display
                $color = '#0d6efd';
                $icon = 'bi-share';
                $networkName = '';
                $profStmt = $pdo->prepare('SELECT network FROM hootsuite_profiles WHERE id=?');
                $profStmt->execute([$profile_id]);
                if ($netKey = strtolower($profStmt->fetchColumn() ?: '')) {
                    $netStmt = $pdo->prepare('SELECT name, icon, color FROM social_networks WHERE LOWER(name)=?');
                    $netStmt->execute([$netKey]);
                    if ($n = $netStmt->fetch()) {
                        $networkName = $n['name'] ?? '';
                        $color = $n['color'] ?? $color;
                        $icon = $n['icon'] ?? $icon;
                    }
                }

                // Save to database
                try {
                    $stmt = $pdo->prepare('INSERT INTO hootsuite_posts (post_id, store_id, text, scheduled_send_time, raw_json, state, social_profile_id, tags, media, created_by_user_id, media_urls) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE text=VALUES(text), scheduled_send_time=VALUES(scheduled_send_time), raw_json=VALUES(raw_json), state=VALUES(state), social_profile_id=VALUES(social_profile_id), tags=VALUES(tags), media=VALUES(media), created_by_user_id=VALUES(created_by_user_id), media_urls=VALUES(media_urls)');
                    $stmt->execute([
                        $postId,
                        $store_id,
                        $text,
                        $scheduledSendTime,
                        json_encode($msg),
                        $state,
                        $profile_id,
                        json_encode($tagsArr),
                        json_encode($mediaPayload),
                        $user_id,
                        json_encode($mediaUrls)
                    ]);
                } catch (Exception $e) {
                    error_log("Database error: " . $e->getMessage());
                }

                    $events[] = [
                        'id' => $postId,
                        'title' => $networkName ?: 'Post',
                        'start' => str_replace(' ', 'T', $scheduledSendTime),
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                        'classNames' => ['social-' . ($networkName ? preg_replace('/[^a-z0-9]+/','-', strtolower($networkName)) : 'default')],
                        'extendedProps' => [
                            'text' => $text,
                            'time' => str_replace(' ', 'T', $scheduledSendTime),
                            'tags' => $tagsArr,
                            'source' => 'API',
                            'post_id' => $postId,
                            'created_by_user_id' => $user_id,
                            'social_profile_id' => $profile_id,
                            'media_urls' => $mediaUrls,
                            'posted_by' => $user_name,
                            'image' => !empty($mediaUrls) && !preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                            'video' => !empty($mediaUrls) && preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                            'icon' => $icon,
                            'network' => $networkName
                        ]
                    ];
            }

            $result = ['success' => true, 'events' => $events];

            // Warn user if media timed out even after compression attempt
            if ($mediaTimedOut && empty($mediaPayload)) {
                $result['warning'] = 'Post created successfully, but the media could not be uploaded. The file may be too large or in an unsupported format. Try using a smaller image (<1MB) or manually add media in Hootsuite.';
            }

            // Clean up session variable
            unset($_SESSION['existing_media_urls_for_update']);

            echo json_encode($result);
            exit;
        }

        // If the batch call failed, try individual calls for each profile
        error_log("Batch call failed, trying individual calls for each profile");

        $successfulProfiles = [];
        $failedProfiles = [];

        foreach ($profile_ids as $profile_id) {
            error_log("Creating post for profile: $profile_id");

            // Get network name and type for this profile
            $profStmt = $pdo->prepare('SELECT network, UPPER(type) as type FROM hootsuite_profiles WHERE id=?');
            $profStmt->execute([$profile_id]);
            $profRow = $profStmt->fetch(PDO::FETCH_ASSOC);
            $networkName = strtolower($profRow['network'] ?? '');
            $profileType = $profRow['type'] ?? '';

            // Check if this profile supports media
            $supportsMedia = profileSupportsMedia($pdo, $profile_id);

            // Check profile capabilities first (optional, for logging)
            $profileInfo = getProfileCapabilities($token, $profile_id);

            // Upload media for this specific profile only if it supports it
            $profileMediaPayload = [];
            if ($hasMedia && $supportsMedia) {
                // Get platform-specific image settings once for this profile
                $platformSettings = getPlatformImageSettings($pdo, $networkName);

                // Loop through ALL media files (not just the first one)
                foreach ($localMediaPaths as $mediaFile) {
                    $uploadPath = $mediaFile['path'];
                    $uploadMime = $mediaFile['mime'];
                    $wasCompressed = false;
                    $wasCropped = false;
                    $croppedPath = null;

                    // Apply platform-specific cropping if enabled (only for images)
                    if (strpos($uploadMime, 'image/') === 0 && $platformSettings && !empty($platformSettings['enabled'])) {
                        $targetWidth = $platformSettings['target_width'] ?? 1080;
                        $targetHeight = $platformSettings['target_height'] ?? 1080;

                        error_log("Platform $networkName requires {$targetWidth}x{$targetHeight} (aspect ratio: {$platformSettings['aspect_ratio']})");

                        // Generate platform-specific crop
                        $pathInfo = pathinfo($mediaFile['path']);
                        $croppedFilename = time() . '_' . $networkName . '_' . $pathInfo['filename'] . '.jpg';
                        $croppedPath = $uploadDir . $croppedFilename;

                        $cropResult = cropImageToAspectRatio(
                            $mediaFile['path'],
                            $targetWidth,
                            $targetHeight,
                            $croppedPath
                        );

                        if ($cropResult && file_exists($croppedPath)) {
                            $uploadPath = $croppedPath;
                            $uploadMime = 'image/jpeg';
                            $wasCropped = true;
                            error_log("Generated platform-specific crop for $networkName: $croppedPath");
                        } else {
                            error_log("Failed to crop image for $networkName, using original");
                        }
                    }

                    // Compress image if needed (only for images)
                    if (strpos($uploadMime, 'image/') === 0) {
                        $compressionResult = compressImageIfNeeded($uploadPath, $uploadMime);

                        if ($compressionResult && $compressionResult !== false) {
                            list($compressedPath, $compressedSize, $wasCompressed) = $compressionResult;

                            if ($wasCompressed) {
                                // If we created a crop, clean it up before using compressed version
                                if ($wasCropped && $uploadPath !== $mediaFile['path'] && file_exists($uploadPath)) {
                                    @unlink($uploadPath);
                                }
                                $uploadPath = $compressedPath;
                                $uploadMime = 'image/jpeg';
                                error_log("Image compressed for profile $profile_id: " . $mediaFile['name']);
                            }
                        }
                    }

                    $mediaId = uploadMediaToHootsuite(
                        $token,
                        $uploadPath,
                        $mediaFile['name'],
                        $uploadMime
                    );

                    // Clean up temporary files
                    if ($wasCompressed && file_exists($uploadPath)) {
                        @unlink($uploadPath);
                    } else if ($wasCropped && $uploadPath !== $mediaFile['path'] && file_exists($uploadPath)) {
                        @unlink($uploadPath);
                    }

                    if ($mediaId) {
                        $profileMediaPayload[] = ['id' => $mediaId];
                        error_log("Media upload complete with ID: $mediaId for profile: $profile_id (" . $mediaFile['name'] . ")");
                    } else {
                        error_log("Media upload failed for profile: $profile_id (" . $mediaFile['name'] . "), proceeding without this media");
                    }
                }

                error_log("Total media uploaded for profile $profile_id: " . count($profileMediaPayload) . " of " . count($localMediaPaths));
            } else if ($hasMedia && !$supportsMedia) {
                error_log("Skipping media upload for profile: $profile_id (platform restrictions detected)");
            }

            // Truncate text for this specific platform
            $profileText = truncateForPlatform($text, $profileType);

            $profilePayload = [
                'text' => $profileText,
                'socialProfileIds' => [$profile_id],
                'scheduledSendTime' => $utc_time
            ];

            if ($tagsArr) {
                $profilePayload['tags'] = $tagsArr;
            }

            // Only add media if we have it
            if ($profileMediaPayload && !empty($profileMediaPayload[0]['id'])) {
                $profilePayload['media'] = $profileMediaPayload;
            }

            error_log("Payload for profile $profile_id: " . json_encode($profilePayload));

            $ch = curl_init('https://platform.hootsuite.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $token",
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($profilePayload)
            ]);

            $profileResponse = curl_exec($ch);
            $profileCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log individual response
            $logEntry = date('Y-m-d H:i:s') . " - Profile: $profile_id - Code: $profileCode\n";
            $logEntry .= "Payload sent:\n" . json_encode($profilePayload, JSON_PRETTY_PRINT) . "\n";
            $logEntry .= "Response received:\n" . $profileResponse . "\n";
            $logEntry .= "----------------------------------------\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

            if ($profileCode >= 200 && $profileCode < 300) {
                $successfulProfiles[] = $profile_id;

                $data = json_decode($profileResponse, true);
                $messages = $data['data'] ?? [];

                foreach ($messages as $msg) {
                    $postId = $msg['id'] ?? uniqid('post_');
                    $state = $msg['state'] ?? 'SCHEDULED';
                    $scheduledSendTime = $msg['scheduledSendTime'] ?? date('c', $ts);
                    $scheduledSendTime = date('Y-m-d H:i:s', strtotime($scheduledSendTime));

                    // Get network info
                    $color = '#0d6efd';
                    $icon = 'bi-share';
                    $networkName = '';
                    $profStmt = $pdo->prepare('SELECT network FROM hootsuite_profiles WHERE id=?');
                    $profStmt->execute([$profile_id]);
                    if ($netKey = strtolower($profStmt->fetchColumn() ?: '')) {
                        $netStmt = $pdo->prepare('SELECT name, icon, color FROM social_networks WHERE LOWER(name)=?');
                        $netStmt->execute([$netKey]);
                        if ($n = $netStmt->fetch()) {
                            $networkName = $n['name'] ?? '';
                            $color = $n['color'] ?? $color;
                            $icon = $n['icon'] ?? $icon;
                        }
                    }

                    // Save to database
                    try {
                        $stmt = $pdo->prepare('INSERT INTO hootsuite_posts (post_id, store_id, text, scheduled_send_time, raw_json, state, social_profile_id, tags, media, created_by_user_id, media_urls) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE text=VALUES(text), scheduled_send_time=VALUES(scheduled_send_time), raw_json=VALUES(raw_json), state=VALUES(state), social_profile_id=VALUES(social_profile_id), tags=VALUES(tags), media=VALUES(media), created_by_user_id=VALUES(created_by_user_id), media_urls=VALUES(media_urls)');
                        $stmt->execute([
                            $postId,
                            $store_id,
                            $text,
                            $scheduledSendTime,
                            json_encode($msg),
                            $state,
                            $profile_id,
                            json_encode($tagsArr),
                            json_encode($profileMediaPayload),
                            $user_id,
                            json_encode($mediaUrls)
                        ]);
                    } catch (Exception $e) {
                        error_log("Database error: " . $e->getMessage());
                    }

                    $events[] = [
                        'id' => $postId,
                        'title' => $networkName ?: 'Post',
                        'start' => str_replace(' ', 'T', $scheduledSendTime),
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                        'classNames' => ['social-' . ($networkName ? preg_replace('/[^a-z0-9]+/','-', strtolower($networkName)) : 'default')],
                        'extendedProps' => [
                            'text' => $text,
                            'time' => str_replace(' ', 'T', $scheduledSendTime),
                            'tags' => $tagsArr,
                            'source' => 'API',
                            'post_id' => $postId,
                            'created_by_user_id' => $user_id,
                            'social_profile_id' => $profile_id,
                            'media_urls' => $mediaUrls,
                            'posted_by' => $user_name,
                            'image' => !empty($mediaUrls) && !preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                            'video' => !empty($mediaUrls) && preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                            'icon' => $icon,
                            'network' => $networkName
                        ]
                    ];
                }
            } else {
                $failedProfiles[] = $profile_id;
                $responseData = json_decode($profileResponse, true);
                $errorCode = $responseData['errors'][0]['code'] ?? null;
                $errorMsg = $responseData['errors'][0]['message'] ?? 'Unknown error';

                // Check if it's a media-related error (5000 often indicates media issues)
                if ($profileMediaPayload && !empty($profileMediaPayload[0]['id']) && ($errorCode == 5000 || $profileCode == 400)) {
                    error_log("Retrying profile $profile_id without media due to error code $profileCode (error: $errorMsg)");

                    unset($profilePayload['media']);

                    $ch = curl_init('https://platform.hootsuite.com/v1/messages');
                    curl_setopt_array($ch, [
                        CURLOPT_HTTPHEADER => [
                            "Authorization: Bearer $token",
                            'Content-Type: application/json',
                            'Accept: application/json'
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($profilePayload)
                    ]);

                    $retryResponse = curl_exec($ch);
                    $retryCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    // Log retry attempt
                    $logEntry = date('Y-m-d H:i:s') . " - Profile: $profile_id (RETRY WITHOUT MEDIA) - Code: $retryCode\n";
                    $logEntry .= "Payload sent:\n" . json_encode($profilePayload, JSON_PRETTY_PRINT) . "\n";
                    $logEntry .= "Response received:\n" . $retryResponse . "\n";
                    $logEntry .= "----------------------------------------\n";
                    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

                    if ($retryCode >= 200 && $retryCode < 300) {
                        error_log("Success without media for profile $profile_id");
                        $successfulProfiles[] = $profile_id;

                        // Process the response
                        $data = json_decode($retryResponse, true);
                        $messages = $data['data'] ?? [];

                        foreach ($messages as $msg) {
                            $postId = $msg['id'] ?? uniqid('post_');
                            $state = $msg['state'] ?? 'SCHEDULED';
                            $scheduledSendTime = $msg['scheduledSendTime'] ?? date('c', $ts);
                            $scheduledSendTime = date('Y-m-d H:i:s', strtotime($scheduledSendTime));

                            // Get network info
                            $color = '#0d6efd';
                            $icon = 'bi-share';
                            $networkName = '';
                            $profStmt = $pdo->prepare('SELECT network FROM hootsuite_profiles WHERE id=?');
                            $profStmt->execute([$profile_id]);
                            if ($netKey = strtolower($profStmt->fetchColumn() ?: '')) {
                                $netStmt = $pdo->prepare('SELECT name, icon, color FROM social_networks WHERE LOWER(name)=?');
                                $netStmt->execute([$netKey]);
                                if ($n = $netStmt->fetch()) {
                                    $networkName = $n['name'] ?? '';
                                    $color = $n['color'] ?? $color;
                                    $icon = $n['icon'] ?? $icon;
                                }
                            }

                            // Save to database (without media)
                            try {
                                $stmt = $pdo->prepare('INSERT INTO hootsuite_posts (post_id, store_id, text, scheduled_send_time, raw_json, state, social_profile_id, tags, media, created_by_user_id, media_urls) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE text=VALUES(text), scheduled_send_time=VALUES(scheduled_send_time), raw_json=VALUES(raw_json), state=VALUES(state), social_profile_id=VALUES(social_profile_id), tags=VALUES(tags), media=VALUES(media), created_by_user_id=VALUES(created_by_user_id), media_urls=VALUES(media_urls)');
                                $stmt->execute([
                                    $postId,
                                    $store_id,
                                    $text,
                                    $scheduledSendTime,
                                    json_encode($msg),
                                    $state,
                                    $profile_id,
                                    json_encode($tagsArr),
                                    json_encode([]), // No media for this retry
                                    $user_id,
                                    json_encode($mediaUrls) // Still save local URLs for reference
                                ]);
                            } catch (Exception $e) {
                                error_log("Database error: " . $e->getMessage());
                            }

                            $events[] = [
                                'id' => $postId,
                                'title' => $networkName ?: 'Post',
                                'start' => str_replace(' ', 'T', $scheduledSendTime),
                                'backgroundColor' => $color,
                                'borderColor' => $color,
                                'classNames' => ['social-' . ($networkName ? preg_replace('/[^a-z0-9]+/','-', strtolower($networkName)) : 'default')],
                                'extendedProps' => [
                                    'text' => $text,
                                    'time' => str_replace(' ', 'T', $scheduledSendTime),
                                    'tags' => $tagsArr,
                                    'source' => 'API',
                                    'post_id' => $postId,
                                    'created_by_user_id' => $user_id,
                                    'social_profile_id' => $profile_id,
                                    'media_urls' => $mediaUrls,
                                    'posted_by' => $user_name,
                                    'image' => '', // No image since media failed
                                    'video' => '',
                                    'icon' => $icon,
                                    'network' => $networkName,
                                    'note' => 'Posted without media due to profile restrictions'
                                ]
                            ];
                        }
                    } else {
                        $failedProfiles[] = $profile_id;
                        $responseData = json_decode($retryResponse, true);
                        $errorMsg = $responseData['errors'][0]['message'] ?? 'Unknown error';
                        error_log("Failed for profile $profile_id even without media: $errorMsg");
                    }
                } else {
                    $failedProfiles[] = $profile_id;
                    $responseData = json_decode($profileResponse, true);
                    $errorMsg = $responseData['errors'][0]['message'] ?? 'Unknown error';
                    error_log("Failed for profile $profile_id: $errorMsg");
                }
            }
        }

        if (empty($events)) {
            // Clean up session variable
            unset($_SESSION['existing_media_urls_for_update']);
            echo json_encode(['success' => false, 'error' => 'Failed to create posts for all profiles']);
        } else {
            $result = ['success' => true, 'events' => $events];
            if (!empty($failedProfiles)) {
                $result['warnings'] = 'Some profiles failed: ' . implode(', ', $failedProfiles);
            }
            // Clean up session variable
            unset($_SESSION['existing_media_urls_for_update']);
            echo json_encode($result);
        }
        exit;
    }
    // The 'update' action now uses delete-and-recreate (see lines 577-619)
    // and falls through to the 'create' logic above, so no separate update code is needed
}

if ($action === 'delete') {
    $post_id = $_POST['post_id'] ?? '';
    if ($post_id === '') {
        echo json_encode(['success' => false, 'error' => 'Missing post id']);
        exit;
    }

    // Check ownership
    // Check ownership for normal store users. Admins can bypass this check.
    $stmt = $pdo->prepare('SELECT created_by_user_id FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);
    $owner = $stmt->fetchColumn();
    if (!$is_admin_action && $owner && $owner != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Not authorized to delete this post']);
        exit;
    }

    // Try to delete from Hootsuite
    $ch = curl_init('https://platform.hootsuite.com/v1/messages/' . urlencode($post_id));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE'
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Delete from database regardless of API response
    // (post might already be sent or deleted in Hootsuite)
    $stmt = $pdo->prepare('DELETE FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
?>
