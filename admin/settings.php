<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_once __DIR__.'/../lib/settings.php';
$test_action = '';
require_login();
$pdo = get_pdo();

$success = false;
$test_result = null;
$active_tab = $_POST['active_tab'] ?? ($_GET['active_tab'] ?? 'general');

if (isset($_GET['hootsuite_token_saved'])) {
    $test_result = [true, 'Access token saved'];
    $test_action = 'hootsuite';
    $active_tab = 'calendar';
} elseif (isset($_GET['hootsuite_token_error'])) {
    $test_result = [false, $_GET['hootsuite_token_error']];
    $test_action = 'hootsuite';
    $active_tab = 'calendar';
}

// Fetch upload statuses
$statuses = $pdo->query('SELECT id, name, color FROM upload_statuses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
// Fetch social networks
$networks = $pdo->query('SELECT id, name, icon, color FROM social_networks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Service Account JSON upload
    if (isset($_FILES['sa_json']) && is_uploaded_file($_FILES['sa_json']['tmp_name'])) {
        $target = __DIR__.'/../service-account.json';
        move_uploaded_file($_FILES['sa_json']['tmp_name'], $target);
    }

    // Save all settings
    $settings = [
        'drive_base_folder' => $_POST['drive_folder'] ?? '',
        'drive_debug'       => isset($_POST['drive_debug']) ? '1' : '0',
        'notification_email' => $_POST['notify_email'] ?? '',
        'email_from_name' => $_POST['email_from_name'] ?? 'Cosmick Media',
        'email_from_address' => $_POST['email_from_address'] ?? 'noreply@cosmickmedia.com',
        'admin_notification_subject' => $_POST['admin_notification_subject'] ?? 'New uploads from {store_name}',
        'store_notification_subject' => $_POST['store_notification_subject'] ?? 'Content Submission Confirmation - Cosmick Media',
        'store_message_subject' => $_POST['store_message_subject'] ?? 'New message from Cosmick Media',
        'admin_article_notification_subject' => $_POST['admin_article_notification_subject'] ?? 'New article submission from {store_name}',
        'store_article_notification_subject' => $_POST['store_article_notification_subject'] ?? 'Article Submission Confirmation - Cosmick Media',
        'article_approval_subject' => $_POST['article_approval_subject'] ?? 'Article Status Update - Cosmick Media',
        'max_article_length' => $_POST['max_article_length'] ?? '50000',
        'groundhogg_site_url'     => trim($_POST['groundhogg_site_url'] ?? ''),
        'groundhogg_username'     => trim($_POST['groundhogg_username'] ?? ''),
        'groundhogg_public_key'   => trim($_POST['groundhogg_public_key'] ?? ''),
        'groundhogg_token'        => trim($_POST['groundhogg_token'] ?? ''),
        'groundhogg_secret_key'   => trim($_POST['groundhogg_secret_key'] ?? ''),
        'groundhogg_debug'        => isset($_POST['groundhogg_debug']) ? '1' : '0',
        'groundhogg_contact_tags' => trim($_POST['groundhogg_contact_tags'] ?? ''),
        'company_address'         => trim($_POST['company_address'] ?? ''),
        'company_city'            => trim($_POST['company_city'] ?? ''),
        'company_state'           => trim($_POST['company_state'] ?? ''),
        'company_zip'             => trim($_POST['company_zip'] ?? ''),
        'company_country'         => trim($_POST['company_country'] ?? ''),
        'calendar_sheet_url'      => trim($_POST['calendar_sheet_url'] ?? ''),
        'calendar_sheet_range'    => trim($_POST['calendar_sheet_range'] ?? 'Sheet1!A:A'),
        'calendar_update_interval'=> trim($_POST['calendar_update_interval'] ?? '24'),
        'calendar_enabled'        => isset($_POST['calendar_enabled']) ? '1' : '0',
        'calendar_display_customer' => isset($_POST['calendar_display_customer']) ? '1' : '0',
        'hootsuite_enabled'       => isset($_POST['hootsuite_enabled']) ? '1' : '0',
        'hootsuite_display_customer' => isset($_POST['hootsuite_display_customer']) ? '1' : '0',
        'hootsuite_update_interval'=> trim($_POST['hootsuite_update_interval'] ?? '24'),
        'hootsuite_token_refresh_interval'=> trim($_POST['hootsuite_token_refresh_interval'] ?? '24'),
        'hootsuite_client_id'     => trim($_POST['hootsuite_client_id'] ?? ''),
        'hootsuite_client_secret' => trim($_POST['hootsuite_client_secret'] ?? ''),
        'hootsuite_redirect_uri'  => trim($_POST['hootsuite_redirect_uri'] ?? ''),
        'hootsuite_debug'         => isset($_POST['hootsuite_debug']) ? '1' : '0'
    ];

    foreach ($settings as $name => $value) {
        set_setting($name, $value);
    }

    // Save statuses
    if (isset($_POST['status_name'])) {
        $ids = $_POST['status_id'] ?? [];
        $names = $_POST['status_name'];
        $colors = $_POST['status_color'];
        foreach ($names as $i => $name) {
            $name = trim($name);
            $color = $colors[$i] ?? '#000000';
            $id = $ids[$i] ?? '';
            if ($name === '' && $id) {
                $stmt = $pdo->prepare('DELETE FROM upload_statuses WHERE id = ?');
                $stmt->execute([$id]);
                continue;
            }
            if ($name === '') { continue; }
            if ($id) {
                $stmt = $pdo->prepare('UPDATE upload_statuses SET name=?, color=? WHERE id=?');
                $stmt->execute([$name, $color, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO upload_statuses (name, color) VALUES (?, ?)');
                $stmt->execute([$name, $color]);
            }
        }
        $statuses = $pdo->query('SELECT id, name, color FROM upload_statuses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    // Save social networks
    if (isset($_POST['network_name'])) {
        $ids = $_POST['network_id'] ?? [];
        $names = $_POST['network_name'];
        $icons = $_POST['network_icon'];
        $colors = $_POST['network_color'];
        foreach ($names as $i => $name) {
            $name = trim($name);
            $icon = trim($icons[$i] ?? '');
            $color = $colors[$i] ?? '#000000';
            $id = $ids[$i] ?? '';
            if ($name === '' && $id) {
                $stmt = $pdo->prepare('DELETE FROM social_networks WHERE id = ?');
                $stmt->execute([$id]);
                continue;
            }
            if ($name === '') { continue; }
            if ($id) {
                $stmt = $pdo->prepare('UPDATE social_networks SET name=?, icon=?, color=? WHERE id=?');
                $stmt->execute([$name, $icon, $color, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO social_networks (name, icon, color) VALUES (?, ?, ?)');
                $stmt->execute([$name, $icon, $color]);
            }
        }
        $networks = $pdo->query('SELECT id, name, icon, color FROM social_networks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    if (isset($_POST['delete_chats'])) {
        $pdo->exec('DELETE FROM store_messages WHERE store_id IS NOT NULL');
        $active_tab = 'reset';
    } elseif (isset($_POST['delete_broadcasts'])) {
        $pdo->exec('DELETE FROM store_messages WHERE store_id IS NULL');
        $active_tab = 'reset';
    } elseif (isset($_POST['delete_uploads'])) {
        $pdo->exec('DELETE FROM uploads');
        $active_tab = 'reset';
    } elseif (isset($_POST['delete_store_users'])) {
        $pdo->exec('DELETE FROM store_users');
        $active_tab = 'reset';
    } elseif (isset($_POST['delete_stores'])) {
        $pdo->exec('DELETE FROM stores');
        $active_tab = 'reset';
    } elseif (isset($_POST['clear_sessions'])) {
        $path = session_save_path() ?: sys_get_temp_dir();
        foreach (glob($path . '/sess_*') as $file) {
            @unlink($file);
        }
        $active_tab = 'reset';
    } elseif (isset($_POST['clear_cache'])) {
        $cacheDir = __DIR__ . '/../public/calendar_media';
        if (is_dir($cacheDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileInfo) {
                $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileInfo->getRealPath());
            }
        }
        $active_tab = 'reset';
    } elseif (isset($_POST['test_groundhogg'])) {
        [$ok, $msg] = test_groundhogg_connection();
        $test_result = [$ok, $msg];
        $test_action = 'groundhogg';
    } elseif (isset($_POST['calendar_update'])) {
        if (get_setting('calendar_enabled') === '1') {
            require_once __DIR__.'/../lib/calendar.php';
            [$ok, $msg] = calendar_update(false);
        } else {
            [$ok, $msg] = [false, 'Calendar integration disabled'];
        }
        $test_result = [$ok, $msg];
        $test_action = 'calendar';
        $active_tab = 'calendar';
    } elseif (isset($_POST['force_calendar_update'])) {
        if (get_setting('calendar_enabled') === '1') {
            require_once __DIR__.'/../lib/calendar.php';
            [$ok, $msg] = calendar_update(true);
        } else {
            [$ok, $msg] = [false, 'Calendar integration disabled'];
        }
        $test_result = [$ok, $msg];
        $test_action = 'calendar';
        $active_tab = 'calendar';
    } elseif (isset($_POST['erase_calendar'])) {
        if (get_setting('calendar_enabled') === '1') {
            $pdo->exec('TRUNCATE TABLE calendar');
            $test_result = [true, 'Calendar entries erased'];
        } else {
            $test_result = [false, 'Calendar integration disabled'];
        }
        $test_action = 'calendar';
        $active_tab = 'calendar';
    } elseif (isset($_POST['hootsuite_update'])) {
        if (get_setting('hootsuite_enabled') === '1') {
            require_once __DIR__.'/../hoot/hootsuite_sync.php';
            [$ok, $msg] = hootsuite_update(false, get_setting('hootsuite_debug') === '1');
        } else {
            [$ok, $msg] = [false, 'Hootsuite integration disabled'];
        }
        $test_result = [$ok, $msg];
        $test_action = 'hootsuite';
        $active_tab = 'calendar';
    } elseif (isset($_POST['force_hootsuite_update'])) {
        if (get_setting('hootsuite_enabled') === '1') {
            require_once __DIR__.'/../hoot/hootsuite_sync.php';
            [$ok, $msg] = hootsuite_update(true, get_setting('hootsuite_debug') === '1');
        } else {
            [$ok, $msg] = [false, 'Hootsuite integration disabled'];
        }
        $test_result = [$ok, $msg];
        $test_action = 'hootsuite';
        $active_tab = 'calendar';
    } elseif (isset($_POST['erase_hootsuite'])) {
        if (get_setting('hootsuite_enabled') === '1') {
            require_once __DIR__.'/../hoot/hootsuite_sync.php';
            [$ok, $msg] = hootsuite_erase_all();
        } else {
            [$ok, $msg] = [false, 'Hootsuite integration disabled'];
        }
        $test_result = [$ok, $msg];
        $test_action = 'hootsuite';
        $active_tab = 'calendar';
    } elseif (isset($_POST['test_hootsuite_connection'])) {
        if (get_setting('hootsuite_enabled') === '1') {
            require_once __DIR__.'/../hoot/hootsuite_sync.php';
            [$ok, $msg] = hootsuite_test_connection(get_setting('hootsuite_debug') === '1');
        } else {
            [$ok, $msg] = [false, 'Hootsuite integration disabled'];
        }
        $test_result = [$ok, $msg];
        $test_action = 'hootsuite';
        $active_tab = 'calendar';
    } elseif (isset($_POST['refresh_hootsuite_token'])) {
        if (get_setting('hootsuite_enabled') === '1') {
            require_once __DIR__.'/../hoot/hootsuite_refresh_token.php';
            [$ok, $msg] = hootsuite_refresh_token(get_setting('hootsuite_debug') === '1');
        } else {
            [$ok, $msg] = [false, 'Hootsuite integration disabled'];
        }
        $test_result = [$ok, $msg];
        $test_action = 'hootsuite';
        $active_tab = 'calendar';
    }
    $success = true;
}

// Get current settings
$drive_folder = get_setting('drive_base_folder');
$drive_debug = get_setting('drive_debug');
$notify_email = get_setting('notification_email');
$email_from_name = get_setting('email_from_name') ?: 'Cosmick Media';
$email_from_address = get_setting('email_from_address') ?: 'noreply@cosmickmedia.com';
$admin_notification_subject = get_setting('admin_notification_subject') ?: 'New uploads from {store_name}';
$store_notification_subject = get_setting('store_notification_subject') ?: 'Content Submission Confirmation - Cosmick Media';
$store_message_subject = get_setting('store_message_subject') ?: 'New message from Cosmick Media';
$admin_article_notification_subject = get_setting('admin_article_notification_subject') ?: 'New article submission from {store_name}';
$store_article_notification_subject = get_setting('store_article_notification_subject') ?: 'Article Submission Confirmation - Cosmick Media';
$article_approval_subject = get_setting('article_approval_subject') ?: 'Article Status Update - Cosmick Media';
$max_article_length = get_setting('max_article_length') ?: '50000';
$company_address = get_setting('company_address') ?: '';
$company_city = get_setting('company_city') ?: '';
$company_state = get_setting('company_state') ?: '';
$company_zip = get_setting('company_zip') ?: '';
$company_country = get_setting('company_country') ?: '';
$calendar_sheet_url = get_setting('calendar_sheet_url') ?: '';
$calendar_sheet_range = get_setting('calendar_sheet_range') ?: 'Sheet1!A:A';
$calendar_update_interval = get_setting('calendar_update_interval') ?: '24';
$calendar_enabled = get_setting('calendar_enabled') ?: '0';
$calendar_display_customer = get_setting('calendar_display_customer') ?: '1';
$hootsuite_enabled = get_setting('hootsuite_enabled') ?: '0';
$hootsuite_display_customer = get_setting('hootsuite_display_customer') ?: '1';
$hootsuite_update_interval = get_setting('hootsuite_update_interval') ?: '24';
$hootsuite_token_refresh_interval = get_setting('hootsuite_token_refresh_interval') ?: '24';
$hootsuite_client_id = get_setting('hootsuite_client_id') ?: '';
$hootsuite_client_secret = get_setting('hootsuite_client_secret') ?: '';
$hootsuite_redirect_uri = get_setting('hootsuite_redirect_uri') ?: '';
$hootsuite_debug = get_setting('hootsuite_debug') ?: '0';
$hootsuite_access_token = get_setting('hootsuite_access_token') ?: '';
$groundhogg_site_url = get_setting('groundhogg_site_url');
$groundhogg_username = get_setting('groundhogg_username');
$groundhogg_public_key = get_setting('groundhogg_public_key');
$groundhogg_token = get_setting('groundhogg_token');
$groundhogg_secret_key = get_setting('groundhogg_secret_key');
$groundhogg_debug = get_setting('groundhogg_debug');
$groundhogg_contact_tags = get_setting('groundhogg_contact_tags');

// Get product version
$product_version = trim(file_get_contents(__DIR__.'/../VERSION'));

$active = 'settings';
include __DIR__.'/header.php';
?>


    <div class="animate__animated animate__fadeIn">
        <!-- Page Header with Version -->
        <div class="page-header animate__animated animate__fadeInDown">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">System Settings</h1>
                    <p class="page-subtitle">Configure system-wide settings and integrations</p>
                </div>
                <div class="version-badge">
                    <i class="bi bi-tag"></i>
                    Version <?php echo htmlspecialchars($product_version); ?>
                </div>
            </div>
        </div>

        <!-- System Information Card -->
        <div class="system-info-card animate__animated animate__fadeIn delay-10">
            <h5 class="mb-0">
                <i class="bi bi-speedometer2 me-2"></i>
                MediaHub Admin System
            </h5>
            <div class="system-info-grid">
                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="bi bi-server"></i>
                    </div>
                    <div class="system-info-label">PHP Version</div>
                    <div class="system-info-value"><?php echo PHP_VERSION; ?></div>
                </div>
                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="bi bi-database"></i>
                    </div>
                    <div class="system-info-label">Database</div>
                    <div class="system-info-value">Connected</div>
                </div>
                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="bi bi-memory"></i>
                    </div>
                    <div class="system-info-label">Memory Usage</div>
                    <div class="system-info-value"><?php echo round(memory_get_usage() / 1024 / 1024, 1); ?> MB</div>
                </div>
                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="system-info-label">System Status</div>
                    <div class="system-info-value">Online</div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                Settings saved successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($test_result !== null): ?>
            <?php if ($test_action === 'groundhogg'): ?>
                <?php if ($test_result[0]): ?>
                    <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Dripley connection successful!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Dripley connection failed: <?php echo htmlspecialchars($test_result[1]); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php elseif ($test_action === 'calendar'): ?>
                <?php if ($test_result[0]): ?>
                    <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Calendar update successful: <?php echo htmlspecialchars($test_result[1]); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Calendar update failed: <?php echo htmlspecialchars($test_result[1]); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php elseif ($test_action === 'hootsuite'): ?>
                <?php if ($test_result[0]): ?>
                    <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Hootsuite action successful: <?php echo htmlspecialchars($test_result[1]); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Hootsuite action failed: <?php echo htmlspecialchars($test_result[1]); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <!-- Modern Navigation Tabs -->
            <ul class="nav-tabs-modern animate__animated animate__fadeIn delay-20" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if($active_tab==='general') echo ' active'; ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="bi bi-gear"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if($active_tab==='dripley') echo ' active'; ?>" id="dripley-tab" data-bs-toggle="tab" data-bs-target="#dripley" type="button" role="tab">
                        <i class="bi bi-people"></i> Dripley CRM
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if($active_tab==='subjects') echo ' active'; ?>" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab">
                        <i class="bi bi-envelope"></i> Email Subjects
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if($active_tab==='statuses') echo ' active'; ?>" id="statuses-tab" data-bs-toggle="tab" data-bs-target="#statuses" type="button" role="tab">
                        <i class="bi bi-tags"></i> Statuses
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if($active_tab==='calendar') echo ' active'; ?>" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button" role="tab">
                        <i class="bi bi-calendar"></i> Calendar
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?php if($active_tab==='reset') echo ' active'; ?>" id="reset-tab" data-bs-toggle="tab" data-bs-target="#reset" type="button" role="tab">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- General Settings Tab -->
                <div class="tab-pane fade<?php if($active_tab==='general') echo ' show active'; ?>" id="general" role="tabpanel">
                    <div class="settings-grid settings-grid-2">
                        <!-- Google Drive Settings -->
                        <div class="settings-card animate__animated animate__fadeIn delay-30">
                            <div class="card-header-modern">
                                <h5 class="card-title-modern">
                                    <i class="bi bi-google"></i>
                                    Google Drive Settings
                                </h5>
                            </div>
                            <div class="card-body-modern">
                                <div class="mb-4">
                                    <label class="form-label-modern">
                                        <i class="bi bi-file-earmark-code"></i> Service Account JSON
                                    </label>
                                    <div class="file-upload-area">
                                        <div class="file-upload-icon">
                                            <i class="bi bi-cloud-upload"></i>
                                        </div>
                                        <input class="form-control form-control-modern" type="file" name="sa_json" accept=".json">
                                        <div class="form-text-modern mt-2">Upload Google service account credentials file</div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="drive_folder" class="form-label-modern">
                                        <i class="bi bi-folder"></i> Base Drive Folder ID
                                    </label>
                                    <input type="text" name="drive_folder" id="drive_folder" class="form-control form-control-modern" value="<?php echo htmlspecialchars($drive_folder); ?>">
                                    <div class="form-text-modern">The Google Drive folder ID where store folders will be created</div>
                                </div>
                                <div class="form-check-modern">
                                    <input type="checkbox" name="drive_debug" id="drive_debug" class="form-check-input" value="1" <?php if ($drive_debug === '1') echo 'checked'; ?>>
                                    <label for="drive_debug" class="form-check-label">
                                        <strong>Enable Drive Debug Logging</strong>
                                    </label>
                                    <div class="form-text-modern">Logs Drive API requests to <span class="code-snippet">logs/drive.log</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Settings -->
                        <div class="settings-card animate__animated animate__fadeIn delay-40">
                            <div class="card-header-modern">
                                <h5 class="card-title-modern">
                                    <i class="bi bi-envelope"></i>
                                    Email Settings
                                </h5>
                            </div>
                            <div class="card-body-modern">
                                <div class="mb-4">
                                    <label for="notify_email" class="form-label-modern">
                                        <i class="bi bi-bell"></i> Admin Notification Email(s)
                                    </label>
                                    <input type="text" name="notify_email" id="notify_email" class="form-control form-control-modern" value="<?php echo htmlspecialchars($notify_email); ?>">
                                    <div class="form-text-modern">Comma-separated emails for upload and article notifications</div>
                                </div>
                                <div class="mb-4">
                                    <label for="email_from_name" class="form-label-modern">
                                        <i class="bi bi-person"></i> From Name
                                    </label>
                                    <input type="text" name="email_from_name" id="email_from_name" class="form-control form-control-modern" value="<?php echo htmlspecialchars($email_from_name); ?>">
                                    <div class="form-text-modern">Name shown in email "From" field</div>
                                </div>
                                <div class="mb-4">
                                    <label for="email_from_address" class="form-label-modern">
                                        <i class="bi bi-envelope-at"></i> From Email Address
                                    </label>
                                    <input type="email" name="email_from_address" id="email_from_address" class="form-control form-control-modern" value="<?php echo htmlspecialchars($email_from_address); ?>">
                                    <div class="form-text-modern">Email address used for sending</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Article Settings -->
                    <div class="settings-card animate__animated animate__fadeIn delay-50">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-file-text"></i>
                                Article Settings
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="max_article_length" class="form-label-modern">
                                        <i class="bi bi-textarea-resize"></i> Maximum Article Length (characters)
                                    </label>
                                    <input type="number" name="max_article_length" id="max_article_length" class="form-control form-control-modern" value="<?php echo htmlspecialchars($max_article_length); ?>">
                                    <div class="form-text-modern">Maximum character count allowed for article submissions</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location Information -->
                    <div class="settings-card animate__animated animate__fadeIn delay-60">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-geo-alt"></i>
                                Location Information
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="company_address" class="form-label-modern">
                                        <i class="bi bi-house"></i> Address
                                    </label>
                                    <input type="text" name="company_address" id="company_address" class="form-control form-control-modern" placeholder="123 Main St." value="<?php echo htmlspecialchars($company_address); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="company_city" class="form-label-modern">
                                        <i class="bi bi-building"></i> City
                                    </label>
                                    <input type="text" name="company_city" id="company_city" class="form-control form-control-modern" placeholder="Wind Gap" value="<?php echo htmlspecialchars($company_city); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="company_state" class="form-label-modern">
                                        <i class="bi bi-map"></i> State
                                    </label>
                                    <input type="text" name="company_state" id="company_state" class="form-control form-control-modern" placeholder="PA" value="<?php echo htmlspecialchars($company_state); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="company_zip" class="form-label-modern">
                                        <i class="bi bi-mailbox"></i> Zip Code
                                    </label>
                                    <input type="text" name="company_zip" id="company_zip" class="form-control form-control-modern" placeholder="18091" value="<?php echo htmlspecialchars($company_zip); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="company_country" class="form-label-modern">
                                        <i class="bi bi-flag"></i> Country
                                    </label>
                                    <input type="text" name="company_country" id="company_country" class="form-control form-control-modern" placeholder="US" value="<?php echo htmlspecialchars($company_country); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dripley CRM Tab -->
                <div class="tab-pane fade<?php if($active_tab==='dripley') echo ' show active'; ?>" id="dripley" role="tabpanel">
                    <div class="settings-card animate__animated animate__fadeIn delay-30">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-people"></i>
                                Dripley CRM Integration
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="groundhogg_site_url" class="form-label-modern">
                                        <i class="bi bi-globe"></i> Dripley Site URL
                                    </label>
                                    <input type="text" name="groundhogg_site_url" id="groundhogg_site_url" class="form-control form-control-modern" value="<?php echo htmlspecialchars($groundhogg_site_url); ?>" placeholder="https://www.cosmickmedia.com">
                                </div>
                                <div class="col-md-6">
                                    <label for="groundhogg_username" class="form-label-modern">
                                        <i class="bi bi-person"></i> Dripley API Username
                                    </label>
                                    <input type="text" name="groundhogg_username" id="groundhogg_username" class="form-control form-control-modern" value="<?php echo htmlspecialchars($groundhogg_username); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="groundhogg_public_key" class="form-label-modern">
                                        <i class="bi bi-key"></i> Public Key
                                    </label>
                                    <input type="text" name="groundhogg_public_key" id="groundhogg_public_key" class="form-control form-control-modern" value="<?php echo htmlspecialchars($groundhogg_public_key); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="groundhogg_token" class="form-label-modern">
                                        <i class="bi bi-shield-check"></i> Token
                                    </label>
                                    <input type="text" name="groundhogg_token" id="groundhogg_token" class="form-control form-control-modern" value="<?php echo htmlspecialchars($groundhogg_token); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label for="groundhogg_secret_key" class="form-label-modern">
                                        <i class="bi bi-lock"></i> Secret Key
                                    </label>
                                    <input type="text" name="groundhogg_secret_key" id="groundhogg_secret_key" class="form-control form-control-modern" value="<?php echo htmlspecialchars($groundhogg_secret_key); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label for="groundhogg_contact_tags" class="form-label-modern">
                                        <i class="bi bi-tags"></i> Default Contact Tags
                                    </label>
                                    <input type="text" name="groundhogg_contact_tags" id="groundhogg_contact_tags" class="form-control form-control-modern" value="<?php echo htmlspecialchars($groundhogg_contact_tags); ?>">
                                    <div class="form-text-modern">Comma-separated tags applied to new contacts</div>
                                </div>
                            </div>

                            <div class="form-check-modern mt-4">
                                <input type="checkbox" name="groundhogg_debug" id="groundhogg_debug" class="form-check-input" value="1" <?php if ($groundhogg_debug === '1') echo 'checked'; ?>>
                                <label for="groundhogg_debug" class="form-check-label">
                                    <strong>Enable Debug Logging</strong>
                                </label>
                                <div class="form-text-modern">Logs API communication to <span class="code-snippet">logs/groundhogg.log</span></div>
                            </div>

                            <div class="test-section">
                                <h6>
                                    <i class="bi bi-gear"></i> Test & Sync
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="test_groundhogg">
                                        <i class="bi bi-wifi"></i> Test Connection
                                    </button>
                                    <a href="sync_groundhogg.php" class="btn btn-secondary-modern btn-sm-modern">
                                        <i class="bi bi-arrow-repeat"></i> Sync Store Contacts
                                    </a>
                                    <a href="sync_admin_users.php" class="btn btn-secondary-modern btn-sm-modern">
                                        <i class="bi bi-people"></i> Sync Admin Users
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Subjects Tab -->
                <div class="tab-pane fade<?php if($active_tab==='subjects') echo ' show active'; ?>" id="subjects" role="tabpanel">
                    <div class="settings-card animate__animated animate__fadeIn delay-30">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-cloud-upload"></i>
                                Email Subject Lines - Uploads
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="mb-4">
                                <label for="admin_notification_subject" class="form-label-modern">
                                    <i class="bi bi-bell"></i> Admin Notification Subject (New Uploads)
                                </label>
                                <input type="text" name="admin_notification_subject" id="admin_notification_subject" class="form-control form-control-modern" value="<?php echo htmlspecialchars($admin_notification_subject); ?>">
                                <div class="form-text-modern">Subject for emails sent to admin when new content is uploaded. Use {store_name} as placeholder.</div>
                            </div>
                            <div class="mb-4">
                                <label for="store_notification_subject" class="form-label-modern">
                                    <i class="bi bi-check-circle"></i> Store Confirmation Subject (Upload Confirmation)
                                </label>
                                <input type="text" name="store_notification_subject" id="store_notification_subject" class="form-control form-control-modern" value="<?php echo htmlspecialchars($store_notification_subject); ?>">
                                <div class="form-text-modern">Subject for confirmation emails sent to stores after upload. Use {store_name} as placeholder.</div>
                            </div>
                            <div class="mb-4">
                                <label for="store_message_subject" class="form-label-modern">
                                    <i class="bi bi-chat-dots"></i> Store Message Subject (Admin Messages)
                                </label>
                                <input type="text" name="store_message_subject" id="store_message_subject" class="form-control form-control-modern" value="<?php echo htmlspecialchars($store_message_subject); ?>">
                                <div class="form-text-modern">Subject for emails sent to stores when admin posts a message. Use {store_name} as placeholder.</div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card animate__animated animate__fadeIn delay-40">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-file-text"></i>
                                Email Subject Lines - Articles
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="mb-4">
                                <label for="admin_article_notification_subject" class="form-label-modern">
                                    <i class="bi bi-bell"></i> Admin Notification Subject (New Articles)
                                </label>
                                <input type="text" name="admin_article_notification_subject" id="admin_article_notification_subject" class="form-control form-control-modern" value="<?php echo htmlspecialchars($admin_article_notification_subject); ?>">
                                <div class="form-text-modern">Subject for emails sent to admin when new article is submitted. Use {store_name} as placeholder.</div>
                            </div>
                            <div class="mb-4">
                                <label for="store_article_notification_subject" class="form-label-modern">
                                    <i class="bi bi-check-circle"></i> Store Confirmation Subject (Article Submission)
                                </label>
                                <input type="text" name="store_article_notification_subject" id="store_article_notification_subject" class="form-control form-control-modern" value="<?php echo htmlspecialchars($store_article_notification_subject); ?>">
                                <div class="form-text-modern">Subject for confirmation emails sent to stores after article submission. Use {store_name} as placeholder.</div>
                            </div>
                            <div class="mb-4">
                                <label for="article_approval_subject" class="form-label-modern">
                                    <i class="bi bi-envelope-check"></i> Article Status Update Subject
                                </label>
                                <input type="text" name="article_approval_subject" id="article_approval_subject" class="form-control form-control-modern" value="<?php echo htmlspecialchars($article_approval_subject); ?>">
                                <div class="form-text-modern">Subject for emails sent when article status is updated. Use {store_name} as placeholder.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statuses Tab -->
                <div class="tab-pane fade<?php if($active_tab==='statuses') echo ' show active'; ?>" id="statuses" role="tabpanel">
                    <div class="settings-card animate__animated animate__fadeIn delay-30">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-tags"></i>
                                Upload Statuses
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="info-card">
                                <div class="info-card-title">
                                    <i class="bi bi-info-circle"></i> Status Management
                                </div>
                                <div class="info-card-content">
                                    Create custom statuses to organize and track uploaded content. Each status can have a unique name and color.
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-modern" id="statusTable">
                                    <thead>
                                    <tr>
                                        <th>Status Name</th>
                                        <th>Color</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($statuses as $st): ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="status_id[]" value="<?php echo $st['id']; ?>">
                                                <input type="text" name="status_name[]" class="form-control form-control-modern" value="<?php echo htmlspecialchars($st['name']); ?>">
                                            </td>
                                            <td>
                                                <input type="color" name="status_color[]" class="form-control form-control-color" value="<?php echo htmlspecialchars($st['color']); ?>">
                                            </td>
                                            <td>
                                                <button type="button" class="remove-item-btn remove-status">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="add-item-btn" id="addStatus">
                                <i class="bi bi-plus-circle"></i> Add Status
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Calendar Tab -->
                <div class="tab-pane fade<?php if($active_tab==='calendar') echo ' show active'; ?>" id="calendar" role="tabpanel">
                    <div class="settings-card animate__animated animate__fadeIn delay-30">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-calendar"></i>
                                Calendar Import
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="form-check-modern mb-3">
                                <input type="checkbox" name="calendar_enabled" id="calendar_enabled" class="form-check-input" value="1" <?php if ($calendar_enabled === '1') echo 'checked'; ?>>
                                <label for="calendar_enabled" class="form-check-label">
                                    <strong>Enable Calendar Import</strong>
                                </label>
                            </div>
                            <div class="form-check-modern mb-3">
                                <input type="checkbox" name="calendar_display_customer" id="calendar_display_customer" class="form-check-input" value="1" <?php if ($calendar_display_customer === '1') echo 'checked'; ?>>
                                <label for="calendar_display_customer" class="form-check-label">
                                    <strong>Display on customer calendar</strong>
                                </label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="calendar_sheet_url" class="form-label-modern">
                                        <i class="bi bi-link"></i> Google Sheet URL
                                    </label>
                                    <input type="text" name="calendar_sheet_url" id="calendar_sheet_url" class="form-control form-control-modern" value="<?php echo htmlspecialchars($calendar_sheet_url); ?>">
                                    <div class="form-text-modern">Paste the public sheet link; export URL is handled automatically</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="calendar_sheet_range" class="form-label-modern">
                                        <i class="bi bi-grid"></i> Sheet Range
                                    </label>
                                    <input type="text" name="calendar_sheet_range" id="calendar_sheet_range" class="form-control form-control-modern" value="<?php echo htmlspecialchars($calendar_sheet_range); ?>">
                                    <div class="form-text-modern">Range in A1 notation, e.g. Sheet1!A:A</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="calendar_update_interval" class="form-label-modern">
                                        <i class="bi bi-clock"></i> Update Interval (hours)
                                    </label>
                                    <input type="number" name="calendar_update_interval" id="calendar_update_interval" class="form-control form-control-modern" value="<?php echo htmlspecialchars($calendar_update_interval); ?>">
                                </div>
                            </div>

                            <div class="test-section">
                                <h6>
                                    <i class="bi bi-arrow-repeat"></i> Calendar Actions
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="calendar_update">
                                        <i class="bi bi-download"></i> Update
                                    </button>
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="force_calendar_update">
                                        <i class="bi bi-arrow-repeat"></i> Force Sync
                                    </button>
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="erase_calendar">
                                        <i class="bi bi-x-circle"></i> Erase All
                                    </button>
                                </div>
                                <div class="form-text-modern mt-2">
                                    <strong>Update</strong> adds new sheet entries without removing existing ones. <strong>Force Sync</strong> clears all entries then syncs. <strong>Erase All</strong> removes all entries without syncing.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card animate__animated animate__fadeIn delay-40">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-h-square"></i>
                                Hootsuite Integration
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="form-check-modern mb-3">
                                <input type="checkbox" name="hootsuite_enabled" id="hootsuite_enabled" class="form-check-input" value="1" <?php if ($hootsuite_enabled === '1') echo 'checked'; ?>>
                                <label for="hootsuite_enabled" class="form-check-label">
                                    <strong>Enable Hootsuite Integration</strong>
                                </label>
                            </div>
                            <div class="form-check-modern mb-3">
                                <input type="checkbox" name="hootsuite_display_customer" id="hootsuite_display_customer" class="form-check-input" value="1" <?php if ($hootsuite_display_customer === '1') echo 'checked'; ?>>
                                <label for="hootsuite_display_customer" class="form-check-label">
                                    <strong>Display on customer calendar</strong>
                                </label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="hootsuite_client_id" class="form-label-modern">
                                        <i class="bi bi-key"></i> Client ID
                                    </label>
                                    <input type="text" name="hootsuite_client_id" id="hootsuite_client_id" class="form-control form-control-modern" value="<?php echo htmlspecialchars($hootsuite_client_id); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="hootsuite_client_secret" class="form-label-modern">
                                        <i class="bi bi-lock"></i> Client Secret
                                    </label>
                                    <input type="text" name="hootsuite_client_secret" id="hootsuite_client_secret" class="form-control form-control-modern" value="<?php echo htmlspecialchars($hootsuite_client_secret); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="hootsuite_redirect_uri" class="form-label-modern">
                                        <i class="bi bi-link-45deg"></i> Redirect URI
                                    </label>
                                    <input type="text" name="hootsuite_redirect_uri" id="hootsuite_redirect_uri" class="form-control form-control-modern" value="<?php echo htmlspecialchars($hootsuite_redirect_uri); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label for="hootsuite_access_token" class="form-label-modern">
                                        <i class="bi bi-shield-check"></i> Access Token
                                    </label>
                                    <input type="text" id="hootsuite_access_token" class="form-control form-control-modern" value="<?php echo htmlspecialchars($hootsuite_access_token); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="hootsuite_update_interval" class="form-label-modern">
                                        <i class="bi bi-clock"></i> Sync Interval (hours)
                                    </label>
                                    <input type="number" name="hootsuite_update_interval" id="hootsuite_update_interval" class="form-control form-control-modern" value="<?php echo htmlspecialchars($hootsuite_update_interval); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="hootsuite_token_refresh_interval" class="form-label-modern">
                                        <i class="bi bi-arrow-clockwise"></i> Token Refresh Interval (hours)
                                    </label>
                                    <input type="number" name="hootsuite_token_refresh_interval" id="hootsuite_token_refresh_interval" class="form-control form-control-modern" value="<?php echo htmlspecialchars($hootsuite_token_refresh_interval); ?>">
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check-modern">
                                        <input type="checkbox" name="hootsuite_debug" id="hootsuite_debug" class="form-check-input" value="1" <?php if ($hootsuite_debug === '1') echo 'checked'; ?>>
                                        <label for="hootsuite_debug" class="form-check-label">
                                            <strong>Debug Mode</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="test-section">
                                <h6>
                                    <i class="bi bi-arrow-repeat"></i> Hootsuite Actions
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-secondary-modern btn-sm-modern" href="hootsuite_login.php">
                                        <i class="bi bi-box-arrow-in-right"></i> Authenticate
                                    </a>
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="test_hootsuite_connection">
                                        <i class="bi bi-plug"></i> Test Connection
                                    </button>
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="hootsuite_update">
                                        <i class="bi bi-download"></i> Sync Now
                                    </button>
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="force_hootsuite_update">
                                        <i class="bi bi-arrow-repeat"></i> Force Sync
                                    </button>
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="erase_hootsuite">
                                        <i class="bi bi-x-circle"></i> Erase All
                                    </button>
                                    <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="refresh_hootsuite_token">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh Token
                                    </button>
                                </div>
                                <div class="form-text-modern mt-2">
                                    <strong>Test Connection</strong> checks OAuth settings.
                                    <strong>Sync Now</strong> adds new posts without clearing existing ones.
                                    <strong>Force Sync</strong> clears all posts then syncs.
                                    <strong>Erase All</strong> removes all stored posts.
                                    <strong>Refresh Token</strong> obtains a new access token using the saved refresh token.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card animate__animated animate__fadeIn delay-40">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-share"></i>
                                Social Networks
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="info-card">
                                <div class="info-card-title">
                                    <i class="bi bi-info-circle"></i> Social Network Configuration
                                </div>
                                <div class="info-card-content">
                                    Define social networks for content scheduling and publishing integrations.
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-modern" id="networkTable">
                                    <thead>
                                    <tr>
                                        <th>Network Name</th>
                                        <th>Icon Class</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($networks as $n): ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="network_id[]" value="<?php echo $n['id']; ?>">
                                                <input type="text" name="network_name[]" class="form-control form-control-modern" value="<?php echo htmlspecialchars($n['name']); ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="network_icon[]" class="form-control form-control-modern" value="<?php echo htmlspecialchars($n['icon']); ?>">
                                                <input type="hidden" name="network_color[]" value="<?php echo htmlspecialchars($n['color']); ?>">
                                            </td>
                                            <td>
                                                <button type="button" class="remove-item-btn remove-network">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="add-item-btn" id="addNetwork">
                                <i class="bi bi-plus-circle"></i> Add Network
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reset Tab -->
                <div class="tab-pane fade<?php if($active_tab==='reset') echo ' show active'; ?>" id="reset" role="tabpanel">
                    <div class="settings-card animate__animated animate__fadeIn delay-30">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="bi bi-exclamation-triangle"></i>
                                Reset & Cleanup
                            </h5>
                        </div>
                        <div class="card-body-modern">
                            <div class="reset-section">
                                <div class="reset-warning">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    Warning: These actions cannot be undone!
                                </div>
                                <div class="reset-actions">
                                    <button class="btn btn-danger-modern" type="submit" name="delete_chats" onclick="return confirm('Delete all chats? This action cannot be undone.');">
                                        <i class="bi bi-chat-dots"></i> Delete All Chats
                                    </button>
                                    <button class="btn btn-danger-modern" type="submit" name="delete_broadcasts" onclick="return confirm('Delete all broadcasts? This action cannot be undone.');">
                                        <i class="bi bi-megaphone"></i> Delete All Broadcasts
                                    </button>
                                    <button class="btn btn-danger-modern" type="submit" name="delete_uploads" onclick="return confirm('Delete all uploads? This action cannot be undone.');">
                                        <i class="bi bi-cloud-upload"></i> Delete All Uploads
                                    </button>
                                    <button class="btn btn-danger-modern" type="submit" name="delete_store_users" onclick="return confirm('Delete all store users? This action cannot be undone.');">
                                        <i class="bi bi-people"></i> Delete All Store Users
                                    </button>
                                    <button class="btn btn-danger-modern" type="submit" name="delete_stores" onclick="return confirm('Delete all stores? This action cannot be undone.');">
                                        <i class="bi bi-shop"></i> Delete All Stores
                                    </button>
                                    <button class="btn btn-warning-modern" type="submit" name="clear_sessions" onclick="return confirm('Clear all sessions? Users will be logged out.');">
                                        <i class="bi bi-x-circle"></i> Clear Sessions
                                    </button>
                                    <button class="btn btn-warning-modern" type="submit" name="clear_cache" onclick="return confirm('Clear cached calendar media?');">
                                        <i class="bi bi-trash"></i> Clear Cache
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="active_tab" id="active_tab" value="<?php echo htmlspecialchars($active_tab); ?>">

            <!-- Save Button -->
            <div class="save-button-container">
                <button class="btn-save-settings" type="submit">
                    <i class="bi bi-check-circle me-2"></i>Save All Settings
                </button>
            </div>
        </form>
    </div>

    <script>
        // Add Status functionality
        document.getElementById('addStatus').addEventListener('click', function () {
            const tbody = document.querySelector('#statusTable tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>
                <input type="hidden" name="status_id[]" value="">
                <input type="text" name="status_name[]" class="form-control form-control-modern">
            </td>
            <td><input type="color" name="status_color[]" class="form-control form-control-color" value="#000000"></td>
            <td><button type="button" class="remove-item-btn remove-status"><i class="bi bi-trash"></i> Delete</button></td>
        `;
            tbody.appendChild(row);
        });

        // Add Network functionality
        if(document.getElementById('addNetwork')){
            document.getElementById('addNetwork').addEventListener('click', function () {
                const tbody = document.querySelector('#networkTable tbody');
                const row = document.createElement('tr');
                row.innerHTML = `
                <td>
                    <input type="hidden" name="network_id[]" value="">
                    <input type="text" name="network_name[]" class="form-control form-control-modern">
                </td>
                <td>
                    <input type="text" name="network_icon[]" class="form-control form-control-modern" value="">
                    <input type="hidden" name="network_color[]" value="#000000">
                </td>
                <td><button type="button" class="remove-item-btn remove-network"><i class="bi bi-trash"></i> Delete</button></td>
            `;
                tbody.appendChild(row);
            });
        }

        // Remove functionality
        document.addEventListener('click', function(e){
            if(e.target.closest('.remove-network')){
                e.target.closest('tr').remove();
            }
            if(e.target.closest('.remove-status')){
                e.target.closest('tr').remove();
            }
        });

        // Tab management
        document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]').forEach(btn=>{
            btn.addEventListener('shown.bs.tab', e=>{
                document.getElementById('active_tab').value = e.target.id.replace('-tab','');
            });
        });

        document.querySelector('form').addEventListener('submit', () => {
            const activeBtn = document.querySelector('#settingsTabs .nav-link.active');
            if (activeBtn) {
                document.getElementById('active_tab').value = activeBtn.id.replace('-tab','');
            }
        });

        // File upload drag and drop
        const fileUploadArea = document.querySelector('.file-upload-area');
        if (fileUploadArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, unhighlight, false);
            });

            function highlight(e) {
                fileUploadArea.classList.add('dragover');
            }

            function unhighlight(e) {
                fileUploadArea.classList.remove('dragover');
            }
        }
    </script>

<?php include __DIR__.'/footer.php'; ?>