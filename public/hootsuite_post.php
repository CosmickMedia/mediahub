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

if (!isset($_SESSION['store_id']) || !isset($_SESSION['store_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$store_id = (int)$_SESSION['store_id'];
$user_id  = (int)$_SESSION['store_user_id'];
$action   = $_POST['action'] ?? 'create';

$token = get_setting('hootsuite_access_token');
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Missing Hootsuite token']);
    exit;
}

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT hootsuite_profile_ids FROM stores WHERE id=?');
$stmt->execute([$store_id]);
$allowed_profiles = array_filter(array_map('trim', explode(',', (string)$stmt->fetchColumn())));

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

    // Step 3: CRITICAL - Poll until media is READY (has downloadUrl)
    $maxAttempts = 15;  // Up to 30 seconds (15 attempts x 2 seconds)
    $attempt = 0;
    $mediaReady = false;

    error_log("Polling for media to be ready (waiting for downloadUrl)...");

    while ($attempt < $maxAttempts && !$mediaReady) {
        $attempt++;

        // Wait 2 seconds between polls
        if ($attempt > 1) {
            sleep(2);
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

            // Log the full response to understand what we're getting
            error_log("Attempt $attempt response: " . json_encode($verifyData));

            // Check multiple possible fields
            $downloadUrl = $verifyData['data']['downloadUrl'] ?? null;
            $state = $verifyData['data']['state'] ?? null;

            if (!empty($downloadUrl)) {
                error_log("Media READY on attempt $attempt! Download URL: " . substr($downloadUrl, 0, 100) . "...");
                $mediaReady = true;
                break;
            } else if ($state === 'READY') {
                error_log("Media state is READY on attempt $attempt (but no downloadUrl yet)");
                $mediaReady = true;  // Try accepting READY state even without downloadUrl
                break;
            } else {
                error_log("Attempt $attempt: Media still processing (downloadUrl: " . ($downloadUrl ?: 'null') . ", state: " . ($state ?: 'not provided') . ")");
            }
        } else {
            error_log("Attempt $attempt: Failed to check status (code $verifyCode)");
        }
    }

    if (!$mediaReady) {
        error_log("WARNING: Media may not be ready after $maxAttempts attempts (30 seconds)");
        error_log("Proceeding anyway, but image may not attach properly");
    } else {
        error_log("Media confirmed ready - safe to attach to post");
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

    // Networks that commonly have media issues via API
    $restrictedNetworks = ['instagram', 'pinterest'];

    if (in_array($network, $restrictedNetworks)) {
        error_log("Profile $profileId is $network - may have media restrictions");
        return false; // Conservative approach - skip media for these networks
    }

    return true;
}

if ($action === 'create' || $action === 'update') {
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

                        // Only keep first file for Hootsuite upload (API limitation)
                        if ($i === 0) {
                            $localMediaPaths[] = [
                                'path' => $localPath,
                                'name' => $fileName,
                                'mime' => $mimeType
                            ];
                        }

                        $mediaUrls[] = '/public/calendar_media/' . date('Y/m/', $ts) . $savedFileName;
                    } else {
                        error_log("Failed to save file $i locally");
                    }
                }
            }
        }
    }

    if ($action === 'create') {
        $events = [];
        $utc_time = gmdate('Y-m-d\TH:i:s\Z', $ts);

        // Strategy change: Try single call first with all profiles
        // If it fails with media, then try without media
        $hasMedia = !empty($localMediaPaths);
        $mediaPayload = [];

        if ($hasMedia) {
            $mediaFile = $localMediaPaths[0];
            $mediaId = uploadMediaToHootsuite(
                $token,
                $mediaFile['path'],
                $mediaFile['name'],
                $mediaFile['mime']
            );

            if ($mediaId) {
                $mediaPayload[] = ['id' => $mediaId];
                error_log("Media upload complete with ID: $mediaId");
            }
        }

        // Try creating with all profiles together first
        $payload = [
            'text' => $text,
            'socialProfileIds' => $profile_ids,
            'scheduledSendTime' => $utc_time
        ];

        if ($tagsArr) {
            $payload['tags'] = $tagsArr;
        }

        if ($mediaPayload && !empty($mediaPayload[0]['id'])) {
            $payload['media'] = $mediaPayload;
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
                        'image' => !empty($mediaUrls) && !preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                        'video' => !empty($mediaUrls) && preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                        'icon' => $icon,
                        'network' => $networkName
                    ]
                ];
            }

            echo json_encode(['success' => true, 'events' => $events]);
            exit;
        }

        // If the batch call failed, try individual calls for each profile
        error_log("Batch call failed, trying individual calls for each profile");

        $successfulProfiles = [];
        $failedProfiles = [];

        foreach ($profile_ids as $profile_id) {
            error_log("Creating post for profile: $profile_id");

            // Check if this profile supports media
            $supportsMedia = profileSupportsMedia($pdo, $profile_id);

            // Check profile capabilities first (optional, for logging)
            $profileInfo = getProfileCapabilities($token, $profile_id);

            // Upload media for this specific profile only if it supports it
            $profileMediaPayload = [];
            if ($hasMedia && $supportsMedia) {
                // Only try media if profile supports it
                $mediaFile = $localMediaPaths[0];
                $mediaId = uploadMediaToHootsuite(
                    $token,
                    $mediaFile['path'],
                    $mediaFile['name'],
                    $mediaFile['mime']
                );

                if ($mediaId) {
                    $profileMediaPayload[] = ['id' => $mediaId];
                    error_log("Media upload complete with ID: $mediaId for profile: $profile_id");
                } else {
                    error_log("Media upload failed for profile: $profile_id, proceeding without media");
                }
            } else if ($hasMedia && !$supportsMedia) {
                error_log("Skipping media upload for profile: $profile_id (platform restrictions detected)");
            }

            $profilePayload = [
                'text' => $text,
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
                            'image' => !empty($mediaUrls) && !preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                            'video' => !empty($mediaUrls) && preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
                            'icon' => $icon,
                            'network' => $networkName
                        ]
                    ];
                }
            } else {
                // If media failed, try without media
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
            echo json_encode(['success' => false, 'error' => 'Failed to create posts for all profiles']);
        } else {
            $result = ['success' => true, 'events' => $events];
            if (!empty($failedProfiles)) {
                $result['warnings'] = 'Some profiles failed: ' . implode(', ', $failedProfiles);
            }
            echo json_encode($result);
        }
        exit;
    }

    // Update existing post (single profile) - keeping this part the same
    $profile_id = $profile_ids[0];

    // Upload media for update if needed
    $mediaPayload = [];
    if (!empty($localMediaPaths)) {
        $mediaFile = $localMediaPaths[0];
        $mediaId = uploadMediaToHootsuite(
            $token,
            $mediaFile['path'],
            $mediaFile['name'],
            $mediaFile['mime']
        );

        if ($mediaId) {
            $mediaPayload[] = ['id' => $mediaId];
            error_log("Media upload complete for update with ID: $mediaId");
        }
    }

    // Format date in UTC with Z suffix
    $utc_time = gmdate('Y-m-d\TH:i:s\Z', $ts);

    $payload = [
        'text' => $text,
        'socialProfileIds' => [$profile_id],
        'scheduledSendTime' => $utc_time
    ];
    if ($tagsArr) $payload['tags'] = $tagsArr;
    // Add media if present - use mediaIds format for update
    if ($mediaPayload && !empty($mediaPayload[0]['id'])) {
        $mediaIds = array_map(function($m) { return $m['id']; }, $mediaPayload);
        $payload['mediaIds'] = $mediaIds;
    }

    $url = 'https://platform.hootsuite.com/v1/messages/' . urlencode($post_id);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $code >= 400) {
        error_log("Update error (code $code): $response");
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['errors'][0]['message'] ?? 'Failed to update post';
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }

    $data = json_decode($response, true);
    $postId = $data['data']['id'] ?? $data['id'] ?? $post_id;
    $state = $data['data']['state'] ?? $data['state'] ?? 'SCHEDULED';
    $scheduledSendTime = $data['data']['scheduledSendTime'] ?? $data['scheduledSendTime'] ?? date('c', $ts);
    $scheduledSendTime = date('Y-m-d H:i:s', strtotime($scheduledSendTime));

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

    // Check ownership before updating
    $stmt = $pdo->prepare('SELECT created_by_user_id FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);
    $owner = $stmt->fetchColumn();
    if ($owner && $owner != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Not authorized to update this post']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE hootsuite_posts SET text=?, scheduled_send_time=?, raw_json=?, state=?, social_profile_id=?, tags=?, media=?, created_by_user_id=?, media_urls=? WHERE post_id=? AND store_id=?');
    $stmt->execute([
        $text,
        $scheduledSendTime,
        $response,
        $state,
        $profile_id,
        json_encode($tagsArr),
        json_encode($mediaPayload),
        $user_id,
        json_encode($mediaUrls),
        $post_id,
        $store_id
    ]);

    $event = [
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
            'image' => !empty($mediaUrls) && !preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
            'video' => !empty($mediaUrls) && preg_match('/\.mp4$/i', $mediaUrls[0]) ? $mediaUrls[0] : '',
            'icon' => $icon,
            'network' => $networkName
        ]
    ];

    echo json_encode(['success' => true, 'event' => $event]);
    exit;
}

if ($action === 'delete') {
    $post_id = $_POST['post_id'] ?? '';
    if ($post_id === '') {
        echo json_encode(['success' => false, 'error' => 'Missing post id']);
        exit;
    }

    // Check ownership
    $stmt = $pdo->prepare('SELECT created_by_user_id FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);
    $owner = $stmt->fetchColumn();
    if ($owner && $owner != $user_id) {
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