<?php
// Store uploader main page
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/drive.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/auth.php';

$config = get_config();
$localUploadDir = $config['local_upload_dir'] ?? (__DIR__ . '/uploads');

ensure_session();

$errors = [];
$success = [];

// Handle logout legacy parameter
if (isset($_GET['logout'])) {
    header('Location: logout.php');
    exit;
}

// Check if store is logged in - be very explicit about this check
$isLoggedIn = isset($_SESSION['store_id']) &&
    !empty($_SESSION['store_id']) &&
    isset($_SESSION['store_pin']) &&
    !empty($_SESSION['store_pin']);

if (!$isLoggedIn) {
    // Handle PIN and email submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'], $_POST['email'])) {
        $pin = trim($_POST['pin']);
        $email = trim($_POST['email']);
        if ($pin !== '' && $email !== '') {
            $pdo = get_pdo();
            // Check if last_seen_version column exists
            $hasVersionCol = false;
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM store_users LIKE 'last_seen_version'");
                $hasVersionCol = $colCheck->fetch() !== false;
            } catch (Exception $e) {}

            if ($hasVersionCol) {
                $stmt = $pdo->prepare('SELECT s.*, u.id AS uid, u.first_name AS ufname, u.last_name AS ulname, u.last_seen_version AS ulast_seen FROM stores s JOIN store_users u ON u.store_id = s.id WHERE s.pin = ? AND u.email = ?');
            } else {
                $stmt = $pdo->prepare('SELECT s.*, u.id AS uid, u.first_name AS ufname, u.last_name AS ulname FROM stores s JOIN store_users u ON u.store_id = s.id WHERE s.pin = ? AND u.email = ?');
            }
            $stmt->execute([$pin, $email]);
            if ($store = $stmt->fetch()) {
                session_regenerate_id(true);
                $_SESSION['store_id'] = $store['id'];
                $_SESSION['store_pin'] = $pin;
                $_SESSION['store_name'] = $store['name'];
                $_SESSION['store_user_email'] = $email;
                $_SESSION['store_user_id'] = $store['uid'] ?? null;
                $_SESSION['store_first_name'] = $store['ufname'] ?? '';
                $_SESSION['store_last_name'] = $store['ulname'] ?? '';
                $_SESSION['last_seen_version'] = $store['ulast_seen'] ?? '0.0.0';

                if (!empty($_POST['remember'])) {
                    $rememberLifetime = 60 * 60 * 24 * 30;
                    setcookie('cm_public_remember', '1', time() + $rememberLifetime, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                    setcookie(session_name(), session_id(), time() + $rememberLifetime, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                }

                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Invalid PIN or email';
            }
        } else {
            $errors[] = 'Please enter a PIN and email';
        }
    }

    // Show PIN login form
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="MediaHub">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="MediaHub">
        <meta name="format-detection" content="telephone=no">
        <meta name="theme-color" content="#667eea">
        <title>MediaHub CosmicSk</title>
        <meta name="robots" content="noindex, nofollow">
        <!-- Bootstrap CSS from CDN -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
        <!-- Montserrat Font -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/common.css">
        <link rel="stylesheet" href="inc/css/style.css">

        <!-- Favicons -->
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">

        <!-- Apple Touch Icons for iOS -->
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

        <!-- Android Chrome Icons -->
        <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/icon-512.png">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/public/manifest.json">

        <!-- Additional Meta Tags for Windows Tiles -->
        <meta name="msapplication-TileColor" content="#667eea">
        <meta name="msapplication-TileImage" content="/icon-192.png">

        <!-- Service Worker Registration for PWA -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/public/service-worker.js')
                        .then(registration => {
                            console.log('Service Worker registered successfully:', registration.scope);
                        })
                        .catch(error => {
                            console.log('Service Worker registration failed:', error);
                        });
                });
            }
        </script>
    </head>
    <body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="text-center mb-4">
                    <img src="/assets/images/mediahub-logo.png" alt="MediaHub" class="login-logo">
                </div>
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4 store-pin-title">Store Login</h3>
                        <?php foreach ($errors as $e): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
                        <?php endforeach; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control form-control-lg" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="pin" class="form-label">Store PIN</label>
                                <input type="text" name="pin" id="pin" class="form-control form-control-lg" required>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                                <label class="form-check-label" for="remember" style="margin-left: 0.25rem;">Remember me</label>
                            </div>
                            <button class="btn btn-login btn-lg w-100" type="submit">Login</button>
                        </form>
                        <div class="text-center admin-link">
                            <a href="/admin" class="text-muted text-decoration-none">Admin Portal</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    </body>
    </html>
    <?php
    exit;
}

