<?php
/**
 * Setup script to initialize database tables and create default admin user.
 */
require_once __DIR__.'/lib/config.php';
$config = get_config();

try {
    $pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4", $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("DB Connection failed: " . $e->getMessage());
}

$queries = [
    // Stores table
    "CREATE TABLE IF NOT EXISTS stores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        pin VARCHAR(50) NOT NULL UNIQUE,
        admin_email VARCHAR(255),
        drive_folder VARCHAR(255),
        hootsuite_token VARCHAR(255),
        hootsuite_campaign_tag VARCHAR(100),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        phone VARCHAR(50),
        address VARCHAR(255),
        marketing_report_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Store users table
    "CREATE TABLE IF NOT EXISTS store_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY store_email_unique (store_id, email),
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Uploads table with custom_message field
    "CREATE TABLE IF NOT EXISTS uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        description TEXT,
        custom_message TEXT,
        created_at DATETIME NOT NULL,
        ip VARCHAR(45) NOT NULL,
        mime VARCHAR(100) NOT NULL,
        size INT NOT NULL,
        drive_id VARCHAR(255),
        FOREIGN KEY (store_id) REFERENCES stores(id),
        INDEX idx_created_at (created_at),
        INDEX idx_store_id (store_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Settings table
    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Store messages table
    "CREATE TABLE IF NOT EXISTS store_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT DEFAULT NULL,
        sender ENUM('admin','store') DEFAULT 'admin',
        message TEXT NOT NULL,
        is_reply TINYINT(1) DEFAULT 0,
        upload_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        like_by_store TINYINT(1) DEFAULT 0,
        like_by_admin TINYINT(1) DEFAULT 0,
        love_by_store TINYINT(1) DEFAULT 0,
        love_by_admin TINYINT(1) DEFAULT 0,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE,
        INDEX idx_store_id (store_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Upload statuses table
    "CREATE TABLE IF NOT EXISTS upload_statuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) NOT NULL,
        UNIQUE KEY name_unique (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Upload status history table
    "CREATE TABLE IF NOT EXISTS upload_status_history (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Logs table
    "CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT,
        action VARCHAR(50),
        message TEXT,
        created_at DATETIME NOT NULL,
        ip VARCHAR(45) NOT NULL,
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Calendar table
    "CREATE TABLE IF NOT EXISTS calendar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ext_id VARCHAR(50) NOT NULL UNIQUE,
        store_id INT NOT NULL,
        text TEXT,
        scheduled_time DATETIME,
        raw_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        INDEX idx_store_time (store_id, scheduled_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// Execute table creation queries
foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Table created/verified\n";
    } catch (PDOException $e) {
        echo "✗ Error creating table: " . $e->getMessage() . "\n";
    }
}

// Add custom_message column if it doesn't exist (for existing installations)
try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN custom_message TEXT AFTER description");
    echo "✓ Added custom_message column to uploads table\n";
} catch (PDOException $e) {
    // Column might already exist, that's okay
}

try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN status_id INT DEFAULT NULL AFTER drive_id");
    $pdo->exec("ALTER TABLE uploads ADD CONSTRAINT fk_status_id FOREIGN KEY (status_id) REFERENCES upload_statuses(id)");
    echo "✓ Added status_id column to uploads table\n";
} catch (PDOException $e) {
    // Column might already exist
}

// Add is_reply and upload_id columns to store_messages if they don't exist
try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN is_reply TINYINT(1) DEFAULT 0 AFTER message");
    echo "✓ Added is_reply column to store_messages table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN sender ENUM('admin','store') DEFAULT 'admin' AFTER store_id");
    echo "✓ Added sender column to store_messages table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN upload_id INT DEFAULT NULL AFTER is_reply");
    $pdo->exec("ALTER TABLE store_messages ADD CONSTRAINT fk_upload_id FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE");
    echo "✓ Added upload_id column to store_messages table\n";
} catch (PDOException $e) {
    // Column might already exist
}

