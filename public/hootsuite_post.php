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

    $mediaPayload = [];
    if (!empty($_FILES['media']['tmp_name'])) {
        $file = curl_file_create($_FILES['media']['tmp_name'], $_FILES['media']['type'], $_FILES['media']['name']);
        $ch = curl_init('https://platform.hootsuite.com/v1/media');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => ['file' => $file]
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $mdata = json_decode($resp, true);
            if (!empty($mdata['data']['id'])) {
                $mediaPayload[] = ['id' => $mdata['data']['id']];
            }
        }
    }

    if ($action === 'create') {
        $events = [];
        foreach ($profile_ids as $profile_id) {
            $payload = [
                'text' => $text,
                'socialProfileIds' => [$profile_id],
                'scheduledSendTime' => date('c', $ts)
            ];
            if ($tagsArr) $payload['tags'] = $tagsArr;
            if ($mediaPayload) $payload['media'] = $mediaPayload;

            $ch = curl_init('https://platform.hootsuite.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err || $code >= 400) continue;
            $data = json_decode($response, true);
            $postId = $data['data']['id'] ?? null;
            $state = $data['data']['state'] ?? null;
            $scheduledSendTime = $data['data']['scheduledSendTime'] ?? date('c', $ts);
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

            $stmt = $pdo->prepare('INSERT INTO hootsuite_posts (post_id, store_id, text, scheduled_send_time, raw_json, state, social_profile_id, tags, media, created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE text=VALUES(text), scheduled_send_time=VALUES(scheduled_send_time), raw_json=VALUES(raw_json), state=VALUES(state), social_profile_id=VALUES(social_profile_id), tags=VALUES(tags), media=VALUES(media), created_by_user_id=VALUES(created_by_user_id)');
            $stmt->execute([$postId, $store_id, $text, $scheduledSendTime, $response, $state, $profile_id, json_encode($tagsArr), json_encode($mediaPayload), $user_id]);

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
                    'image' => '',
                    'video' => '',
                    'icon' => $icon,
                    'network' => $networkName
                ]
            ];
        }

        echo json_encode(['success' => true, 'events' => $events]);
        exit;
    }

    // Update existing post (single profile)
    $profile_id = $profile_ids[0];

    $payload = [
        'text' => $text,
        'socialProfileIds' => [$profile_id],
        'scheduledSendTime' => date('c', $ts)
    ];
    if ($tagsArr) $payload['tags'] = $tagsArr;
    if ($mediaPayload) $payload['media'] = $mediaPayload;

    $url = 'https://platform.hootsuite.com/v1/messages/' . urlencode($post_id);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || $code >= 400) {
        echo json_encode(['success' => false, 'error' => 'API error']);
        exit;
    }
    $data = json_decode($response, true);
    $postId = $data['data']['id'] ?? $post_id;
    $state = $data['data']['state'] ?? null;
    $scheduledSendTime = $data['data']['scheduledSendTime'] ?? date('c', $ts);
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

    $stmt = $pdo->prepare('SELECT created_by_user_id FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);
    $owner = $stmt->fetchColumn();
    if ($owner != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Not allowed']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE hootsuite_posts SET text=?, scheduled_send_time=?, raw_json=?, state=?, social_profile_id=?, tags=?, media=?, created_by_user_id=? WHERE post_id=? AND store_id=?');
    $stmt->execute([$text, $scheduledSendTime, $response, $state, $profile_id, json_encode($tagsArr), json_encode($mediaPayload), $user_id, $post_id, $store_id]);

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
            'image' => '',
            'video' => '',
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
    $stmt = $pdo->prepare('SELECT created_by_user_id FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);
    $owner = $stmt->fetchColumn();
    if ($owner != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Not allowed']);
        exit;
    }
    $ch = curl_init('https://platform.hootsuite.com/v1/messages/' . urlencode($post_id));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE'
    ]);
    curl_exec($ch);
    curl_close($ch);
    $stmt = $pdo->prepare('DELETE FROM hootsuite_posts WHERE post_id=? AND store_id=?');
    $stmt->execute([$post_id, $store_id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
