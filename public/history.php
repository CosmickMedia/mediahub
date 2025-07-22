<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';

session_start();

// Check if logged in
if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];
$pdo = get_pdo();

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $upload_id = $_POST['delete_id'];

    // Verify this upload belongs to the current store
    $stmt = $pdo->prepare('SELECT drive_id FROM uploads WHERE id = ? AND store_id = ?');
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
                <div class="stat-number" data-count="<?php echo $stats['total_files']; ?>">0</div>
                <div class="stat-label">Total Files</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card total-size animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-hdd-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo number_format($total_size_gb, 1); ?>">0</div>
                <div class="stat-label">GB Storage</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card images animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_images']; ?>">0</div>
                <div class="stat-label">Images</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card videos animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-camera-video-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_videos']; ?>">0</div>
                <div class="stat-label">Videos</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card recent animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['recent_uploads']; ?>">0</div>
                <div class="stat-label">This Week</div>
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
                        <div class="upload-media" onclick="showPreview('<?php echo $upload['id']; ?>', '<?php echo $isVideo ? 'video' : 'image'; ?>')">
                            <?php if ($isVideo): ?>
                                <img src="thumbnail.php?id=<?php echo $upload['id']; ?>&size=medium"
                                     alt="<?php echo htmlspecialchars($upload['filename']); ?>"
                                     loading="lazy">
                                <div class="video-indicator">
                                    <i class="bi bi-play-circle-fill"></i>
                                </div>
                            <?php else: ?>
                                <img src="thumbnail.php?id=<?php echo $upload['id']; ?>&size=medium"
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

                            <div class="upload-actions">
                                <a href="https://drive.google.com/file/d/<?php echo $upload['drive_id']; ?>/view"
                                   target="_blank"
                                   class="action-button view-button">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="https://drive.google.com/uc?export=download&id=<?php echo $upload['drive_id']; ?>"
                                   target="_blank"
                                   class="action-button download-button">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <form method="post" class="flex-1" onsubmit="return confirmDelete('<?php echo htmlspecialchars($upload['filename']); ?>')">
                                    <input type="hidden" name="delete_id" value="<?php echo $upload['id']; ?>">
                                    <button type="submit" class="action-button delete-button w-100">
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
        });

        // Delete confirmation
        function confirmDelete(filename) {
            return confirm(`Are you sure you want to delete "${filename}"?`);
        }

        // Preview functionality
        function showPreview(uploadId, type) {
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
            img.src = `thumbnail.php?id=${uploadId}&size=large`;
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