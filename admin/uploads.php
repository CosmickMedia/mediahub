<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/drive.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $upload_id = $_POST['delete_id'];

    // Get upload details
    $stmt = $pdo->prepare('SELECT drive_id FROM uploads WHERE id = ?');
    $stmt->execute([$upload_id]);
    $upload = $stmt->fetch();

    if ($upload) {
        // Delete from Google Drive
        try {
            drive_delete($upload['drive_id']);
        } catch (Exception $e) {
            // Continue even if Drive delete fails
        }

        // Delete from database
        $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = ?');
        $stmt->execute([$upload_id]);

        $success = 'File deleted successfully';
    }
}

// Handle reply action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $upload_id = $_POST['upload_id'];
    $reply_message = trim($_POST['reply_message']);

    if (!empty($reply_message)) {
        // Get store_id from upload
        $stmt = $pdo->prepare('SELECT store_id FROM uploads WHERE id = ?');
        $stmt->execute([$upload_id]);
        $store_id = $stmt->fetchColumn();

        if ($store_id) {
            // Insert reply as a special message
            try {
                $stmt = $pdo->prepare('INSERT INTO store_messages (store_id, message, is_reply, upload_id, created_at) VALUES (?, ?, 1, ?, NOW())');
                $stmt->execute([$store_id, $reply_message, $upload_id]);
                $success = 'Reply sent successfully';
            } catch (PDOException $e) {
                // If is_reply column doesn't exist, try without it
                $stmt = $pdo->prepare('INSERT INTO store_messages (store_id, message, created_at) VALUES (?, ?, NOW())');
                $stmt->execute([$store_id, "Re: File - " . $reply_message]);
                $success = 'Reply sent successfully';
            }
        }
    }
}

// Build query
$where = [];
$params = [];

// Store filter
if (!empty($_GET['store_id'])) {
    $where[] = 's.id = ?';
    $params[] = $_GET['store_id'];
}

// Date range filter
if (!empty($_GET['date_range'])) {
    switch($_GET['date_range']) {
        case 'day':
            $where[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
            break;
        case 'week':
            $where[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        // 'all' requires no filter
    }
}

// Get all stores for dropdown
$stores = $pdo->query('SELECT id, name, pin FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build main query
$sql = 'SELECT u.*, s.name as store_name, s.pin FROM uploads u JOIN stores s ON u.store_id=s.id';
if ($where) {
    $sql .= ' WHERE '.implode(' AND ', $where);
}
$sql .= " ORDER BY u.created_at DESC LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_sql = 'SELECT COUNT(*) FROM uploads u JOIN stores s ON u.store_id=s.id';
if ($where) {
    $count_sql .= ' WHERE '.implode(' AND ', $where);
}
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Calculate if upload is new (within 24 hours)
$now = new DateTime();

$active = 'uploads';
include __DIR__.'/header.php';
?>

    <style>
        .upload-card {
            height: 100%;
            transition: all 0.3s ease;
        }
        .upload-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .new-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
            background-color: #f8f9fa;
        }
        .video-thumbnail {
            background-color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 3rem;
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Content Review</h4>
        <div>
            <span class="badge bg-secondary">Total: <?php echo $total_count; ?> files</span>
        </div>
    </div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <form method="get" class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="store_id" class="form-label">Store</label>
                    <select name="store_id" id="store_id" class="form-select">
                        <option value="">All Stores</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>" <?php echo ($_GET['store_id'] ?? '') == $store['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($store['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="date_range" class="form-label">Date Range</label>
                    <select name="date_range" id="date_range" class="form-select">
                        <option value="all" <?php echo ($_GET['date_range'] ?? 'all') == 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="day" <?php echo ($_GET['date_range'] ?? '') == 'day' ? 'selected' : ''; ?>>Last 24 Hours</option>
                        <option value="week" <?php echo ($_GET['date_range'] ?? '') == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo ($_GET['date_range'] ?? '') == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a href="uploads.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <div class="row">
        <?php foreach ($rows as $r):
            $uploadTime = new DateTime($r['created_at']);
            $hoursAgo = $now->diff($uploadTime)->h + ($now->diff($uploadTime)->days * 24);
            $isNew = $hoursAgo < 24;
            $isOld = $hoursAgo > 168; // 7 days
            $isVideo = strpos($r['mime'], 'video') !== false;
            ?>
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="card upload-card <?php echo $isOld ? 'opacity-75' : ''; ?>">
                    <?php if ($isNew): ?>
                        <span class="new-badge badge bg-warning text-dark">NEW</span>
                    <?php endif; ?>

                    <?php if ($isVideo): ?>
                        <div class="card-img-top video-thumbnail">
                            <i class="bi bi-play-circle"></i>
                        </div>
                    <?php else: ?>
                        <img src="thumbnail.php?id=<?php echo $r['id']; ?>&size=medium"
                             class="card-img-top"
                             alt="<?php echo htmlspecialchars($r['filename']); ?>"
                             loading="lazy">
                    <?php endif; ?>

                    <div class="card-body">
                        <h6 class="card-title text-truncate" title="<?php echo htmlspecialchars($r['filename']); ?>">
                            <?php echo htmlspecialchars($r['filename']); ?>
                        </h6>

                        <p class="card-text">
                            <strong class="text-primary"><?php echo htmlspecialchars($r['store_name']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo format_ts($r['created_at']); ?><br>
                                <?php echo number_format($r['size'] / 1024 / 1024, 2); ?> MB • <?php echo htmlspecialchars(explode('/', $r['mime'])[0]); ?>
                            </small>
                        </p>

                        <?php if (!empty($r['description'])): ?>
                            <p class="card-text small">
                                <strong>Description:</strong> <?php echo htmlspecialchars($r['description']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($r['custom_message'])): ?>
                            <div class="alert alert-info py-2 small mb-2">
                                <strong>Customer Message:</strong><br>
                                <?php echo nl2br(htmlspecialchars($r['custom_message'])); ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mb-2">
                            <a class="btn btn-sm btn-primary" href="https://drive.google.com/file/d/<?php echo $r['drive_id']; ?>/view" target="_blank">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <a class="btn btn-sm btn-primary" href="download.php?id=<?php echo $r['id']; ?>" target="_blank">
                                <i class="bi bi-download"></i> Download
                            </a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this file?');">
                                <input type="hidden" name="delete_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>

                        <!-- Reply form -->
                        <form method="post">
                            <input type="hidden" name="upload_id" value="<?php echo $r['id']; ?>">
                            <div class="input-group input-group-sm">
                                <input type="text" name="reply_message" class="form-control" placeholder="Reply to customer...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php if (empty($rows)): ?>
    <div class="alert alert-info">
        No uploads found matching your criteria.
    </div>
<?php endif; ?>

    <!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
            </li>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php include __DIR__.'/footer.php'; ?>