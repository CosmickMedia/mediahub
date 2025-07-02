<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

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
    if (isset($_FILES['sa_json']) && is_uploaded_file($_FILES['sa_json']['tmp_name'])) {
        $target = __DIR__.'/../service-account.json';
        move_uploaded_file($_FILES['sa_json']['tmp_name'], $target);
    }
    set_setting('drive_base_folder', $_POST['drive_folder']);
    set_setting('notification_email', $_POST['notify_email']);
}
$drive_folder = get_setting('drive_base_folder');
$notify_email = get_setting('notification_email');
$active = 'settings';
include __DIR__.'/header.php';
?>
<h4>Settings</h4>
<form method="post" enctype="multipart/form-data">
<div class="mb-3">
  <label class="form-label">Service Account JSON</label>
  <input class="form-control" type="file" name="sa_json">
</div>
<div class="mb-3">
  <label for="drive_folder" class="form-label">Base Drive Folder ID</label>
  <input type="text" name="drive_folder" id="drive_folder" class="form-control" value="<?php echo htmlspecialchars($drive_folder); ?>">
</div>
<div class="mb-3">
  <label for="notify_email" class="form-label">Notification Email</label>
  <input type="text" name="notify_email" id="notify_email" class="form-control" value="<?php echo htmlspecialchars($notify_email); ?>">
</div>
<button class="btn btn-primary" type="submit">Save</button>
</form>
<?php include __DIR__.'/footer.php'; ?>

