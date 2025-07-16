<?php
/**
 * Complete Hootsuite API Integration
 *
 * This script provides a comprehensive integration with Hootsuite's API
 * including authentication, posting, and retrieving scheduled messages.
 */

class HootsuiteAPI {
    private $access_token;
    private $base_url = 'https://platform.hootsuite.com/v1';

    public function __construct($access_token) {
        $this->access_token = $access_token;
    }

    /**
     * Make authenticated API request
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->access_token}",
                "Accept: application/json",
                "Content-Type: application/json"
            ]
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        return [
            'status_code' => $http_code,
            'body' => json_decode($response, true),
            'raw_response' => $response
        ];
    }

    /**
     * Get user's social profiles
     */
    public function getSocialProfiles() {
        return $this->makeRequest('/socialProfiles');
    }

    /**
     * Get user information
     */
    public function getMe() {
        return $this->makeRequest('/me');
    }

    /**
     * Get scheduled messages
     */
    public function getScheduledMessages($social_profile_id, $start_date = null, $end_date = null) {
        $start_date = $start_date ?: date('Y-m-d\TH:i:s\Z', strtotime('-7 days'));
        $end_date = $end_date ?: date('Y-m-d\TH:i:s\Z', strtotime('+7 days'));

        $query = http_build_query([
            'start' => $start_date,
            'end' => $end_date,
            'socialProfileIds' => $social_profile_id,
        ]);

        return $this->makeRequest("/messages/outbound?{$query}");
    }

    /**
     * Schedule a new message
     */
    public function scheduleMessage($social_profile_id, $text, $schedule_time = null) {
        $message_data = [
            'text' => $text,
            'socialProfileIds' => [$social_profile_id]
        ];

        if ($schedule_time) {
            $message_data['scheduledSendTime'] = $schedule_time;
        }

        return $this->makeRequest('/messages', 'POST', $message_data);
    }

    /**
     * Get media upload URL
     */
    public function getMediaUploadUrl($size_bytes, $mime_type) {
        $data = [
            'sizeBytes' => $size_bytes,
            'mimeType' => $mime_type
        ];

        return $this->makeRequest('/media', 'POST', $data);
    }
}

// =======================
// CONFIGURATION & USAGE
// =======================

// STEP 1: Set your credentials here
$access_token = 'PASTE_YOUR_ACCESS_TOKEN_HERE';

// Create API instance
$hootsuite = new HootsuiteAPI($access_token);

