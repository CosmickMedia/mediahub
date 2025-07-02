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
        include __DIR__.'/header.php';
        ?>
        <h3>Enter Store PIN</h3>
        <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?php echo $e; ?></div>
        <?php endforeach; ?>
        <form method="post">
            <div class="mb-3">
                <label for="pin" class="form-label">Store PIN</label>
                <input type="text" name="pin" id="pin" class="form-control" required>
            </div>
            <button class="btn btn-primary" type="submit">Continue</button>
        </form>
        <?php
        include __DIR__.'/footer.php';
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
include __DIR__.'/header.php';
?>
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
<?php include __DIR__.'/footer.php'; ?>

