<?php
session_start();
include('config.php');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    die("No access token found. Please authenticate first by visiting test_auth.php");
}

$access_token = $_SESSION['access_token'];

// Set content type to plain text for better readability
header('Content-Type: text/plain; charset=utf-8');

echo "===========================================\n";
echo "HOOTSUITE API DETAILED DEBUG\n";
echo "===========================================\n\n";

// Function to make API call and display FULL results
function debugApiCall($url, $access_token, $description) {
    echo "-------------------------------------------\n";
    echo "TEST: $description\n";
    echo "-------------------------------------------\n";
    echo "URL: $url\n\n";

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

    echo "HTTP Code: $http_code\n";

    if ($error) {
        echo "cURL Error: $error\n";
        return null;
    }

    $data = json_decode($result, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Decode Error: " . json_last_error_msg() . "\n";
        echo "Raw Response (first 500 chars):\n";
        echo substr($result, 0, 500) . "\n";
        return null;
    }

    echo "\nFULL RESPONSE:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

    // Analyze the response
    if (isset($data['data'])) {
        if (is_array($data['data'])) {
            echo "ANALYSIS:\n";
            echo "- Found 'data' array with " . count($data['data']) . " items\n";

            if (count($data['data']) > 0) {
                echo "- First item structure:\n";
                $firstItem = $data['data'][0];
                echo "  Available fields: " . implode(', ', array_keys($firstItem)) . "\n";

                // Check for specific fields
                if (isset($firstItem['state'])) {
                    echo "  State: " . $firstItem['state'] . "\n";
                }
                if (isset($firstItem['scheduledSendTime'])) {
                    echo "  Scheduled Time: " . $firstItem['scheduledSendTime'] . "\n";
                }
                if (isset($firstItem['text'])) {
                    echo "  Has text content: Yes (" . strlen($firstItem['text']) . " chars)\n";
                }
                if (isset($firstItem['socialProfileIds'])) {
                    echo "  Social Profile IDs: " . implode(', ', $firstItem['socialProfileIds']) . "\n";
                }
                if (isset($firstItem['tags'])) {
                    echo "  Tags: " . implode(', ', $firstItem['tags']) . "\n";
                }
            }
        } else {
            echo "ANALYSIS: 'data' exists but is not an array\n";
        }
    } else {
        echo "ANALYSIS: No 'data' field in response\n";
        echo "Root level fields: " . implode(', ', array_keys($data)) . "\n";
    }

    echo "\n";
    return $data;
}

