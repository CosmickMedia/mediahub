<?php
session_start();
include('config.php');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    die("No access token found. Please authenticate first by visiting test_auth.php");
}

$access_token = $_SESSION['access_token'];

// Function to make API calls
function makeApiCall($url, $access_token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        return json_decode($result, true);
    }
    return null;
}

// Get the profiles data
$profilesUrl = "https://content.cosmickmedia.com/get_scheduled_posts_json.php";
$profilesResponse = @file_get_contents($profilesUrl);
$jsonProfilesData = $profilesResponse;

// Get the calendar/debug data
$debugUrl = "https://content.cosmickmedia.com/debug_hootsuite.php";
$debugResponse = @file_get_contents($debugUrl);
$jsonPostsData = $debugResponse;

// If the above URLs don't work, make direct API calls
if (!$jsonProfilesData || !$jsonPostsData) {
    // Fetch profiles directly
    $profilesApiUrl = "https://platform.hootsuite.com/v1/socialProfiles?limit=100";
    $profilesApiData = makeApiCall($profilesApiUrl, $access_token);

    // Fetch all messages (try different states)
    $allMessages = [];

    // Get scheduled messages
    $today = date('Y-m-d');
    $next_month = date('Y-m-d', strtotime('+60 days'));
    $calendarApiUrl = "https://platform.hootsuite.com/v1/messages?startTime={$today}T00:00:00Z&endTime={$next_month}T23:59:59Z&limit=100";
    $calendarApiData = makeApiCall($calendarApiUrl, $access_token);
    if ($calendarApiData && isset($calendarApiData['data'])) {
        $allMessages = array_merge($allMessages, $calendarApiData['data']);
    }

    // Also get scheduled state specifically
    $scheduledUrl = "https://platform.hootsuite.com/v1/messages?state=SCHEDULED&limit=100";
    $scheduledData = makeApiCall($scheduledUrl, $access_token);
    if ($scheduledData && isset($scheduledData['data'])) {
        $allMessages = array_merge($allMessages, $scheduledData['data']);
    }

    // Get sent messages for historical data
    $sentUrl = "https://platform.hootsuite.com/v1/messages?state=SENT&limit=50";
    $sentData = makeApiCall($sentUrl, $access_token);
    if ($sentData && isset($sentData['data'])) {
        $allMessages = array_merge($allMessages, $sentData['data']);
    }

    // Create the expected structure
    $jsonProfilesData = json_encode([
        'social_profiles' => $profilesApiData['data'] ?? []
    ]);

    $jsonPostsData = json_encode([
        'debug_details' => [
            'calendar_view' => [
                'response' => [
                    'data' => $allMessages
                ]
            ]
        ]
    ]);
}

// Decode both JSON strings
$profilesData = json_decode($jsonProfilesData, true);
$postsData = json_decode($jsonPostsData, true);

// Create profiles lookup map with enhanced data
$profilesMap = [];
$companiesMap = []; // Map to group profiles by company

