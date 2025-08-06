<?php
/**
 * Database update script to add new email settings, article functionality, and other features
 * Run this once to update your existing database
 */
require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/lib/db.php';

$pdo = get_pdo();

echo "Starting comprehensive database update...\n\n";

// ========== EMAIL & MESSAGING FUNCTIONALITY ==========

// Migrate old Dripley settings to Groundhogg naming
$mapping = [
    'dripley_site_url' => 'groundhogg_site_url',
    'dripley_username' => 'groundhogg_username'
];
foreach ($mapping as $old => $new) {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = ?");
        $stmt->execute([$old]);
        $value = $stmt->fetchColumn();
        if ($value !== false) {
            $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)")->execute([$new, $value]);
            $pdo->prepare("DELETE FROM settings WHERE name = ?")->execute([$old]);
            echo "✓ Migrated setting $old to $new\n";
        }
    } catch (PDOException $e) {
        echo "✗ Error migrating $old: " . $e->getMessage() . "\n";
    }
}

// Ensure new Groundhogg API settings exist
$newGhSettings = ['groundhogg_public_key', 'groundhogg_token', 'groundhogg_secret_key', 'groundhogg_contact_tags'];
foreach ($newGhSettings as $setting) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
        $stmt->execute([$setting]);
        if (!$stmt->fetchColumn()) {
            $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, '')")->execute([$setting]);
            echo "✓ Added setting $setting\n";
        }
    } catch (PDOException $e) {
        echo "✗ Error adding $setting: " . $e->getMessage() . "\n";
    }
}

// Default email settings to add
$defaultSettings = [
    'email_from_name' => 'Cosmick Media',
    'email_from_address' => 'noreply@cosmickmedia.com',
    'admin_notification_subject' => 'New uploads from {store_name}',
    'store_notification_subject' => 'Content Submission Confirmation - Cosmick Media',
    'store_message_subject' => 'New message from Cosmick Media',
    // Default tags for Groundhogg contacts
    'groundhogg_contact_tags' => 'media-hub, store-onboarding',
    'company_address' => '',
    'company_city' => '',
    'company_state' => '',
    'company_zip' => '',
    'company_country' => '',
    'calendar_sheet_url' => '',
    'calendar_sheet_range' => 'Sheet1!A:A',
    'calendar_update_interval' => '24',
    'calendar_last_update' => '',
    'calendar_enabled' => '0',
    'hootsuite_enabled' => '0',
    'hootsuite_update_interval' => '24',
    'hootsuite_client_id' => '',
    'hootsuite_client_secret' => '',
    'hootsuite_redirect_uri' => '',
    'hootsuite_debug' => '0',
    'drive_debug' => '0',
    // Article notification settings
    'admin_article_notification_subject' => 'New article submission from {store_name}',
    'store_article_notification_subject' => 'Article Submission Confirmation - Cosmick Media',
    'article_approval_subject' => 'Article Status Update - Cosmick Media',
    'max_article_length' => '50000' // Characters
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

// ========== ARTICLE FUNCTIONALITY ==========

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

// Add images column to articles table
try {
    $pdo->exec("ALTER TABLE articles ADD COLUMN images TEXT AFTER excerpt");
    echo "✓ Added images column to articles table\n";
} catch (PDOException $e) {
    echo "• Images column might already exist\n";
}

// ========== USER MANAGEMENT ==========

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

try {
    $pdo->exec("ALTER TABLE store_users ADD COLUMN mobile_phone VARCHAR(50) AFTER last_name");
    echo "✓ Added mobile_phone column to store_users table\n";
} catch (PDOException $e) {
    echo "• mobile_phone column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_users ADD COLUMN opt_in_status ENUM('unconfirmed','confirmed','unsubscribed','subscribed_weekly','subscribed_monthly','bounced','spam','complained','blocked') DEFAULT 'confirmed' AFTER mobile_phone");
    echo "✓ Added opt_in_status column to store_users table\n";
} catch (PDOException $e) {
    echo "• opt_in_status column might already exist\n";
}

// ========== MESSAGING SYSTEM UPDATES ==========

// Update store_messages to support article replies
try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN article_id INT DEFAULT NULL AFTER upload_id");
    $pdo->exec("ALTER TABLE store_messages ADD CONSTRAINT fk_article_id FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE");
    echo "✓ Added article_id column to store_messages table\n";
} catch (PDOException $e) {
    echo "• Article_id column might already exist\n";
}

