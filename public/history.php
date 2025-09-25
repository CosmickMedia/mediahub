<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/drive.php';

$config = get_config();
$localUploadDir = $config['local_upload_dir'] ?? (__DIR__ . '/uploads');

ensure_session();

// Check if logged in
if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];
$pdo = get_pdo();

// Upload token
if (empty($_SESSION['upload_token'])) {
    $_SESSION['upload_token'] = bin2hex(random_bytes(16));
}
$upload_token = $_SESSION['upload_token'];

// Handle quick image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $tokenValid = isset($_POST['upload_token']) && hash_equals($_SESSION['upload_token'], $_POST['upload_token']);
    if ($tokenValid) {
        unset($_SESSION['upload_token']);
        try {
            $storeFolderId = get_or_create_store_folder($store_id);
            $totalFiles = count($_FILES['files']['name']);
            $cols = $pdo->query("SHOW COLUMNS FROM uploads")->fetchAll(PDO::FETCH_COLUMN);
            $hasLocalPath = in_array('local_path', $cols, true);
            $hasThumbPath = in_array('thumb_path', $cols, true);
            $uploadCount = 0;
            $uploadedFiles = [];
            for ($i = 0; $i < $totalFiles; $i++) {
                if (!is_uploaded_file($_FILES['files']['tmp_name'][$i])) continue;

                $tmp = $_FILES['files']['tmp_name'][$i];
                $name = $_FILES['files']['name'][$i];
                $size = $_FILES['files']['size'][$i];
                $err = $_FILES['files']['error'][$i];
                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = "Error uploading $name: " . getUploadErrorMessage($err);
                    continue;
                }
                if ($size > 20 * 1024 * 1024) {
                    $errors[] = "$name is too large (max 20MB)";
                    continue;
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                if (strpos($mime, 'image/') !== 0) {
                    $errors[] = "$name is not an image";
                    continue;
                }

                try {
                    $subDir = $store_id . '/' . date('Y/m');
                    $targetDir = rtrim($localUploadDir, '/\\') . '/' . $subDir;
                    $thumbDir = $targetDir . '/thumbs';
                    if (!is_dir($thumbDir) && !mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
                        throw new Exception('Failed to create upload directory');
                    }

                    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
                    $localPath = $targetDir . '/' . $safe;
                    if (!move_uploaded_file($tmp, $localPath)) {
                        throw new Exception('Failed to store file locally');
                    }
                    $thumbPath = $thumbDir . '/' . $safe;
                    $thumbUrl = null;
                    if (create_local_thumbnail($localPath, $thumbPath, $mime)) {
                        $thumbUrl = 'uploads/' . $subDir . '/thumbs/' . $safe;
                    }

                    $driveId = drive_upload($localPath, $mime, $name, $storeFolderId);

                    $fields = ['store_id', 'filename', 'created_at', 'ip', 'mime', 'size', 'drive_id'];
                    $placeholders = '?, ?, NOW(), ?, ?, ?, ?';
                    $values = [$store_id, $name, $_SERVER['REMOTE_ADDR'], $mime, $size, $driveId];

                    if ($hasLocalPath) {
                        $fields[] = 'local_path';
                        $placeholders .= ', ?';
                        $values[] = 'uploads/' . $subDir . '/' . $safe;
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
                    $uploadedFiles[] = $name;
                } catch (Exception $e) {
                    $errors[] = "Failed to upload $name: " . $e->getMessage();
                }
            }

            if ($uploadCount > 0) {
                $success = "Successfully uploaded $uploadCount file(s)";

                $emailSettings = [];
                $settingsQuery = $pdo->query("SELECT name, value FROM settings WHERE name IN ('notification_email', 'email_from_name', 'email_from_address', 'admin_notification_subject', 'store_notification_subject')");
                while ($row = $settingsQuery->fetch()) {
                    $emailSettings[$row['name']] = $row['value'];
                }

                $fromName = $emailSettings['email_from_name'] ?? 'Cosmick Media';
                $fromAddress = $emailSettings['email_from_address'] ?? 'noreply@cosmickmedia.com';
                $adminSubject = str_replace('{store_name}', $store_name, $emailSettings['admin_notification_subject'] ?? 'New uploads from {store_name}');
                $storeSubject = str_replace('{store_name}', $store_name, $emailSettings['store_notification_subject'] ?? 'Content Submission Confirmation - Cosmick Media');

                $headers = "From: $fromName <$fromAddress>\r\n";
                $headers .= "Reply-To: $fromAddress\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();

                $notifyEmails = $emailSettings['notification_email'] ?? '';
                if ($notifyEmails) {
                    $emailList = array_map('trim', explode(',', $notifyEmails));
                    $message = "$uploadCount new file(s) uploaded from store: $store_name\n\n";
                    $message .= "Files uploaded:\n";
                    foreach ($uploadedFiles as $f) { $message .= "- $f\n"; }
                    foreach ($emailList as $email) {
                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            mail($email, $adminSubject, $message, $headers);
                        }
                    }
                }

                if (!empty($store['admin_email'])) {
                    $confirmMessage = "Dear $store_name,\n\n";
                    $confirmMessage .= "Thank you for your submission to the Cosmick Media Content Library.\n\n";
                    $confirmMessage .= "We have successfully received the following files:\n";
                    foreach ($uploadedFiles as $f) { $confirmMessage .= "- $f\n"; }
                    $confirmMessage .= "\nYour content is now pending curation by our team.\n";
                    $confirmMessage .= "We will review your submission and get back to you if we need any additional information.\n\n";
                    $confirmMessage .= "Best regards,\n$fromName";

                    mail($store['admin_email'], $storeSubject, $confirmMessage, $headers);
                }
            }

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    } else {
        $errors[] = 'Invalid upload token';
    }
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $upload_id = $_POST['delete_id'];

    // Verify this upload belongs to the current store
    $stmt = $pdo->prepare('SELECT drive_id, local_path, thumb_path FROM uploads WHERE id = ? AND store_id = ?');
    $stmt->execute([$upload_id, $store_id]);
    $upload = $stmt->fetch();

    if ($upload) {
        // Delete from Google Drive
        try {
            require_once __DIR__.'/../lib/drive.php';
            drive_delete($upload['drive_id']);
        } catch (Exception $e) {
            // Continue even if Drive delete fails
        }

        if (!empty($upload['local_path'])) {
            $path = __DIR__ . '/' . ltrim($upload['local_path'], '/');
            @unlink($path);
        }
        if (!empty($upload['thumb_path'])) {
            $path = __DIR__ . '/' . ltrim($upload['thumb_path'], '/');
            @unlink($path);
        }

        // Delete from database
        $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = ? AND store_id = ?');
        $stmt->execute([$upload_id, $store_id]);

        $success = 'File deleted successfully';
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'date_desc';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // Changed to 12 for grid layout
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ['store_id = ?'];
$params = [$store_id];

if ($filter_type !== 'all') {
    if ($filter_type === 'image') {
        $where_conditions[] = "mime LIKE 'image/%'";
    } elseif ($filter_type === 'video') {
        $where_conditions[] = "mime LIKE 'video/%'";
    }
}

if ($search_query) {
    $where_conditions[] = "(filename LIKE ? OR description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count with filters
$stmt = $pdo->prepare("SELECT COUNT(*) FROM uploads WHERE $where_clause");
$stmt->execute($params);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Sorting
$order_clause = match($sort_by) {
    'date_asc' => 'created_at ASC',
    'name_asc' => 'filename ASC',
    'name_desc' => 'filename DESC',
    'size_asc' => 'size ASC',
    'size_desc' => 'size DESC',
    default => 'created_at DESC'
};

// Get uploads with filters
$stmt = $pdo->prepare("
    SELECT * FROM uploads 
    WHERE $where_clause 
    ORDER BY $order_clause 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_files,
        SUM(size) as total_size,
        COUNT(CASE WHEN mime LIKE 'image/%' THEN 1 END) as total_images,
        COUNT(CASE WHEN mime LIKE 'video/%' THEN 1 END) as total_videos,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_uploads
    FROM uploads 
    WHERE store_id = ?
");
$stats_stmt->execute([$store_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_size_gb = $stats['total_size'] / (1024 * 1024 * 1024);

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

include __DIR__.'/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">


    <div class="history-container animate__animated animate__fadeIn">
        <!-- Header Section -->
        <div class="history-header">
            <div>
                <h2 class="history-title">Upload History</h2>
                <p class="history-subtitle"><?php echo htmlspecialchars($store_name); ?></p>
            </div>
            <a href="index.php" class="btn btn-modern-primary">
                <i class="bi bi-arrow-left"></i> Back to Upload
            </a>
        </div>

        <div class="text-end mb-3">
            <button class="btn btn-modern-secondary" type="button" id="toggleQuickUpload">
                <i class="bi bi-plus-circle"></i> Add Images
            </button>
        </div>

        <div id="quickUpload" class="mb-4" style="display:none;">
            <form method="post" enctype="multipart/form-data" id="quickUploadForm">
                <div class="upload-area small" id="quickUploadArea">
                    <i class="bi bi-cloud-upload upload-icon"></i>
                    <p class="upload-text">Drag & drop or browse</p>
                    <div class="file-buttons">
                        <button type="button" class="btn-modern btn-modern-primary" onclick="document.getElementById('quickFiles').click();">
                            <i class="bi bi-folder2-open"></i> Browse Files
                        </button>
                        <button type="button" class="btn-modern btn-modern-secondary" onclick="document.getElementById('quickCamera').click();">
                            <i class="bi bi-camera"></i> Use Camera
                        </button>
                    </div>
                    <input class="d-none" type="file" name="files[]" id="quickFiles" multiple accept="image/*">
                    <input type="file" id="quickCamera" accept="image/*" capture="camera" class="d-none">
                    <div id="quickFileList"></div>
                </div>
                <input type="hidden" name="upload_token" value="<?php echo htmlspecialchars($upload_token); ?>">
                <button type="submit" class="btn-modern btn-modern-primary mt-2">Upload</button>
            </form>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-dashboard">
            <div class="stat-card total-files animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-folder-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" data-count="<?php echo $stats['total_files']; ?>">0</div>
                    <div class="stat-label">Total Files</div>
                </div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card total-size animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-hdd-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" data-count="<?php echo number_format($total_size_gb, 1); ?>">0</div>
                    <div class="stat-label">GB Storage</div>
                </div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card images animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" data-count="<?php echo $stats['total_images']; ?>">0</div>
                    <div class="stat-label">Images</div>
                </div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card videos animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-camera-video-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" data-count="<?php echo $stats['total_videos']; ?>">0</div>
                    <div class="stat-label">Videos</div>
                </div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card recent animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" data-count="<?php echo $stats['recent_uploads']; ?>">0</div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section animate__animated animate__fadeIn delay-50">
            <div class="filters-row">
                <div class="filter-group">
                    <span class="filter-label">Type:</span>
                    <a href="?type=all" class="filter-button <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                        <i class="bi bi-grid-3x3-gap"></i> All
                    </a>
                    <a href="?type=image" class="filter-button <?php echo $filter_type === 'image' ? 'active' : ''; ?>">
                        <i class="bi bi-image"></i> Images
                    </a>
                    <a href="?type=video" class="filter-button <?php echo $filter_type === 'video' ? 'active' : ''; ?>">
                        <i class="bi bi-camera-video"></i> Videos
                    </a>
                </div>

                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Search files..."
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           id="searchInput">
                </div>

                <div class="filter-group">
                    <span class="filter-label">Sort:</span>
                    <select class="sort-select" id="sortSelect">
                        <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                        <option value="size_desc" <?php echo $sort_by === 'size_desc' ? 'selected' : ''; ?>>Largest First</option>
                        <option value="size_asc" <?php echo $sort_by === 'size_asc' ? 'selected' : ''; ?>>Smallest First</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if (empty($uploads)): ?>
            <div class="empty-state animate__animated animate__fadeIn">
                <i class="bi bi-inbox"></i>
                <h3>No uploads found</h3>
                <p>
                    <?php if ($search_query || $filter_type !== 'all'): ?>
                        Try adjusting your filters or search query
                    <?php else: ?>
                        Upload your first file to see it here
                    <?php endif; ?>
                </p>
                <a href="index.php" class="btn btn-modern-primary">
                    <i class="bi bi-cloud-upload"></i> Upload Files
                </a>
            </div>
        <?php else: ?>
            <!-- Uploads Grid -->
            <div class="uploads-grid">
                <?php foreach ($uploads as $index => $upload):
                    $isVideo = strpos($upload['mime'], 'video') !== false;
                    $fileExtension = pathinfo($upload['filename'], PATHINFO_EXTENSION);
                    ?>
                    <div class="upload-card animate__animated animate__fadeInUp" style="animation-delay: <?php echo min($index * 0.05, 0.5); ?>s">
                        <div class="upload-media" onclick="showPreview('<?php echo $upload['id']; ?>', '<?php echo $isVideo ? 'video' : 'image'; ?>', '<?php echo $upload['local_path'] ?? ''; ?>')">
                            <?php if ($isVideo): ?>
                                <img src="<?php echo htmlspecialchars($upload['thumb_path'] ?: 'thumbnail.php?id=' . $upload['id'] . '&size=medium'); ?>"
                                     alt="<?php echo htmlspecialchars($upload['filename']); ?>"
                                     loading="lazy">
                                <div class="video-indicator">
                                    <i class="bi bi-play-circle-fill"></i>
                                </div>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($upload['thumb_path'] ?: 'thumbnail.php?id=' . $upload['id'] . '&size=medium'); ?>"
                                     alt="<?php echo htmlspecialchars($upload['filename']); ?>"
                                     loading="lazy">
                            <?php endif; ?>
                            <span class="upload-type-badge <?php echo $isVideo ? 'type-video' : 'type-image'; ?>">
                            <?php echo $isVideo ? 'VIDEO' : 'IMAGE'; ?>
                        </span>
                        </div>

                        <div class="upload-info">
                            <h5 class="upload-title" title="<?php echo htmlspecialchars($upload['filename']); ?>">
                                <?php echo htmlspecialchars(shorten_filename($upload['filename'])); ?>
                            </h5>

                            <p class="upload-description">
                                <?php echo !empty($upload['description']) ?
                                    htmlspecialchars($upload['description']) :
                                    '<em class="text-muted">No description</em>'; ?>
                            </p>

                            <div class="upload-meta">
                                <div class="upload-date">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo format_ts($upload['created_at']); ?>
                                </div>
                                <div class="upload-size">
                                    <i class="bi bi-hdd"></i>
                                    <?php echo number_format($upload['size'] / 1024 / 1024, 1); ?> MB
                                </div>
                            </div>

                            <?php if (!empty($upload['custom_message'])): ?>
                                <div class="upload-message mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-chat-dots"></i>
                                        <?php echo htmlspecialchars(substr($upload['custom_message'], 0, 50)); ?>...
                                    </small>
                                </div>
                            <?php endif; ?>

                            <div class="history-actions">
                                <a href="https://drive.google.com/file/d/<?php echo $upload['drive_id']; ?>/view"
                                   target="_blank"
                                   class="history-btn history-btn-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="https://drive.google.com/uc?export=download&id=<?php echo $upload['drive_id']; ?>"
                                   target="_blank"
                                   class="history-btn history-btn-success">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <form method="post" class="flex-1" onsubmit="return confirmDelete('<?php echo htmlspecialchars($upload['filename']); ?>')">
                                    <input type="hidden" name="delete_id" value="<?php echo $upload['id']; ?>">
                                    <button type="submit" class="history-btn history-btn-danger w-100">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="modern-pagination">
                    <?php
                    $query_params = [];
                    if ($filter_type !== 'all') $query_params['type'] = $filter_type;
                    if ($search_query) $query_params['search'] = $search_query;
                    if ($sort_by !== 'date_desc') $query_params['sort'] = $sort_by;

                    function build_page_url($page, $params) {
                        $params['page'] = $page;
                        return '?' . http_build_query($params);
                    }
                    ?>

                    <a href="<?php echo build_page_url($page - 1, $query_params); ?>"
                       class="page-link-modern <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1): ?>
                        <a href="<?php echo build_page_url(1, $query_params); ?>" class="page-link-modern">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="text-muted">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="<?php echo build_page_url($i, $query_params); ?>"
                           class="page-link-modern <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="text-muted">...</span>
                        <?php endif; ?>
                        <a href="<?php echo build_page_url($total_pages, $query_params); ?>" class="page-link-modern">
                            <?php echo $total_pages; ?>
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo build_page_url($page + 1, $query_params); ?>"
                       class="page-link-modern <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="preview-modal" onclick="closePreview()">
        <div class="preview-close">
            <i class="bi bi-x-lg"></i>
        </div>
        <div class="preview-content" id="previewContent">
            <!-- Content will be loaded here -->
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate counters
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseFloat(counter.getAttribute('data-count'));
                const decimals = counter.getAttribute('data-count').includes('.') ? 1 : 0;
                const animation = new countUp.CountUp(counter, target, {
                    duration: 2,
                    useEasing: true,
                    useGrouping: true,
                    decimalPlaces: decimals
                });
                if (!animation.error) {
                    animation.start();
                }
            });

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    updateFilters();
                }, 500);
            });

            // Sort functionality
            const sortSelect = document.getElementById('sortSelect');
            sortSelect.addEventListener('change', updateFilters);

            function updateFilters() {
                const params = new URLSearchParams(window.location.search);

                // Update search
                if (searchInput.value) {
                    params.set('search', searchInput.value);
                } else {
                    params.delete('search');
                }

                // Update sort
                params.set('sort', sortSelect.value);

                // Reset to page 1 when filters change
                params.set('page', '1');

                window.location.search = params.toString();
            }

            const toggleBtn = document.getElementById('toggleQuickUpload');
            const quickSection = document.getElementById('quickUpload');
            const quickFiles = document.getElementById('quickFiles');
            const quickCamera = document.getElementById('quickCamera');
            const quickList = document.getElementById('quickFileList');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    if (quickSection.style.display === 'none') {
                        quickSection.style.display = 'block';
                    } else {
                        quickSection.style.display = 'none';
                    }
                });
            }

            function updateQuickList(files) {
                quickList.innerHTML = '';
                Array.from(files).forEach(f => {
                    const div = document.createElement('div');
                    div.textContent = f.name;
                    quickList.appendChild(div);
                });
            }

            if (quickFiles) {
                quickFiles.addEventListener('change', () => updateQuickList(quickFiles.files));
            }
            if (quickCamera) {
                quickCamera.addEventListener('change', () => {
                    if (quickCamera.files.length > 0) {
                        updateQuickList(quickCamera.files);
                        quickFiles.files = quickCamera.files;
                    }
                });
            }
        });

        // Delete confirmation
        function confirmDelete(filename) {
            return confirm(`Are you sure you want to delete "${filename}"?`);
        }

        // Preview functionality
        function showPreview(uploadId, type, localPath) {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');

            if (type === 'video') {
                // For videos, just open in Google Drive
                window.open(`https://drive.google.com/file/d/${uploadId}/view`, '_blank');
                return;
            }

            // Show loading
            content.innerHTML = '<div class="loading text-white"><i class="bi bi-hourglass-split"></i> Loading...</div>';
            modal.style.display = 'flex';

            // Load full size image
            const img = new Image();
            img.onload = function() {
                content.innerHTML = '';
                content.appendChild(img);
            };
            img.onerror = function() {
                content.innerHTML = '<div class="text-white"><i class="bi bi-exclamation-triangle"></i> Failed to load image</div>';
            };
            if (localPath) {
                img.src = localPath;
            } else {
                img.src = `thumbnail.php?id=${uploadId}&size=large`;
            }
        }

        function closePreview(event) {
            if (event) {
                event.stopPropagation();
            }
            document.getElementById('previewModal').style.display = 'none';
        }

        // Keyboard navigation for preview
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>