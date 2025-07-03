<?php
// Store uploader main page
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/drive.php';

$config = get_config();

session_start();
$errors = [];
$success = [];

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
            $_SESSION['store_name'] = $store['name'];
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
            <div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
        <form method="post">
            <div class="mb-3">
                <label for="pin" class="form-label">Store PIN</label>
                <input type="text" name="pin" id="pin" class="form-control" required autofocus>
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
$store_name = $_SESSION['store_name'] ?? 'Store';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $pdo = get_pdo();

    try {
        // Get or create store folder
        $storeFolderId = get_or_create_store_folder($store_id);

        $uploadCount = 0;
        $totalFiles = count($_FILES['files']['tmp_name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if (!is_uploaded_file($_FILES['files']['tmp_name'][$i])) {
                continue;
            }

            $tmpFile = $_FILES['files']['tmp_name'][$i];
            $originalName = $_FILES['files']['name'][$i];
            $fileSize = $_FILES['files']['size'][$i];
            $fileError = $_FILES['files']['error'][$i];

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

                // Save to database
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

                $uploadCount++;
                $success[] = "$originalName uploaded successfully";

            } catch (Exception $e) {
                $errors[] = "Failed to upload $originalName: " . $e->getMessage();
            }
        }

        if ($uploadCount > 0) {
            $success[] = "Successfully uploaded $uploadCount file(s)";

            // Send notification email if configured
            $stmt = $pdo->prepare('SELECT value FROM settings WHERE name=?');
            $stmt->execute(['notification_email']);
            $notifyEmail = $stmt->fetchColumn();

            if ($notifyEmail) {
                $subject = "New uploads from $store_name";
                $message = "$uploadCount new file(s) uploaded from store: $store_name (PIN: $store_pin)";
                mail($notifyEmail, $subject, $message);
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
    <h4>Upload Files for <?php echo htmlspecialchars($store_name); ?></h4>
    <p class="text-muted">Store PIN: <?php echo htmlspecialchars($store_pin); ?></p>

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

    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <div class="mb-3">
            <label for="files" class="form-label">Select Photos/Videos</label>
            <input class="form-control" type="file" name="files[]" id="files" multiple accept="image/*,video/*" capture="environment" required>
            <div class="form-text">You can select multiple files. Maximum 20MB per file.</div>
        </div>

        <div id="fileList" class="mb-3"></div>

        <button class="btn btn-primary" type="submit" id="uploadBtn">
            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
            Upload Files
        </button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('files');
            const fileList = document.getElementById('fileList');
            const uploadForm = document.getElementById('uploadForm');
            const uploadBtn = document.getElementById('uploadBtn');

            fileInput.addEventListener('change', function() {
                fileList.innerHTML = '';

                if (this.files.length === 0) return;

                const table = document.createElement('table');
                table.className = 'table table-sm';

                const thead = document.createElement('thead');
                thead.innerHTML = '<tr><th>File</th><th>Size</th><th>Description</th></tr>';
                table.appendChild(thead);

                const tbody = document.createElement('tbody');

                Array.from(this.files).forEach((file, index) => {
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

                    tbody.appendChild(row);
                });

                table.appendChild(tbody);
                fileList.appendChild(table);
            });

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