// Add store_user_id column to store_messages table
try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN store_user_id INT DEFAULT NULL AFTER store_id");
    echo "✓ Added store_user_id column to store_messages table\n";
} catch (PDOException $e) {
    echo "• store_user_id column might already exist\n";
}

// Add sender column to store_messages table
try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN sender ENUM('admin','store') DEFAULT 'admin' AFTER store_id");
    echo "✓ Added sender column to store_messages table\n";
} catch (PDOException $e) {
    echo "• sender column might already exist\n";
}

// Additional messaging columns
try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN parent_id INT DEFAULT NULL AFTER sender");
    echo "✓ Added parent_id column to store_messages table\n";
} catch (PDOException $e) {
    echo "• parent_id column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN read_by_admin TINYINT(1) DEFAULT 0 AFTER created_at");
    echo "✓ Added read_by_admin column to store_messages table\n";
} catch (PDOException $e) {
    echo "• read_by_admin column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN read_by_store TINYINT(1) DEFAULT 0 AFTER read_by_admin");
    echo "✓ Added read_by_store column to store_messages table\n";
} catch (PDOException $e) {
    echo "• read_by_store column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN like_by_store TINYINT(1) DEFAULT 0 AFTER read_by_store");
    echo "✓ Added like_by_store column to store_messages table\n";
} catch (PDOException $e) {
    echo "• like_by_store column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN like_by_admin TINYINT(1) DEFAULT 0 AFTER like_by_store");
    echo "✓ Added like_by_admin column to store_messages table\n";
} catch (PDOException $e) {
    echo "• like_by_admin column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN love_by_store TINYINT(1) DEFAULT 0 AFTER like_by_admin");
    echo "✓ Added love_by_store column to store_messages table\n";
} catch (PDOException $e) {
    echo "• love_by_store column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE store_messages ADD COLUMN love_by_admin TINYINT(1) DEFAULT 0 AFTER love_by_store");
    echo "✓ Added love_by_admin column to store_messages table\n";
} catch (PDOException $e) {
    echo "• love_by_admin column might already exist\n";
}

// ========== STORE ENHANCEMENTS ==========

// Add hootsuite_token column to stores table
try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN hootsuite_token VARCHAR(255) AFTER drive_folder");
    echo "✓ Added hootsuite_token column to stores table\n";
} catch (PDOException $e) {
    echo "• hootsuite_token column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN hootsuite_campaign_tag VARCHAR(100) AFTER hootsuite_token");
    echo "✓ Added hootsuite_campaign_tag column to stores table\n";
} catch (PDOException $e) {
    echo "• hootsuite_campaign_tag column might already exist\n";
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
    $pdo->exec("ALTER TABLE stores ADD COLUMN city VARCHAR(100) AFTER address");
    echo "✓ Added city column to stores table\n";
} catch (PDOException $e) {
    echo "• city column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN state VARCHAR(100) AFTER city");
    echo "✓ Added state column to stores table\n";
} catch (PDOException $e) {
    echo "• state column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN zip_code VARCHAR(20) AFTER state");
    echo "✓ Added zip_code column to stores table\n";
} catch (PDOException $e) {
    echo "• zip_code column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN country VARCHAR(100) AFTER zip_code");
    echo "✓ Added country column to stores table\n";
} catch (PDOException $e) {
    echo "• country column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE stores ADD COLUMN marketing_report_url VARCHAR(255) AFTER address");
    echo "✓ Added marketing_report_url column to stores table\n";
} catch (PDOException $e) {
    echo "• marketing_report_url column might already exist\n";
}

// ========== ADMIN USER UPDATES ==========

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

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN mobile_phone VARCHAR(50) AFTER email");
    echo "✓ Added mobile_phone column to users table\n";
} catch (PDOException $e) {
    echo "• mobile_phone column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN opt_in_status ENUM('unconfirmed','confirmed','unsubscribed','subscribed_weekly','subscribed_monthly','bounced','spam','complained','blocked') DEFAULT 'confirmed' AFTER mobile_phone");
    echo "✓ Added opt_in_status column to users table\n";
} catch (PDOException $e) {
    echo "• opt_in_status column might already exist\n";
}

