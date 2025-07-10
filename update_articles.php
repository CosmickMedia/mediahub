<?php
/**
 * Database update script to add article submission functionality
 * Run this once to update your existing database
 */
require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/lib/db.php';

$pdo = get_pdo();

echo "Starting database update for article functionality...\n\n";

// Create articles table
$createArticlesTable = "CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    status ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'submitted',
    admin_notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    ip VARCHAR(45) NOT NULL,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store_id (store_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createArticlesTable);
    echo "✓ Articles table created/verified\n";
} catch (PDOException $e) {
    echo "✗ Error creating articles table: " . $e->getMessage() . "\n";
}

// Add article notification settings
$articleSettings = [
    'admin_article_notification_subject' => 'New article submission from {store_name}',
    'store_article_notification_subject' => 'Article Submission Confirmation - Cosmick Media',
    'article_approval_subject' => 'Article Status Update - Cosmick Media',
    'max_article_length' => '50000' // Characters
];

foreach ($articleSettings as $name => $value) {
    try {
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

// Update store_messages to support article replies
try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN article_id INT DEFAULT NULL AFTER upload_id");
    $pdo->exec("ALTER TABLE store_messages ADD CONSTRAINT fk_article_id FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE");
    echo "✓ Added article_id column to store_messages table\n";
} catch (PDOException $e) {
    // Column might already exist
    echo "• Article_id column might already exist\n";
}

echo "\n✓ Database update complete!\n";
echo "Article submission functionality is now available.\n";
?>