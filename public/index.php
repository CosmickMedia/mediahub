<?php
// Store uploader main page
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/drive.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/auth.php';

$config = get_config();

ensure_session();

$errors = [];
$success = [];

// Handle logout
if (isset($_GET['logout'])) {
    // Clear all store-related session data
    unset($_SESSION['store_id']);
    unset($_SESSION['store_pin']);
    unset($_SESSION['store_name']);
    unset($_SESSION['store_user_email']);
    unset($_SESSION['store_first_name']);
    unset($_SESSION['store_last_name']);

    // Destroy the session completely
    session_destroy();

    header('Location: index.php');
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
            $stmt = $pdo->prepare('SELECT s.*, u.first_name AS ufname, u.last_name AS ulname FROM stores s JOIN store_users u ON u.store_id = s.id WHERE s.pin = ? AND u.email = ?');
            $stmt->execute([$pin, $email]);
            if ($store = $stmt->fetch()) {
                session_regenerate_id(true);
                $_SESSION['store_id'] = $store['id'];
                $_SESSION['store_pin'] = $pin;
                $_SESSION['store_name'] = $store['name'];
                $_SESSION['store_user_email'] = $email;
                $_SESSION['store_first_name'] = $store['ufname'] ?? '';
                $_SESSION['store_last_name'] = $store['ulname'] ?? '';
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
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Store Login - MediaHub</title>
        <!-- Bootstrap CSS from CDN -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
        <link rel="stylesheet" href="inc/css/style.css">
    </head>
    <body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="text-center">
                    <img src="/assets/images/mediahub-logo.png" alt="MediaHub" class="login-logo">
                </div>
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4 store-pin-title">Store PIN</h3>
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
        unset($_SESSION['upload_token']);
        try {
        // Get or create store folder
        $storeFolderId = get_or_create_store_folder($store_id);

        $uploadCount = 0;
        $totalFiles = count($_FILES['files']['tmp_name']);
        $customMessage = $_POST['custom_message'] ?? '';
        $uploadedFiles = [];
        $processedHashes = []; // Track processed files to prevent duplicates

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

            // Check file size (20MB limit)
            if ($fileSize > 20 * 1024 * 1024) {
                $errors[] = "$originalName is too large (max 20MB)";
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
                // Upload to Google Drive
                $driveId = drive_upload($tmpFile, $mimeType, $originalName, $storeFolderId);

                // Get description
                $description = $_POST['descriptions'][$i] ?? '';

                // Save to database with custom message
                try {
                    // Check if custom_message column exists
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM uploads LIKE 'custom_message'");
                    $hasCustomMessage = $checkColumn->fetch() !== false;

                    if ($hasCustomMessage) {
                        $stmt = $pdo->prepare('INSERT INTO uploads (store_id, filename, description, custom_message, created_at, ip, mime, size, drive_id) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)');
                        $stmt->execute([
                            $store_id,
                            $originalName,
                            $description,
                            $customMessage,
                            $_SERVER['REMOTE_ADDR'],
                            $mimeType,
                            $fileSize,
                            $driveId
                        ]);
                    } else {
                        // Insert without custom_message column
                        $stmt = $pdo->prepare('INSERT INTO uploads (store_id, filename, description, created_at, ip, mime, size, drive_id) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)');
                        $stmt->execute([
                            $store_id,
                            $originalName,
                            $description,
                            $_SERVER['REMOTE_ADDR'],
                            $mimeType,
                            $fileSize,
                            $driveId
                        ]);
                    }

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
            $settingsQuery = $pdo->query("SELECT name, value FROM settings WHERE name IN ('notification_email', 'email_from_name', 'email_from_address', 'admin_notification_subject', 'store_notification_subject')");
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

            // Send notification emails to admin
            $notifyEmails = $emailSettings['notification_email'] ?? '';
            if ($notifyEmails) {
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
            if (!empty($store['admin_email'])) {
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

// show upload form
include __DIR__.'/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dashboard-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .welcome-section {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
        }

        .welcome-time {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-card .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .stat-card.primary .stat-icon { color: #667eea; }
        .stat-card.success .stat-icon { color: #4facfe; }
        .stat-card.warning .stat-icon { color: #fa709a; }
        .stat-card.info .stat-icon { color: #4ade80; }
        .stat-card.danger .stat-icon { color: #f5576c; }
        .stat-card.secondary .stat-icon { color: #f093fb; }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .stat-change {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive { color: #4ade80; }
        .stat-change.negative { color: #f5576c; }

        .stat-bg {
            position: absolute;
            right: -20px;
            bottom: -20px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            opacity: 0.1;
        }

        .stat-card.primary .stat-bg { background: var(--primary-gradient); }
        .stat-card.success .stat-bg { background: var(--success-gradient); }
        .stat-card.warning .stat-bg { background: var(--warning-gradient); }
        .stat-card.info .stat-bg { background: linear-gradient(135deg, #4ade80, #22c55e); }
        .stat-card.danger .stat-bg { background: var(--danger-gradient); }
        .stat-card.secondary .stat-bg { background: var(--secondary-gradient); }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .left-column {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Upload Section */
        .upload-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            font-size: 1.25rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .upload-area {
            border: 3px dashed #e0e0e0;
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            transition: var(--transition);
            background: #f8f9fa;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: #f5f7ff;
        }

        .upload-area.drag-over {
            border-color: #667eea;
            background: #f5f7ff;
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .upload-text {
            font-size: 1.125rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .upload-subtext {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .file-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            border: none;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-modern-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-modern-secondary {
            background: white;
            color: #6c757d;
            border: 2px solid #e0e0e0;
        }

        .btn-modern-secondary:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
        }

        .action-grid {
            display: grid;
            gap: 1rem;
        }

        .action-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            background: #f8f9fa;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .action-card.articles .action-icon { background: var(--primary-gradient); }
        .action-card.history .action-icon { background: var(--success-gradient); }
        .action-card.calendar .action-icon { background: var(--warning-gradient); }
        .action-card.messages .action-icon { background: var(--info-gradient); }
        .action-card.marketing .action-icon { background: var(--danger-gradient); }

        .action-content {
            flex: 1;
        }

        .action-title {
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-desc {
            font-size: 0.875rem;
            color: #6c757d;
            margin: 0;
        }

        .action-badge {
            background: #dc3545;
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
        }

        .action-arrow {
            position: absolute;
            right: 1rem;
            color: #adb5bd;
            transition: var(--transition);
        }

        .action-card:hover .action-arrow {
            transform: translateX(5px);
            color: #667eea;
        }

        /* Activity Feed */
        .activity-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
        }

        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }

        .activity-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .activity-dot {
            position: absolute;
            left: -2.25rem;
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: white;
            border: 2px solid #667eea;
        }

        .activity-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 12px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .activity-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .activity-details {
            font-size: 0.875rem;
            color: #6c757d;
        }

        /* Chat Feed */
        .chat-section {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .chat-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 2rem;
        }

        .chat-messages {
            max-height: 400px;
            overflow-y: auto;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
        }

        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #e9ecef;
            border-radius: 10px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #adb5bd;
            border-radius: 10px;
        }

        .message-bubble {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 16px;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .message-bubble.mine {
            background: var(--primary-gradient);
            color: white;
            margin-left: auto;
            max-width: 80%;
        }

        .message-bubble.theirs {
            max-width: 80%;
        }

        .message-sender {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .message-text {
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .chat-input-wrapper {
            padding: 1.5rem 2rem;
            background: white;
            border-top: 1px solid #e9ecef;
        }

        .chat-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .chat-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .chat-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-send {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* Alerts */
        .alert-modern {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .alert-modern.alert-broadcast {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .alert-modern.alert-success {
            background: #d1f2eb;
            border-left: 4px solid #4ade80;
        }

        .alert-modern.alert-info {
            background: #cff4fc;
            border-left: 4px solid #4facfe;
        }

        .alert-modern.alert-warning {
            background: #fff3cd;
            border-left: 4px solid #fa709a;
        }

        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .alert-message {
            font-size: 0.9rem;
            margin: 0;
        }

        /* File List */
        #fileList {
            margin-top: 1.5rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .file-size {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .file-description {
            flex: 2;
        }

        .file-remove {
            color: #dc3545;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-remove:hover {
            transform: scale(1.2);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .welcome-title {
                font-size: 1.5rem;
            }

            .upload-area {
                padding: 2rem 1rem;
            }

            .file-buttons {
                flex-direction: column;
            }

            .btn-modern {
                width: 100%;
                justify-content: center;
            }
        }

        /* Loading State */
        .upload-loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .upload-loading.active {
            display: block;
        }

        .upload-progress {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .upload-progress-bar {
            height: 100%;
            background: var(--primary-gradient);
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>

    <div class="dashboard-container animate__animated animate__fadeIn">
        <!-- Welcome Section -->
        <div class="welcome-section animate__animated animate__fadeInDown">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($your_name ?: $store_name); ?>!</h1>
            <p class="welcome-subtitle">Your MediaHub Dashboard</p>
            <p class="welcome-time"><?php echo date('l, F j, Y'); ?></p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($latest_broadcast)): ?>
            <div class="alert alert-dismissible fade show alert-modern alert-broadcast animate__animated animate__fadeIn" id="broadcastAlert" data-id="<?php echo $latest_broadcast['id']; ?>">
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
            <div class="alert alert-dismissible fade show alert-modern alert-info animate__animated animate__fadeIn" id="chatAlert" data-id="<?php echo $latest_chat['id']; ?>">
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
            <div class="alert alert-dismissible fade show alert-modern alert-warning animate__animated animate__fadeIn" id="replyAlert" data-id="<?php echo $last_reply_id; ?>">
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
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($e); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?php foreach ($success as $s): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($s); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card primary animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-cloud-upload-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upload_stats['total_uploads']; ?>">0</div>
                <div class="stat-label">Total Uploads</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card success animate__animated animate__fadeInUp delay-10">
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

            <div class="stat-card warning animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upload_stats['total_images']; ?>">0</div>
                <div class="stat-label">Images</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card info animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-camera-video-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upload_stats['total_videos']; ?>">0</div>
                <div class="stat-label">Videos</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card danger animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-calendar-event-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $upcoming_posts; ?>">0</div>
                <div class="stat-label">Scheduled Posts</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card secondary animate__animated animate__fadeInUp delay-50">
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
            <div class="upload-section animate__animated animate__fadeIn delay-60">
                <h3 class="section-title">
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                    Upload Content
                </h3>

                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-area" id="uploadArea">
                        <i class="bi bi-cloud-upload upload-icon"></i>
                        <p class="upload-text">Drag & drop files here or click to browse</p>
                        <p class="upload-subtext">Supports images and videos up to 20MB</p>

                        <div class="file-buttons">
                            <button type="button" class="btn-modern btn-modern-primary" onclick="document.getElementById('files').click();">
                                <i class="bi bi-folder2-open"></i> Browse Files
                            </button>
                            <button type="button" class="btn-modern btn-modern-secondary" onclick="document.getElementById('cameraInput').click();">
                                <i class="bi bi-camera"></i> Use Camera
                            </button>
                        </div>

                        <input class="d-none" type="file" name="files[]" id="files" multiple accept="image/*,video/*">
                        <input type="file" id="cameraInput" accept="image/*,video/*" capture="camera" class="d-none">
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
                <div class="chat-section animate__animated animate__fadeIn delay-90">
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
                <!-- Quick Actions -->
                <div class="quick-actions animate__animated animate__fadeIn delay-70">
                    <h3 class="section-title">
                        <i class="bi bi-lightning-charge-fill"></i>
                        Quick Actions
                    </h3>

                    <div class="action-grid">
                        <a href="articles.php" class="action-card articles">
                            <div class="action-icon">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <div class="action-content">
                                <h5 class="action-title">
                                    Submit Articles
                                    <?php if ($article_count > 0): ?>
                                        <span class="action-badge"><?php echo $article_count; ?></span>
                                    <?php endif; ?>
                                </h5>
                                <p class="action-desc">Write and submit content</p>
                            </div>
                            <i class="bi bi-chevron-right action-arrow"></i>
                        </a>

                        <a href="history.php" class="action-card history">
                            <div class="action-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="action-content">
                                <h5 class="action-title">Upload History</h5>
                                <p class="action-desc">View all your uploads</p>
                            </div>
                            <i class="bi bi-chevron-right action-arrow"></i>
                        </a>

                        <a href="calendar.php" class="action-card calendar">
                            <div class="action-icon">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div class="action-content">
                                <h5 class="action-title">
                                    Calendar
                                    <?php if ($upcoming_posts > 0): ?>
                                        <span class="action-badge"><?php echo $upcoming_posts; ?></span>
                                    <?php endif; ?>
                                </h5>
                                <p class="action-desc">View scheduled posts</p>
                            </div>
                            <i class="bi bi-chevron-right action-arrow"></i>
                        </a>

                        <a href="chat.php" class="action-card messages">
                            <div class="action-icon">
                                <i class="bi bi-chat-dots"></i>
                            </div>
                            <div class="action-content">
                                <h5 class="action-title">
                                    Chat
                                    <?php if ($unread_count > 0): ?>
                                        <span class="action-badge"><?php echo $unread_count; ?></span>
                                    <?php endif; ?>
                                </h5>
                                <p class="action-desc">Chat with admin</p>
                            </div>
                            <i class="bi bi-chevron-right action-arrow"></i>
                        </a>

                        <a href="marketing.php" class="action-card marketing">
                            <div class="action-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="action-content">
                                <h5 class="action-title">Marketing Report</h5>
                                <p class="action-desc">View analytics</p>
                            </div>
                            <i class="bi bi-chevron-right action-arrow"></i>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <?php if (!empty($recent_uploads)): ?>
                    <div class="activity-section mt-3 animate__animated animate__fadeIn delay-80">
                        <h3 class="section-title">
                            <i class="bi bi-activity"></i>
                            Recent Activity
                        </h3>

                        <div class="activity-timeline">
                            <?php foreach ($recent_uploads as $upload): ?>
                                <div class="activity-item">
                                    <div class="activity-dot"></div>
                                    <div class="activity-content">
                                        <div class="activity-header">
                                    <span class="activity-title">
                                        <?php echo strpos($upload['mime'], 'video') !== false ?
                                            '<i class="bi bi-camera-video"></i> Video' :
                                            '<i class="bi bi-image"></i> Image'; ?> uploaded
                                    </span>
                                            <span class="activity-time"><?php echo format_ts($upload['created_at']); ?></span>
                                        </div>
                                        <div class="activity-details">
                                            <?php echo htmlspecialchars(shorten_filename($upload['filename'])); ?>
                                            (<?php echo number_format($upload['size'] / 1024 / 1024, 1); ?> MB)
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
                    uploadBtn.style.display = 'none';
                    return;
                }

                uploadBtn.style.display = 'block';

                allFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item animate__animated animate__fadeIn';

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
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>