// ========== UPLOAD STATUS TRACKING ==========

// Create upload_statuses table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS upload_statuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) NOT NULL,
        UNIQUE KEY name_unique (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created upload_statuses table\n";
} catch (PDOException $e) {
    echo "✗ Error creating upload_statuses table: " . $e->getMessage() . "\n";
}

// Create social_networks table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS social_networks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        icon VARCHAR(100) NOT NULL,
        color VARCHAR(20) NOT NULL,
        UNIQUE KEY name_unique (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created social_networks table\n";
} catch (PDOException $e) {
    echo "✗ Error creating social_networks table: " . $e->getMessage() . "\n";
}

// Add status_id column to uploads
try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN status_id INT DEFAULT NULL AFTER drive_id");
    $pdo->exec("ALTER TABLE uploads ADD CONSTRAINT fk_status_id FOREIGN KEY (status_id) REFERENCES upload_statuses(id)");
    echo "✓ Added status_id column to uploads table\n";
} catch (PDOException $e) {
    echo "• status_id column might already exist\n";
}

// Add local_path and thumb_path columns to uploads
try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN local_path TEXT AFTER status_id");
    echo "✓ Added local_path column to uploads table\n";
} catch (PDOException $e) {
    echo "• local_path column might already exist\n";
}

try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN thumb_path TEXT AFTER local_path");
    echo "✓ Added thumb_path column to uploads table\n";
} catch (PDOException $e) {
    echo "• thumb_path column might already exist\n";
}

// Insert default statuses if not already present
$defaultStatuses = [
    ['Reviewed', '#198754'],
    ['Pending Submission', '#ffc107'],
    ['Scheduled', '#0dcaf0']
];
foreach ($defaultStatuses as $st) {
    list($name, $color) = $st;
    try {
        $check = $pdo->prepare('SELECT COUNT(*) FROM upload_statuses WHERE name = ?');
        $check->execute([$name]);
        if (!$check->fetchColumn()) {
            $stmt = $pdo->prepare('INSERT INTO upload_statuses (name, color) VALUES (?, ?)');
            $stmt->execute([$name, $color]);
            echo "✓ Added upload status $name\n";
        } else {
            echo "• Upload status already exists: $name\n";
        }
    } catch (PDOException $e) {
        echo "✗ Error adding status $name: " . $e->getMessage() . "\n";
    }
}