// Date references
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$lastWeek = date('Y-m-d', strtotime('-7 days'));
$lastMonth = date('Y-m-d', strtotime('-30 days'));
$last60Days = date('Y-m-d', strtotime('-60 days'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$nextWeek = date('Y-m-d', strtotime('+7 days'));
$nextMonth = date('Y-m-d', strtotime('+30 days'));
$next60Days = date('Y-m-d', strtotime('+60 days'));
$nextYear = date('Y-m-d', strtotime('+365 days'));

echo "DATE REFERENCES:\n";
echo "- Today: $today\n";
echo "- Last 60 days: $last60Days to $today\n";
echo "- Next 60 days: $today to $next60Days\n";
echo "- Next year: $today to $nextYear\n\n";

// Test 1: Get all messages without any filters
echo "===========================================\n";
echo "TEST 1: GET ALL MESSAGES (NO FILTERS)\n";
echo "===========================================\n";
$allMessages = debugApiCall(
    "https://platform.hootsuite.com/v1/messages?limit=5",
    $access_token,
    "Get 5 messages without any filters"
);

// Test 2: Get messages with wide date range
echo "===========================================\n";
echo "TEST 2: MESSAGES WITH DATE RANGE\n";
echo "===========================================\n";
$dateRangeMessages = debugApiCall(
    "https://platform.hootsuite.com/v1/messages?startTime={$last60Days}T00:00:00Z&endTime={$next60Days}T23:59:59Z&limit=5",
    $access_token,
    "Messages from 60 days ago to 60 days ahead"
);

// Test 3: Get SCHEDULED messages
echo "===========================================\n";
echo "TEST 3: SCHEDULED MESSAGES ONLY\n";
echo "===========================================\n";
$scheduledMessages = debugApiCall(
    "https://platform.hootsuite.com/v1/messages?state=SCHEDULED&limit=5",
    $access_token,
    "Only SCHEDULED state messages"
);

// Test 4: Get SENT messages
echo "===========================================\n";
echo "TEST 4: SENT MESSAGES (HISTORICAL)\n";
echo "===========================================\n";
$sentMessages = debugApiCall(
    "https://platform.hootsuite.com/v1/messages?state=SENT&limit=5",
    $access_token,
    "Only SENT state messages"
);

// Test 5: Get messages for the next year
echo "===========================================\n";
echo "TEST 5: MESSAGES FOR NEXT YEAR\n";
echo "===========================================\n";
$futureMessages = debugApiCall(
    "https://platform.hootsuite.com/v1/messages?startTime={$today}T00:00:00Z&endTime={$nextYear}T23:59:59Z&limit=5",
    $access_token,
    "Messages scheduled for the next 365 days"
);

// Test 6: Get social profiles (to verify they're accessible)
echo "===========================================\n";
echo "TEST 6: SOCIAL PROFILES\n";
echo "===========================================\n";
$profiles = debugApiCall(
    "https://platform.hootsuite.com/v1/socialProfiles?limit=3",
    $access_token,
    "Get first 3 social profiles"
);

// Test 7: Try different state values
echo "===========================================\n";
echo "TEST 7: OTHER POSSIBLE STATES\n";
echo "===========================================\n";

$states = ['DRAFT', 'PENDING_APPROVAL', 'QUEUED', 'FAILED', 'REJECTED'];
foreach ($states as $state) {
    echo "\nTrying state: $state\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://platform.hootsuite.com/v1/messages?state=$state&limit=2");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($result, true);
    if ($data && isset($data['data'])) {
        $count = count($data['data']);
        if ($count > 0) {
            echo "  ✓ Found $count messages with state=$state\n";
            echo "  First message scheduled time: " . ($data['data'][0]['scheduledSendTime'] ?? 'N/A') . "\n";
        } else {
            echo "  - No messages with state=$state\n";
        }
    } else {
        echo "  ✗ Error or no data for state=$state\n";
    }
}

// Final summary
echo "\n===========================================\n";
echo "SUMMARY\n";
echo "===========================================\n";

$totalFound = 0;
$foundStates = [];

if ($allMessages && isset($allMessages['data'])) {
    foreach ($allMessages['data'] as $msg) {
        if (isset($msg['state']) && !in_array($msg['state'], $foundStates)) {
            $foundStates[] = $msg['state'];
        }
    }
    $totalFound += count($allMessages['data']);
}

echo "Total messages found (all tests): ~$totalFound\n";
echo "States found: " . (!empty($foundStates) ? implode(', ', $foundStates) : 'None') . "\n\n";

echo "TROUBLESHOOTING:\n";
if ($totalFound == 0) {
    echo "❌ NO MESSAGES FOUND\n";
    echo "Possible reasons:\n";
    echo "1. No posts have been created in Hootsuite yet\n";
    echo "2. Posts are in a different organization/team\n";
    echo "3. API permissions issue\n";
    echo "4. Posts are archived or deleted\n\n";
    echo "SOLUTION: Log into Hootsuite and create a test post first\n";
} else {
    echo "✅ MESSAGES FOUND\n";
    echo "The API is returning data. Check the full responses above to see:\n";
    echo "1. What fields are available\n";
    echo "2. What states the messages are in\n";
    echo "3. The date ranges of scheduled posts\n";
    echo "4. The structure of the data\n";
}

echo "\n===========================================\n";
echo "END OF DEBUG REPORT\n";
echo "===========================================\n";
?>