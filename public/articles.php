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

$success = [];
$errors = [];

// Get store info
$stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch();

// Handle article submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_article'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $category = $_POST['category'] ?? 'blog';
    $tags = trim($_POST['tags'] ?? '');

    if (empty($title)) {
        $errors[] = 'Article title is required';
    }
    if (empty($content)) {
        $errors[] = 'Article content is required';
    }

    // Get max article length from settings
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE name = ?');
    $stmt->execute(['max_article_length']);
    $maxLength = intval($stmt->fetchColumn() ?: 50000);

    if (strlen($content) > $maxLength) {
        $errors[] = "Article content is too long (maximum $maxLength characters)";
    }

    if (empty($errors)) {
        try {
            // Detect available columns
            $cols = $pdo->query("SHOW COLUMNS FROM articles")->fetchAll(PDO::FETCH_COLUMN);
            $hasCategory = in_array('category', $cols, true);
            $hasTags = in_array('tags', $cols, true);
            $hasImages = in_array('images', $cols, true);

            // Handle image uploads
            $uploadedImages = [];
            if (!empty($_FILES['article_images']['name'][0])) {
                $storeFolderId = get_or_create_store_folder($store_id);
                $totalFiles = count($_FILES['article_images']['name']);
                for ($i = 0; $i < $totalFiles; $i++) {
                    if (!is_uploaded_file($_FILES['article_images']['tmp_name'][$i])) continue;

                    $tmpFile = $_FILES['article_images']['tmp_name'][$i];
                    $originalName = $_FILES['article_images']['name'][$i];
                    $fileSize = $_FILES['article_images']['size'][$i];
                    $fileError = $_FILES['article_images']['error'][$i];

                    if ($fileError !== UPLOAD_ERR_OK) {
                        $errors[] = "Error uploading $originalName: " . getUploadErrorMessage($fileError);
                        continue;
                    }
                    if ($fileSize > 20 * 1024 * 1024) {
                        $errors[] = "$originalName is too large (max 20MB)";
                        continue;
                    }

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $tmpFile);
                    finfo_close($finfo);

                    if (strpos($mimeType, 'image/') !== 0) {
                        $errors[] = "$originalName is not an image";
                        continue;
                    }

                    try {
                        $subDir = 'articles/' . $store_id . '/' . date('Y/m');
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
                        $driveId = drive_upload($localPath, $mimeType, $originalName, $storeFolderId);
                        $uploadedImages[] = [
                                'filename' => $originalName,
                                'drive_id' => $driveId,
                                'local_path' => 'uploads/' . $subDir . '/' . $safeName,
                                'thumb_path' => $thumbUrl
                        ];
                    } catch (Exception $e) {
                        $errors[] = "Failed to upload $originalName: " . $e->getMessage();
                    }
                }
            }

            if ($errors) {
                throw new Exception('Image upload failed');
            }

            $fields = ['store_id', 'title', 'content', 'excerpt'];
            $placeholders = '?, ?, ?, ?';
            $values = [$store_id, $title, $content, $excerpt];

            if ($hasCategory) {
                $fields[] = 'category';
                $placeholders .= ', ?';
                $values[] = $category;
            }
            if ($hasTags) {
                $fields[] = 'tags';
                $placeholders .= ', ?';
                $values[] = $tags;
            }
            if ($hasImages) {
                $fields[] = 'images';
                $placeholders .= ', ?';
                $values[] = json_encode($uploadedImages);
            }

            $fields[] = 'status';
            $placeholders .= ', ?';
            $values[] = 'submitted';

            $fields[] = 'created_at';
            $placeholders .= ', NOW()';

            $fields[] = 'ip';
            $placeholders .= ', ?';
            $values[] = $_SERVER['REMOTE_ADDR'];

            $sql = 'INSERT INTO articles (' . implode(',', $fields) . ') VALUES (' . $placeholders . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $success[] = 'Article submitted successfully!';

            // Send email notifications
            $emailSettings = [];
            $settingsQuery = $pdo->query("SELECT name, value FROM settings WHERE name IN ('notification_email', 'email_from_name', 'email_from_address', 'admin_article_notification_subject', 'store_article_notification_subject')");
            while ($row = $settingsQuery->fetch()) {
                $emailSettings[$row['name']] = $row['value'];
            }

            $fromName = $emailSettings['email_from_name'] ?? 'Cosmick Media';
            $fromAddress = $emailSettings['email_from_address'] ?? 'noreply@cosmickmedia.com';
            $adminSubject = str_replace('{store_name}', $store_name, $emailSettings['admin_article_notification_subject'] ?? 'New article submission from {store_name}');
            $storeSubject = str_replace('{store_name}', $store_name, $emailSettings['store_article_notification_subject'] ?? 'Article Submission Confirmation - Cosmick Media');

            $headers = "From: $fromName <$fromAddress>\r\n";
            $headers .= "Reply-To: $fromAddress\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            // Notify admin
            if (!empty($emailSettings['notification_email'])) {
                $emailList = array_map('trim', explode(',', $emailSettings['notification_email']));
                $message = "New article submitted by: $store_name\n\n";
                $message .= "Title: $title\n";
                $message .= "Category: " . ucfirst($category) . "\n";
                $message .= "Excerpt: " . substr($excerpt ?: strip_tags($content), 0, 200) . "...\n\n";
                $message .= "Login to the admin panel to review this article.";

                foreach ($emailList as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        mail($email, $adminSubject, $message, $headers);
                    }
                }
            }

            // Send confirmation to store
            if (!empty($store['admin_email'])) {
                $confirmMessage = "Dear $store_name,\n\n";
                $confirmMessage .= "Thank you for submitting your article to Cosmick Media.\n\n";
                $confirmMessage .= "Article Title: $title\n";
                $confirmMessage .= "Category: " . ucfirst($category) . "\n\n";
                $confirmMessage .= "Your article is now pending review by our team. ";
                $confirmMessage .= "We will notify you once it has been reviewed.\n\n";
                $confirmMessage .= "Best regards,\n$fromName";

                mail($store['admin_email'], $storeSubject, $confirmMessage, $headers);
            }

        } catch (PDOException $e) {
            $errors[] = 'Failed to submit article. Please try again.';
            error_log("Article submission error: " . $e->getMessage());
        }
    }
}

