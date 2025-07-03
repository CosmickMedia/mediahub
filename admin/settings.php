<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

$success = false;

function get_setting($name) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE name=?');
    $stmt->execute([$name]);
    return $stmt->fetchColumn();
}

function set_setting($name, $value) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
    $stmt->execute([$name, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Service Account JSON upload
    if (isset($_FILES['sa_json']) && is_uploaded_file($_FILES['sa_json']['tmp_name'])) {
        $target = __DIR__.'/../service-account.json';
        move_uploaded_file($_FILES['sa_json']['tmp_name'], $target);
    }

    // Save all settings
    $settings = [
        'drive_base_folder' => $_POST['drive_folder'] ?? '',
        'notification_email' => $_POST['notify_email'] ?? '',
        'email_from_name' => $_POST['email_from_name'] ?? 'Cosmick Media',
        'email_from_address' => $_POST['email_from_address'] ?? 'noreply@cosmickmedia.com',
        'admin_notification_subject' => $_POST['admin_notification_subject'] ?? 'New uploads from {store_name}',
        'store_notification_subject' => $_POST['store_notification_subject'] ?? 'Content Submission Confirmation - Cosmick Media',
        'store_message_subject' => $_POST['store_message_subject'] ?? 'New message from Cosmick Media'
    ];

    foreach ($settings as $name => $value) {
        set_setting($name, $value);
    }

    $success = true;
}

// Get current settings
$drive_folder = get_setting('drive_base_folder');
$notify_email = get_setting('notification_email');
$email_from_name = get_setting('email_from_name') ?: 'Cosmick Media';
$email_from_address = get_setting('email_from_address') ?: 'noreply@cosmickmedia.com';
$admin_notification_subject = get_setting('admin_notification_subject') ?: 'New uploads from {store_name}';
$store_notification_subject = get_setting('store_notification_subject') ?: 'Content Submission Confirmation - Cosmick Media';
$store_message_subject = get_setting('store_message_subject') ?: 'New message from Cosmick Media';

$active = 'settings';
include __DIR__.'/header.php';
?>

    <h4>Settings</h4>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Settings saved successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Google Drive Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Service Account JSON</label>
                            <input class="form-control" type="file" name="sa_json" accept=".json">
                            <div class="form-text">Upload Google service account credentials file</div>
                        </div>
                        <div class="mb-3">
                            <label for="drive_folder" class="form-label">Base Drive Folder ID</label>
                            <input type="text" name="drive_folder" id="drive_folder" class="form-control"
                                   value="<?php echo htmlspecialchars($drive_folder); ?>">
                            <div class="form-text">The Google Drive folder ID where store folders will be created</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Email Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notify_email" class="form-label">Admin Notification Email(s)</label>
                            <input type="text" name="notify_email" id="notify_email" class="form-control"
                                   value="<?php echo htmlspecialchars($notify_email); ?>">
                            <div class="form-text">Comma-separated emails for upload notifications</div>
                        </div>
                        <div class="mb-3">
                            <label for="email_from_name" class="form-label">From Name</label>
                            <input type="text" name="email_from_name" id="email_from_name" class="form-control"
                                   value="<?php echo htmlspecialchars($email_from_name); ?>">
                            <div class="form-text">Name shown in email "From" field</div>
                        </div>
                        <div class="mb-3">
                            <label for="email_from_address" class="form-label">From Email Address</label>
                            <input type="email" name="email_from_address" id="email_from_address" class="form-control"
                                   value="<?php echo htmlspecialchars($email_from_address); ?>">
                            <div class="form-text">Email address used for sending</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Email Subject Lines</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="admin_notification_subject" class="form-label">Admin Notification Subject (New Uploads)</label>
                    <input type="text" name="admin_notification_subject" id="admin_notification_subject" class="form-control"
                           value="<?php echo htmlspecialchars($admin_notification_subject); ?>">
                    <div class="form-text">Subject for emails sent to admin when new content is uploaded. Use {store_name} as placeholder.</div>
                </div>
                <div class="mb-3">
                    <label for="store_notification_subject" class="form-label">Store Confirmation Subject (Upload Confirmation)</label>
                    <input type="text" name="store_notification_subject" id="store_notification_subject" class="form-control"
                           value="<?php echo htmlspecialchars($store_notification_subject); ?>">
                    <div class="form-text">Subject for confirmation emails sent to stores after upload. Use {store_name} as placeholder.</div>
                </div>
                <div class="mb-3">
                    <label for="store_message_subject" class="form-label">Store Message Subject (Admin Messages)</label>
                    <input type="text" name="store_message_subject" id="store_message_subject" class="form-control"
                           value="<?php echo htmlspecialchars($store_message_subject); ?>">
                    <div class="form-text">Subject for emails sent to stores when admin posts a message. Use {store_name} as placeholder.</div>
                </div>
            </div>
        </div>

        <button class="btn btn-primary" type="submit">Save Settings</button>
    </form>

<?php include __DIR__.'/footer.php'; ?>