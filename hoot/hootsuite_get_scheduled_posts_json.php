<?php
session_start();
require_once __DIR__.'/../lib/settings.php';

// Set JSON content type
header('Content-Type: application/json');

$access_token = $_SESSION['access_token'] ?? get_setting('hootsuite_access_token');
if (!$access_token) {
    http_response_code(401);
    echo json_encode([
        'error' => true,
        'message' => 'No access token found. Please authenticate first via ../admin/hootsuite_login.php'
    ]);
    exit;
}
$_SESSION['access_token'] = $access_token;

// Initialize response array
$response = [
    'error' => false,
    'scheduled_posts' => [],
    'social_profiles' => [],
    'metadata' => []
];

// Function to fetch all pages of results
function fetchAllPages($url, $access_token, $max_pages = 10) {
    $all_data = [];
    $page_count = 0;
    $next_url = $url;

    while ($next_url && $page_count < $max_pages) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $next_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $access_token,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $data = json_decode($result, true);

            if (isset($data['data'])) {
                $all_data = array_merge($all_data, $data['data']);
            }

            // Check for next page
            $next_url = isset($data['pagination']['next']) ? $data['pagination']['next'] : null;
            $page_count++;
        } else {
            break;
        }
    }

    return $all_data;
}

// Get ALL scheduled messages with expanded fields
// Try multiple states to ensure we capture all scheduled content
$schedule_url = "https://platform.hootsuite.com/v1/messages";
// Remove state filter initially to see all messages
$schedule_url .= "?limit=100"; // Maximum allowed per page
$schedule_url .= "&includeDeleted=false"; // Exclude deleted
$schedule_url .= "&sort=scheduledSendTime"; // Sort by scheduled time ascending

// Fetch all scheduled posts (handles pagination)
$all_posts = fetchAllPages($schedule_url, $access_token);

// Also try specific states if the general query returns nothing
if (empty($all_posts)) {
    // Try SCHEDULED state specifically
    $scheduled_url = "https://platform.hootsuite.com/v1/messages?state=SCHEDULED&limit=100";
    $all_posts = fetchAllPages($scheduled_url, $access_token);

    // If still empty, try PENDING_APPROVAL
    if (empty($all_posts)) {
        $pending_url = "https://platform.hootsuite.com/v1/messages?state=PENDING_APPROVAL&limit=100";
        $all_posts = fetchAllPages($pending_url, $access_token);
    }

    // If still empty, try DRAFT state
    if (empty($all_posts)) {
        $draft_url = "https://platform.hootsuite.com/v1/messages?state=DRAFT&limit=100";
        $all_posts = fetchAllPages($draft_url, $access_token);
    }
}

// Add debug information about what we tried
$response['debug'] = [
    'posts_found' => count($all_posts),
    'queried_url' => $schedule_url,
    'attempted_states' => ['ALL', 'SCHEDULED', 'PENDING_APPROVAL', 'DRAFT']
];

// Process each post to ensure all fields are captured
foreach ($all_posts as $post) {
    $processed_post = [
        // Basic Information
        'id' => $post['id'] ?? null,
        'state' => $post['state'] ?? null,
        'messageType' => $post['messageType'] ?? null,

        // Content
        'text' => $post['text'] ?? null,
        'originalText' => $post['originalText'] ?? null,

        // Scheduling Information
        'scheduledSendTime' => $post['scheduledSendTime'] ?? null,
        'createdTime' => $post['createdTime'] ?? null,
        'lastModifiedTime' => $post['lastModifiedTime'] ?? null,

        // Social Profile Information
        'socialProfileIds' => $post['socialProfileIds'] ?? [],
        'socialProfileId' => $post['socialProfileId'] ?? null,

        // Media/Attachments
        'media' => $post['media'] ?? [],
        'mediaUrls' => $post['mediaUrls'] ?? [],
        'videoOptions' => $post['videoOptions'] ?? null,

        // Links and URLs
        'extendedMediaUrls' => $post['extendedMediaUrls'] ?? [],
        'linkAttachment' => $post['linkAttachment'] ?? null,
        'links' => $post['links'] ?? [],

        // Targeting and Tags
        'tags' => $post['tags'] ?? [],
        'hashtags' => $post['hashtags'] ?? [],
        'mentions' => $post['mentions'] ?? [],
        'targetingOptions' => $post['targetingOptions'] ?? null,
        'locationTargeting' => $post['locationTargeting'] ?? null,
        'audienceTargeting' => $post['audienceTargeting'] ?? null,

        // Campaign and Organization
        'campaignIds' => $post['campaignIds'] ?? [],
        'organizationId' => $post['organizationId'] ?? null,
        'ownerId' => $post['ownerId'] ?? null,

        // Approval Workflow
        'approvalStatus' => $post['approvalStatus'] ?? null,
        'approvalHistory' => $post['approvalHistory'] ?? [],
        'reviewerIds' => $post['reviewerIds'] ?? [],

        // Privacy and Compliance
        'privacy' => $post['privacy'] ?? null,
        'isPrivate' => $post['isPrivate'] ?? false,
        'contentLabels' => $post['contentLabels'] ?? [],

        // Engagement Predictions/Analytics
        'predictedEngagement' => $post['predictedEngagement'] ?? null,
        'engagementRate' => $post['engagementRate'] ?? null,

        // Platform-Specific Options
        'facebookOptions' => $post['facebookOptions'] ?? null,
        'twitterOptions' => $post['twitterOptions'] ?? null,
        'instagramOptions' => $post['instagramOptions'] ?? null,
        'linkedinOptions' => $post['linkedinOptions'] ?? null,
        'youtubeOptions' => $post['youtubeOptions'] ?? null,
        'tiktokOptions' => $post['tiktokOptions'] ?? null,
        'pinterestOptions' => $post['pinterestOptions'] ?? null,

        // Additional Metadata
        'metadata' => $post['metadata'] ?? null,
        'customProperties' => $post['customProperties'] ?? null,
        'notes' => $post['notes'] ?? null,
        'internalNotes' => $post['internalNotes'] ?? null,

        // Collaboration
        'assignedTo' => $post['assignedTo'] ?? null,
        'teamId' => $post['teamId'] ?? null,

        // Any other fields not explicitly captured
        'raw_data' => $post
    ];

    $response['scheduled_posts'][] = $processed_post;
}