// =======================
// HTML OUTPUT STARTS
// =======================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hootsuite API Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .profile-card { display: inline-block; margin: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; min-width: 200px; }
        .test-form { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
        input, textarea, select { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>

<h1>🔗 Hootsuite API Integration Test</h1>

<?php if ($access_token === 'PASTE_YOUR_ACCESS_TOKEN_HERE'): ?>
    <div class="section error">
        <h2>❌ Setup Required</h2>
        <p><strong>You need to get your Hootsuite credentials first!</strong></p>
        <p>Follow these steps:</p>
        <ol>
            <li><strong>Create a Hootsuite Developer Account:</strong>
                <ul>
                    <li>Go to <a href="https://developer.hootsuite.com/" target="_blank">https://developer.hootsuite.com/</a></li>
                    <li>Sign up or log in with your Hootsuite account</li>
                </ul>
            </li>
            <li><strong>Create an App:</strong>
                <ul>
                    <li>Go to "My Apps" in the developer dashboard</li>
                    <li>Click "Create New App"</li>
                    <li>Fill in the required information</li>
                    <li>Set redirect URI (e.g., http://localhost/callback.php)</li>
                </ul>
            </li>
            <li><strong>Get OAuth Credentials:</strong>
                <ul>
                    <li>Note your Client ID and Client Secret</li>
                    <li>Request the following scopes: <code>offline_access</code>, <code>read_write_messages</code>, <code>read_social_profiles</code></li>
                </ul>
            </li>
            <li><strong>Generate Access Token:</strong>
                <ul>
                    <li>Use OAuth 2.0 flow to get an access token</li>
                    <li>Or use Hootsuite's "Generate Token" feature in the developer dashboard for testing</li>
                </ul>
            </li>
        </ol>
    </div>

    <div class="section info">
        <h3>📋 Quick OAuth Example URL</h3>
        <p>Replace <code>YOUR_CLIENT_ID</code> and <code>YOUR_REDIRECT_URI</code> with your actual values:</p>
        <pre>https://platform.hootsuite.com/oauth2/auth?response_type=code&client_id=YOUR_CLIENT_ID&scope=offline_access,read_write_messages,read_social_profiles&redirect_uri=YOUR_REDIRECT_URI</pre>
    </div>

<?php else: ?>

    <?php
    try {
        // Test 1: Get user info
        echo '<div class="section">';
        echo '<h2>👤 User Information</h2>';
        $user_info = $hootsuite->getMe();

        if ($user_info['status_code'] === 200) {
            echo '<div class="success">✅ Successfully connected to Hootsuite!</div>';
            echo '<pre>' . htmlentities(json_encode($user_info['body'], JSON_PRETTY_PRINT)) . '</pre>';
        } else {
            echo '<div class="error">❌ Failed to get user info</div>';
            echo '<pre>' . htmlentities($user_info['raw_response']) . '</pre>';
        }
        echo '</div>';

        // Test 2: Get social profiles
        echo '<div class="section">';
        echo '<h2>📱 Social Profiles</h2>';
        $profiles = $hootsuite->getSocialProfiles();

        if ($profiles['status_code'] === 200 && isset($profiles['body']['data'])) {
            echo '<div class="success">✅ Found ' . count($profiles['body']['data']) . ' social profile(s)</div>';

            foreach ($profiles['body']['data'] as $profile) {
                echo '<div class="profile-card">';
                echo '<h4>' . htmlentities($profile['socialNetworkUsername'] ?? 'Unknown') . '</h4>';
                echo '<p><strong>Network:</strong> ' . htmlentities($profile['type'] ?? 'Unknown') . '</p>';
                echo '<p><strong>ID:</strong> <code>' . htmlentities($profile['id']) . '</code></p>';
                echo '<p><strong>Status:</strong> ' . htmlentities($profile['socialNetworkId'] ? 'Connected' : 'Not Connected') . '</p>';
                echo '</div>';
            }

            // Use first profile for testing
            $first_profile_id = $profiles['body']['data'][0]['id'] ?? null;

            if ($first_profile_id) {
                // Test 3: Get scheduled messages
                echo '</div><div class="section">';
                echo '<h2>📅 Scheduled Messages (Last 7 days)</h2>';
                $messages = $hootsuite->getScheduledMessages($first_profile_id);

                if ($messages['status_code'] === 200) {
                    $posts = $messages['body']['data'] ?? [];
                    echo '<div class="success">✅ Retrieved ' . count($posts) . ' scheduled post(s)</div>';
                    if (!empty($posts)) {
                        echo '<pre>' . htmlentities(json_encode($posts, JSON_PRETTY_PRINT)) . '</pre>';
                    } else {
                        echo '<p>No scheduled posts found in the specified date range.</p>';
                    }
                } else {
                    echo '<div class="error">❌ Failed to get scheduled messages</div>';
                    echo '<pre>' . htmlentities($messages['raw_response']) . '</pre>';
                }

                // Test 4: Post scheduling form
                echo '</div><div class="section">';
                echo '<h2>✍️ Schedule a Test Message</h2>';

                if ($_POST['test_message'] ?? false) {
                    $message_text = $_POST['message_text'];
                    $schedule_time = $_POST['schedule_time'] ? date('Y-m-d\TH:i:s\Z', strtotime($_POST['schedule_time'])) : null;

                    $result = $hootsuite->scheduleMessage($first_profile_id, $message_text, $schedule_time);

                    if ($result['status_code'] === 201) {
                        echo '<div class="success">✅ Message scheduled successfully!</div>';
                        echo '<pre>' . htmlentities(json_encode($result['body'], JSON_PRETTY_PRINT)) . '</pre>';
                    } else {
                        echo '<div class="error">❌ Failed to schedule message</div>';
                        echo '<pre>' . htmlentities($result['raw_response']) . '</pre>';
                    }
                }

                echo '<form method="POST" class="test-form">';
                echo '<h4>Test Message Posting</h4>';
                echo '<textarea name="message_text" placeholder="Enter your message here..." rows="3">' . htmlentities($_POST['message_text'] ?? 'Test message from Hootsuite API integration! 🚀') . '</textarea>';
                echo '<input type="datetime-local" name="schedule_time" value="' . date('Y-m-d\TH:i', strtotime('+1 hour')) . '">';
                echo '<p><small>Leave schedule time for immediate posting, or set a future time</small></p>';
                echo '<button type="submit" name="test_message" value="1">Schedule Message</button>';
                echo '</form>';
            }

        } else {
            echo '<div class="error">❌ Failed to get social profiles</div>';
            echo '<pre>' . htmlentities($profiles['raw_response']) . '</pre>';
        }
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="section error">';
        echo '<h2>❌ Error</h2>';
        echo '<p>' . htmlentities($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

<?php endif; ?>

<div class="section info">
    <h2>📖 API Documentation</h2>
    <p>For more information about Hootsuite's API, visit:</p>
    <ul>
        <li><a href="https://developer.hootsuite.com/docs" target="_blank">Official API Documentation</a></li>
        <li><a href="https://platform.hootsuite.com/docs" target="_blank">Platform API Reference</a></li>
        <li><a href="https://developer.hootsuite.com/docs/authentication" target="_blank">Authentication Guide</a></li>
    </ul>
</div>

</body>
</html>