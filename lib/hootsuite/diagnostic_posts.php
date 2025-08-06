<?php
session_start();
include __DIR__ . '/../../config.php';

// Set JSON content type
header('Content-Type: text/html; charset=utf-8');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    die("No access token found. Please authenticate first by visiting test_auth.php");
}

$access_token = $_SESSION['access_token'];

// Function to make API calls with detailed error handling
function makeApiCall($url, $access_token) {
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Calling:</strong> " . htmlspecialchars($url) . "<br>";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "<strong>HTTP Code:</strong> " . $http_code . "<br>";

    if ($error) {
        echo "<strong style='color: red;'>Error:</strong> " . $error . "<br>";
    }

    $decoded = json_decode($result, true);

    if ($decoded) {
        echo "<strong style='color: green;'>✓ Valid JSON response</strong><br>";
        if (isset($decoded['data'])) {
            echo "<strong>Data items found:</strong> " . count($decoded['data']) . "<br>";
        }
        if (isset($decoded['error'])) {
            echo "<strong style='color: red;'>API Error:</strong> " . $decoded['error'] . "<br>";
        }
    } else {
        echo "<strong style='color: red;'>✗ Invalid JSON response</strong><br>";
        echo "<details><summary>Raw Response</summary><pre>" . htmlspecialchars(substr($result, 0, 500)) . "</pre></details>";
    }

    echo "</div>";

    return $decoded;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic: Find Posts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .post-item {
            background: #f9f9f9;
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }
        pre {
            background: #f0f0f0;
            padding: 10px;
            overflow-x: auto;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<h1>🔍 Diagnostic: Finding Your Hootsuite Posts</h1>

<div class="section">
    <h2>Step 1: Check Access Token</h2>
    <?php
    echo "Token exists: <span class='success'>✓ Yes</span><br>";
    echo "Token preview: " . substr($access_token, 0, 20) . "...<br>";
    ?>
</div>

<div class="section">
    <h2>Step 2: Try Different API Endpoints</h2>

    <h3>A. Get ALL Messages (no filters)</h3>
    <?php
    $allMessages = makeApiCall(
        "https://platform.hootsuite.com/v1/messages?limit=10",
        $access_token
    );

    if ($allMessages && isset($allMessages['data']) && count($allMessages['data']) > 0) {
        echo "<div class='success'>✓ Found " . count($allMessages['data']) . " messages</div>";
        foreach ($allMessages['data'] as $msg) {
            echo "<div class='post-item'>";
            echo "<strong>ID:</strong> " . $msg['id'] . "<br>";
            echo "<strong>State:</strong> " . ($msg['state'] ?? 'unknown') . "<br>";
            echo "<strong>Text:</strong> " . htmlspecialchars(substr($msg['text'] ?? '', 0, 100)) . "...<br>";
            echo "<strong>Scheduled:</strong> " . ($msg['scheduledSendTime'] ?? 'not set') . "<br>";
            if (isset($msg['tags']) && !empty($msg['tags'])) {
                echo "<strong>Tags:</strong> " . implode(', ', $msg['tags']) . "<br>";
            }
            echo "</div>";
        }
    } else {
        echo "<div class='error'>✗ No messages found</div>";
    }
    ?>

    <h3>B. Get SCHEDULED Messages</h3>
    <?php
    $scheduledMessages = makeApiCall(
        "https://platform.hootsuite.com/v1/messages?state=SCHEDULED&limit=10",
        $access_token
    );

    if ($scheduledMessages && isset($scheduledMessages['data']) && count($scheduledMessages['data']) > 0) {
        echo "<div class='success'>✓ Found " . count($scheduledMessages['data']) . " scheduled messages</div>";
    } else {
        echo "<div class='error'>✗ No scheduled messages found</div>";
    }
    ?>

    <h3>C. Get SENT Messages (Historical)</h3>
    <?php
    $sentMessages = makeApiCall(
        "https://platform.hootsuite.com/v1/messages?state=SENT&limit=10",
        $access_token
    );

    if ($sentMessages && isset($sentMessages['data']) && count($sentMessages['data']) > 0) {
        echo "<div class='success'>✓ Found " . count($sentMessages['data']) . " sent messages</div>";
    } else {
        echo "<div class='error'>✗ No sent messages found</div>";
    }
    ?>

    <h3>D. Get Messages by Date Range (Next 30 Days)</h3>
    <?php
    $today = date('Y-m-d');
    $futureDate = date('Y-m-d', strtotime('+30 days'));
    $dateRangeMessages = makeApiCall(
        "https://platform.hootsuite.com/v1/messages?startTime={$today}T00:00:00Z&endTime={$futureDate}T23:59:59Z&limit=10",
        $access_token
    );

    if ($dateRangeMessages && isset($dateRangeMessages['data']) && count($dateRangeMessages['data']) > 0) {
        echo "<div class='success'>✓ Found " . count($dateRangeMessages['data']) . " messages in date range</div>";
    } else {
        echo "<div class='error'>✗ No messages found in date range ({$today} to {$futureDate})</div>";
    }
    ?>

    <h3>E. Get Messages by Date Range (Past 30 Days)</h3>
    <?php
    $pastDate = date('Y-m-d', strtotime('-30 days'));
    $pastMessages = makeApiCall(
        "https://platform.hootsuite.com/v1/messages?startTime={$pastDate}T00:00:00Z&endTime={$today}T23:59:59Z&limit=10",
        $access_token
    );

    if ($pastMessages && isset($pastMessages['data']) && count($pastMessages['data']) > 0) {
        echo "<div class='success'>✓ Found " . count($pastMessages['data']) . " messages in past 30 days</div>";
    } else {
        echo "<div class='error'>✗ No messages found in past 30 days</div>";
    }
    ?>
</div>

<div class="section">
    <h2>Step 3: Check Organization/Team Access</h2>
    <?php
    // Get user's organizations
    $orgs = makeApiCall(
        "https://platform.hootsuite.com/v1/organizations",
        $access_token
    );

    if ($orgs && isset($orgs['data'])) {
        echo "<h3>Organizations Found:</h3>";
        foreach ($orgs['data'] as $org) {
            echo "<div class='post-item'>";
            echo "<strong>Org ID:</strong> " . $org['id'] . "<br>";
            echo "<strong>Name:</strong> " . ($org['name'] ?? 'unnamed') . "<br>";
            echo "</div>";
        }
    }

    // Get teams
    $teams = makeApiCall(
        "https://platform.hootsuite.com/v1/teams",
        $access_token
    );

    if ($teams && isset($teams['data'])) {
        echo "<h3>Teams Found:</h3>";
        foreach ($teams['data'] as $team) {
            echo "<div class='post-item'>";
            echo "<strong>Team ID:</strong> " . $team['id'] . "<br>";
            echo "<strong>Name:</strong> " . ($team['name'] ?? 'unnamed') . "<br>";
            echo "</div>";
        }
    }
    ?>
</div>

<div class="section">
    <h2>Step 4: Check Permissions</h2>
    <?php
    $me = makeApiCall(
        "https://platform.hootsuite.com/v1/me",
        $access_token
    );

    if ($me && isset($me['data'])) {
        echo "<div class='post-item'>";
        echo "<strong>User:</strong> " . ($me['data']['fullName'] ?? 'unknown') . "<br>";
        echo "<strong>Email:</strong> " . ($me['data']['email'] ?? 'unknown') . "<br>";
        echo "<strong>Account Created:</strong> " . ($me['data']['createdDate'] ?? 'unknown') . "<br>";
        echo "</div>";
    }
    ?>
</div>

<div class="section">
    <h2>Summary</h2>
    <?php
    $totalFound = 0;
    $foundIn = [];

    if ($allMessages && isset($allMessages['data'])) {
        $totalFound += count($allMessages['data']);
        $foundIn[] = "All Messages: " . count($allMessages['data']);
    }
    if ($scheduledMessages && isset($scheduledMessages['data'])) {
        $foundIn[] = "Scheduled: " . count($scheduledMessages['data']);
    }
    if ($sentMessages && isset($sentMessages['data'])) {
        $foundIn[] = "Sent: " . count($sentMessages['data']);
    }

    if ($totalFound > 0) {
        echo "<div class='success'>✓ Successfully connected to Hootsuite API</div>";
        echo "<div class='success'>✓ Found messages in: " . implode(', ', $foundIn) . "</div>";
        echo "<p>The issue might be with how the data is being processed in listing.php</p>";
    } else {
        echo "<div class='error'>✗ No messages found in any endpoint</div>";
        echo "<p>Possible reasons:</p>";
        echo "<ul>";
        echo "<li>No posts have been created in Hootsuite yet</li>";
        echo "<li>Posts are in a different organization/team that this token doesn't have access to</li>";
        echo "<li>The OAuth scope might be missing necessary permissions</li>";
        echo "<li>Posts might be archived or in a different state</li>";
        echo "</ul>";
    }
    ?>
</div>

<div class="section">
    <h2>Next Steps</h2>
    <ol>
        <li>If posts were found above, the issue is with the listing.php processing</li>
        <li>If no posts were found, create a test post in Hootsuite first</li>
        <li>Make sure you're logged into the correct Hootsuite organization</li>
        <li>Check that posts are created for the profiles you see (organizationId: 967793)</li>
    </ol>

    <p><a href="listing.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;">Back to Listing</a></p>
</div>
</body>
</html>