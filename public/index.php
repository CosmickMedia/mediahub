<?php
// Store uploader main page
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/drive.php';

$config = get_config();

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');

    // Set session cookie parameters before starting session
    $currentCookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie
        'path' => '/',
        'domain' => '', // Current domain
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

$errors = [];
$success = [];

// Handle logout
if (isset($_GET['logout'])) {
    // Clear all store-related session data
    unset($_SESSION['store_id']);
    unset($_SESSION['store_pin']);
    unset($_SESSION['store_name']);
    unset($_SESSION['store_user_email']);

    // Regenerate session ID for security
    session_regenerate_id(true);

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
            $stmt = $pdo->prepare('SELECT s.* FROM stores s JOIN store_users u ON u.store_id = s.id WHERE s.pin = ? AND u.email = ?');
            $stmt->execute([$pin, $email]);
            if ($store = $stmt->fetch()) {
                session_regenerate_id(true);
                $_SESSION['store_id'] = $store['id'];
                $_SESSION['store_pin'] = $pin;
                $_SESSION['store_name'] = $store['name'];
                $_SESSION['store_user_email'] = $email;
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
        <style>
            body {
                background-color: #262829;
                display: flex;
                align-items: center;
                min-height: 100vh;
            }
            .login-logo {
                max-width: 200px;
                margin-bottom: 30px;
            }
            .btn-login {
                background-color: #ff675b;
                border-color: #ff675b;
                color: white;
            }
            .btn-login:hover {
                background-color: #e55750;
                border-color: #e55750;
                color: white;
            }
            .admin-link {
                margin-top: 20px;
                font-size: 0.875rem;
            }
            .store-pin-title {
                font-size: 1.5rem;
            }
        </style>
    </head>
    <body>
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

// Get store messages - handle missing columns gracefully
$messages = [];
$replies = [];

// Check if is_reply column exists
$checkColumn = $pdo->query("SHOW COLUMNS FROM store_messages LIKE 'is_reply'");
$hasReplyColumn = $checkColumn->fetch() !== false;

if ($hasReplyColumn) {
    // Column exists, use full query
    $stmt = $pdo->prepare('
        SELECT * FROM store_messages 
        WHERE (store_id = ? OR store_id IS NULL) 
        AND (is_reply = 0 OR is_reply IS NULL)
        ORDER BY created_at DESC
    ');
    $stmt->execute([$store_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get reply messages
    $stmt = $pdo->prepare('
        SELECT m.*, u.filename 
        FROM store_messages m 
        LEFT JOIN uploads u ON m.upload_id = u.id 
        WHERE m.store_id = ? AND m.is_reply = 1 
        ORDER BY m.created_at DESC
    ');
    $stmt->execute([$store_id]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Column doesn't exist, use simple query
    $stmt = $pdo->prepare('
        SELECT * FROM store_messages 
        WHERE (store_id = ? OR store_id IS NULL) 
        ORDER BY created_at DESC
    ');
    $stmt->execute([$store_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get article count for badge
$stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE store_id = ?');
$stmt->execute([$store_id]);
$article_count = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
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

    <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($store_name); ?>!</h2>

<?php if (!empty($messages)): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <h5 class="alert-heading">Important Messages:</h5>
        <?php foreach ($messages as $msg): ?>
            <p class="mb-1"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($replies)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h5 class="alert-heading">Admin Feedback:</h5>
        <?php foreach ($replies as $reply): ?>
            <div class="mb-2">
                <strong>Re: <?php echo htmlspecialchars($reply['filename']); ?></strong><br>
                <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                <small class="text-muted d-block"><?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?></small>
            </div>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

<?php foreach ($success as $s): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($s); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="card-title mb-3">Upload Files</h4>
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <div class="mb-3">
                            <label for="files" class="form-label">Select Photos/Videos</label>
                            <div class="input-group">
                                <input class="form-control" type="file" name="files[]" id="files" multiple accept="image/*,video/*" required>
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('cameraInput').click();">
                                    <i class="bi bi-camera"></i> Camera
                                </button>
                            </div>
                            <input type="file" id="cameraInput" accept="image/*,video/*" capture="camera" style="display:none;">
                            <div class="form-text">You can select multiple files. Maximum 20MB per file.</div>
                        </div>

                        <div id="fileList" class="mb-3"></div>

                        <div class="mb-3">
                            <label for="custom_message" class="form-label">Message (Optional)</label>
                            <textarea class="form-control" name="custom_message" id="custom_message" rows="3"
                                      placeholder="Add any special instructions or information about these files..."></textarea>
                        </div>

                        <button class="btn btn-primary" type="submit" id="uploadBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            Upload Files
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="articles.php" class="btn btn-primary position-relative">
                            <i class="bi bi-pencil-square"></i> Submit Articles
                            <?php if ($article_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $article_count; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        <a href="history.php" class="btn btn-primary">
                            <i class="bi bi-clock-history"></i> View Upload History
                        </a>
                        <a href="calendar.php" class="btn btn-primary">
                            <i class="bi bi-calendar-event"></i> View Calendar
                        </a>
                        <a href="?logout=1" class="btn btn-secondary">
                            <i class="bi bi-box-arrow-right"></i> Change Store
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('files');
            const cameraInput = document.getElementById('cameraInput');
            const fileList = document.getElementById('fileList');
            const uploadForm = document.getElementById('uploadForm');
            const uploadBtn = document.getElementById('uploadBtn');

            let allFiles = [];

            function handleFileSelect(input) {
                const newFiles = Array.from(input.files);

                // For camera input, only add the single file
                if (input.id === 'cameraInput' && newFiles.length > 0) {
                    allFiles.push(newFiles[0]);
                } else {
                    // For regular file input, replace all files
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
                // Clear camera input after processing to prevent re-adding
                this.value = '';
            });

            function updateFileList() {
                fileList.innerHTML = '';

                if (allFiles.length === 0) return;

                const table = document.createElement('table');
                table.className = 'table table-sm';

                const thead = document.createElement('thead');
                thead.innerHTML = '<tr><th>File</th><th>Size</th><th>Description</th><th></th></tr>';
                table.appendChild(thead);

                const tbody = document.createElement('tbody');

                allFiles.forEach((file, index) => {
                    const row = document.createElement('tr');

                    // File name
                    const nameCell = document.createElement('td');
                    nameCell.textContent = file.name;
                    row.appendChild(nameCell);

                    // File size
                    const sizeCell = document.createElement('td');
                    sizeCell.textContent = formatFileSize(file.size);
                    if (file.size > 20 * 1024 * 1024) {
                        sizeCell.classList.add('text-danger');
                        sizeCell.innerHTML += ' <small>(too large)</small>';
                    }
                    row.appendChild(sizeCell);

                    // Description input
                    const descCell = document.createElement('td');
                    const descInput = document.createElement('input');
                    descInput.type = 'text';
                    descInput.name = `descriptions[${index}]`;
                    descInput.className = 'form-control form-control-sm';
                    descInput.placeholder = 'Optional description';
                    descCell.appendChild(descInput);
                    row.appendChild(descCell);

                    // Remove button
                    const removeCell = document.createElement('td');
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-sm btn-outline-danger';
                    removeBtn.innerHTML = '<i class="bi bi-x"></i>';
                    removeBtn.onclick = function() {
                        allFiles.splice(index, 1);
                        updateFileList();
                        updateMainFileInput();
                    };
                    removeCell.appendChild(removeBtn);
                    row.appendChild(removeCell);

                    tbody.appendChild(row);
                });

                table.appendChild(tbody);
                fileList.appendChild(table);
            }

            uploadForm.addEventListener('submit', function(e) {
                uploadBtn.disabled = true;
                uploadBtn.querySelector('.spinner-border').classList.remove('d-none');
                uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Uploading...';
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