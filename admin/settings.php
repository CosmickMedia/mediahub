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
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
</head>
<body class="container">
<h4>Settings</h4>
<form method="post" enctype="multipart/form-data">
<div class="file-field input-field">
<div class="btn"><span>Service Account JSON</span><input type="file" name="sa_json"></div>
<div class="file-path-wrapper"><input class="file-path validate" type="text" placeholder="Upload new key"></div>
</div>
<div class="input-field"><input type="text" name="drive_folder" id="drive_folder" value="<?php echo htmlspecialchars($drive_folder); ?>"><label for="drive_folder">Base Drive Folder ID</label></div>
<div class="input-field"><input type="text" name="notify_email" id="notify_email" value="<?php echo htmlspecialchars($notify_email); ?>"><label for="notify_email">Notification Email</label></div>
<button class="btn" type="submit">Save</button>
</form>
</body>
</html>