if (isset($profilesData['social_profiles'])) {
    foreach ($profilesData['social_profiles'] as $profile) {
        // Determine the social network type
        $networkType = strtoupper($profile['type'] ?? '');
        $displayType = $networkType;

        // Clean up network type names
        switch($networkType) {
            case 'FACEBOOKPAGE':
            case 'FACEBOOK':
                $displayType = 'Facebook';
                $networkIcon = '📘';
                break;
            case 'INSTAGRAM':
            case 'INSTAGRAMBUSINESS':
                $displayType = 'Instagram';
                $networkIcon = '📷';
                break;
            case 'TWITTER':
            case 'X':
                $displayType = 'X (Twitter)';
                $networkIcon = '🐦';
                break;
            case 'LINKEDIN':
            case 'LINKEDINCOMPANY':
            case 'LINKEDINPROFILE':
                $displayType = 'LinkedIn';
                $networkIcon = '💼';
                break;
            case 'TIKTOK':
                $displayType = 'TikTok';
                $networkIcon = '🎵';
                break;
            case 'YOUTUBE':
                $displayType = 'YouTube';
                $networkIcon = '📺';
                break;
            case 'PINTEREST':
                $displayType = 'Pinterest';
                $networkIcon = '📌';
                break;
            default:
                $displayType = $networkType;
                $networkIcon = '🌐';
        }

        // Extract company/owner information
        $ownerId = $profile['ownerId'] ?? 'unknown';
        $organizationId = $profile['organizationId'] ?? $ownerId;

        $profilesMap[$profile['id']] = [
            'socialNetworkUsername' => $profile['socialNetworkUsername'] ?? 'Unknown Profile',
            'avatarUrl' => $profile['avatarUrl'] ?? '',
            'networkType' => $displayType,
            'networkIcon' => $networkIcon,
            'ownerId' => $ownerId,
            'organizationId' => $organizationId,
            'profileUrl' => $profile['profileUrl'] ?? '',
            'isActive' => $profile['isActive'] ?? false
        ];

        // Group profiles by organization/owner for company grouping
        if (!isset($companiesMap[$organizationId])) {
            $companiesMap[$organizationId] = [
                'profiles' => [],
                'name' => null // Will be determined from tags or profile names
            ];
        }
        $companiesMap[$organizationId]['profiles'][] = $profile['id'];
    }
}

// Initialize arrays for posts grouped by company
$postsByCompany = [];
$mergedCalendarPosts = [];

// Get posts from various possible locations
$posts = [];
if (isset($postsData['debug_details']['calendar_view']['response']['data'])) {
    $posts = $postsData['debug_details']['calendar_view']['response']['data'];
} elseif (isset($postsData['data'])) {
    $posts = $postsData['data'];
} elseif (isset($postsData['scheduled_posts'])) {
    $posts = $postsData['scheduled_posts'];
}

// If still no posts, try all message states
if (empty($posts)) {
    foreach (['all_messages', 'scheduled_messages', 'sent_messages', 'draft_messages'] as $messageType) {
        if (isset($postsData['debug_details'][$messageType]['response']['data']) &&
            !empty($postsData['debug_details'][$messageType]['response']['data'])) {
            $posts = array_merge($posts, $postsData['debug_details'][$messageType]['response']['data']);
        }
    }
}

// Remove duplicate posts (by ID)
$uniquePosts = [];
$seenIds = [];
foreach ($posts as $post) {
    if (isset($post['id']) && !in_array($post['id'], $seenIds)) {
        $uniquePosts[] = $post;
        $seenIds[] = $post['id'];
    }
}
$posts = $uniquePosts;

