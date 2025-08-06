<?php
session_start();

echo "<h2>Session Test Page</h2>";
echo "Session ID: " . session_id() . "<br><br>";

// Test setting a session variable
if (!isset($_SESSION['test_time'])) {
    $_SESSION['test_time'] = time();
    echo "Set test_time in session: " . $_SESSION['test_time'] . "<br>";
} else {
    echo "Found existing test_time in session: " . $_SESSION['test_time'] . "<br>";
    echo "That was " . (time() - $_SESSION['test_time']) . " seconds ago.<br>";
}

echo "<br><h3>Current Session Contents:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<br><h3>Session Configuration:</h3>";
echo "session.save_path: " . ini_get('session.save_path') . "<br>";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "<br>";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "<br>";
echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . " seconds<br>";

echo "<br><h3>Quick Links:</h3>";
echo "<a href='test_auth.php'>Start OAuth Flow</a> | ";
echo "<a href='get_scheduled_posts.php'>Get Scheduled Posts</a> | ";
echo "<a href='test_session.php'>Refresh This Page</a>";

if (isset($_SESSION['access_token'])) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-top: 10px;'>";
    echo "✅ Access token found in session!<br>";
    echo "First 20 chars: " . substr($_SESSION['access_token'], 0, 20) . "...";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-top: 10px;'>";
    echo "⚠️ No access token in session. You need to authenticate first.";
    echo "</div>";
}
?><?php