// Insert default social networks if not already present
$defaultNetworks = [
    ['Facebook', 'bi-facebook', '#1877F2'],
    ['Instagram', 'bi-instagram', '#C13584'],
    ['X', 'bi-twitter', '#000000'],
    ['YouTube', 'bi-youtube', '#FF0000'],
    ['Pinterest', 'bi-pinterest', '#E60023'],
    ['TikTok', 'bi-tiktok', '#69C9D0']
];
foreach ($defaultNetworks as $net) {
    list($name, $icon, $color) = $net;
    try {
        $check = $pdo->prepare('SELECT COUNT(*) FROM social_networks WHERE name = ?');
        $check->execute([$name]);
        if (!$check->fetchColumn()) {
            $stmt = $pdo->prepare('INSERT INTO social_networks (name, icon, color) VALUES (?, ?, ?)');
            $stmt->execute([$name, $icon, $color]);
            echo "✓ Added social network $name\n";
        } else {
            echo "• Social network already exists: $name\n";
        }
    } catch (PDOException $e) {
        echo "✗ Error adding network $name: " . $e->getMessage() . "\n";
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

// ========== CALENDAR FUNCTIONALITY ==========

// Create Hootsuite posts table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hootsuite_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id VARCHAR(50) NOT NULL UNIQUE,
        store_id INT NOT NULL,
        text TEXT,
        scheduled_send_time DATETIME,
        raw_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        state VARCHAR(50),
        social_profile_id VARCHAR(50),
        media_urls TEXT,
        media_thumb_urls TEXT,
        media TEXT,
        webhook_urls TEXT,
        tags TEXT,
        targeting TEXT,
        privacy TEXT,
        location TEXT,
        email_notification TEXT,
        post_url TEXT,
        post_id_external VARCHAR(50),
        reviewers TEXT,
        created_by_member_id VARCHAR(50),
        last_updated_by_member_id VARCHAR(50),
        extended_info TEXT,
        sequence_number INT,
        imt_length INT,
        imt_index INT,
        INDEX idx_store_time (store_id, scheduled_send_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created hootsuite_posts table\n";
} catch (PDOException $e) {
    echo "✗ Error creating hootsuite_posts table: " . $e->getMessage() . "\n";
}

$hootColumns = [
    'state VARCHAR(50)',
    'social_profile_id VARCHAR(50)',
    'media_urls TEXT',
    'media_thumb_urls TEXT',
    'media TEXT',
    'webhook_urls TEXT',
    'tags TEXT',
    'targeting TEXT',
    'privacy TEXT',
    'location TEXT',
    'email_notification TEXT',
    'post_url TEXT',
    'post_id_external VARCHAR(50)',
    'reviewers TEXT',
    'created_by_member_id VARCHAR(50)',
    'last_updated_by_member_id VARCHAR(50)',
    'extended_info TEXT',
    'sequence_number INT',
    'imt_length INT',
    'imt_index INT'
];
foreach ($hootColumns as $col) {
    try { $pdo->exec("ALTER TABLE hootsuite_posts ADD COLUMN $col"); } catch (PDOException $e) { }
}

try {
    $pdo->exec("ALTER TABLE hootsuite_posts ADD UNIQUE KEY uniq_post_id (post_id)");
    echo "✓ Added uniq_post_id index\n";
} catch (PDOException $e) {
    echo "ℹ︎ Could not add uniq_post_id index: " . $e->getMessage() . "\n";
}

// Create calendar table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id VARCHAR(50) NOT NULL UNIQUE,
        store_id INT NOT NULL,
        state VARCHAR(50),
        text TEXT,
        scheduled_send_time DATETIME,
        social_profile_id VARCHAR(50),
        media_urls TEXT,
        media_thumb_urls TEXT,
        media TEXT,
        webhook_urls TEXT,
        tags TEXT,
        targeting TEXT,
        privacy TEXT,
        location TEXT,
        email_notification TEXT,
        post_url TEXT,
        post_id_external VARCHAR(50),
        reviewers TEXT,
        created_by_member_id VARCHAR(50),
        last_updated_by_member_id VARCHAR(50),
        extended_info TEXT,
        sequence_number INT,
        imt_length INT,
        imt_index INT,
        raw_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        INDEX idx_store_time (store_id, scheduled_send_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created calendar table\n";
} catch (PDOException $e) {
    echo "✗ Error creating calendar table: " . $e->getMessage() . "\n";
}

// Upgrade calendar table from older schema
try {
    $pdo->exec("ALTER TABLE calendar CHANGE ext_id post_id VARCHAR(50) NOT NULL");
    echo "✓ Renamed ext_id to post_id in calendar table\n";
} catch (PDOException $e) {
    echo "• ext_id column might not exist or already renamed\n";
}

try {
    $pdo->exec("ALTER TABLE calendar CHANGE scheduled_time scheduled_send_time DATETIME");
    echo "✓ Renamed scheduled_time to scheduled_send_time in calendar table\n";
} catch (PDOException $e) {
    echo "• scheduled_time column might not exist or already renamed\n";
}

$calendarColumns = [
    'state VARCHAR(50)',
    'social_profile_id VARCHAR(50)',
    'media_urls TEXT',
    'media_thumb_urls TEXT',
    'media TEXT',
    'webhook_urls TEXT',
    'tags TEXT',
    'targeting TEXT',
    'privacy TEXT',
    'location TEXT',
    'email_notification TEXT',
    'post_url TEXT',
    'post_id_external VARCHAR(50)',
    'reviewers TEXT',
    'created_by_member_id VARCHAR(50)',
    'last_updated_by_member_id VARCHAR(50)',
    'extended_info TEXT',
    'sequence_number INT',
    'imt_length INT',
    'imt_index INT'
];
foreach ($calendarColumns as $col) {
    list($name) = explode(' ', $col, 2);
    try {
        $pdo->exec("ALTER TABLE calendar ADD COLUMN $col");
        echo "✓ Added $name column to calendar table\n";
    } catch (PDOException $e) {
        echo "• $name column might already exist in calendar table\n";
    }
}

echo "\n✓ Comprehensive database update complete!\n";
echo "All features including email settings, article submission, and calendar functionality are now available.\n";
?>