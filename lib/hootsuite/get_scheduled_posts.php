<?php
session_start();
include('config.php');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    die("No access token found. Please authenticate first by visiting test_auth.php");
}

$access_token = $_SESSION['access_token'];

// Function to fetch all pages of results
function fetchAllMessages($access_token, $state = null) {
    $all_messages = [];
    $page_count = 0;
    $max_pages = 10;

    // Build initial URL with date range (API requires this)
    // Using 28-day range forward from today
    $base_url = "https://platform.hootsuite.com/v1/messages";

    // Create proper ISO 8601 formatted dates - today to 28 days in future
    $startTime = date('c');  // Today
    $endTime = date('c', strtotime('+28 days'));

    $params = [
        'limit' => 100,
        'startTime' => $startTime,
        'endTime' => $endTime
    ];

    if ($state) {
        $params['state'] = $state;
    }

    $url = $base_url . '?' . http_build_query($params);

    // Debug output
    echo "<!-- Debug: Fetching messages with state=" . ($state ?? 'ALL') . " from $startTime to $endTime -->\n";

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
                $all_messages = array_merge($all_messages, $data['data']);
            }

            // Check for next page
            $url = isset($data['pagination']['next']) ? $data['pagination']['next'] : null;
            $page_count++;
        } else {
            echo "Error fetching messages. HTTP Code: " . $http_code . "<br>";
            echo "Response: " . htmlspecialchars($response) . "<br>";
            break;
        }
    }

    return $all_messages;
}

// Fetch messages in different states
echo "<!-- Starting to fetch messages... -->\n";

// First try to get ALL messages without state filter
$all_messages_no_state = fetchAllMessages($access_token);
echo "<!-- Fetched " . count($all_messages_no_state) . " messages without state filter -->\n";

// Then get specific states (DRAFT is not supported, so we skip it)
$scheduled_messages = fetchAllMessages($access_token, 'SCHEDULED');
echo "<!-- Fetched " . count($scheduled_messages) . " SCHEDULED messages -->\n";

$pending_messages = fetchAllMessages($access_token, 'PENDING_APPROVAL');
echo "<!-- Fetched " . count($pending_messages) . " PENDING_APPROVAL messages -->\n";

// DRAFT state is not supported by the API, so we'll skip it
$draft_messages = [];
echo "<!-- DRAFT state not supported by API, skipping -->\n";

$sent_messages = fetchAllMessages($access_token, 'SENT');
echo "<!-- Fetched " . count($sent_messages) . " SENT messages -->\n";

// Try REJECTED state as well
$rejected_messages = fetchAllMessages($access_token, 'REJECTED');
echo "<!-- Fetched " . count($rejected_messages) . " REJECTED messages -->\n";

// Try FAILED state
$failed_messages = fetchAllMessages($access_token, 'FAILED');
echo "<!-- Fetched " . count($failed_messages) . " FAILED messages -->\n";

// Combine all messages
$all_messages = array_merge($all_messages_no_state, $scheduled_messages, $pending_messages, $sent_messages, $rejected_messages, $failed_messages);

// Remove duplicates by ID
$unique_messages = [];
$seen_ids = [];
foreach ($all_messages as $message) {
    if (!in_array($message['id'], $seen_ids)) {
        $unique_messages[] = $message;
        $seen_ids[] = $message['id'];
    }
}

// Sort by scheduled time (or created time if no scheduled time)
usort($unique_messages, function($a, $b) {
    $timeA = $a['scheduledSendTime'] ?? $a['createdTime'] ?? '0';
    $timeB = $b['scheduledSendTime'] ?? $b['createdTime'] ?? '0';
    return strcmp($timeA, $timeB);
});

