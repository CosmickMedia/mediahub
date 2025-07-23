<?php
require_once __DIR__.'/lib/db.php';
require_once __DIR__.'/lib/helpers.php';
require_once __DIR__.'/lib/auth.php';
require_once __DIR__.'/lib/drive.php';

$config = get_config();
$localUploadDir = $config['local_upload_dir'] ?? (__DIR__ . '/public/uploads');

ensure_session();
$pdo = get_pdo();

$cols = $pdo->query("SHOW COLUMNS FROM uploads")->fetchAll(PDO::FETCH_COLUMN);
$hasLocalPath = in_array('local_path', $cols, true);
$hasThumbPath = in_array('thumb_path', $cols, true);

$isAdmin = isset($_SESSION['user_id']);
$store_id = 0;
if ($isAdmin) {
    require_login();
    $store_id = intval($_POST['store_id'] ?? 0);
} else {
    $store_id = intval($_SESSION['store_id'] ?? 0);
}
if ($store_id <= 0 || !isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$parent_id = intval($_POST['parent_id'] ?? 0) ?: null;
$tmp  = $_FILES['file']['tmp_name'];
$orig = $_FILES['file']['name'];
$size = $_FILES['file']['size'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $tmp);
finfo_close($finfo);

try {
    $folderId = get_or_create_store_folder($store_id);

    $subDir = $store_id . '/' . date('Y/m');
    $targetDir = rtrim($localUploadDir, '/\\') . '/' . $subDir;
    $thumbDir  = $targetDir . '/thumbs';
    if (!is_dir($thumbDir) && !mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
        throw new Exception('Failed to create upload directory');
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($orig));
    $localPath = $targetDir . '/' . $safeName;
    if (!move_uploaded_file($tmp, $localPath)) {
        throw new Exception('Failed to store file locally');
    }

    $thumbPath = $thumbDir . '/' . $safeName;
    $thumbUrl = null;
    if ($hasThumbPath && create_local_thumbnail($localPath, $thumbPath, $mime)) {
        $thumbUrl = 'uploads/' . $subDir . '/thumbs/' . $safeName;
    }

    $driveId = drive_upload($localPath, $mime, $orig, $folderId);

    $fields = ['store_id', 'filename', 'created_at', 'ip', 'mime', 'size', 'drive_id'];
    $placeholders = '?, ?, NOW(), ?, ?, ?, ?';
    $values = [$store_id, $orig, $_SERVER['REMOTE_ADDR'] ?? '', $mime, $size, $driveId];

    if ($hasLocalPath) {
        $fields[] = 'local_path';
        $placeholders .= ', ?';
        $values[] = 'uploads/' . $subDir . '/' . $safeName;
    }

    if ($hasThumbPath) {
        $fields[] = 'thumb_path';
        $placeholders .= ', ?';
        $values[] = $thumbUrl;
    }

    $sql = 'INSERT INTO uploads (' . implode(',', $fields) . ') VALUES (' . $placeholders . ')';
    $ins = $pdo->prepare($sql);
    $ins->execute($values);
    $upload_id = $pdo->lastInsertId();

    $sender = $isAdmin ? 'admin' : 'store';
    $read_admin = $isAdmin ? 1 : 0;
    $read_store = $isAdmin ? 0 : 1;
    $storeUserId = $isAdmin ? null : ($_SESSION['store_user_id'] ?? null);
    $msg = sanitize_message($_POST['message'] ?? $orig);
    $stmt = $pdo->prepare('INSERT INTO store_messages (store_id, store_user_id, sender, message, parent_id, upload_id, created_at, read_by_admin, read_by_store) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
    $stmt->execute([$store_id, $storeUserId, $sender, $msg, $parent_id, $upload_id, $read_admin, $read_store]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function create_local_thumbnail(string $src, string $dest, string $mime): bool {
    $max = 400;
    if (strpos($mime, 'image/') === 0) {
        $img = @imagecreatefromstring(file_get_contents($src));
        if (!$img) return false;
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min($max / $w, $max / $h, 1);
        $tw = (int)($w * $scale);
        $th = (int)($h * $scale);
        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagejpeg($thumb, $dest, 80);
        imagedestroy($img);
        imagedestroy($thumb);
        return true;
    }
    if (strpos($mime, 'video/') === 0) {
        $cmd = 'ffmpeg -y -i ' . escapeshellarg($src) . ' -ss 00:00:01 -frames:v 1 -vf scale=' . $max . ':-1 ' . escapeshellarg($dest) . ' 2>/dev/null';
        exec($cmd);
        return file_exists($dest);
    }
    return false;
}

