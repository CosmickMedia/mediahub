<?php
session_start();
include('config.php');

// Set JSON content type
header('Content-Type: application/json');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['error' => 'No access token']);
    exit;
}

$access_token = $_SESSION['access_token'];
$debug_info = [];

// Function to make API call and return full response
function apiCall($url, $access_token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return [
        'http_code' => $http_code,
        'url' => $url,
        'response' => json_decode($result, true),
        'raw_response' => $result,
        'curl_info' => $info
    ];
}

// 1. Try to get ALL messages without filters
$debug_info['all_messages'] = apiCall(
    "https://platform.hootsuite.com/v1/messages?limit=10",
    $access_token
);

// 2. Try SCHEDULED state specifically
$debug_info['scheduled_messages'] = apiCall(
    "https://platform.hootsuite.com/v1/messages?state=SCHEDULED&limit=10",
    $access_token
);

// 3. Try PENDING_APPROVAL state
$debug_info['pending_messages'] = apiCall(
    "https://platform.hootsuite.com/v1/messages?state=PENDING_APPROVAL&limit=10",
    $access_token
);

// 4. Try DRAFT state
$debug_info['draft_messages'] = apiCall(
    "https://platform.hootsuite.com/v1/messages?state=DRAFT&limit=10",
    $access_token
);

// 5. Try SENT state to see historical posts
$debug_info['sent_messages'] = apiCall(
    "https://platform.hootsuite.com/v1/messages?state=SENT&limit=10",
    $access_token
);

// 6. Get message states available
$debug_info['available_states'] = [
    'SCHEDULED',
    'PENDING_APPROVAL',
    'DRAFT',
    'SENT',
    'FAILED',
    'REJECTED'
];

// 7. Check permissions/scopes
$debug_info['user_info'] = apiCall(
    "https://platform.hootsuite.com/v1/me",
    $access_token
);

// 8. Get social profiles to verify they exist
$debug_info['social_profiles'] = apiCall(
    "https://platform.hootsuite.com/v1/socialProfiles?limit=10",
    $access_token
);

// 9. Try calendar view endpoint if available
$today = date('Y-m-d');
$next_month = date('Y-m-d', strtotime('+30 days'));
$debug_info['calendar_view'] = apiCall(
    "https://platform.hootsuite.com/v1/messages?startTime={$today}T00:00:00Z&endTime={$next_month}T23:59:59Z&limit=100",
    $access_token
);

// Create summary
$summary = [
    'total_messages_found' => 0,
    'messages_by_state' => [],
    'social_profiles_count' => 0,
    'date_range_checked' => "{$today} to {$next_month}",
    'first_scheduled_post' => null
];

// Count messages by state
foreach ($debug_info as $key => $data) {
    if (strpos($key, '_messages') !== false && isset($data['response']['data'])) {
        $state = str_replace('_messages', '', $key);
        $count = count($data['response']['data']);
        $summary['messages_by_state'][$state] = $count;
        $summary['total_messages_found'] += $count;

        // Find first scheduled post with date
        if (!$summary['first_scheduled_post'] && !empty($data['response']['data'])) {
            foreach ($data['response']['data'] as $post) {
                if (isset($post['scheduledSendTime'])) {
                    $summary['first_scheduled_post'] = [
                        'scheduledSendTime' => $post['scheduledSendTime'],
                        'text' => substr($post['text'] ?? '', 0, 100),
                        'state' => $post['state'] ?? 'unknown'
                    ];
                    break;
                }
            }
        }
    }
}

if (isset($debug_info['social_profiles']['response']['data'])) {
    $summary['social_profiles_count'] = count($debug_info['social_profiles']['response']['data']);
}

// Output
echo json_encode([
    'summary' => $summary,
    'debug_details' => $debug_info,
    'timestamp' => date('c'),
    'note' => 'Check debug_details for full API responses. Look for scheduledSendTime field in message objects.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?><?php