// Get social profiles with full details
$profiles_url = "https://platform.hootsuite.com/v1/socialProfiles?limit=100";
$all_profiles = fetchAllPages($profiles_url, $access_token);

// Process each profile
foreach ($all_profiles as $profile) {
    $processed_profile = [
        'id' => $profile['id'] ?? null,
        'type' => $profile['type'] ?? null,
        'socialNetworkId' => $profile['socialNetworkId'] ?? null,
        'socialNetworkUsername' => $profile['socialNetworkUsername'] ?? null,
        'socialNetworkUserId' => $profile['socialNetworkUserId'] ?? null,
        'avatarUrl' => $profile['avatarUrl'] ?? null,
        'profileUrl' => $profile['profileUrl'] ?? null,
        'isActive' => $profile['isActive'] ?? false,
        'isDefault' => $profile['isDefault'] ?? false,
        'createdTime' => $profile['createdTime'] ?? null,
        'organizationId' => $profile['organizationId'] ?? null,
        'ownerId' => $profile['ownerId'] ?? null,
        'teamIds' => $profile['teamIds'] ?? [],
        'permissions' => $profile['permissions'] ?? [],
        'metadata' => $profile['metadata'] ?? null,
        'raw_data' => $profile
    ];

    $response['social_profiles'][] = $processed_profile;
}

// Create a mapping of profile IDs to profile details for easier lookup
$profile_map = [];
foreach ($response['social_profiles'] as $profile) {
    $profile_map[$profile['id']] = [
        'username' => $profile['socialNetworkUsername'],
        'type' => $profile['type'],
        'avatarUrl' => $profile['avatarUrl']
    ];
}

// Enhance posts with profile information
foreach ($response['scheduled_posts'] as &$post) {
    $post['social_profiles_details'] = [];
    if (!empty($post['socialProfileIds'])) {
        foreach ($post['socialProfileIds'] as $profileId) {
            if (isset($profile_map[$profileId])) {
                $post['social_profiles_details'][] = $profile_map[$profileId];
            }
        }
    }
}

// Add metadata
$response['metadata'] = [
    'total_scheduled_posts' => count($response['scheduled_posts']),
    'total_social_profiles' => count($response['social_profiles']),
    'fetched_at' => date('c'),
    'api_version' => 'v1',
    'timezone' => date_default_timezone_get()
];

// Also fetch user info for context
$user_url = "https://platform.hootsuite.com/v1/me";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$user_result = curl_exec($ch);
$user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($user_http_code == 200) {
    $user_data = json_decode($user_result, true);
    $response['user_info'] = $user_data['data'] ?? null;
}

// Try to get campaigns if available (may require additional permissions)
$campaigns_url = "https://platform.hootsuite.com/v1/campaigns?limit=100";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $campaigns_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$campaigns_result = curl_exec($ch);
$campaigns_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($campaigns_http_code == 200) {
    $campaigns_data = json_decode($campaigns_result, true);
    $response['campaigns'] = $campaigns_data['data'] ?? [];
}

// Output JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>