// Process posts and extract company information from tags
foreach ($posts as $post) {
    // Get profile ID(s)
    $profileIds = [];
    if (isset($post['socialProfile']['id'])) {
        $profileIds[] = $post['socialProfile']['id'];
    } elseif (isset($post['socialProfileId'])) {
        $profileIds[] = $post['socialProfileId'];
    } elseif (isset($post['socialProfileIds']) && !empty($post['socialProfileIds'])) {
        $profileIds = $post['socialProfileIds'];
    }

    // Extract tags
    $tags = [];
    if (isset($post['tags']) && is_array($post['tags'])) {
        $tags = $post['tags'];
    }
    // Also check for hashtags
    if (isset($post['hashtags']) && is_array($post['hashtags'])) {
        $tags = array_merge($tags, $post['hashtags']);
    }

    // Determine company from tags (look for business name tags)
    $companyName = null;
    $companyTag = null;
    foreach ($tags as $tag) {
        // Tags might be objects or strings
        $tagValue = is_array($tag) ? ($tag['name'] ?? $tag['value'] ?? $tag['tag'] ?? '') : $tag;
        $tagValue = trim($tagValue);

        // Look for company indicators in tags
        if (!empty($tagValue)) {
            // Check if this might be a company name (you can customize this logic)
            if (!preg_match('/^#/', $tagValue) || preg_match('/(company|business|store|shop|brand)/i', $tagValue)) {
                if (!$companyTag) {
                    $companyTag = $tagValue;
                    $companyName = str_replace('#', '', $tagValue);
                }
            }
        }
    }

    // Extract media URLs
    $mediaUrls = [];
    if (isset($post['mediaUrls']) && is_array($post['mediaUrls'])) {
        foreach ($post['mediaUrls'] as $media) {
            if (is_string($media)) {
                $mediaUrls[] = $media;
            } elseif (isset($media['url'])) {
                $mediaUrls[] = $media['url'];
            }
        }
    }
    if (isset($post['media']) && is_array($post['media'])) {
        foreach ($post['media'] as $media) {
            if (isset($media['url'])) {
                $mediaUrls[] = $media['url'];
            } elseif (isset($media['downloadUrl'])) {
                $mediaUrls[] = $media['downloadUrl'];
            }
        }
    }

    // Process each profile this post is for
    foreach ($profileIds as $profileId) {
        $profileInfo = isset($profilesMap[$profileId]) ? $profilesMap[$profileId] : [
            'socialNetworkUsername' => 'Unknown Profile (ID: ' . $profileId . ')',
            'avatarUrl' => '',
            'networkType' => 'Unknown',
            'networkIcon' => '❓',
            'organizationId' => 'unknown'
        ];

        // Determine company group
        $organizationId = $profileInfo['organizationId'];
        if ($companyName) {
            // Override with tag-based company name
            if (!isset($companiesMap[$organizationId])) {
                $companiesMap[$organizationId] = [
                    'profiles' => [],
                    'name' => $companyName
                ];
            } else {
                $companiesMap[$organizationId]['name'] = $companyName;
            }
        }

        // If no company name from tags, try to extract from profile name
        if (!$companiesMap[$organizationId]['name']) {
            // Extract potential company name from profile username
            $username = $profileInfo['socialNetworkUsername'];
            // Remove common suffixes/prefixes
            $cleanName = preg_replace('/(Official|Page|Account|Profile)$/i', '', $username);
            $companiesMap[$organizationId]['name'] = trim($cleanName);
        }

        $postUrl = $post['postUrl'] ?? $post['permalink'] ?? $post['url'] ?? '';
        $scheduledTime = $post['scheduledSendTime'] ?? $post['scheduledTime'] ?? $post['createdTime'] ?? '';
        $postText = $post['text'] ?? $post['message'] ?? '';
        $postState = $post['state'] ?? 'UNKNOWN';

        $mergedPost = [
            'company_name' => $companiesMap[$organizationId]['name'] ?? 'Unknown Company',
            'company_id' => $organizationId,
            'location_name' => $profileInfo['socialNetworkUsername'],
            'profile_id' => $profileId,
            'profile_image_url' => $profileInfo['avatarUrl'],
            'network_type' => $profileInfo['networkType'],
            'network_icon' => $profileInfo['networkIcon'],
            'scheduled_date' => $scheduledTime,
            'post_text' => listing . phpsubstr($postText, 0, 200) . (strlen($postText) > 200 ? '...' : ''),
            'post_state' => $postState,
            'post_media' => $mediaUrls,
            'post_link' => $postUrl,
            'post_id' => $post['id'] ?? 'unknown',
            'tags' => $tags,
            'company_tag' => $companyTag
        ];

        $mergedCalendarPosts[] = $mergedPost;

        // Group by company
        if (!isset($postsByCompany[$organizationId])) {
            $postsByCompany[$organizationId] = [
                'company_name' => $companiesMap[$organizationId]['name'] ?? 'Unknown Company',
                'posts' => [],
                'networks' => []
            ];
        }
        $postsByCompany[$organizationId]['posts'][] = $mergedPost;
        if (!in_array($profileInfo['networkType'], $postsByCompany[$organizationId]['networks'])) {
            $postsByCompany[$organizationId]['networks'][] = $profileInfo['networkType'];
        }
    }
}

// Sort posts by scheduled date within each company
foreach ($postsByCompany as &$company) {
    usort($company['posts'], function($a, $b) {
        return strcmp($a['scheduled_date'], $b['scheduled_date']);
    });
}

