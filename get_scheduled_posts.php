<?php
session_start();
include('config.php');

// Check if we have an access token
if (!isset($_SESSION['access_token'])) {
    die("No access token found. Please authenticate first by visiting test_auth.php");
}

$access_token = $_SESSION['access_token'];

// Get scheduled messages
// Note: The correct endpoint for scheduled messages
$schedule_url = "https://platform.hootsuite.com/v1/messages";

// You can add query parameters to filter the results
// For example: ?state=SCHEDULED to get only scheduled posts
$schedule_url .= "?state=SCHEDULED&limit=50";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $schedule_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer " . $access_token,
    "Content-Type: application/json"
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    die("cURL Error: " . $curl_error);
}

echo "<h2>Scheduled Posts from Hootsuite</h2>";
echo "HTTP Response Code: " . $http_code . "<br><br>";

if ($http_code == 200) {
    $data = json_decode($response, true);

    if (isset($data['data']) && count($data['data']) > 0) {
        echo "<h3>Found " . count($data['data']) . " scheduled posts:</h3>";

        foreach ($data['data'] as $post) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Scheduled for:</strong> " . $post['scheduledSendTime'] . "<br>";
            echo "<strong>Text:</strong> " . htmlspecialchars($post['text']) . "<br>";

            if (isset($post['socialProfileIds']) && count($post['socialProfileIds']) > 0) {
                echo "<strong>Profiles:</strong> " . implode(", ", $post['socialProfileIds']) . "<br>";
            }

            if (isset($post['media']) && count($post['media']) > 0) {
                echo "<strong>Has media:</strong> Yes (" . count($post['media']) . " items)<br>";
            }

            echo "<strong>State:</strong> " . $post['state'] . "<br>";
            echo "<strong>ID:</strong> " . $post['id'] . "<br>";
            echo "</div>";
        }

        // Show pagination info if available
        if (isset($data['pagination'])) {
            echo "<br><strong>Pagination:</strong><br>";
            echo "Total results: " . ($data['pagination']['total'] ?? 'N/A') . "<br>";
            if (isset($data['pagination']['next'])) {
                echo "There are more results available.<br>";
            }
        }
    } else {
        echo "No scheduled posts found.<br>";
    }

    // Show raw response for debugging
    echo "<br><details>";
    echo "<summary>View Raw Response</summary>";
    echo "<pre>" . htmlspecialchars(json_encode(json_decode($response), JSON_PRETTY_PRINT)) . "</pre>";
    echo "</details>";
} else {
    echo "Error retrieving scheduled posts.<br>";
    echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";
}

// Also get social profiles to understand which accounts are connected
echo "<br><h2>Your Connected Social Profiles</h2>";

$profiles_url = "https://platform.hootsuite.com/v1/socialProfiles";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $profiles_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer " . $access_token,
    "Content-Type: application/json"
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$profiles_response = curl_exec($ch);
$profiles_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($profiles_http_code == 200) {
    $profiles_data = json_decode($profiles_response, true);

    if (isset($profiles_data['data']) && count($profiles_data['data']) > 0) {
        echo "Found " . count($profiles_data['data']) . " connected profiles:<br><ul>";
        foreach ($profiles_data['data'] as $profile) {
            echo "<li><strong>" . htmlspecialchars($profile['socialNetworkUsername']) . "</strong> (" . $profile['type'] . " - ID: " . $profile['id'] . ")</li>";
        }
        echo "</ul>";
    }
}
?><?php
