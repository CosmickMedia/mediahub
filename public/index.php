<?php
// Store uploader main page
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/drive.php';

$config = require __DIR__.'/../config.php';

session_start();
$errors = [];

if (isset($_GET['logout'])) {
    unset($_SESSION['store_id'], $_SESSION['store_pin']);
}

if (!isset($_SESSION['store_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
        $pin = $_POST['pin'];
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT * FROM stores WHERE pin=?');
        $stmt->execute([$pin]);
        if ($store = $stmt->fetch()) {
            $_SESSION['store_id'] = $store['id'];
            $_SESSION['store_pin'] = $pin;
        } else {
            $errors[] = 'Invalid PIN';
        }
    }
    if (!isset($_SESSION['store_id'])) {
        // show PIN form
        echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/material/bootstrap.min.css" rel="stylesheet">';
        echo '</head><body>';
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary">';
        echo '<div class="container-fluid">';
        echo '<a class="navbar-brand" href="#">Store Upload</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPublic" aria-controls="navbarPublic" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span></button>';
        echo '<div class="collapse navbar-collapse" id="navbarPublic"></div></div></nav>';
        echo '<div class="container mt-4"><h3>Enter Store PIN</h3>';
        foreach ($errors as $e) echo "<div class=\"alert alert-danger\">$e</div>";
        echo '<form method="post">';
        echo '<div class="mb-3"><label for="pin" class="form-label">Store PIN</label><input type="text" name="pin" id="pin" class="form-control" required></div>';
        echo '<button class="btn btn-primary" type="submit">Continue</button></form></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>';
        exit;
    }
}

$store_id = $_SESSION['store_id'];
$store_pin = $_SESSION['store_pin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $uploads = [];
    foreach ($_FILES['files']['tmp_name'] as $idx => $tmp) {
        if (!is_uploaded_file($tmp)) continue;
        $name = $_FILES['files']['name'][$idx];
        $mime = mime_content_type($tmp);
        $size = $_FILES['files']['size'][$idx];
        if ($size > 20*1024*1024) { // limit 20MB
            $errors[] = "$name too large";
            continue;
        }
        $tmpPath = sys_get_temp_dir().'/'.uniqid();
        move_uploaded_file($tmp, $tmpPath);
        $driveId = drive_upload($tmpPath, $mime, $name, $config['drive_base_folder']);
        unlink($tmpPath);
        $desc = $_POST['desc'][$idx] ?? '';
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO uploads (store_id, filename, description, created_at, ip, mime, size, drive_id) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)');
        $stmt->execute([$store_id, $name, $desc, $_SERVER['REMOTE_ADDR'], $mime, $size, $driveId]);
        $uploads[] = $name;
    }
    if ($uploads) {
        echo '<script>alert("Upload successful");</script>';
    }
}

// show upload form
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/material/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Store Upload</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPublic2" aria-controls="navbarPublic2" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarPublic2">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="?logout=1">Change Store</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container mt-4">
<h4>Upload Files for Store <?php echo htmlspecialchars($store_pin); ?></h4>
<?php foreach ($errors as $e) echo "<div class=\"alert alert-danger\">$e</div>"; ?>
<form method="post" enctype="multipart/form-data" id="uploadForm">
    <div class="mb-3">
        <label for="files" class="form-label">Files</label>
        <input class="form-control" type="file" name="files[]" id="files" multiple accept="image/*,video/*" capture="environment" required>
    </div>
    <div id="descriptions"></div>
    <button class="btn btn-primary" type="submit">Upload</button>
</form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const fileInput = document.querySelector('input[type=file]');
fileInput.addEventListener('change', () => {
  const container = document.getElementById('descriptions');
  container.innerHTML = '';
  [...fileInput.files].forEach((f, i) => {
    const div = document.createElement('div');
    div.className = 'mb-3';
    div.innerHTML = `<label class=\"form-label\" for=\"desc${i}\">Description for ${f.name}</label><input type=\"text\" name=\"desc[${i}]\" id=\"desc${i}\" class=\"form-control\">`;
    container.appendChild(div);
  });
});
</script>
</body>
</html>
