<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();

$pdo = get_pdo();

echo "<h2>Upload Database Test</h2>";
echo "<pre>";

// 1. Check if uploads table exists and show structure
echo "1. Uploads Table Structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE uploads");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo sprintf("%-20s %-20s %-10s %-10s\n", $col['Field'], $col['Type'], $col['Null'], $col['Default']);
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 2. Check recent uploads
echo "\n2. Last 10 Uploads:\n";
try {
    $stmt = $pdo->query("SELECT id, store_id, filename, created_at, drive_id FROM uploads ORDER BY created_at DESC LIMIT 10");
    $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($uploads)) {
        echo "No uploads found in database.\n";
    } else {
        foreach ($uploads as $upload) {
            echo sprintf("ID: %d | Store: %d | File: %s | Date: %s | Drive: %s\n",
                $upload['id'],
                $upload['store_id'],
                substr($upload['filename'], 0, 30),
                $upload['created_at'],
                substr($upload['drive_id'], 0, 20) . '...'
            );
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 3. Check stores
echo "\n3. Active Stores:\n";
try {
    $stmt = $pdo->query("SELECT id, name, pin, drive_folder FROM stores ORDER BY name");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stores as $store) {
        echo sprintf("ID: %d | Name: %s | PIN: %s | Folder: %s\n",
            $store['id'],
            $store['name'],
            $store['pin'],
            $store['drive_folder'] ?: 'Not set'
        );
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 4. Check settings
echo "\n4. Drive Settings:\n";
try {
    $stmt = $pdo->query("SELECT name, value FROM settings WHERE name IN ('drive_base_folder', 'notification_email')");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($settings as $setting) {
        echo sprintf("%s: %s\n", $setting['name'], $setting['value']);
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 5. Check if service account file exists
echo "\n5. Service Account File:\n";
$config = get_config();
$sa_file = $config['service_account_json'];
echo "Path: " . $sa_file . "\n";
echo "Exists: " . (file_exists($sa_file) ? 'Yes' : 'No') . "\n";
if (file_exists($sa_file)) {
    $sa_data = json_decode(file_get_contents($sa_file), true);
    echo "Client Email: " . ($sa_data['client_email'] ?? 'Not found') . "\n";
}

// 6. Test database connection
echo "\n6. Database Connection Test:\n";
try {
    $testInsert = $pdo->prepare("INSERT INTO uploads (store_id, filename, description, created_at, ip, mime, size, drive_id) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)");
    $testId = 'test_' . uniqid();
    $testInsert->execute([1, 'test.jpg', 'Test upload', '127.0.0.1', 'image/jpeg', 1024, $testId]);
    $lastId = $pdo->lastInsertId();
    echo "Test insert successful, ID: " . $lastId . "\n";

    // Delete test record
    $pdo->exec("DELETE FROM uploads WHERE id = " . $lastId);
    echo "Test record deleted.\n";
} catch (PDOException $e) {
    echo "Error inserting test record: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<hr>";
echo "<a href='index.php'>Back to Dashboard</a>";
?>