// Get statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_articles,
        COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending_articles,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_articles,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_articles,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_articles,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_articles
    FROM articles 
    WHERE store_id = ?
");
$stats_stmt->execute([$store_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate approval rate
$approval_rate = $stats['total_articles'] > 0 ?
        round(($stats['approved_articles'] / $stats['total_articles']) * 100) : 0;

// Get store's articles with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ['store_id = ?'];
$params = [$store_id];

if ($filter_status !== 'all') {
    $where_conditions[] = 'status = ?';
    $params[] = $filter_status;
}

// Check if category column exists
$checkColumn = $pdo->query("SHOW COLUMNS FROM articles LIKE 'category'");
$hasCategory = $checkColumn->fetch() !== false;

if ($hasCategory && $filter_category !== 'all') {
    $where_conditions[] = 'category = ?';
    $params[] = $filter_category;
}

if ($search_query) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE $where_clause");
$stmt->execute($params);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get articles
$orderBy = $_GET['sort'] ?? 'date_desc';
$order_clause = match($orderBy) {
    'date_asc' => 'created_at ASC',
    'title_asc' => 'title ASC',
    'title_desc' => 'title DESC',
    'status' => 'status ASC, created_at DESC',
    default => 'created_at DESC'
};

$stmt = $pdo->prepare("
    SELECT * FROM articles
    WHERE $where_clause
    ORDER BY $order_clause
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active tab
$tab = $_GET['tab'] ?? 'submit';

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

// Add versioned additional styles before including header
$version = trim(file_get_contents(__DIR__.'/../VERSION'));
$extra_head = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">'
        . '<link rel="stylesheet" href="inc/css/articles.css?v=' . $version . '">';

include __DIR__.'/header.php';
?>

    <div class="articles-container animate__animated animate__fadeIn">
        <!-- Header Section -->
        <div class="articles-header">
            <div>
                <h2 class="articles-title">Content Articles</h2>
                <p class="articles-subtitle"><?php echo htmlspecialchars($store_name); ?></p>
            </div>
            <a href="index.php" class="btn btn-modern-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

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

        <!-- Statistics Dashboard -->
        <div class="stats-dashboard">
            <div class="stat-card primary animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-file-text-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_articles']; ?>">0</div>
                <div class="stat-label">Total Articles</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card warning animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['pending_articles']; ?>">0</div>
                <div class="stat-label">Pending Review</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card success animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['approved_articles']; ?>">0</div>
                <div class="stat-label">Approved</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card danger animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['rejected_articles']; ?>">0</div>
                <div class="stat-label">Rejected</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card secondary animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['draft_articles']; ?>">0</div>
                <div class="stat-label">Drafts</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card info animate__animated animate__fadeInUp delay-50">
                <div class="stat-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $approval_rate; ?>">0</div>
                <div class="stat-label">Approval Rate %</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-navigation animate__animated animate__fadeIn delay-60">
            <a class="tab-btn <?php echo $tab === 'submit' ? 'active' : ''; ?>" href="?tab=submit">
                <i class="bi bi-pencil-square"></i>
                <span>Submit Article</span>
            </a>
            <a class="tab-btn <?php echo $tab === 'history' ? 'active' : ''; ?>" href="?tab=history">
                <i class="bi bi-clock-history"></i>
                <span>Article History</span>
                <?php if ($total_count > 0): ?>
                    <span class="tab-count"><?php echo $total_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ($tab === 'submit'): ?>
            <!-- Submit Article Tab -->
            <div class="form-container animate__animated animate__fadeIn delay-70">
                <div class="form-header">
                    <h3 class="section-title">
                        <i class="bi bi-pencil-square"></i>
                        Submit New Article
                    </h3>
                    <p class="section-subtitle">Share your story, news, or press release with us</p>
                </div>

                <form method="post" enctype="multipart/form-data" id="articleForm">
                    <input type="hidden" name="submit_article" value="1">

                    <div class="form-grid">
                        <!-- Title Field -->
                        <div class="form-group full-width">
                            <label for="title" class="form-label">
                                Article Title <span class="required">*</span>
                            </label>
                            <input type="text"
                                   class="form-control-modern"
                                   id="title"
                                   name="title"
                                   required
                                   placeholder="Enter a compelling title"
                                   maxlength="255">
                            <div class="field-helper">
                                <span id="titleCount">0</span> / 255 characters
                            </div>
                        </div>

                        <!-- Category Selection -->
                        <div class="form-group">
                            <label class="form-label">
                                Category <span class="required">*</span>
                            </label>
                            <div class="category-grid">
                                <label class="category-card">
                                    <input type="radio" name="category" value="blog" checked>
                                    <div class="category-content">
                                        <i class="bi bi-journal-text"></i>
                                        <span>Blog</span>
                                    </div>
                                </label>
                                <label class="category-card">
                                    <input type="radio" name="category" value="news">
                                    <div class="category-content">
                                        <i class="bi bi-newspaper"></i>
                                        <span>News</span>
                                    </div>
                                </label>
                                <label class="category-card">
                                    <input type="radio" name="category" value="press">
                                    <div class="category-content">
                                        <i class="bi bi-megaphone"></i>
                                        <span>Press</span>
                                    </div>
                                </label>
                                <label class="category-card">
                                    <input type="radio" name="category" value="story">
                                    <div class="category-content">
                                        <i class="bi bi-book"></i>
                                        <span>Story</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Excerpt Field -->
                        <div class="form-group">
                            <label for="excerpt" class="form-label">
                                Brief Description
                                <span class="form-label-optional">(Optional)</span>
                            </label>
                            <textarea class="form-control-modern"
                                      id="excerpt"
                                      name="excerpt"
                                      rows="3"
                                      placeholder="Brief summary of your article"
                                      maxlength="500"></textarea>
                            <div class="field-helper">
                                <span id="excerptCount">0</span> / 500 characters
                            </div>
                        </div>

                        <!-- Tags Field -->
                        <div class="form-group">
                            <label for="tags" class="form-label">
                                Tags
                                <span class="form-label-optional">(Optional)</span>
                            </label>
                            <input type="text"
                                   class="form-control-modern"
                                   id="tags"
                                   name="tags"
                                   placeholder="Add tags separated by commas">
                            <div class="field-helper">
                                E.g., marketing, social media, tips
                            </div>
                            <div class="tag-suggestions" id="tagSuggestions"></div>
                        </div>

                        <!-- Media Upload -->
                        <div class="form-group full-width">
                            <label class="form-label">
                                Article Images
                                <span class="form-label-optional">(Optional)</span>
                            </label>
                            <div class="upload-area small" id="articleImageArea">
                                <i class="bi bi-cloud-upload upload-icon"></i>
                                <p class="upload-text">Drag & drop images or click to browse</p>
                                <p class="upload-subtext">PNG, JPG up to 20MB</p>
                                <div class="file-buttons">
                                    <button type="button" class="btn-modern btn-modern-primary" onclick="document.getElementById('articleImages').click();">
                                        <i class="bi bi-folder2-open"></i> Browse Files
                                    </button>
                                    <button type="button" class="btn-modern btn-modern-secondary" onclick="document.getElementById('articleCamera').click();">
                                        <i class="bi bi-camera"></i> Use Camera
                                    </button>
                                </div>
                                <input class="d-none" type="file" name="article_images[]" id="articleImages" multiple accept="image/*">
                                <input type="file" id="articleCamera" accept="image/*" capture="camera" class="d-none">
                            </div>
                            <div id="articleFileList" class="file-list"></div>
                        </div>

                        <!-- Content Editor -->
                        <div class="form-group full-width">
                            <label for="content" class="form-label">
                                Article Content <span class="required">*</span>
                            </label>
                            <div class="editor-wrapper">
                                <textarea class="form-control-modern" id="content" name="content"></textarea>
                            </div>
                            <div class="field-helper">
                                <i class="bi bi-info-circle"></i>
                                You can paste content from Word or Google Docs. Formatting will be preserved.
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-modern btn-modern-secondary" onclick="saveDraft()">
                            <i class="bi bi-save"></i>
                            Save Draft
                        </button>
                        <button type="submit" class="btn-modern btn-modern-primary" id="submitBtn">
                            <i class="bi bi-send"></i>
                            Submit Article
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Article History Tab -->
            <?php if (empty($articles)): ?>
                <div class="empty-state animate__animated animate__fadeIn">
                    <i class="bi bi-file-text"></i>
                    <h3>No articles found</h3>
                    <p>
                        <?php if ($search_query || $filter_status !== 'all' || $filter_category !== 'all'): ?>
                            Try adjusting your filters or search query
                        <?php else: ?>
                            Start by submitting your first article
                        <?php endif; ?>
                    </p>
                    <a href="?tab=submit" class="btn btn-modern-primary">
                        <i class="bi bi-pencil-square"></i> Write Article
                    </a>
                </div>
            <?php else: ?>
                <!-- Filters Section -->
                <div class="filters-section animate__animated animate__fadeIn delay-70">
                    <div class="filters-row">
                        <div class="filter-group">
                            <span class="filter-label">Status:</span>
                            <a href="?tab=history&status=all"
                               class="filter-button <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                                All
                            </a>
                            <a href="?tab=history&status=submitted"
                               class="filter-button <?php echo $filter_status === 'submitted' ? 'active' : ''; ?>">
                                Submitted
                            </a>
                            <a href="?tab=history&status=approved"
                               class="filter-button <?php echo $filter_status === 'approved' ? 'active' : ''; ?>">
                                Approved
                            </a>
                            <a href="?tab=history&status=rejected"
                               class="filter-button <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>">
                                Rejected
                            </a>
                            <a href="?tab=history&status=draft"
                               class="filter-button <?php echo $filter_status === 'draft' ? 'active' : ''; ?>">
                                Drafts
                            </a>
                        </div>

                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text"
                                   class="search-input"
                                   placeholder="Search articles..."
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   id="searchInput">
                        </div>

                        <div class="filter-group">
                            <span class="filter-label">Sort:</span>
                            <select class="sort-select" id="sortSelect">
                                <option value="date_desc" <?php echo $orderBy === 'date_desc' ? 'selected' : ''; ?>>
                                    Newest First
                                </option>
                                <option value="date_asc" <?php echo $orderBy === 'date_asc' ? 'selected' : ''; ?>>
                                    Oldest First
                                </option>
                                <option value="title_asc" <?php echo $orderBy === 'title_asc' ? 'selected' : ''; ?>>
                                    Title A-Z
                                </option>
                                <option value="title_desc" <?php echo $orderBy === 'title_desc' ? 'selected' : ''; ?>>
                                    Title Z-A
                                </option>
                                <option value="status" <?php echo $orderBy === 'status' ? 'selected' : ''; ?>>
                                    By Status
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Articles Grid -->
                <div class="articles-grid">
                    <?php foreach ($articles as $index => $article):
                        $category = $article['category'] ?? 'blog';
                        $categoryIcons = [
                                'blog' => 'bi-journal-text',
                                'news' => 'bi-newspaper',
                                'press' => 'bi-megaphone',
                                'story' => 'bi-book'
                        ];
                        ?>
                        <div class="article-card animate__animated animate__fadeInUp"
                             style="animation-delay: <?php echo min($index * 0.05, 0.5); ?>s">
                            <div class="article-header">
                            <span class="article-category">
                                <i class="bi <?php echo $categoryIcons[$category] ?? 'bi-file-text'; ?>"></i>
                                <?php echo ucfirst($category); ?>
                            </span>
                                <span class="article-status status-<?php echo $article['status']; ?>">
                                <?php echo ucfirst($article['status']); ?>
                            </span>
                            </div>

                            <h5 class="article-title">
                                <?php echo htmlspecialchars($article['title']); ?>
                            </h5>

                            <p class="article-excerpt">
                                <?php
                                $excerpt = $article['excerpt'] ?: strip_tags($article['content']);
                                echo htmlspecialchars(substr($excerpt, 0, 150)) . '...';
                                ?>
                            </p>

                            <?php if (!empty($article['tags'])): ?>
                                <div class="article-tags">
                                    <?php
                                    $tags = array_slice(explode(',', $article['tags']), 0, 3);
                                    foreach ($tags as $tag):
                                        ?>
                                        <span class="tag-badge">
                                        <i class="bi bi-hash"></i><?php echo trim($tag); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="article-footer">
                                <div class="article-date">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo format_ts($article['created_at']); ?>
                                </div>
                                <div class="article-actions">
                                    <button class="action-btn primary"
                                            onclick="viewArticle(<?php echo $article['id']; ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php if ($article['status'] === 'draft'): ?>
                                        <a href="?tab=submit&edit=<?php echo $article['id']; ?>"
                                           class="action-btn secondary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($article['admin_notes'])): ?>
                                <div class="admin-notes">
                                    <i class="bi bi-chat-square-text"></i>
                                    <strong>Admin Notes:</strong>
                                    <?php echo htmlspecialchars($article['admin_notes']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="modern-pagination">
                        <?php
                        $query_params = [];
                        if ($filter_status !== 'all') $query_params['status'] = $filter_status;
                        if ($filter_category !== 'all') $query_params['category'] = $filter_category;
                        if ($search_query) $query_params['search'] = $search_query;
                        if ($orderBy !== 'date_desc') $query_params['sort'] = $orderBy;
                        $query_params['tab'] = 'history';

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
                                <span class="page-dots">...</span>
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
                                <span class="page-dots">...</span>
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
        <?php endif; ?>

        <!-- Autosave Indicator -->
        <div class="autosave-indicator" id="autosaveIndicator">
            <i class="bi bi-check-circle"></i>
            <span>Draft saved</span>
        </div>
    </div>

    <!-- Article View Modal -->
    <div class="modal fade" id="articleModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="articleModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="articleModalBody">
                    <!-- Article content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- CKEditor CDN -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>

    <script>
        let editor;
        let autosaveTimer;
        let selectedFiles = [];

        // Initialize counters animation
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat counters
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const animation = new countUp.CountUp(counter, target, {
                    duration: 2,
                    useEasing: true,
                    useGrouping: true,
                    suffix: counter.closest('.stat-card').classList.contains('info') ? '%' : ''
                });
                if (!animation.error) {
                    animation.start();
                }
            });

            // Character counters
            const titleInput = document.getElementById('title');
            const excerptInput = document.getElementById('excerpt');

            if (titleInput) {
                titleInput.addEventListener('input', function() {
                    document.getElementById('titleCount').textContent = this.value.length;
                });
            }

            if (excerptInput) {
                excerptInput.addEventListener('input', function() {
                    document.getElementById('excerptCount').textContent = this.value.length;
                });
            }

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        updateFilters();
                    }, 500);
                });
            }

            // Sort functionality
            const sortSelect = document.getElementById('sortSelect');
            if (sortSelect) {
                sortSelect.addEventListener('change', updateFilters);
            }

            function updateFilters() {
                const params = new URLSearchParams(window.location.search);
                params.set('tab', 'history');

                if (searchInput && searchInput.value) {
                    params.set('search', searchInput.value);
                } else {
                    params.delete('search');
                }

                if (sortSelect) {
                    params.set('sort', sortSelect.value);
                }

                params.set('page', '1');
                window.location.search = params.toString();
            }

            // File upload handling
            setupFileUpload();

            // Initialize CKEditor if content field exists
            if (document.querySelector('#content')) {
                initializeEditor();
            }
        });

        function setupFileUpload() {
            const imgInput = document.getElementById('articleImages');
            const cameraInput = document.getElementById('articleCamera');
            const uploadArea = document.getElementById('articleImageArea');
            const fileList = document.getElementById('articleFileList');

            if (!imgInput || !uploadArea) return;

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
                const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
                handleFiles(files);
            });

            // File input change
            imgInput.addEventListener('change', () => {
                handleFiles(Array.from(imgInput.files));
            });

            // Camera input
            if (cameraInput) {
                cameraInput.addEventListener('change', () => {
                    if (cameraInput.files.length > 0) {
                        handleFiles(Array.from(cameraInput.files));
                    }
                });
            }
        }

        function handleFiles(files) {
            selectedFiles = selectedFiles.concat(files).slice(0, 10); // Max 10 files
            updateFileList();
            updateFileInput();
        }

        function updateFileList() {
            const fileList = document.getElementById('articleFileList');
            if (!fileList) return;

            fileList.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'file-item';
                item.innerHTML = `
                <div class="file-icon">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="file-info">
                    <p class="file-name">${file.name}</p>
                    <p class="file-size">${formatFileSize(file.size)}</p>
                </div>
                <i class="bi bi-x-circle-fill file-remove" onclick="removeFile(${index})"></i>
            `;
                fileList.appendChild(item);
            });
        }

        function updateFileInput() {
            const imgInput = document.getElementById('articleImages');
            if (!imgInput) return;

            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            imgInput.files = dt.files;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            updateFileInput();
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Initialize CKEditor
        function initializeEditor() {
            ClassicEditor
                .create(document.querySelector('#content'), {
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', 'underline', 'strikethrough', '|',
                        'link', 'bulletedList', 'numberedList', '|',
                        'outdent', 'indent', '|',
                        'blockQuote', 'insertTable', '|',
                        'undo', 'redo'
                    ],
                    heading: {
                        options: [
                            { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                            { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                        ]
                    }
                })
                .then(newEditor => {
                    editor = newEditor;
                    editor.model.document.on('change:data', () => {
                        triggerAutosave();
                    });
                    loadDraft();
                })
                .catch(error => {
                    console.error(error);
                });
        }

        // Autosave functionality
        function triggerAutosave() {
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(saveDraft, 2000);
        }

        function saveDraft(showIndicator = true) {
            if (!document.getElementById('title')) return;

            const title = document.getElementById('title').value;
            const excerpt = document.getElementById('excerpt').value;
            const content = editor ? editor.getData() : '';
            const category = document.querySelector('input[name="category"]:checked')?.value || 'blog';
            const tags = document.getElementById('tags').value;

            if (!title && !content) return;

            const draft = {
                title: title,
                excerpt: excerpt,
                content: content,
                category: category,
                tags: tags,
                savedAt: new Date().toISOString()
            };

            localStorage.setItem('articleDraft', JSON.stringify(draft));

            if (showIndicator) {
                const indicator = document.getElementById('autosaveIndicator');
                indicator.classList.add('show');
                setTimeout(() => {
                    indicator.classList.remove('show');
                }, 2000);
            }
        }

        function loadDraft() {
            const draft = localStorage.getItem('articleDraft');
            if (draft && document.getElementById('title') && !document.getElementById('title').value) {
                const draftData = JSON.parse(draft);
                const savedDate = new Date(draftData.savedAt);

                if (confirm(`Restore draft from ${savedDate.toLocaleDateString()} ${savedDate.toLocaleTimeString()}?`)) {
                    document.getElementById('title').value = draftData.title;
                    document.getElementById('excerpt').value = draftData.excerpt;
                    document.getElementById('tags').value = draftData.tags;

                    const categoryInput = document.querySelector(`input[name="category"][value="${draftData.category}"]`);
                    if (categoryInput) categoryInput.checked = true;

                    if (editor) {
                        editor.setData(draftData.content);
                    }

                    document.getElementById('titleCount').textContent = draftData.title.length;
                    document.getElementById('excerptCount').textContent = draftData.excerpt.length;
                }
            }
        }

        // Form submission
        const articleForm = document.getElementById('articleForm');
        if (articleForm) {
            articleForm.addEventListener('submit', function(e) {
                const contentField = document.getElementById('content');
                const contentValue = editor ? editor.getData().trim() : contentField.value.trim();
                if (!contentValue) {
                    e.preventDefault();
                    alert('Article content is required');
                    return;
                }
                contentField.value = contentValue;

                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting...';

                localStorage.removeItem('articleDraft');
            });
        }

        // View article function
        function viewArticle(articleId) {
            fetch(`view_article.php?id=${articleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const article = data.article;
                        document.getElementById('articleModalTitle').textContent = article.title;

                        let contentHtml = '<div class="article-modal-content">';

                        // Meta information
                        contentHtml += '<div class="article-modal-meta">';
                        contentHtml += `<span class="meta-item"><i class="bi bi-calendar3"></i> ${article.created_at}</span>`;
                        contentHtml += `<span class="meta-item status-${article.status}"><i class="bi bi-tag"></i> ${article.status}</span>`;
                        if (article.category) {
                            contentHtml += `<span class="meta-item"><i class="bi bi-folder"></i> ${article.category}</span>`;
                        }
                        contentHtml += '</div>';

                        // Article content
                        contentHtml += '<div class="article-content-body">';
                        contentHtml += article.content;
                        contentHtml += '</div>';

                        if (article.admin_notes) {
                            contentHtml += '<div class="admin-notes-modal">';
                            contentHtml += '<strong><i class="bi bi-chat-square-text"></i> Admin Notes:</strong><br>';
                            contentHtml += article.admin_notes;
                            contentHtml += '</div>';
                        }

                        contentHtml += '</div>';

                        document.getElementById('articleModalBody').innerHTML = contentHtml;

                        const modal = new bootstrap.Modal(document.getElementById('articleModal'));
                        modal.show();
                    } else {
                        alert('Failed to load article');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load article');
                });
        }

        // Tag suggestions
        const tagInput = document.getElementById('tags');
        const tagSuggestions = document.getElementById('tagSuggestions');
        const commonTags = [
            'marketing', 'social media', 'content', 'strategy', 'tips',
            'business', 'branding', 'digital', 'seo', 'advertising'
        ];

        if (tagInput && tagSuggestions) {
            tagInput.addEventListener('focus', function() {
                const currentTags = this.value.split(',').map(t => t.trim().toLowerCase());
                const availableTags = commonTags.filter(tag => !currentTags.includes(tag));

                if (availableTags.length > 0) {
                    tagSuggestions.innerHTML = availableTags
                        .slice(0, 5)
                        .map(tag => `<div class="tag-suggestion" data-tag="${tag}">${tag}</div>`)
                        .join('');
                    tagSuggestions.style.display = 'block';
                }
            });

            tagInput.addEventListener('blur', function() {
                setTimeout(() => {
                    tagSuggestions.style.display = 'none';
                }, 200);
            });

            tagSuggestions.addEventListener('click', function(e) {
                if (e.target.classList.contains('tag-suggestion')) {
                    const tag = e.target.dataset.tag;
                    const currentValue = tagInput.value.trim();
                    if (currentValue) {
                        tagInput.value = currentValue + ', ' + tag;
                    } else {
                        tagInput.value = tag;
                    }
                    tagInput.focus();
                }
            });
        }
    </script>

<?php include __DIR__.'/footer.php'; ?>