// Sort overall posts by scheduled date
usort($mergedCalendarPosts, function($a, $b) {
    return strcmp($a['scheduled_date'], $b['scheduled_date']);
});

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hootsuite Calendar - Grouped by Company</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 {
            margin: 0;
            color: #764ba2;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #667eea;
        }
        .summary-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        .company-section {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .company-header {
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .network-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .network-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .post-grid {
            display: grid;
            gap: 15px;
        }
        .post-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 12px;
            border: 2px solid #667eea;
        }
        .post-meta {
            flex-grow: 1;
        }
        .profile-name {
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .network-icon {
            font-size: 1.2em;
        }
        .scheduled-date {
            color: #666;
            font-size: 0.9em;
            margin-top: 4px;
        }
        .post-text {
            color: #444;
            margin: 12px 0;
            padding: 12px;
            background: white;
            border-radius: 8px;
            line-height: 1.5;
        }
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 10px 0;
        }
        .tag {
            background: #e9ecef;
            color: #495057;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85em;
        }
        .tag.company-tag {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 500;
        }
        .media-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .media-thumb {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
        }
        .state-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .state-scheduled { background: #d4edda; color: #155724; }
        .state-sent { background: #cce5ff; color: #004085; }
        .state-draft { background: #fff3cd; color: #856404; }
        .state-pending_approval { background: #f8d7da; color: #721c24; }
        .raw-data {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9em;
        }
        .toggle-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        .toggle-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📅 Hootsuite Calendar - Company Grouped View</h1>
        <p>Posts organized by company and social network</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <h3>Total Companies</h3>
            <div class="number"><?php echo count($postsByCompany); ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Posts</h3>
            <div class="number"><?php echo count($mergedCalendarPosts); ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Profiles</h3>
            <div class="number"><?php echo count($profilesMap); ?></div>
        </div>
        <div class="summary-card">
            <h3>Date Range</h3>
            <div style="font-size: 1.1em; margin-top: 10px;">
                <?php echo date('M d, Y') . ' - ' . date('M d, Y', strtotime('+60 days')); ?>
            </div>
        </div>
    </div>

    <?php if (empty($postsByCompany)): ?>
        <div class="company-section">
            <p>No posts found. This could mean:</p>
            <ul>
                <li>No posts are scheduled in Hootsuite</li>
                <li>Posts are in DRAFT or PENDING_APPROVAL state</li>
                <li>The access token doesn't have permission to view posts</li>
            </ul>
        </div>
    <?php else: ?>
        <?php foreach ($postsByCompany as $companyId => $companyData): ?>
            <div class="company-section">
                <div class="company-header">
                    <div class="company-name">
                        🏢 <?php echo htmlspecialchars($companyData['company_name']); ?>
                    </div>
                    <div class="network-badges">
                        <?php foreach ($companyData['networks'] as $network): ?>
                            <span class="network-badge"><?php echo htmlspecialchars($network); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 10px; color: #666;">
                        Company ID: <?php echo htmlspecialchars($companyId); ?> |
                        <?php echo count($companyData['posts']); ?> posts scheduled
                    </div>
                </div>

                <div class="post-grid">
                    <?php foreach ($companyData['posts'] as $post): ?>
                        <div class="post-card">
                            <div class="post-header">
                                <?php if ($post['profile_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($post['profile_image_url']); ?>"
                                         alt="Profile" class="profile-img" onerror="this.style.display='none'">
                                <?php endif; ?>
                                <div class="post-meta">
                                    <div class="profile-name">
                                        <span class="network-icon"><?php echo $post['network_icon']; ?></span>
                                        <?php echo htmlspecialchars($post['location_name']); ?>
                                        <span style="color: #999; font-weight: normal; font-size: 0.9em;">
                                                (<?php echo htmlspecialchars($post['network_type']); ?>)
                                            </span>
                                    </div>
                                    <div class="scheduled-date">
                                        <?php
                                        if ($post['scheduled_date']) {
                                            echo '📅 ' . date('M d, Y g:i A', strtotime($post['scheduled_date']));
                                        } else {
                                            echo '📅 No date set';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <span class="state-badge state-<?php echo strtolower($post['post_state']); ?>">
                                        <?php echo htmlspecialchars($post['post_state']); ?>
                                    </span>
                            </div>

                            <?php if (!empty($post['tags'])): ?>
                                <div class="tags-container">
                                    <?php foreach ($post['tags'] as $tag): ?>
                                        <?php
                                        $tagValue = is_array($tag) ? ($tag['name'] ?? $tag['value'] ?? $tag['tag'] ?? '') : $tag;
                                        $isCompanyTag = ($tagValue == $post['company_tag']);
                                        ?>
                                        <span class="tag <?php echo $isCompanyTag ? 'company-tag' : ''; ?>">
                                                <?php echo htmlspecialchars($tagValue); ?>
                                            </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($post['post_text']): ?>
                                <div class="post-text">
                                    <?php echo htmlspecialchars($post['post_text']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($post['post_media'])): ?>
                                <div class="media-container">
                                    <?php foreach (array_slice($post['post_media'], 0, 4) as $mediaUrl): ?>
                                        <img src="<?php echo htmlspecialchars($mediaUrl); ?>"
                                             alt="Post media" class="media-thumb" onerror="this.style.display='none'">
                                    <?php endforeach; ?>
                                    <?php if (count($post['post_media']) > 4): ?>
                                        <div style="display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 8px; font-weight: bold; color: #666;">
                                            +<?php echo count($post['post_media']) - 4; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($post['post_link']): ?>
                                <p style="margin-top: 10px;">
                                    <small>🔗 <a href="<?php echo htmlspecialchars($post['post_link']); ?>" target="_blank" style="color: #667eea;">View Post</a></small>
                                </p>
                            <?php endif; ?>

                            <p style="margin-top: 10px; font-size: 0.8em; color: #999;">
                                Post ID: <?php echo htmlspecialchars($post['post_id']); ?> |
                                Profile ID: <?php echo htmlspecialchars($post['profile_id']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="raw-data">
        <h2>🔍 Debug Information</h2>

        <button class="toggle-btn" onclick="toggleSection('raw-merged')">Show/Hide Raw Merged Data</button>
        <div id="raw-merged" style="display: none;">
            <h3>Raw Merged Calendar Posts</h3>
            <pre><?php print_r($mergedCalendarPosts); ?></pre>
        </div>

        <button class="toggle-btn" onclick="toggleSection('company-map')">Show/Hide Company Mapping</button>
        <div id="company-map" style="display: none;">
            <h3>Companies Map</h3>
            <pre><?php print_r($companiesMap); ?></pre>
        </div>

        <button class="toggle-btn" onclick="toggleSection('profile-map')">Show/Hide Profile Mapping</button>
        <div id="profile-map" style="display: none;">
            <h3>Profile Mapping (with Network Types)</h3>
            <pre><?php print_r($profilesMap); ?></pre>
        </div>

        <button class="toggle-btn" onclick="toggleSection('grouped-posts')">Show/Hide Posts by Company</button>
        <div id="grouped-posts" style="display: none;">
            <h3>Posts Grouped by Company</h3>
            <pre><?php print_r($postsByCompany); ?></pre>
        </div>

        <h3>Summary Statistics</h3>
        <pre><?php
            $stats = [
                'Total Posts' => count($mergedCalendarPosts),
                'Total Companies' => count($postsByCompany),
                'Total Profiles' => count($profilesMap),
                'Unique Post IDs' => count($seenIds),
                'Network Types Found' => array_unique(array_column($profilesMap, 'networkType')),
                'Posts by State' => array_count_values(array_column($mergedCalendarPosts, 'post_state'))
            ];
            print_r($stats);
            ?></pre>
    </div>
</div>

<script>
    function toggleSection(id) {
        const section = document.getElementById(id);
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    }
</script>
</body>
</html>