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
$active_tab = $_POST['active_tab'] ?? 'general';

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
        'calendar_update_interval'=> trim($_POST['calendar_update_interval'] ?? '24')
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
    } elseif (isset($_POST['test_groundhogg'])) {
        [$ok, $msg] = test_groundhogg_connection();
        $test_result = [$ok, $msg];
        $test_action = 'groundhogg';
    } elseif (isset($_POST['force_calendar_update'])) {
        require_once __DIR__.'/../lib/calendar.php';
        [$ok, $msg] = calendar_update(true);
        $test_result = [$ok, $msg];
        $test_action = 'calendar';
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

    <style>
        /* Page Header */
        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="80" cy="20" r="30" fill="rgba(255,255,255,0.1)"/><circle cx="20" cy="80" r="20" fill="rgba(255,255,255,0.05)"/></svg>');
            pointer-events: none;
        }

        .page-header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
        }

        .version-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .version-badge i {
            font-size: 1rem;
        }

        /* Modern Tabs */
        .nav-tabs-modern {
            border: none;
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            list-style: none;
        }

        .nav-tabs-modern .nav-item {
            list-style: none;
        }

        .nav-tabs-modern .nav-link {
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            color: #6c757d;
            font-weight: 600;
            background: #f8f9fa;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .nav-tabs-modern .nav-link:hover {
            background: #e9ecef;
            color: #495057;
            transform: translateY(-2px);
        }

        .nav-tabs-modern .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* Settings Cards */
        .settings-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header-modern {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title-modern {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title-modern i {
            font-size: 1.1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-body-modern {
            padding: 2rem;
        }

        /* Form Controls */
        .form-control-modern, .form-select-modern {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .form-control-modern:focus, .form-select-modern:focus {
            border-color: #667eea;
            box-shadow: none;
            outline: none;
        }

        .form-label-modern {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label-modern i {
            font-size: 1rem;
            color: #6c757d;
        }

        .form-text-modern {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        /* Form File Upload */
        .file-upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            background: #fafafa;
        }

        .file-upload-area:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .file-upload-area.dragover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .file-upload-icon {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        /* Checkboxes */
        .form-check-modern {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 2px solid #e9ecef;
            transition: var(--transition);
        }

        .form-check-modern:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .form-check-modern .form-check-input:checked ~ .form-check-label {
            color: #667eea;
            font-weight: 600;
        }

        /* Tables */
        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            margin: 0;
            background: white;
        }

        .table-modern thead {
            background: var(--primary-gradient);
            color: white;
        }

        .table-modern th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-modern td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .table-modern tbody tr:hover {
            background: #f8f9fa;
        }

        .table-modern tbody tr:last-child td {
            border-bottom: none;
        }

        /* Color picker styling */
        .form-control-color {
            width: 60px;
            height: 40px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
        }

        /* Action Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-secondary-modern {
            background: #6c757d;
            color: white;
        }

        .btn-secondary-modern:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }

        .btn-success-modern {
            background: var(--success-gradient);
            color: white;
        }

        .btn-success-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
            color: white;
        }

        .btn-danger-modern {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-danger-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.3);
            color: white;
        }

        .btn-sm-modern {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Add/Remove buttons */
        .add-item-btn {
            background: var(--success-gradient);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: var(--transition);
            margin-top: 1rem;
        }

        .add-item-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
            color: white;
        }

        .remove-item-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .remove-item-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Test Connection Section */
        .test-section {
            background: #e3f2fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
            border-left: 4px solid #2196f3;
        }

        .test-section h6 {
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Reset Section Styling */
        .reset-section {
            background: #ffebee;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid #f44336;
        }

        .reset-warning {
            color: #c62828;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reset-actions {
            display: grid;
            gap: 1rem;
        }

        /* Save Button */
        .save-button-container {
            position: sticky;
            bottom: 2rem;
            z-index: 100;
            text-align: center;
            margin-top: 3rem;
        }

        .btn-save-settings {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1rem 3rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .btn-save-settings:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
            color: white;
        }

        /* Grid Layout */
        .settings-grid {
            display: grid;
            gap: 2rem;
        }

        .settings-grid-2 {
            grid-template-columns: 1fr 1fr;
        }

        /* Info Cards */
        .info-card {
            background: #e8f5e8;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #4caf50;
        }

        .info-card-title {
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card-content {
            font-size: 0.875rem;
            color: #1b5e20;
        }

        /* Code styling */
        .code-snippet {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.25rem 0.5rem;
            font-family: monospace;
            font-size: 0.875rem;
            color: #e83e8c;
            border: 1px solid #e9ecef;
        }

        /* System Information Card */
        .system-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .system-info-item {
            text-align: center;
        }

        .system-info-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .system-info-label {
            font-size: 0.875rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }

        .system-info-value {
            font-size: 1.25rem;
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .settings-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .page-header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-tabs-modern {
                padding: 0.75rem;
                flex-direction: column;
            }

            .nav-tabs-modern .nav-link {
                justify-content: center;
            }

            .card-body-modern {
                padding: 1.5rem;
            }

            .table-modern {
                font-size: 0.875rem;
            }

            .btn-save-settings {
                width: 100%;
                margin: 0 1rem;
            }

            .system-info-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
    </style>

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
                                <button class="btn btn-secondary-modern btn-sm-modern" type="submit" name="force_calendar_update">
                                    <i class="bi bi-download"></i> Force Update
                                </button>
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