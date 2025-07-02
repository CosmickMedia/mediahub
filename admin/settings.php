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
<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/material/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="stores.php">Stores</a></li>
        <li class="nav-item"><a class="nav-link" href="uploads.php">Uploads</a></li>
        <li class="nav-item"><a class="nav-link active" href="settings.php">Settings</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