// Get all possible fields from messages for metadata analysis
$all_fields = [];
foreach ($unique_messages as $message) {
    $all_fields = array_merge($all_fields, array_keys($message));
}
$all_fields = array_unique($all_fields);
sort($all_fields);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hootsuite Posts - Complete Metadata View</title>
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
        h1 {
            color: #333;
            margin: 0;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #667eea;
            font-size: 0.9em;
        }
        .summary-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        .post-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        .post-id {
            font-family: monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .state-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .state-SCHEDULED { background: #d4edda; color: #155724; }
        .state-SENT { background: #cce5ff; color: #004085; }
        .state-DRAFT { background: #fff3cd; color: #856404; }
        .state-PENDING_APPROVAL { background: #f8d7da; color: #721c24; }
        .state-FAILED { background: #f8d7da; color: #721c24; }
        .state-REJECTED { background: #e2e3e5; color: #383d41; }
        .metadata-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            font-size: 0.9em;
        }
        .metadata-key {
            font-weight: 600;
            color: #495057;
            padding: 5px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .metadata-value {
            padding: 5px;
            word-break: break-word;
        }
        .metadata-value.empty {
            color: #adb5bd;
            font-style: italic;
        }
        .metadata-value.array,
        .metadata-value.object {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 8px;
            font-family: monospace;
            font-size: 0.85em;
            max-height: 200px;
            overflow-y: auto;
        }
        .text-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin: 10px 0;
        }
        .tag {
            background: #667eea;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85em;
        }
        .media-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .media-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        .media-thumb {
            max-width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        .expand-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        .expand-btn:hover {
            background: #5a67d8;
        }
        .raw-json {
            display: none;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
        .fields-info {
            background: white;
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
    </style>
</head>
<body>

<div class="header">
    <h1>📊 Hootsuite Posts - Complete Metadata View</h1>
    <p>Displaying posts from <?php echo date('M d, Y'); ?> to <?php echo date('M d, Y', strtotime('+28 days')); ?> (Next 28 days)</p>
    <p style="color: #666; font-size: 0.9em;">Note: Showing scheduled posts for the next 28 days</p>
</div>

<div class="summary">
    <div class="summary-card">
        <h3>Unique Posts</h3>
        <div class="number"><?php echo count($unique_messages); ?></div>
    </div>
    <div class="summary-card">
        <h3>All Messages</h3>
        <div class="number"><?php echo count($all_messages_no_state); ?></div>
    </div>
    <div class="summary-card">
        <h3>Scheduled</h3>
        <div class="number"><?php echo count($scheduled_messages); ?></div>
    </div>
    <div class="summary-card">
        <h3>Pending Approval</h3>
        <div class="number"><?php echo count($pending_messages); ?></div>
    </div>
    <div class="summary-card">
        <h3>Rejected</h3>
        <div class="number"><?php echo count($rejected_messages ?? []); ?></div>
    </div>
    <div class="summary-card">
        <h3>Failed</h3>
        <div class="number"><?php echo count($failed_messages ?? []); ?></div>
    </div>
    <div class="summary-card">
        <h3>Sent</h3>
        <div class="number"><?php echo count($sent_messages); ?></div>
    </div>
</div>

<div class="fields-info">
    <h2>Available Metadata Fields (<?php echo count($all_fields); ?> total)</h2>
    <div class="field-list">
        <?php foreach ($all_fields as $field): ?>
            <span class="field-badge"><?php echo htmlspecialchars($field); ?></span>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($unique_messages)): ?>
    <div class="post-container">
        <p>No posts found. Please check:</p>
        <ul>
            <li>You have posts created in Hootsuite</li>
            <li>Your access token has the correct permissions</li>
            <li>You're accessing the correct organization</li>
        </ul>
    </div>
<?php else: ?>
    <?php foreach ($unique_messages as $index => $message): ?>
        <div class="post-container">
            <div class="post-header">
                <div>
                    <h3>Post #<?php echo ($index + 1); ?></h3>
                    <span class="post-id">ID: <?php echo htmlspecialchars($message['id'] ?? 'N/A'); ?></span>
                </div>
                <span class="state-badge state-<?php echo $message['state'] ?? 'UNKNOWN'; ?>">
                    <?php echo htmlspecialchars($message['state'] ?? 'UNKNOWN'); ?>
                </span>
            </div>

            <?php if (!empty($message['text'])): ?>
                <div class="text-content">
                    <strong>Post Text:</strong><br>
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($message['tags'])): ?>
                <div class="tags-container">
                    <strong style="margin-right: 10px;">Tags:</strong>
                    <?php foreach ($message['tags'] as $tag): ?>
                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h4>Complete Metadata:</h4>
            <div class="metadata-grid">
                <?php foreach ($message as $key => $value): ?>
                    <div class="metadata-key"><?php echo htmlspecialchars($key); ?></div>
                    <div class="metadata-value <?php echo empty($value) ? 'empty' : ''; ?> <?php echo is_array($value) || is_object($value) ? (is_array($value) ? 'array' : 'object') : ''; ?>">
                        <?php
                        if (is_null($value) || $value === '') {
                            echo '(empty)';
                        } elseif (is_bool($value)) {
                            echo $value ? 'true' : 'false';
                        } elseif (is_array($value) || is_object($value)) {
                            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            echo '<pre>' . htmlspecialchars($json) . '</pre>';
                        } else {
                            // Special formatting for certain fields
                            if (strpos($key, 'Time') !== false || strpos($key, 'Date') !== false) {
                                echo htmlspecialchars($value);
                                if (strtotime($value)) {
                                    echo ' <em>(' . date('M d, Y g:i A', strtotime($value)) . ')</em>';
                                }
                            } elseif (strpos($key, 'Url') !== false || strpos($key, 'url') !== false) {
                                echo '<a href="' . htmlspecialchars($value) . '" target="_blank">' . htmlspecialchars($value) . '</a>';
                            } else {
                                echo htmlspecialchars($value);
                            }
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($message['mediaUrls']) || !empty($message['media'])): ?>
                <h4>Media Attachments:</h4>
                <div class="media-preview">
                    <?php
                    $mediaItems = array_merge(
                        isset($message['mediaUrls']) ? (is_array($message['mediaUrls']) ? $message['mediaUrls'] : []) : [],
                        isset($message['media']) ? (is_array($message['media']) ? $message['media'] : []) : []
                    );

                    foreach ($mediaItems as $media):
                        $url = is_string($media) ? $media : ($media['url'] ?? $media['downloadUrl'] ?? '');
                        if ($url):
                            ?>
                            <div class="media-item">
                                <img src="<?php echo htmlspecialchars($url); ?>"
                                     alt="Media"
                                     class="media-thumb"
                                     onerror="this.parentElement.innerHTML='<span>Failed to load media</span>'">
                                <div style="margin-top: 5px;">
                                    <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" style="font-size: 0.8em;">View Full</a>
                                </div>
                            </div>
                        <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>

            <button class="expand-btn" onclick="toggleRaw('raw-<?php echo $index; ?>')">
                Show/Hide Raw JSON
            </button>
            <div id="raw-<?php echo $index; ?>" class="raw-json">
                <pre><?php echo htmlspecialchars(json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
    function toggleRaw(id) {
        const element = document.getElementById(id);
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
</script>

</body>
</html>