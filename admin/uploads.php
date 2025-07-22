<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$errors = [];
$success = [];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = ?');
    $stmt->execute([$_POST['delete_id']]);
    $success[] = 'Upload deleted successfully';
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $stmt = $pdo->prepare('UPDATE uploads SET status_id = ? WHERE id = ?');
    $stmt->execute([$_POST['status_id'], $_POST['upload_id']]);
    $success[] = 'Status updated successfully';
}

// Get filter parameters
$store_id = $_GET['store_id'] ?? '';
$status_id = $_GET['status_id'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$media_type = $_GET['media_type'] ?? '';

// Build query with filters
$query = 'SELECT u.*, s.name as store_name, u.mime, u.drive_id,
          us.name AS status_name, us.color AS status_color
          FROM uploads u
          JOIN stores s ON u.store_id = s.id
          LEFT JOIN upload_statuses us ON u.status_id = us.id
          WHERE 1=1';

$params = [];

if ($store_id) {
    $query .= ' AND u.store_id = ?';
    $params[] = $store_id;
}

if ($status_id) {
    $query .= ' AND u.status_id = ?';
    $params[] = $status_id;
}

if ($search) {
    $query .= ' AND (u.filename LIKE ? OR u.description LIKE ? OR u.custom_message LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($date_from) {
    $query .= ' AND DATE(u.created_at) >= ?';
    $params[] = $date_from;
}

if ($date_to) {
    $query .= ' AND DATE(u.created_at) <= ?';
    $params[] = $date_to;
}

if ($media_type === 'image') {
    $query .= ' AND u.mime LIKE "image/%"';
} elseif ($media_type === 'video') {
    $query .= ' AND u.mime LIKE "video/%"';
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total count
$count_query = str_replace('SELECT u.*, s.name as store_name, u.mime, u.drive_id, us.name AS status_name, us.color AS status_color', 'SELECT COUNT(*)', $query);
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_pages = ceil($total / $per_page);

// Get uploads
$query .= ' ORDER BY u.created_at DESC LIMIT ' . $per_page . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stores for filter dropdown
$stores = $pdo->query('SELECT id, name FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Get upload statuses
$statuses = $pdo->query('SELECT * FROM upload_statuses ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$stats_query = 'SELECT 
    COUNT(*) as total_uploads,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_uploads,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today_uploads,
    COUNT(CASE WHEN mime LIKE "image/%" THEN 1 END) as total_images,
    COUNT(CASE WHEN mime LIKE "video/%" THEN 1 END) as total_videos,
    COUNT(CASE WHEN status_id IS NULL THEN 1 END) as pending_review
    FROM uploads';

if ($store_id) {
    $stats_query .= ' WHERE store_id = ?';
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([$store_id]);
} else {
    $stmt = $pdo->query($stats_query);
}
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$active = 'uploads';
include __DIR__.'/header.php';
?>


    <div class="animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <h1 class="page-title">Content Review</h1>
            <p class="page-subtitle">Review and manage all uploaded content</p>
        </div>

        <!-- Alerts -->
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($e); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?php foreach ($success as $s): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($s); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-cloud-upload-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_uploads']; ?>">0</div>
                <div class="stat-label">Total Uploads</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card success animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-calendar-week-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['week_uploads']; ?>">0</div>
                <div class="stat-label">This Week</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card warning animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['today_uploads']; ?>">0</div>
                <div class="stat-label">Today</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card info animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_images']; ?>">0</div>
                <div class="stat-label">Images</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card danger animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-camera-video-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_videos']; ?>">0</div>
                <div class="stat-label">Videos</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card secondary animate__animated animate__fadeInUp delay-50">
                <div class="stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['pending_review']; ?>">0</div>
                <div class="stat-label">Pending Review</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card animate__animated animate__fadeIn delay-60">
            <h5 class="filter-title">
                <i class="bi bi-funnel"></i> Filters
            </h5>
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <div class="search-input-group">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="form-control form-control-modern"
                               placeholder="Search uploads..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="store_id" class="form-select form-select-modern">
                        <option value="">All Stores</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>"
                                <?php echo $store_id == $store['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($store['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status_id" class="form-select form-select-modern">
                        <option value="">All Statuses</option>
                        <option value="NULL" <?php echo $status_id === 'NULL' ? 'selected' : ''; ?>>No Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status['id']; ?>"
                                <?php echo $status_id == $status['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="media_type" class="form-select form-select-modern">
                        <option value="">All Media</option>
                        <option value="image" <?php echo $media_type == 'image' ? 'selected' : ''; ?>>Images</option>
                        <option value="video" <?php echo $media_type == 'video' ? 'selected' : ''; ?>>Videos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control form-control-modern"
                           value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From Date">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control form-control-modern"
                           value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To Date">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-filter">
                        <i class="bi bi-funnel-fill me-2"></i>Apply Filters
                    </button>
                    <a href="uploads.php" class="btn btn-reset ms-2">
                        <i class="bi bi-arrow-clockwise me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Uploads Table -->
        <div class="uploads-card animate__animated animate__fadeIn delay-70">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-cloud-upload"></i>
                    Uploaded Content
                    <span class="results-count">(<?php echo $total; ?> results)</span>
                </h5>
            </div>

            <?php if (empty($uploads)): ?>
                <div class="empty-state">
                    <i class="bi bi-cloud-slash"></i>
                    <h4>No uploads found</h4>
                    <p>Try adjusting your filters or wait for stores to upload content</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Upload Info</th>
                            <th>Store</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($uploads as $upload):
                            $isVideo = strpos($upload['mime'], 'video') !== false;
                            ?>
                            <tr>
                                <td>
                                    <div class="media-preview">
                                        <img src="thumbnail.php?id=<?php echo $upload['id']; ?>&size=small"
                                             alt="<?php echo htmlspecialchars($upload['filename']); ?>"
                                             loading="lazy">
                                        <?php if ($isVideo): ?>
                                            <div class="video-indicator">
                                                <i class="bi bi-play-fill"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                <span class="upload-filename">
                                    <?php echo htmlspecialchars(shorten_filename($upload['filename'])); ?>
                                </span>
                                    <div class="upload-meta">
                                        <?php if (!empty($upload['description'])): ?>
                                            <div><i class="bi bi-chat-text me-1"></i> <?php echo htmlspecialchars($upload['description']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($upload['custom_message'])): ?>
                                            <div><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($upload['custom_message']); ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <i class="bi bi-hdd me-1"></i> <?php echo number_format($upload['size'] / 1024 / 1024, 1); ?> MB
                                            • <?php echo $isVideo ? 'Video' : 'Image'; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="store-name"><?php echo htmlspecialchars($upload['store_name']); ?></span>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($upload['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($upload['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($upload['status_name']): ?>
                                        <span class="status-badge" style="background-color: <?php echo htmlspecialchars($upload['status_color']); ?>;">
                                        <?php echo htmlspecialchars($upload['status_name']); ?>
                                    </span>
                                    <?php else: ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="upload_id" value="<?php echo $upload['id']; ?>">
                                            <select name="status_id" class="status-select" onchange="this.form.submit()">
                                                <option value="">Set Status</option>
                                                <?php foreach ($statuses as $status): ?>
                                                    <option value="<?php echo $status['id']; ?>">
                                                        <?php echo htmlspecialchars($status['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="https://drive.google.com/file/d/<?php echo $upload['drive_id']; ?>/view"
                                       target="_blank" class="btn btn-action btn-action-primary" title="View in Drive">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="https://drive.google.com/uc?export=download&id=<?php echo $upload['drive_id']; ?>"
                                       class="btn btn-action btn-action-success" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this upload?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $upload['id']; ?>">
                                        <button type="submit" class="btn btn-action btn-action-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-modern">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                               class="page-link-modern">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                               class="page-link-modern">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link-modern disabled">
                            <i class="bi bi-chevron-double-left"></i>
                        </span>
                            <span class="page-link-modern disabled">
                            <i class="bi bi-chevron-left"></i>
                        </span>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if ($start > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                               class="page-link-modern">1</a>
                            <?php if ($start > 2): ?>
                                <span class="page-link-modern disabled">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="page-link-modern active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                   class="page-link-modern"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="page-link-modern disabled">...</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                               class="page-link-modern"><?php echo $total_pages; ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                               class="page-link-modern">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                               class="page-link-modern">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link-modern disabled">
                            <i class="bi bi-chevron-right"></i>
                        </span>
                            <span class="page-link-modern disabled">
                            <i class="bi bi-chevron-double-right"></i>
                        </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>