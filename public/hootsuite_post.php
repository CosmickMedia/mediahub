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

    // Media upload handling - Fixed version
    $mediaPayload = [];
    if (!empty($_FILES['media']['tmp_name'])) {
        error_log("Uploading media file: " . $_FILES['media']['name']);

        // Read the file content
        $fileContent = file_get_contents($_FILES['media']['tmp_name']);
        $fileName = $_FILES['media']['name'];
        $mimeType = $_FILES['media']['type'];

        // Create a unique boundary
        $boundary = uniqid();

        // Build the multipart request body
        $body = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$fileName\"\r\n";
        $body .= "Content-Type: $mimeType\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--$boundary--\r\n";

        $ch = curl_init('https://platform.hootsuite.com/v1/media');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Content-Type: multipart/form-data; boundary=$boundary",
                "Accept: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $body
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Media upload cURL error: " . $error);
        } else {
            error_log("Media upload response (code $code): " . $resp);
        }

        if ($code >= 200 && $code < 300) {
            $mdata = json_decode($resp, true);
            if (!empty($mdata['data']['id'])) {
                $mediaPayload[] = ['id' => $mdata['data']['id']];
                error_log("Media uploaded successfully with ID: " . $mdata['data']['id']);
            } else if (!empty($mdata['id'])) {
                // Sometimes the ID is directly in the response
                $mediaPayload[] = ['id' => $mdata['id']];
                error_log("Media uploaded successfully with ID: " . $mdata['id']);
            }
        } else {
            error_log("Media upload failed with code $code: " . $resp);
            // For now, continue without media if upload fails
            // You might want to save media locally and reference it differently
        }
    }

    if ($action === 'create') {
        $events = [];
        $errors = [];
        $success_count = 0;

        foreach ($profile_ids as $profile_id) {
            error_log("Creating post for profile: $profile_id");

            // Format date in UTC with Z suffix (Hootsuite prefers this format)
            $utc_time = gmdate('Y-m-d\TH:i:s\Z', $ts);

            $payload = [
                'text' => $text,
                'socialProfileIds' => [$profile_id],
                'scheduledSendTime' => $utc_time  // Use UTC format with Z
            ];
            if ($tagsArr) $payload['tags'] = $tagsArr;
            if ($mediaPayload) $payload['media'] = $mediaPayload;

            error_log("Sending to Hootsuite API: " . json_encode($payload));

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

            if ($err) {
                error_log("cURL error for profile $profile_id: $err");
                $errors[] = "Network error for profile $profile_id";
                continue;
            }

            if ($code >= 400) {
                error_log("API error for profile $profile_id (code $code): $response");
                $responseData = json_decode($response, true);
                $errorMsg = $responseData['errors'][0]['message'] ?? 'Unknown error';
                $errors[] = "Failed for profile $profile_id: $errorMsg";
                continue;
            }

            error_log("Success for profile $profile_id: $response");
            $success_count++;

            $data = json_decode($response, true);
            $postId = $data['data']['id'] ?? $data['id'] ?? uniqid('post_');
            $state = $data['data']['state'] ?? $data['state'] ?? 'SCHEDULED';
            $scheduledSendTime = $data['data']['scheduledSendTime'] ?? $data['scheduledSendTime'] ?? date('c', $ts);
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

            // Save media URLs if we have them
            $mediaUrls = [];
            if (!empty($mediaPayload)) {
                // Save the original filename as a reference
                $mediaUrls[] = $_FILES['media']['name'] ?? 'uploaded_media';
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

        if ($success_count > 0) {
            echo json_encode(['success' => true, 'events' => $events]);
        } else {
            $error_msg = 'Failed to schedule posts. ';
            if (!empty($errors)) {
                $error_msg .= implode(', ', $errors);
            }
            echo json_encode(['success' => false, 'error' => $error_msg]);
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
        'scheduledSendTime' => $utc_time  // Use UTC format
    ];
    if ($tagsArr) $payload['tags'] = $tagsArr;
    if ($mediaPayload) $payload['media'] = $mediaPayload;

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

    // Save media URLs if we have them
    $mediaUrls = [];
    if (!empty($mediaPayload)) {
        $mediaUrls[] = $_FILES['media']['name'] ?? 'uploaded_media';
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