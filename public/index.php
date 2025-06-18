<?php
// Store uploader main page
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/drive.php';

$config = require __DIR__.'/../config.php';

session_start();
$errors = [];

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
        echo '<!doctype html><html><head><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"></head><body class="container"><h3>Enter Store PIN</h3>';
        foreach ($errors as $e) echo "<p class=red-text>$e</p>";
        echo '<form method="post"><div class="input-field"><input type="text" name="pin" id="pin" required><label for="pin">Store PIN</label></div><button class="btn" type="submit">Continue</button></form>';
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script></body></html>';
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
</head>
<body class="container">
<h4>Upload Files for Store <?php echo htmlspecialchars($store_pin); ?></h4>
<?php foreach ($errors as $e) echo "<p class=red-text>$e</p>"; ?>
<form method="post" enctype="multipart/form-data" id="uploadForm">
    <div class="file-field input-field">
        <div class="btn"><span>Files</span><input type="file" name="files[]" multiple accept="image/*,video/*" capture="environment" required></div>
        <div class="file-path-wrapper"><input class="file-path validate" type="text" placeholder="Upload one or more files"></div>
    </div>
    <div id="descriptions"></div>
    <button class="btn" type="submit">Upload</button>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
const fileInput = document.querySelector('input[type=file]');
fileInput.addEventListener('change', () => {
  const container = document.getElementById('descriptions');
  container.innerHTML = '';
  [...fileInput.files].forEach((f, i) => {
    const div = document.createElement('div');
    div.className = 'input-field';
    div.innerHTML = `<input type="text" name="desc[${i}]" id="desc${i}"><label for="desc${i}">Description for ${f.name}</label>`;
    container.appendChild(div);
  });
});
</script>
</body>
</html>
