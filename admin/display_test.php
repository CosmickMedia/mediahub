<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();

$pdo = get_pdo();

echo "<h2>Display Query Test</h2>";
echo "<pre>";

// 1. Test dashboard queries
echo "1. Dashboard Statistics:\n";

// Total uploads
$stmt = $pdo->query('SELECT COUNT(*) FROM uploads');
echo "Total uploads: " . $stmt->fetchColumn() . "\n";

// Uploads today
$stmt = $pdo->query('SELECT COUNT(*) FROM uploads WHERE DATE(created_at) = CURDATE()');
echo "Uploads today: " . $stmt->fetchColumn() . "\n";

// Uploads this week
$stmt = $pdo->query('SELECT COUNT(*) FROM uploads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
echo "Uploads this week: " . $stmt->fetchColumn() . "\n";

// Check current date
echo "\nCurrent database date/time: ";
$stmt = $pdo->query('SELECT NOW()');
echo $stmt->fetchColumn() . "\n";

// 2. Test the recent uploads query from dashboard
echo "\n2. Recent Uploads Query (from dashboard):\n";
$stmt = $pdo->query('
    SELECT u.*, s.name as store_name, u.mime, u.drive_id
    FROM uploads u 
    JOIN stores s ON u.store_id = s.id 
    ORDER BY u.created_at DESC 
    LIMIT 5
');
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent as $r) {
    echo sprintf("ID: %d | Store: %s | File: %s | Date: %s\n",
        $r['id'],
        $r['store_name'],
        substr($r['filename'], 0, 30),
        $r['created_at']
    );
}

// 3. Test uploads page query
echo "\n3. Uploads Page Query:\n";
$sql = 'SELECT u.*, s.name as store_name, s.pin FROM uploads u JOIN stores s ON u.store_id=s.id ORDER BY u.created_at DESC LIMIT 10';
$stmt = $pdo->query($sql);
$uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($uploads) . " uploads\n";

// 4. Check for specific store (Petland Cosmick)
echo "\n4. Petland Cosmick Uploads:\n";
$stmt = $pdo->prepare('SELECT * FROM uploads WHERE store_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([3]);
$petland = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($petland as $p) {
    echo sprintf("ID: %d | File: %s | Date: %s\n",
        $p['id'],
        substr($p['filename'], 0, 40),
        $p['created_at']
    );
}

// 5. Check timezone
echo "\n5. Timezone Check:\n";
echo "PHP Timezone: " . date_default_timezone_get() . "\n";
echo "PHP Current Time: " . date('Y-m-d H:i:s') . "\n";
$stmt = $pdo->query("SELECT @@global.time_zone, @@session.time_zone");
$tz = $stmt->fetch();
echo "MySQL Global TZ: " . $tz[0] . "\n";
echo "MySQL Session TZ: " . $tz[1] . "\n";

echo "</pre>";

echo "<hr>";
echo "<p>Navigate to: ";
echo "<a href='index.php'>Dashboard</a> | ";
echo "<a href='uploads.php'>Uploads Page</a> | ";
echo "<a href='upload_test.php'>Upload Test</a>";
echo "</p>";
?>