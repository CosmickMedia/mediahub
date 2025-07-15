<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_login();
$pdo = get_pdo();

$success = false;
$test_result = null;

// Fetch upload statuses
$statuses = $pdo->query('SELECT id, name, color FROM upload_statuses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

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
        'groundhogg_contact_tags' => trim($_POST['groundhogg_contact_tags'] ?? '')
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

    if (isset($_POST['test_groundhogg'])) {
        [$ok, $msg] = test_groundhogg_connection();
        $test_result = [$ok, $msg];
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
$admin_article_notification_subject = get_setting('admin_article_notification_subject') ?: 'New article submission from {store_name}';
$store_article_notification_subject = get_setting('store_article_notification_subject') ?: 'Article Submission Confirmation - Cosmick Media';
$article_approval_subject = get_setting('article_approval_subject') ?: 'Article Status Update - Cosmick Media';
$max_article_length = get_setting('max_article_length') ?: '50000';
$groundhogg_site_url = get_setting('groundhogg_site_url');
$groundhogg_username = get_setting('groundhogg_username');
$groundhogg_public_key = get_setting('groundhogg_public_key');
$groundhogg_token = get_setting('groundhogg_token');
$groundhogg_secret_key = get_setting('groundhogg_secret_key');
$groundhogg_debug = get_setting('groundhogg_debug');
$groundhogg_contact_tags = get_setting('groundhogg_contact_tags');

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
<?php if ($test_result !== null): ?>
    <?php if ($test_result[0]): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">Dripley connection successful!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php else: ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">Dripley connection failed: <?php echo htmlspecialchars($test_result[1]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dripley-tab" data-bs-toggle="tab" data-bs-target="#dripley" type="button" role="tab">Dripley CRM</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab">Email Subjects</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="statuses-tab" data-bs-toggle="tab" data-bs-target="#statuses" type="button" role="tab">Upload Statuses</button>
            </li>
        </ul>
        <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
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
                                    <input type="text" name="drive_folder" id="drive_folder" class="form-control" value="<?php echo htmlspecialchars($drive_folder); ?>">
                                    <div class="form-text">The Google Drive folder ID where store folders will be created</div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Article Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="max_article_length" class="form-label">Maximum Article Length (characters)</label>
                                    <input type="number" name="max_article_length" id="max_article_length" class="form-control" value="<?php echo htmlspecialchars($max_article_length); ?>">
                                    <div class="form-text">Maximum character count allowed for article submissions</div>
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
                                    <input type="text" name="notify_email" id="notify_email" class="form-control" value="<?php echo htmlspecialchars($notify_email); ?>">
                                    <div class="form-text">Comma-separated emails for upload and article notifications</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email_from_name" class="form-label">From Name</label>
                                    <input type="text" name="email_from_name" id="email_from_name" class="form-control" value="<?php echo htmlspecialchars($email_from_name); ?>">
                                    <div class="form-text">Name shown in email "From" field</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email_from_address" class="form-label">From Email Address</label>
                                    <input type="email" name="email_from_address" id="email_from_address" class="form-control" value="<?php echo htmlspecialchars($email_from_address); ?>">
                                    <div class="form-text">Email address used for sending</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="dripley" role="tabpanel" aria-labelledby="dripley-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Dripley CRM Integration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="groundhogg_site_url" class="form-label">Dripley Site URL</label>
                            <input type="text" name="groundhogg_site_url" id="groundhogg_site_url" class="form-control" value="<?php echo htmlspecialchars($groundhogg_site_url); ?>" placeholder="https://www.cosmickmedia.com">
                        </div>
                        <div class="mb-3">
                            <label for="groundhogg_username" class="form-label">Dripley API Username</label>
                            <input type="text" name="groundhogg_username" id="groundhogg_username" class="form-control" value="<?php echo htmlspecialchars($groundhogg_username); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="groundhogg_public_key" class="form-label">Public Key</label>
                            <input type="text" name="groundhogg_public_key" id="groundhogg_public_key" class="form-control" value="<?php echo htmlspecialchars($groundhogg_public_key); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="groundhogg_token" class="form-label">Token</label>
                            <input type="text" name="groundhogg_token" id="groundhogg_token" class="form-control" value="<?php echo htmlspecialchars($groundhogg_token); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="groundhogg_secret_key" class="form-label">Secret Key</label>
                            <input type="text" name="groundhogg_secret_key" id="groundhogg_secret_key" class="form-control" value="<?php echo htmlspecialchars($groundhogg_secret_key); ?>">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="groundhogg_debug" id="groundhogg_debug" class="form-check-input" value="1" <?php if ($groundhogg_debug === '1') echo 'checked'; ?>>
                            <label for="groundhogg_debug" class="form-check-label">Enable Debug Logging</label>
                            <div class="form-text">Logs API communication to <code>logs/groundhogg.log</code></div>
                        </div>
                        <div class="mb-3">
                            <label for="groundhogg_contact_tags" class="form-label">Default Contact Tags</label>
                            <input type="text" name="groundhogg_contact_tags" id="groundhogg_contact_tags" class="form-control" value="<?php echo htmlspecialchars($groundhogg_contact_tags); ?>">
                            <div class="form-text">Comma-separated tags applied to new contacts</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-secondary" type="submit" name="test_groundhogg">Test Connection</button>
                            <a href="sync_groundhogg.php" class="btn btn-secondary">Sync Contacts</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="subjects" role="tabpanel" aria-labelledby="subjects-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Email Subject Lines - Uploads</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="admin_notification_subject" class="form-label">Admin Notification Subject (New Uploads)</label>
                            <input type="text" name="admin_notification_subject" id="admin_notification_subject" class="form-control" value="<?php echo htmlspecialchars($admin_notification_subject); ?>">
                            <div class="form-text">Subject for emails sent to admin when new content is uploaded. Use {store_name} as placeholder.</div>
                        </div>
                        <div class="mb-3">
                            <label for="store_notification_subject" class="form-label">Store Confirmation Subject (Upload Confirmation)</label>
                            <input type="text" name="store_notification_subject" id="store_notification_subject" class="form-control" value="<?php echo htmlspecialchars($store_notification_subject); ?>">
                            <div class="form-text">Subject for confirmation emails sent to stores after upload. Use {store_name} as placeholder.</div>
                        </div>
                        <div class="mb-3">
                            <label for="store_message_subject" class="form-label">Store Message Subject (Admin Messages)</label>
                            <input type="text" name="store_message_subject" id="store_message_subject" class="form-control" value="<?php echo htmlspecialchars($store_message_subject); ?>">
                            <div class="form-text">Subject for emails sent to stores when admin posts a message. Use {store_name} as placeholder.</div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Email Subject Lines - Articles</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="admin_article_notification_subject" class="form-label">Admin Notification Subject (New Articles)</label>
                            <input type="text" name="admin_article_notification_subject" id="admin_article_notification_subject" class="form-control" value="<?php echo htmlspecialchars($admin_article_notification_subject); ?>">
                            <div class="form-text">Subject for emails sent to admin when new article is submitted. Use {store_name} as placeholder.</div>
                        </div>
                        <div class="mb-3">
                            <label for="store_article_notification_subject" class="form-label">Store Confirmation Subject (Article Submission)</label>
                            <input type="text" name="store_article_notification_subject" id="store_article_notification_subject" class="form-control" value="<?php echo htmlspecialchars($store_article_notification_subject); ?>">
                            <div class="form-text">Subject for confirmation emails sent to stores after article submission. Use {store_name} as placeholder.</div>
                        </div>
                        <div class="mb-3">
                            <label for="article_approval_subject" class="form-label">Article Status Update Subject</label>
                            <input type="text" name="article_approval_subject" id="article_approval_subject" class="form-control" value="<?php echo htmlspecialchars($article_approval_subject); ?>">
                            <div class="form-text">Subject for emails sent when article status is updated. Use {store_name} as placeholder.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="statuses" role="tabpanel" aria-labelledby="statuses-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upload Statuses</h5>
                    </div>
                    <div class="card-body">
                        <table class="table" id="statusTable">
                            <thead>
                            <tr><th>Name</th><th>Color</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($statuses as $st): ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="status_id[]" value="<?php echo $st['id']; ?>">
                                        <input type="text" name="status_name[]" class="form-control" value="<?php echo htmlspecialchars($st['name']); ?>">
                                    </td>
                                    <td><input type="color" name="status_color[]" class="form-control form-control-color" value="<?php echo htmlspecialchars($st['color']); ?>"></td>
                                    <td><button type="button" class="btn btn-sm btn-danger remove-status">Delete</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-secondary" id="addStatus">Add Status</button>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn btn-primary" type="submit">Save Settings</button>
    </form>

    <script>
        document.getElementById('addStatus').addEventListener('click', function () {
            const tbody = document.querySelector('#statusTable tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <input type="hidden" name="status_id[]" value="">
                    <input type="text" name="status_name[]" class="form-control">
                </td>
                <td><input type="color" name="status_color[]" class="form-control form-control-color" value="#000000"></td>
                <td><button type="button" class="btn btn-sm btn-danger remove-status">Delete</button></td>
            `;
            tbody.appendChild(row);
        });
        document.addEventListener('click', function(e){
            if(e.target.classList.contains('remove-status')){
                e.target.closest('tr').remove();
            }
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>