// If we get here, store is logged in
$store_id = $_SESSION['store_id'];
$store_pin = $_SESSION['store_pin'];
$store_name = $_SESSION['store_name'] ?? 'Store';

// Get store info for email
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch();

// Verify store still exists
if (!$store) {
    // Store was deleted, log them out
    unset($_SESSION['store_id']);
    unset($_SESSION['store_pin']);
    unset($_SESSION['store_name']);
    header('Location: index.php');
    exit;
}

// Generate an upload token for this session if not present
if (empty($_SESSION['upload_token'])) {
    $_SESSION['upload_token'] = bin2hex(random_bytes(16));
}
$upload_token = $_SESSION['upload_token'];

// Handle token refresh requests (AJAX endpoint)
if (isset($_GET['refresh_token']) && $_GET['refresh_token'] === '1') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'token' => $_SESSION['upload_token']]);
    exit;
}

// Get store messages - handle missing columns gracefully
$messages = [];
$replies = [];
$latest_broadcast = null;
$latest_chat = null;
$recent_chats = [];

// Check if is_reply column exists
$checkColumn = $pdo->query("SHOW COLUMNS FROM store_messages LIKE 'is_reply'");
$hasReplyColumn = $checkColumn->fetch() !== false;

if ($hasReplyColumn) {
    // Column exists, get latest broadcast and chats
    $stmt = $pdo->prepare(
        "SELECT * FROM store_messages WHERE store_id IS NULL AND sender='admin' ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute();
    $latest_broadcast = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT * FROM store_messages WHERE store_id = ? AND sender='admin' AND (is_reply = 0 OR is_reply IS NULL) ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$store_id]);
    $latest_chat = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT id, sender, message, created_at, like_by_store, like_by_admin, love_by_store, love_by_admin FROM store_messages WHERE store_id = ? ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$store_id]);
    $recent_chats = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Get reply messages
    $stmt = $pdo->prepare(
        "SELECT m.*, u.filename FROM store_messages m LEFT JOIN uploads u ON m.upload_id = u.id WHERE m.store_id = ? AND m.is_reply = 1 ORDER BY m.created_at DESC"
    );
    $stmt->execute([$store_id]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $last_reply_id = empty($replies) ? 0 : max(array_column($replies, 'id'));
} else {
    // Column doesn't exist, use simple query
    $stmt = $pdo->prepare('
        SELECT * FROM store_messages
        WHERE (store_id = ? OR store_id IS NULL)
        ORDER BY created_at DESC
    ');
    $stmt->execute([$store_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $last_reply_id = 0;
}

// Get statistics for dashboard
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_uploads,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_uploads,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today_uploads,
        SUM(size) as total_size,
        COUNT(CASE WHEN mime LIKE 'image/%' THEN 1 END) as total_images,
        COUNT(CASE WHEN mime LIKE 'video/%' THEN 1 END) as total_videos
    FROM uploads 
    WHERE store_id = ?
");
$stats_stmt->execute([$store_id]);
$upload_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent uploads for activity feed
$recent_uploads_stmt = $pdo->prepare("
    SELECT filename, mime, size, created_at, drive_id 
    FROM uploads 
    WHERE store_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_uploads_stmt->execute([$store_id]);
$recent_uploads = $recent_uploads_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get article count for badge
$stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE store_id = ?');
$stmt->execute([$store_id]);
$article_count = $stmt->fetchColumn();

// Get upcoming posts count if calendar data exists
$upcoming_posts = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM calendar_posts 
        WHERE store_id = ? 
        AND (scheduled_send_time >= NOW() OR scheduled_time >= NOW())
    ");
    $stmt->execute([$store_id]);
    $upcoming_posts = $stmt->fetchColumn();
} catch (Exception $e) {
    // Table might not exist
}

// Get unread messages count
$unread_count = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM store_messages WHERE store_id = ? AND sender = 'admin' AND read_by_store = 0");
$stmt->execute([$store_id]);
$unread_count = $stmt->fetchColumn();

$adminRow = $pdo->query('SELECT first_name, last_name FROM users ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$admin_name = trim(($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? ''));
$your_name = trim(($_SESSION['store_first_name'] ?? '') . ' ' . ($_SESSION['store_last_name'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $tokenValid = isset($_SESSION['upload_token']) && isset($_POST['upload_token']) &&
        hash_equals($_SESSION['upload_token'], $_POST['upload_token']);

    if (!$tokenValid) {
        $errors[] = 'Invalid or duplicate submission detected.';
    } else {
        // Regenerate token for next upload immediately after consuming
        $_SESSION['upload_token'] = bin2hex(random_bytes(16));
        $upload_token = $_SESSION['upload_token'];

        try {
        // Get or create store folder
        $storeFolderId = get_or_create_store_folder($store_id);

        $uploadCount = 0;
        $totalFiles = count($_FILES['files']['tmp_name']);
        $customMessage = $_POST['custom_message'] ?? '';
        $uploadedFiles = [];
        $processedHashes = []; // Track processed files to prevent duplicates

        // Detect available optional columns
        $cols = $pdo->query("SHOW COLUMNS FROM uploads")->fetchAll(PDO::FETCH_COLUMN);
        $hasCustomMessage = in_array('custom_message', $cols, true);
        $hasLocalPath = in_array('local_path', $cols, true);
        $hasThumbPath = in_array('thumb_path', $cols, true);

        for ($i = 0; $i < $totalFiles; $i++) {
            if (!is_uploaded_file($_FILES['files']['tmp_name'][$i])) {
                continue;
            }

            $tmpFile = $_FILES['files']['tmp_name'][$i];
            $originalName = $_FILES['files']['name'][$i];
            $fileSize = $_FILES['files']['size'][$i];
            $fileError = $_FILES['files']['error'][$i];

            // Create hash of file content to detect duplicates
            $fileHash = md5_file($tmpFile);
            if (in_array($fileHash, $processedHashes)) {
                continue; // Skip duplicate file
            }
            $processedHashes[] = $fileHash;

            // Check for upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading $originalName: " . getUploadErrorMessage($fileError);
                continue;
            }

            // Check file size (200MB limit)
            if ($fileSize > 200 * 1024 * 1024) {
                $errors[] = "$originalName is too large (max 200MB)";
                continue;
            }

            // Get MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            // Check if it's an image or video
            if (!preg_match('/^(image|video)\//', $mimeType)) {
                $errors[] = "$originalName is not an image or video";
                continue;
            }

            try {
                // Build local storage paths
                $subDir = $store_id . '/' . date('Y/m');
                $targetDir = rtrim($localUploadDir, '/\\') . '/' . $subDir;
                $thumbDir = $targetDir . '/thumbs';
                if (!is_dir($thumbDir) && !mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
                    throw new Exception('Failed to create upload directory');
                }

                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
                $localPath = $targetDir . '/' . $safeName;
                if (!move_uploaded_file($tmpFile, $localPath)) {
                    throw new Exception('Failed to store file locally');
                }

                $thumbPath = $thumbDir . '/' . $safeName;
                $thumbUrl = null;
                if (create_local_thumbnail($localPath, $thumbPath, $mimeType)) {
                    $thumbUrl = 'uploads/' . $subDir . '/thumbs/' . $safeName;
                }

                // Upload to Google Drive using local file
                $driveId = drive_upload($localPath, $mimeType, $originalName, $storeFolderId);

                // Get description
                $description = $_POST['descriptions'][$i] ?? '';

                // Save to database with optional columns
                try {
                    $fields = ['store_id', 'filename', 'description'];
                    $placeholders = '?, ?, ?';
                    $values = [$store_id, $originalName, $description];

                    if ($hasCustomMessage) {
                        $fields[] = 'custom_message';
                        $placeholders .= ', ?';
                        $values[] = $customMessage;
                    }

                    $fields[] = 'created_at';
                    $placeholders .= ', NOW()';

                    $fields[] = 'ip';
                    $placeholders .= ', ?';
                    $values[] = $_SERVER['REMOTE_ADDR'];

                    $fields[] = 'mime';
                    $placeholders .= ', ?';
                    $values[] = $mimeType;

                    $fields[] = 'size';
                    $placeholders .= ', ?';
                    $values[] = $fileSize;

                    $fields[] = 'drive_id';
                    $placeholders .= ', ?';
                    $values[] = $driveId;

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
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);

                    $uploadCount++;
                    $uploadedFiles[] = $originalName;

                } catch (PDOException $e) {
                    error_log("Database insert error: " . $e->getMessage());
                    $errors[] = "Failed to save $originalName to database: " . $e->getMessage();
                }

            } catch (Exception $e) {
                $errors[] = "Failed to upload $originalName: " . $e->getMessage();
            }
        }

        if ($uploadCount > 0) {
            $success[] = "Successfully uploaded $uploadCount file(s)";

            // Get email settings
            $emailSettings = [];
            $settingsQuery = $pdo->query("SELECT name, value FROM settings WHERE name IN ('notification_email', 'upload_notification_email', 'email_from_name', 'email_from_address', 'admin_notification_subject', 'store_notification_subject', 'enable_upload_admin_notification', 'enable_upload_store_confirmation')");
            while ($row = $settingsQuery->fetch()) {
                $emailSettings[$row['name']] = $row['value'];
            }

            $fromName = $emailSettings['email_from_name'] ?? 'Cosmick Media';
            $fromAddress = $emailSettings['email_from_address'] ?? 'noreply@cosmickmedia.com';
            $adminSubject = $emailSettings['admin_notification_subject'] ?? "New uploads from {store_name}";
            $storeSubject = $emailSettings['store_notification_subject'] ?? "Content Submission Confirmation - Cosmick Media";

            // Replace placeholders
            $adminSubject = str_replace('{store_name}', $store_name, $adminSubject);
            $storeSubject = str_replace('{store_name}', $store_name, $storeSubject);

            $headers = "From: $fromName <$fromAddress>\r\n";
            $headers .= "Reply-To: $fromAddress\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            // Send notification emails to admin (use upload-specific email if set, otherwise fall back to general)
            $enableAdminNotification = ($emailSettings['enable_upload_admin_notification'] ?? '1') !== '0';
            $notifyEmails = !empty($emailSettings['upload_notification_email'])
                ? $emailSettings['upload_notification_email']
                : ($emailSettings['notification_email'] ?? '');
            if ($enableAdminNotification && $notifyEmails) {
                // Split by comma for multiple emails
                $emailList = array_map('trim', explode(',', $notifyEmails));

                $message = "$uploadCount new file(s) uploaded from store: $store_name\n\n";
                $message .= "Files uploaded:\n";
                foreach ($uploadedFiles as $file) {
                    $message .= "- $file\n";
                }
                if ($customMessage) {
                    $message .= "\nCustomer Message:\n$customMessage\n";
                }

                foreach ($emailList as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        mail($email, $adminSubject, $message, $headers);
                    }
                }
            }

            // Send confirmation email to store if configured
            $enableStoreConfirmation = ($emailSettings['enable_upload_store_confirmation'] ?? '1') !== '0';
            if ($enableStoreConfirmation && !empty($store['admin_email'])) {
                $confirmMessage = "Dear $store_name,\n\n";
                $confirmMessage .= "Thank you for your submission to the Cosmick Media Content Library.\n\n";
                $confirmMessage .= "We have successfully received the following files:\n";
                foreach ($uploadedFiles as $file) {
                    $confirmMessage .= "- $file\n";
                }
                $confirmMessage .= "\nYour content is now pending curation by our team.\n";
                $confirmMessage .= "We will review your submission and get back to you if we need any additional information.\n\n";
                $confirmMessage .= "Best regards,\n$fromName";

                mail($store['admin_email'], $storeSubject, $confirmMessage, $headers);
            }
        }

    } catch (Exception $e) {
        $errors[] = "Upload error: " . $e->getMessage();
    }
    // refresh stats after successful upload or failure
    $stats_stmt = $pdo->prepare(
        "SELECT COUNT(*) as total_uploads,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_uploads,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today_uploads,
                SUM(size) as total_size,
                COUNT(CASE WHEN mime LIKE 'image/%' THEN 1 END) as total_images,
                COUNT(CASE WHEN mime LIKE 'video/%' THEN 1 END) as total_videos
         FROM uploads WHERE store_id = ?"
    );
    $stats_stmt->execute([$store_id]);
    $upload_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
}
}



function getUploadErrorMessage($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File exceeds upload_max_filesize in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File exceeds MAX_FILE_SIZE in form';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

function create_local_thumbnail(string $src, string $dest, string $mime): bool {
    $max = 400;

    // Handle HEIC/HEIF conversion using macOS sips (only if exec() is available)
    if (in_array($mime, ['image/heic', 'image/heif', 'image/x-heic', 'image/x-heif']) ||
        preg_match('/\.(heic|heif)$/i', $src)) {
        if (function_exists('exec')) {
            $tempJpg = $src . '.temp.jpg';
            $cmd = '/usr/bin/sips -s format jpeg ' . escapeshellarg($src) . ' --out ' . escapeshellarg($tempJpg) . ' 2>/dev/null';
            @exec($cmd, $output, $returnCode);
            if ($returnCode === 0 && file_exists($tempJpg)) {
                $src = $tempJpg;
                $mime = 'image/jpeg';
                // Register cleanup function for temp file
                register_shutdown_function(function() use ($tempJpg) {
                    if (file_exists($tempJpg)) @unlink($tempJpg);
                });
            }
        }
        // If exec() not available or conversion failed, continue with original file
    }

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
        // Only attempt ffmpeg thumbnail if exec() is available
        if (function_exists('exec')) {
            $cmd = '/opt/homebrew/bin/ffmpeg -y -i ' . escapeshellarg($src) . ' -ss 00:00:01 -frames:v 1 -vf scale=' . $max . ':-1 ' . escapeshellarg($dest) . ' 2>/dev/null';
            @exec($cmd);
        }
        return file_exists($dest);
    }
    return false;
}

// show upload form
include __DIR__.'/header.php';
?>

    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($your_name ?: $store_name); ?>!</h1>
            <p class="welcome-subtitle">Your MediaHub Dashboard</p>
            <p class="welcome-time"><?php echo date('l, F j, Y'); ?></p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($latest_broadcast)): ?>
            <div class="alert alert-dismissible fade show alert-modern alert-broadcast" id="broadcastAlert" data-id="<?php echo $latest_broadcast['id']; ?>">
                <div class="alert-icon">
                    <i class="bi bi-megaphone-fill"></i>
                </div>
                <div class="alert-content">
                    <h5 class="alert-title">Admin Broadcast</h5>
                    <p class="alert-message"><?php echo nl2br(htmlspecialchars($latest_broadcast['message'])); ?></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($latest_chat)): ?>
            <div class="alert alert-dismissible fade show alert-modern alert-info" id="chatAlert" data-id="<?php echo $latest_chat['id']; ?>">
                <div class="alert-icon">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <div class="alert-content">
                    <p class="alert-message"><?php echo nl2br(htmlspecialchars($latest_chat['message'])); ?></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($replies)): ?>
            <div class="alert alert-dismissible fade show alert-modern alert-warning" id="replyAlert" data-id="<?php echo $last_reply_id; ?>">
                <div class="alert-icon">
                    <i class="bi bi-reply-fill"></i>
                </div>
                <div class="alert-content">
                    <h5 class="alert-title">Admin Feedback</h5>
                    <?php foreach ($replies as $reply): ?>
                        <div class="mb-2">
                            <strong>Re: <?php echo htmlspecialchars($reply['filename']); ?></strong><br>
                            <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                            <small class="text-muted d-block"><?php echo format_ts($reply['created_at']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($e); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?php foreach ($success as $s): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($s); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="bi bi-cloud-upload-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upload_stats['total_uploads']; ?>">0</div>
                <div class="stat-label">Total Uploads</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-calendar-week-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upload_stats['week_uploads']; ?>">0</div>
                <div class="stat-label">This Week</div>
                <?php if ($upload_stats['week_uploads'] > 0): ?>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up"></i> Active
                    </div>
                <?php endif; ?>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upload_stats['total_images']; ?>">0</div>
                <div class="stat-label">Images</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="bi bi-camera-video-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upload_stats['total_videos']; ?>">0</div>
                <div class="stat-label">Videos</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="bi bi-calendar-event-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upcoming_posts; ?>">0</div>
                <div class="stat-label">Scheduled Posts</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card secondary">
                <div class="stat-icon">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $unread_count; ?>">0</div>
                <div class="stat-label">Unread Messages</div>
                <?php if ($unread_count > 0): ?>
                    <div class="stat-change positive">
                        <i class="bi bi-circle-fill"></i> New
                    </div>
                <?php endif; ?>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <div class="left-column">
            <!-- Upload Section -->
            <div class="upload-section">
                <h3 class="section-title">
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                    Upload Content
                </h3>

                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-area" id="uploadArea">
                        <i class="bi bi-cloud-upload upload-icon"></i>
                        <p class="upload-text">Drag & drop files here or click to browse</p>
                        <p class="upload-subtext">Supports images and videos up to 200MB</p>

                        <div class="file-buttons">
                            <button type="button" class="btn-modern btn-modern-primary" onclick="document.getElementById('files').click();">
                                <i class="bi bi-folder2-open"></i> Browse Files
                            </button>
                            <button type="button" class="btn-modern btn-modern-secondary" onclick="document.getElementById('cameraInput').click();">
                                <i class="bi bi-camera"></i> Use Camera
                            </button>
                        </div>

                        <div style="margin-top: 15px; padding: 12px 15px; background-color: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px; font-size: 14px; line-height: 1.6;">
                            <i class="bi bi-info-circle" style="color: #2196f3; margin-right: 8px;"></i>
                            <strong>iPhone Users:</strong> For best compatibility, change your camera settings to "Most Compatible" format and turn off ProRes.
                            <a href="javascript:void(0)" onclick="showIphoneTutorial()" style="color: #1976d2; text-decoration: underline; font-weight: 500; cursor: pointer;">Click here to watch how</a>
                        </div>

                        <input class="d-none" type="file" name="files[]" id="files" multiple accept="image/*,image/heic,image/heif,video/*,video/quicktime,video/mp4">
                        <input type="file" id="cameraInput" accept="image/*,image/heic,image/heif,video/*,video/quicktime,video/mp4" capture="camera" class="d-none">
                    </div>

                    <div id="fileList"></div>

                    <div class="upload-loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Uploading...</span>
                        </div>
                        <p class="mt-2">Uploading your files...</p>
                        <div class="upload-progress">
                            <div class="upload-progress-bar"></div>
                        </div>
                    </div>

                    <input type="hidden" name="upload_token" value="<?php echo htmlspecialchars($upload_token); ?>">

                    <div class="mb-3 mt-3">
                        <label for="custom_message" class="form-label fw-semibold">
                            <i class="bi bi-chat-text"></i> Message (Optional)
                        </label>
                        <textarea class="form-control" name="custom_message" id="custom_message" rows="3"
                                  placeholder="Add any special instructions or information about these files..."
                                  class="textarea-rounded"></textarea>
                    </div>

                    <button class="btn-modern btn-modern-primary w-100 d-none" type="submit" id="uploadBtn">
                        <i class="bi bi-cloud-upload"></i> Upload Files
                    </button>
                </form>
            </div>

            <?php if (!empty($recent_chats)): ?>
                <div class="chat-section">
                    <div class="chat-header">
                        <h3 class="section-title m-0 text-white">
                            <i class="bi bi-chat-dots-fill"></i>
                            Recent Messages
                        </h3>
                    </div>

                    <div class="chat-messages" id="latestChats">
                        <?php foreach ($recent_chats as $msg): ?>
                            <div class="message-bubble <?php echo $msg['sender'] === 'admin' ? 'theirs' : 'mine'; ?>">
                                <div class="message-sender">
                                    <?php echo $msg['sender'] === 'admin' ? htmlspecialchars($admin_name) : 'You'; ?>
                                </div>
                                <div class="message-text"><?php echo nl2br($msg['message']); ?></div>
                                <div class="message-footer">
                                    <span class="message-time"><?php echo format_ts($msg['created_at']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="chat-input-wrapper">
                        <form method="post" action="send_message.php" id="quickChatForm" class="chat-form">
                            <input type="text" name="message" class="chat-input" placeholder="Type a message..." required>
                            <button class="btn-send" type="submit">
                                <i class="bi bi-send-fill"></i> Send
                            </button>
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="parent_id" id="parent_id" value="">
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Recent Activity -->
                <?php if (!empty($recent_uploads)): ?>
                    <div class="activity-section mt-3">
                        <h3 class="section-title">
                            <i class="bi bi-activity"></i>
                            Recent Activity
                        </h3>

                        <div class="activity-timeline">
                            <?php foreach ($recent_uploads as $upload): ?>
                                <?php
                                $isVideo = strpos($upload['mime'], 'video') !== false;
                                $viewUrl = !empty($upload['local_path']) ? '/public/' . $upload['local_path'] : 'https://drive.google.com/file/d/' . $upload['drive_id'] . '/view';
                                ?>
                                <div class="activity-item">
                                    <div class="activity-dot"></div>
                                    <div class="activity-content">
                                        <div class="activity-header">
                                    <span class="activity-title">
                                        <?php echo $isVideo ?
                                            '<i class="bi bi-camera-video"></i> Video' :
                                            '<i class="bi bi-image"></i> Image'; ?> uploaded
                                    </span>
                                            <span class="activity-time"><?php echo format_ts($upload['created_at']); ?></span>
                                        </div>
                                        <div class="activity-details">
                                            <a href="<?php echo htmlspecialchars($viewUrl); ?>"
                                               <?php echo $isVideo ? 'target="_blank"' : 'target="_blank"'; ?>
                                               style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars(shorten_filename($upload['filename'])); ?>
                                                (<?php echo number_format($upload['size'] / 1024 / 1024, 1); ?> MB)
                                                <i class="bi bi-box-arrow-up-right" style="font-size: 0.8em; opacity: 0.6;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate counters
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const animation = new countUp.CountUp(counter, target, {
                    duration: 2,
                    useEasing: true,
                    useGrouping: true
                });
                if (!animation.error) {
                    animation.start();
                }
            });

            // Alert handling
            const bcAlert = document.getElementById('broadcastAlert');
            const chatAlert = document.getElementById('chatAlert');
            const replyAlert = document.getElementById('replyAlert');

            if (bcAlert) {
                const id = bcAlert.dataset.id;
                if (localStorage.getItem('closedBroadcastId') == id) {
                    bcAlert.remove();
                } else {
                    bcAlert.querySelector('.btn-close').addEventListener('click', () => {
                        localStorage.setItem('closedBroadcastId', id);
                        bcAlert.remove();
                    });
                }
            }

            if (chatAlert) {
                const id = chatAlert.dataset.id;
                if (localStorage.getItem('closedChatId') == id) {
                    chatAlert.remove();
                } else {
                    chatAlert.querySelector('.btn-close').addEventListener('click', () => {
                        localStorage.setItem('closedChatId', id);
                        chatAlert.remove();
                    });
            }

            if (replyAlert) {
                const id = replyAlert.dataset.id;
                if (localStorage.getItem('closedReplyId') == id) {
                    replyAlert.remove();
                } else {
                    replyAlert.querySelector('.btn-close').addEventListener('click', () => {
                        localStorage.setItem('closedReplyId', id);
                        replyAlert.remove();
                    });
                }
            }
            }

            // Quick chat form
            const quickForm = document.getElementById('quickChatForm');
            if (quickForm) {
                quickForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    fetch('send_message.php', { method: 'POST', body: new FormData(this) })
                        .then(r => r.json())
                        .then(() => { location.reload(); });
                });
            }

            // File handling
            const fileInput = document.getElementById('files');
            const cameraInput = document.getElementById('cameraInput');
            const fileList = document.getElementById('fileList');
            const uploadForm = document.getElementById('uploadForm');
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadArea = document.getElementById('uploadArea');

            let allFiles = [];

            // Drag and drop
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('drag-over');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');

                const files = Array.from(e.dataTransfer.files);
                files.forEach(file => {
                    if (file.type.match(/^(image|video)\//)) {
                        allFiles.push(file);
                    }
                });
                updateFileList();
                updateMainFileInput();
            });

            function handleFileSelect(input) {
                const newFiles = Array.from(input.files);
                if (input.id === 'cameraInput' && newFiles.length > 0) {
                    allFiles.push(newFiles[0]);
                } else {
                    allFiles = newFiles;
                }
                updateFileList();
                updateMainFileInput();
            }

            function updateMainFileInput() {
                const dt = new DataTransfer();
                allFiles.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;
            }

            fileInput.addEventListener('change', function() {
                handleFileSelect(this);
            });

            cameraInput.addEventListener('change', function() {
                handleFileSelect(this);
                this.value = '';
            });

            function updateFileList() {
                fileList.innerHTML = '';

                if (allFiles.length === 0) {
                    uploadBtn.classList.add('d-none');
                    uploadBtn.style.display = 'none';
                    return;
                }

                uploadBtn.classList.remove('d-none');
                uploadBtn.style.display = 'block';

                allFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';

                    const isVideo = file.type.startsWith('video/');
                    const iconClass = isVideo ? 'bi-camera-video-fill' : 'bi-image-fill';

                    fileItem.innerHTML = `
                <div class="file-icon">
                    <i class="bi ${iconClass}"></i>
                </div>
                <div class="file-info">
                    <p class="file-name">${file.name}</p>
                    <p class="file-size">${formatFileSize(file.size)}</p>
                </div>
                <div class="file-description">
                    <input type="text" name="descriptions[${index}]" class="form-control form-control-sm"
                           placeholder="Optional description" class="rounded-8">
                </div>
                <i class="bi bi-x-circle-fill file-remove" onclick="removeFile(${index})"></i>
            `;

                    fileList.appendChild(fileItem);
                });
            }

            window.removeFile = function(index) {
                allFiles.splice(index, 1);
                updateFileList();
                updateMainFileInput();
            };

            uploadForm.addEventListener('submit', function(e) {
                if (allFiles.length === 0) {
                    e.preventDefault();
                    alert('Please select files to upload');
                    return;
                }

                uploadBtn.disabled = true;
                document.querySelector('.upload-area').style.display = 'none';
                document.querySelector('.upload-loading').classList.add('active');

                // Simulate progress
                let progress = 0;
                const progressBar = document.querySelector('.upload-progress-bar');
                const interval = setInterval(() => {
                    progress += Math.random() * 30;
                    if (progress > 90) progress = 90;
                    progressBar.style.width = progress + '%';
                }, 500);
            });

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // Auto-refresh upload token every 20 minutes to prevent session expiry
            window.tokenRefreshInterval = setInterval(function() {
                fetch('?refresh_token=1')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.token) {
                            const tokenInput = document.querySelector('input[name="upload_token"]');
                            if (tokenInput) {
                                tokenInput.value = data.token;
                                console.log('Upload token refreshed');
                            }
                        }
                    })
                    .catch(err => console.error('Token refresh failed:', err));
            }, 20 * 60 * 1000); // 20 minutes
        });

        // iPhone Tutorial Modal Functions
        function showIphoneTutorial() {
            const modal = document.getElementById('iphoneTutorialModal');
            const iframe = document.getElementById('iphoneTutorialVideo');

            // Set YouTube embed URL with autoplay
            iframe.src = 'https://www.youtube.com/embed/yIXz84MiyDg?autoplay=1&rel=0';
            modal.style.display = 'flex';
        }

        function closeIphoneTutorial(event) {
            if (event) event.stopPropagation();
            const modal = document.getElementById('iphoneTutorialModal');
            const iframe = document.getElementById('iphoneTutorialVideo');

            // Stop video by clearing src
            iframe.src = '';
            modal.style.display = 'none';
        }
    </script>

    <!-- iPhone Tutorial Video Modal -->
    <div id="iphoneTutorialModal" class="preview-modal" onclick="closeIphoneTutorial(event)">
        <div class="preview-close" onclick="closeIphoneTutorial(event)">
            <i class="bi bi-x-lg"></i>
        </div>
        <div class="preview-content" onclick="event.stopPropagation();" style="max-width: 500px;">
            <div style="width: 100%; aspect-ratio: 9/16; max-height: 80vh;">
                <iframe id="iphoneTutorialVideo"
                        width="100%"
                        height="100%"
                        src=""
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        style="border-radius: 8px; background: #000;">
                </iframe>
            </div>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>
