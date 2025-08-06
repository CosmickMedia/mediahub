<?php
session_start();
include('config.php');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    die("No access token found. Please authenticate first by visiting test_auth.php");
}

$access_token = $_SESSION['access_token'];

// Function to fetch all social profiles (handles pagination)
function fetchAllProfiles($access_token) {
    $all_profiles = [];
    $page_count = 0;
    $max_pages = 10;

    $url = "https://platform.hootsuite.com/v1/socialProfiles?limit=100";

    while ($url && $page_count < $max_pages) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $access_token,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            echo "cURL Error: " . $curl_error . "<br>";
            break;
        }

        if ($http_code == 200) {
            $data = json_decode($response, true);

            if (isset($data['data']) && is_array($data['data'])) {
                $all_profiles = array_merge($all_profiles, $data['data']);
            }

            // Check for next page
            $url = isset($data['pagination']['next']) ? $data['pagination']['next'] : null;
            $page_count++;
        } else {
            echo "Error fetching profiles. HTTP Code: " . $http_code . "<br>";
            echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";
            break;
        }
    }

    return $all_profiles;
}

// Fetch all social profiles
$profiles = fetchAllProfiles($access_token);

// Group profiles by type
$profiles_by_type = [];
foreach ($profiles as $profile) {
    $type = $profile['type'] ?? 'UNKNOWN';
    if (!isset($profiles_by_type[$type])) {
        $profiles_by_type[$type] = [];
    }
    $profiles_by_type[$type][] = $profile;
}

