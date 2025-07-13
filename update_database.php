<?php
/**
 * Database update script to add new email settings
 * Run this once to update your existing database
 */
require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/lib/db.php';

$pdo = get_pdo();

echo "Starting database update...\n\n";

// Default email settings to add
$defaultSettings = [
    'email_from_name' => 'Cosmick Media',
    'email_from_address' => 'noreply@cosmickmedia.com',
    'admin_notification_subject' => 'New uploads from {store_name}',
    'store_notification_subject' => 'Content Submission Confirmation - Cosmick Media',
    'store_message_subject' => 'New message from Cosmick Media'
];

foreach ($defaultSettings as $name => $value) {
    try {
        // Check if setting already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
        $stmt->execute([$name]);
        $exists = $stmt->fetchColumn() > 0;

        if (!$exists) {
            $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?)");
            $stmt->execute([$name, $value]);
            echo "✓ Added setting: $name\n";
        } else {
            echo "• Setting already exists: $name\n";
        }
    } catch (PDOException $e) {
        echo "✗ Error adding setting $name: " . $e->getMessage() . "\n";
    }
}

// Add hootsuite_token column to stores table
try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN hootsuite_token VARCHAR(255) AFTER drive_folder");
    echo "✓ Added hootsuite_token column to stores table\n";
} catch (PDOException $e) {
    echo "• hootsuite_token column might already exist\n";
}

echo "\n✓ Database update complete!\n";
?>