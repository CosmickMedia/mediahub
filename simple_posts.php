<?php
session_start();
include('config.php');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    die("No access token found. Please authenticate first by visiting test_auth.php");
}

$access_token = $_SESSION['access_token'];

// Function to make API calls
function fetchWithDateRange($access_token, $startDays = -60, $endDays = 60, $limit = 100) {
    $startDate = date('Y-m-d', strtotime("{$startDays} days"));
    $endDate = date('Y-m-d', strtotime("{$endDays} days"));

    $url = "https://platform.hootsuite.com/v1/messages";
    $url .= "?startTime={$startDate}T00:00:00Z";
    $url .= "&endTime={$endDate}T23:59:59Z";
    $url .= "&limit={$limit}";

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

// Fetch profiles
$profilesUrl = "https://platform.hootsuite.com/v1/socialProfiles?limit=100";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $profilesUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $access_token,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$profilesResult = curl_exec($ch);
curl_close($ch);

$profilesData = json_decode($profilesResult, true);
$profiles = [];

// Map profiles
if (isset($profilesData['data'])) {
    foreach ($profilesData['data'] as $profile) {
        $profiles[$profile['id']] = [
            'name' => $profile['socialNetworkUsername'] ?? 'Unknown',
            'type' => $profile['type'] ?? 'Unknown',
            'avatar' => $profile['avatarUrl'] ?? ''
        ];
    }
}

// Fetch posts with date range (required by API)
$postsData = fetchWithDateRange($access_token);
$posts = isset($postsData['data']) ? $postsData['data'] : [];

// Group posts by first tag (company identifier)
$postsByCompany = [];

foreach ($posts as $post) {
    // Get tags
    $tags = isset($post['tags']) && is_array($post['tags']) ? $post['tags'] : [];

    // Use first tag as company identifier, or 'no-tag' if none
    $companyTag = !empty($tags) ? $tags[0] : 'no-tag';
    $companyName = str_replace(['-', '_'], ' ', $companyTag);
    $companyName = ucwords($companyName);

    // Get profile info
    $profileId = null;
    if (isset($post['socialProfile']['id'])) {
        $profileId = $post['socialProfile']['id'];
    } elseif (isset($post['socialProfileId'])) {
        $profileId = $post['socialProfileId'];
    } elseif (isset($post['socialProfileIds'][0])) {
        $profileId = $post['socialProfileIds'][0];
    }

    $profileInfo = isset($profiles[$profileId]) ? $profiles[$profileId] : [
        'name' => 'Unknown Profile',
        'type' => 'Unknown',
        'avatar' => ''
    ];

    // Create post entry
    $postEntry = [
        'id' => $post['id'] ?? 'unknown',
        'state' => $post['state'] ?? 'UNKNOWN',
        'text' => $post['text'] ?? '',
        'scheduledTime' => $post['scheduledSendTime'] ?? $post['createdTime'] ?? '',
        'tags' => $tags,
        'profile' => $profileInfo,
        'profileId' => $profileId,
        'media' => $post['mediaUrls'] ?? [],
        'postUrl' => $post['postUrl'] ?? ''
    ];

    if (!isset($postsByCompany[$companyTag])) {
        $postsByCompany[$companyTag] = [
            'name' => $companyName,
            'tag' => $companyTag,
            'posts' => []
        ];
    }

    $postsByCompany[$companyTag]['posts'][] = $postEntry;
}

// Sort companies alphabetically
ksort($postsByCompany);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hootsuite Posts by Company Tags</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .company-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .company-header {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .company-tag {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .post {
            background: #f9f9f9;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .profile-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .post-state {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .state-SCHEDULED { background: #d4edda; color: #155724; }
        .state-SENT { background: #cce5ff; color: #004085; }
        .state-DRAFT { background: #fff3cd; color: #856404; }
        .post-text {
            margin: 10px 0;
            color: #333;
        }
        .tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .tag {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
        }
        .tag:first-child {
            background: #667eea;
            color: white;
        }
        .no-posts {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>📅 Hootsuite Posts - Organized by Company Tags</h1>
    <p>Posts from <?php echo date('M d, Y', strtotime('-60 days')); ?> to <?php echo date('M d, Y', strtotime('+60 days')); ?></p>
</div>

<div class="stats">
    <div class="stat-card">
        <div class="stat-number"><?php echo count($posts); ?></div>
        <div>Total Posts</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo count($postsByCompany); ?></div>
        <div>Companies/Tags</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo count($profiles); ?></div>
        <div>Social Profiles</div>
    </div>
</div>

<?php if (empty($posts)): ?>
    <div class="company-section">
        <div class="no-posts">
            <h2>No posts found</h2>
            <p>No posts were found in the date range. Make sure you have posts scheduled in Hootsuite.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($postsByCompany as $companyData): ?>
        <div class="company-section">
            <div class="company-header">
                <?php echo htmlspecialchars($companyData['name']); ?>
                <span class="company-tag"><?php echo htmlspecialchars($companyData['tag']); ?></span>
                <span style="font-size: 0.6em; color: #666; margin-left: 10px;">
                        (<?php echo count($companyData['posts']); ?> posts)
                    </span>
            </div>

            <?php foreach ($companyData['posts'] as $post): ?>
                <div class="post">
                    <div class="post-header">
                        <div class="profile-info">
                            <?php if ($post['profile']['avatar']): ?>
                                <img src="<?php echo htmlspecialchars($post['profile']['avatar']); ?>"
                                     class="profile-avatar"
                                     onerror="this.style.display='none'">
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($post['profile']['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($post['profile']['type']); ?></small>
                            </div>
                        </div>
                        <div>
                                <span class="post-state state-<?php echo $post['state']; ?>">
                                    <?php echo $post['state']; ?>
                                </span>
                        </div>
                    </div>

                    <?php if ($post['scheduledTime']): ?>
                        <div style="color: #666; font-size: 0.9em;">
                            📅 <?php echo date('M d, Y g:i A', strtotime($post['scheduledTime'])); ?>
                        </div>
                    <?php endif; ?>

                    <div class="post-text">
                        <?php echo htmlspecialchars(substr($post['text'], 0, 200)); ?>
                        <?php echo strlen($post['text']) > 200 ? '...' : ''; ?>
                    </div>

                    <?php if (!empty($post['tags'])): ?>
                        <div class="tags">
                            <?php foreach ($post['tags'] as $tag): ?>
                                <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 10px; font-size: 0.85em; color: #999;">
                        Post ID: <?php echo htmlspecialchars($post['id']); ?> |
                        Profile ID: <?php echo htmlspecialchars($post['profileId']); ?>
                        <?php if ($post['postUrl']): ?>
                            | <a href="<?php echo htmlspecialchars($post['postUrl']); ?>" target="_blank">View Post</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div style="margin-top: 40px; padding: 20px; background: white; border-radius: 10px;">
    <h3>Debug Information</h3>
    <p>Total posts fetched: <?php echo count($posts); ?></p>
    <p>Unique company tags found: <?php echo implode(', ', array_keys($postsByCompany)); ?></p>
    <p>API call used date range: <?php echo date('Y-m-d', strtotime('-60 days')); ?> to <?php echo date('Y-m-d', strtotime('+60 days')); ?></p>
</div>
</body>
</html>