// Get all possible fields from profiles for metadata analysis
$all_fields = [];
foreach ($profiles as $profile) {
    $all_fields = array_merge($all_fields, array_keys($profile));
}
$all_fields = array_unique($all_fields);
sort($all_fields);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hootsuite Connected Social Profiles</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #764ba2;
            margin: 0;
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
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
        }
        .network-section {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .network-header {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .network-icon {
            font-size: 1.2em;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .profile-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            border: 3px solid #667eea;
            object-fit: cover;
        }
        .profile-info {
            flex-grow: 1;
        }
        .profile-name {
            font-weight: bold;
            font-size: 1.1em;
            color: #333;
            margin-bottom: 5px;
        }
        .profile-id {
            font-family: monospace;
            font-size: 0.85em;
            color: #666;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }
        .metadata-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        .metadata-item {
            display: flex;
            margin: 8px 0;
            font-size: 0.9em;
        }
        .metadata-key {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
        }
        .metadata-value {
            color: #333;
            word-break: break-word;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .expand-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            margin-top: 10px;
        }
        .expand-btn:hover {
            background: #5a67d8;
        }
        .raw-data {
            display: none;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.75em;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
        .fields-info {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .field-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .field-badge {
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85em;
        }
        .link-btn {
            color: #667eea;
            text-decoration: none;
            font-size: 0.85em;
            display: inline-block;
            margin-top: 5px;
        }
        .link-btn:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🌐 Connected Social Profiles</h1>
        <p>All social media accounts connected to your Hootsuite dashboard</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <h3>Total Profiles</h3>
            <div class="number"><?php echo count($profiles); ?></div>
        </div>
        <div class="summary-card">
            <h3>Active Profiles</h3>
            <div class="number">
                <?php echo count(array_filter($profiles, function($p) { return $p['isActive'] ?? false; })); ?>
            </div>
        </div>
        <div class="summary-card">
            <h3>Network Types</h3>
            <div class="number"><?php echo count($profiles_by_type); ?></div>
        </div>
    </div>

    <div class="fields-info">
        <h3>Available Profile Metadata Fields (<?php echo count($all_fields); ?> total)</h3>
        <div class="field-list">
            <?php foreach ($all_fields as $field): ?>
                <span class="field-badge"><?php echo htmlspecialchars($field); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($profiles)): ?>
        <div class="network-section">
            <p>No social profiles found. Please connect social accounts in Hootsuite.</p>
        </div>
    <?php else: ?>
        <?php foreach ($profiles_by_type as $type => $type_profiles): ?>
            <?php
            // Determine network icon and clean name
            $network_icon = '🌐';
            $clean_type = $type;

            switch(strtoupper($type)) {
                case 'FACEBOOKPAGE':
                case 'FACEBOOK':
                    $network_icon = '📘';
                    $clean_type = 'Facebook';
                    break;
                case 'INSTAGRAM':
                case 'INSTAGRAMBUSINESS':
                    $network_icon = '📷';
                    $clean_type = 'Instagram';
                    break;
                case 'TWITTER':
                case 'X':
                    $network_icon = '🐦';
                    $clean_type = 'X (Twitter)';
                    break;
                case 'LINKEDIN':
                case 'LINKEDINCOMPANY':
                case 'LINKEDINPROFILE':
                    $network_icon = '💼';
                    $clean_type = 'LinkedIn';
                    break;
                case 'TIKTOK':
                    $network_icon = '🎵';
                    $clean_type = 'TikTok';
                    break;
                case 'YOUTUBE':
                    $network_icon = '📺';
                    $clean_type = 'YouTube';
                    break;
                case 'PINTEREST':
                    $network_icon = '📌';
                    $clean_type = 'Pinterest';
                    break;
            }
            ?>

            <div class="network-section">
                <div class="network-header">
                    <span class="network-icon"><?php echo $network_icon; ?></span>
                    <?php echo htmlspecialchars($clean_type); ?>
                    <span style="font-size: 0.6em; color: #666;">
                        (<?php echo count($type_profiles); ?> profile<?php echo count($type_profiles) > 1 ? 's' : ''; ?>)
                    </span>
                </div>

                <div class="profile-grid">
                    <?php foreach ($type_profiles as $index => $profile): ?>
                        <div class="profile-card">
                            <div class="profile-header">
                                <?php if (!empty($profile['avatarUrl'])): ?>
                                    <img src="<?php echo htmlspecialchars($profile['avatarUrl']); ?>"
                                         alt="Profile Avatar"
                                         class="profile-avatar"
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <div class="profile-avatar" style="background: #e9ecef; display: flex; align-items: center; justify-content: center; font-size: 1.5em;">
                                        <?php echo $network_icon; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="profile-info">
                                    <div class="profile-name">
                                        <?php echo htmlspecialchars($profile['socialNetworkUsername'] ?? 'Unknown Profile'); ?>
                                        <?php if ($profile['isActive'] ?? false): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="profile-id">ID: <?php echo htmlspecialchars($profile['id'] ?? 'N/A'); ?></span>
                                </div>
                            </div>

                            <div class="metadata-section">
                                <?php
                                // Display key metadata fields
                                $key_fields = [
                                    'socialNetworkUserId' => 'Network User ID',
                                    'organizationId' => 'Organization ID',
                                    'ownerId' => 'Owner ID',
                                    'createdTime' => 'Created',
                                    'isDefault' => 'Default Profile',
                                    'teamIds' => 'Team IDs'
                                ];

                                foreach ($key_fields as $field => $label):
                                    if (isset($profile[$field])):
                                        ?>
                                        <div class="metadata-item">
                                            <div class="metadata-key"><?php echo $label; ?>:</div>
                                            <div class="metadata-value">
                                                <?php
                                                $value = $profile[$field];
                                                if (is_bool($value)) {
                                                    echo $value ? 'Yes' : 'No';
                                                } elseif (is_array($value)) {
                                                    echo htmlspecialchars(implode(', ', $value));
                                                } elseif ($field === 'createdTime' && strtotime($value)) {
                                                    echo date('M d, Y', strtotime($value));
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php
                                    endif;
                                endforeach;
                                ?>

                                <?php if (!empty($profile['profileUrl'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile['profileUrl']); ?>"
                                       target="_blank"
                                       class="link-btn">
                                        🔗 View Profile on <?php echo htmlspecialchars($clean_type); ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <button class="expand-btn" onclick="toggleRaw('raw-<?php echo $type . '-' . $index; ?>')">
                                Show/Hide All Metadata
                            </button>
                            <div id="raw-<?php echo $type . '-' . $index; ?>" class="raw-data">
                                <pre><?php echo htmlspecialchars(json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    function toggleRaw(id) {
        const element = document.getElementById(id);
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
</script>

</body>
</html>