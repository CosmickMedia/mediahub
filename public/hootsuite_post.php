<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/settings.php';

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
        error_log("Failed to get upload URL (code $code)");
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
        error_log("S3 upload failed (code $s3Code)");
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

    // Media upload handling - Fixed to handle both single and multiple files
    $mediaPayload = [];
    $mediaUrls = [];

    if (!empty($_FILES['media'])) {
        // Check if it's a single file or multiple files
        $isSingleFile = !is_array($_FILES['media']['name']);

        if ($isSingleFile && !empty($_FILES['media']['tmp_name'])) {
            // Single file upload
            $fileName = $_FILES['media']['name'];
            $tmpName = $_FILES['media']['tmp_name'];
            $mimeType = $_FILES['media']['type'];

            error_log("Processing single media upload: $fileName");

            // Save file locally first
            $uploadDir = __DIR__ . '/calendar_media/' . date('Y/m/', $ts);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $savedFileName = time() . '_0_' . basename($fileName);
            $localPath = $uploadDir . $savedFileName;

            if (move_uploaded_file($tmpName, $localPath)) {
                error_log("File saved locally: $localPath");

                // Upload to Hootsuite
                $mediaId = uploadMediaToHootsuite(
                    $token,
                    $localPath,
                    $fileName,
                    $mimeType
                );

                if ($mediaId) {
                    $mediaPayload[] = ['id' => $mediaId];
                    $mediaUrls[] = '/public/calendar_media/' . date('Y/m/', $ts) . $savedFileName;
                    error_log("Media upload complete with ID: $mediaId");
                } else {
                    error_log("Media upload to Hootsuite failed, but keeping local copy");
                    // Still save the local URL even if Hootsuite upload failed
                    $mediaUrls[] = '/public/calendar_media/' . date('Y/m/', $ts) . $savedFileName;
                }
            } else {
                error_log("Failed to save file locally");
            }
        } elseif (!$isSingleFile) {
            // Multiple files upload (though Hootsuite typically only supports one media per post)
            // We'll process only the first file for Hootsuite
            $fileCount = count($_FILES['media']['name']);
            error_log("Processing multiple files upload: $fileCount files");

            for ($i = 0; $i < $fileCount; $i++) {
                if (!empty($_FILES['media']['tmp_name'][$i])) {
                    $fileName = $_FILES['media']['name'][$i];
                    $tmpName = $_FILES['media']['tmp_name'][$i];
                    $mimeType = $_FILES['media']['type'][$i];

                    error_log("Processing file $i: $fileName");

                    // Save file locally
                    $uploadDir = __DIR__ . '/calendar_media/' . date('Y/m/', $ts);
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $savedFileName = time() . '_' . $i . '_' . basename($fileName);
                    $localPath = $uploadDir . $savedFileName;

                    if (move_uploaded_file($tmpName, $localPath)) {
                        error_log("File $i saved locally: $localPath");

                        // Only upload the first file to Hootsuite (API limitation)
                        if ($i === 0) {
                            $mediaId = uploadMediaToHootsuite(
                                $token,
                                $localPath,
                                $fileName,
                                $mimeType
                            );

                            if ($mediaId) {
                                $mediaPayload[] = ['id' => $mediaId];
                                error_log("Media upload complete with ID: $mediaId");
                            } else {
                                error_log("Media upload to Hootsuite failed for file $i");
                            }
                        }

                        // Save all local URLs
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

        // Format date in UTC with Z suffix (Hootsuite prefers this format)
        $utc_time = gmdate('Y-m-d\TH:i:s\Z', $ts);

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

        // Log the full payload for debugging
        $payloadJson = json_encode($payload);
        error_log("Full payload being sent: " . substr($payloadJson, 0, 1000));
        if (strlen($payloadJson) > 1000) {
            $chunks = str_split($payloadJson, 500);
            foreach ($chunks as $i => $chunk) {
                error_log("Payload chunk $i: $chunk");
            }
        }

        $ch = curl_init('https://platform.hootsuite.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        // Write full response to a dedicated log file
        $logFile = __DIR__ . '/hootsuite_api_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " - Profiles: " . implode(',', $profile_ids) . " - Code: $code\n";
        $logEntry .= "Payload sent:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        $logEntry .= "Response received:\n" . $response . "\n";
        $logEntry .= "----------------------------------------\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        if ($err) {
            error_log("cURL error: $err");
            echo json_encode(['success' => false, 'error' => 'Network error']);
            exit;
        }

        // Always log the response for debugging
        if ($response) {
            if (strlen($response) > 500) {
                $chunks = str_split($response, 500);
                foreach ($chunks as $i => $chunk) {
                    error_log("API response chunk $i (code $code): $chunk");
                }
            } else {
                error_log("API response (code $code): $response");
            }
        } else {
            error_log("Empty API response (code $code)");
        }

        if ($code >= 200 && $code < 300) {
            $data = json_decode($response, true);
            $messages = $data['data'] ?? [];
            foreach ($messages as $msg) {
                $profile_id = $msg['socialProfileId'] ?? null;
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
                        $response,
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
        } else {
            $responseData = json_decode($response, true);
            $errorMsg = 'Failed to schedule posts';
            if (!empty($responseData['errors'][0]['message'])) {
                $errorMsg = $responseData['errors'][0]['message'];
            } elseif (!empty($responseData['error'])) {
                $errorMsg = $responseData['error'];
            } elseif (!empty($responseData['message'])) {
                $errorMsg = $responseData['message'];
            }
            error_log("API error: $errorMsg");
            echo json_encode(['success' => false, 'error' => $errorMsg]);
        }
        exit;
    }


    // Update existing post (single profile)
    $profile_id = $profile_ids[0];

    // Format date in UTC with Z suffix
    $utc_time = gmdate('Y-m-d\TH:i:s\Z', $ts);

    $payload = [
        'text' => $text,
        'socialProfileIds' => [$profile_id],
        'scheduledSendTime' => $utc_time
    ];
    if ($tagsArr) $payload['tags'] = $tagsArr;
    // Add media if present - use mediaIds format for update too
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