// Add hootsuite_token column to stores table if not exists
try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN hootsuite_token VARCHAR(255) AFTER drive_folder");
    echo "✓ Added hootsuite_token column to stores table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN hootsuite_campaign_tag VARCHAR(100) AFTER hootsuite_token");
    echo "✓ Added hootsuite_campaign_tag column to stores table\n";
} catch (PDOException $e) {
    // Column might already exist
}

// Additional store contact columns
try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN first_name VARCHAR(100) AFTER hootsuite_token");
    echo "✓ Added first_name column to stores table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN last_name VARCHAR(100) AFTER first_name");
    echo "✓ Added last_name column to stores table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN phone VARCHAR(50) AFTER last_name");
    echo "✓ Added phone column to stores table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN address VARCHAR(255) AFTER phone");
    echo "✓ Added address column to stores table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN marketing_report_url VARCHAR(255) AFTER address");
    echo "✓ Added marketing_report_url column to stores table\n";
} catch (PDOException $e) {
    // Column might already exist
}

// Ensure store_users table has name columns
try {
    $pdo->exec("ALTER TABLE store_users ADD COLUMN first_name VARCHAR(100) AFTER email");
    echo "✓ Added first_name column to store_users table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE store_users ADD COLUMN last_name VARCHAR(100) AFTER first_name");
    echo "✓ Added last_name column to store_users table\n";
} catch (PDOException $e) {
    // Column might already exist
}

// Ensure users table has new contact columns
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(100) AFTER password");
    echo "✓ Added first_name column to users table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(100) AFTER first_name");
    echo "✓ Added last_name column to users table\n";
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER last_name");
    echo "✓ Added email column to users table\n";
} catch (PDOException $e) {
    // Column might already exist
}

// Insert default upload statuses
$defaultStatuses = [
    ['Reviewed', '#198754'],
    ['Pending Submission', '#ffc107'],
    ['Scheduled', '#0dcaf0']
];
foreach ($defaultStatuses as $st) {
    try {
        $stmt = $pdo->prepare("INSERT INTO upload_statuses (name, color) VALUES (?, ?) ON DUPLICATE KEY UPDATE color=VALUES(color)");
        $stmt->execute($st);
    } catch (PDOException $e) {
        // Ignore if exists
    }
}

// Create default admin user
$username = 'admin';
$password = password_hash($config['admin_password'], PASSWORD_DEFAULT);
$email = $config['notification_email'] ?? 'admin@example.com';
try {
    $pdo->prepare("INSERT IGNORE INTO users (username, password, first_name, last_name, email) VALUES (?, ?, ?, ?, ?)")
        ->execute([$username, $password, 'Admin', '', $email]);
    echo "✓ Default admin user created/verified\n";
} catch (PDOException $e) {
    echo "✗ Error creating admin user: " . $e->getMessage() . "\n";
}

// Set default settings if not exist
$defaultSettings = [
    'drive_base_folder' => $config['drive_base_folder'] ?? '',
    'notification_email' => $config['notification_email'] ?? '',
    'groundhogg_public_key' => '',
    'groundhogg_token' => '',
    'groundhogg_secret_key' => '',
    // Default tags applied to contacts created in Groundhogg
    'groundhogg_contact_tags' => 'media-hub, store-onboarding',
    'company_address' => '',
    'company_city' => '',
    'company_state' => '',
    'company_zip' => '',
    'company_country' => '',
    'calendar_sheet_url' => '',
    'calendar_sheet_id' => '',
    'calendar_sheet_range' => 'Sheet1!A:A',
    'calendar_update_interval' => '24',
    'calendar_last_update' => ''
];

foreach ($defaultSettings as $name => $value) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (name, value) VALUES (?, ?)");
        $stmt->execute([$name, $value]);
    } catch (PDOException $e) {
        // Settings might already exist
    }
}

echo "\n✓ Setup complete!\n";
echo "You can now access:\n";
echo "- Public upload: /public/index.php\n";
echo "- Admin panel: /admin/login.php (username: admin)\n";