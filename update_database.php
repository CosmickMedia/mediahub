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

// Create store_users table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS store_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY store_email_unique (store_id, email),
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created store_users table\n";
} catch (PDOException $e) {
    echo "✗ Error creating store_users table: " . $e->getMessage() . "\n";
}

// Ensure store_users table has name columns
try {
    $pdo->exec("ALTER TABLE store_users ADD COLUMN first_name VARCHAR(100) AFTER email");
    echo "✓ Added first_name column to store_users table\n";
} catch (PDOException $e) {
    echo "• first_name column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_users ADD COLUMN last_name VARCHAR(100) AFTER first_name");
    echo "✓ Added last_name column to store_users table\n";
} catch (PDOException $e) {
    echo "• last_name column might already exist\n";
}

// Add sender column to store_messages table
try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN sender ENUM('admin','store') DEFAULT 'admin' AFTER store_id");
    echo "✓ Added sender column to store_messages table\n";
} catch (PDOException $e) {
    echo "• sender column might already exist\n";
}

// Add hootsuite_token column to stores table
try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN hootsuite_token VARCHAR(255) AFTER drive_folder");
    echo "✓ Added hootsuite_token column to stores table\n";
} catch (PDOException $e) {
    echo "• hootsuite_token column might already exist\n";
}

// New contact columns for stores
try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN first_name VARCHAR(100) AFTER hootsuite_token");
    echo "✓ Added first_name column to stores table\n";
} catch (PDOException $e) {
    echo "• first_name column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN last_name VARCHAR(100) AFTER first_name");
    echo "✓ Added last_name column to stores table\n";
} catch (PDOException $e) {
    echo "• last_name column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN phone VARCHAR(50) AFTER last_name");
    echo "✓ Added phone column to stores table\n";
} catch (PDOException $e) {
    echo "• phone column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN address VARCHAR(255) AFTER phone");
    echo "✓ Added address column to stores table\n";
} catch (PDOException $e) {
    echo "• address column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN marketing_report_url VARCHAR(255) AFTER address");
    echo "✓ Added marketing_report_url column to stores table\n";
} catch (PDOException $e) {
    echo "• marketing_report_url column might already exist\n";
}

// Ensure admin users table has required columns
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "✓ Added created_at column to users table\n";
} catch (PDOException $e) {
    echo "• created_at column might already exist\n";
}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(100) AFTER password");
    echo "✓ Added first_name column to users table\n";
} catch (PDOException $e) {
    echo "• first_name column might already exist\n";
}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(100) AFTER first_name");
    echo "✓ Added last_name column to users table\n";
} catch (PDOException $e) {
    echo "• last_name column might already exist\n";
}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER last_name");
    echo "✓ Added email column to users table\n";
} catch (PDOException $e) {
    echo "• email column might already exist\n";
}

// Create upload_statuses table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS upload_statuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created upload_statuses table\n";
} catch (PDOException $e) {
    echo "✗ Error creating upload_statuses table: " . $e->getMessage() . "\n";
}

// Add status_id column to uploads
try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN status_id INT DEFAULT NULL AFTER drive_id");
    $pdo->exec("ALTER TABLE uploads ADD CONSTRAINT fk_status_id FOREIGN KEY (status_id) REFERENCES upload_statuses(id)");
    echo "✓ Added status_id column to uploads table\n";
} catch (PDOException $e) {
    echo "• status_id column might already exist\n";
}

// Insert default statuses
$defaultStatuses = [
    ['Reviewed', '#198754'],
    ['Pending Submission', '#ffc107'],
    ['Scheduled', '#0dcaf0']
];
foreach ($defaultStatuses as $st) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO upload_statuses (name, color) VALUES (?, ?)");
        $stmt->execute($st);
    } catch (PDOException $e) {
        // ignore
    }
}

// Create upload_status_history table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS upload_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        upload_id INT NOT NULL,
        user_id INT NOT NULL,
        old_status_id INT DEFAULT NULL,
        new_status_id INT DEFAULT NULL,
        changed_at DATETIME NOT NULL,
        FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_upload_id (upload_id),
        INDEX idx_changed_at (changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created upload_status_history table\n";
} catch (PDOException $e) {
    echo "✗ Error creating upload_status_history table: " . $e->getMessage() . "\n";
}

echo "\n✓ Database update complete!\n";
?>