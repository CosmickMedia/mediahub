<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();
$checkCat = $pdo->query("SHOW COLUMNS FROM articles LIKE 'category'");
$hasCategory = $checkCat->fetch() !== false;

$success = [];
$errors = [];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $article_id = $_POST['article_id'];
    $new_status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'] ?? '';

    try {
        $stmt = $pdo->prepare('UPDATE articles SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$new_status, $admin_notes, $article_id]);

        // Get article and store details for email
        $stmt = $pdo->prepare('
            SELECT a.*, s.name as store_name, s.admin_email 
            FROM articles a 
            JOIN stores s ON a.store_id = s.id 
            WHERE a.id = ?
        ');
        $stmt->execute([$article_id]);
        $article = $stmt->fetch();

        if ($article && $article['admin_email']) {
            // Send status update email
            $emailSettings = [];
            $settingsQuery = $pdo->query("SELECT name, value FROM settings WHERE name IN ('email_from_name', 'email_from_address', 'article_approval_subject')");
            while ($row = $settingsQuery->fetch()) {
                $emailSettings[$row['name']] = $row['value'];
            }

            $fromName = $emailSettings['email_from_name'] ?? 'Cosmick Media';
            $fromAddress = $emailSettings['email_from_address'] ?? 'noreply@cosmickmedia.com';
            $subject = str_replace('{store_name}', $article['store_name'], $emailSettings['article_approval_subject'] ?? 'Article Status Update - Cosmick Media');

            $headers = "From: $fromName <$fromAddress>\r\n";
            $headers .= "Reply-To: $fromAddress\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            $message = "Dear {$article['store_name']},\n\n";
            $message .= "Your article \"{$article['title']}\" has been {$new_status}.\n\n";
            if ($admin_notes) {
                $message .= "Admin Notes: $admin_notes\n\n";
            }
            $message .= "Best regards,\n$fromName";

            mail($article['admin_email'], $subject, $message, $headers);
        }

        $success[] = 'Article status updated successfully';
    } catch (PDOException $e) {
        $errors[] = 'Failed to update article status';
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM articles WHERE id = ?');
        $stmt->execute([$_GET['delete']]);
        $success[] = 'Article deleted successfully';
    } catch (PDOException $e) {
        $errors[] = 'Failed to delete article';
    }
}

// Filters
$store_id = $_GET['store_id'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($store_id) {
    $where[] = 'a.store_id = ?';
    $params[] = $store_id;
}

if ($status) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}

if ($search) {
    $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get all stores for dropdown
$stores = $pdo->query('SELECT id, name FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build main query
$sql = 'SELECT a.*, s.name as store_name FROM articles a JOIN stores s ON a.store_id = s.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY a.created_at DESC LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$count_sql = 'SELECT COUNT(*) FROM articles a JOIN stores s ON a.store_id = s.id';
if ($where) {
    $count_sql .= ' WHERE ' . implode(' AND ', $where);
}
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get statistics
$stats = [];
$statsQuery = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM articles
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

$active = 'articles';
include __DIR__.'/header.php';
?>

    <div class="page-header animate__animated animate__fadeInDown">
        <div class="page-header-content">
            <div>
                <h1 class="page-title">Article Management</h1>
                <p class="page-subtitle">Review and manage submitted articles</p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid mb-4">
        <div class="stat-card primary animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
            <div class="stat-number" data-count="<?php echo $stats['total']; ?>">0</div>
            <div class="stat-label">Total Articles</div>
            <div class="stat-bg"></div>
        </div>
        <div class="stat-card info animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            <div class="stat-number" data-count="<?php echo $stats['pending']; ?>">0</div>
            <div class="stat-label">Pending Review</div>
            <div class="stat-bg"></div>
        </div>
        <div class="stat-card success animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
            <div class="stat-number" data-count="<?php echo $stats['approved']; ?>">0</div>
            <div class="stat-label">Approved</div>
            <div class="stat-bg"></div>
        </div>
        <div class="stat-card danger animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
            <div class="stat-number" data-count="<?php echo $stats['rejected']; ?>">0</div>
            <div class="stat-label">Rejected</div>
            <div class="stat-bg"></div>
        </div>
    </div>

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

    <!-- Filters -->
    <form method="get" class="filter-card mb-4">
        <h6 class="filter-title">
            <i class="bi bi-funnel"></i> Filter Articles
        </h6>
        <div class="row g-3">
                <div class="col-md-3">
                    <label for="store_id" class="form-label">Store</label>
                    <select name="store_id" id="store_id" class="form-select">
                        <option value="">All Stores</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>" <?php echo $store_id == $store['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($store['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="submitted" <?php echo $status == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control"
                           placeholder="Search title or content..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
        </div>
    </form>

    <!-- Articles Table -->
    <div class="stores-card animate__animated animate__fadeIn delay-50">
        <div class="card-header-modern">
            <h5 class="card-title-modern">
                <i class="bi bi-file-earmark-text"></i>
                All Articles
            </h5>
            <span class="results-count"><?php echo $total_count; ?> total</span>
        </div>
        <div class="card-body-modern">
            <?php if (empty($articles)): ?>
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text"></i>
                    <h4>No articles found</h4>
                    <p>Articles will appear here once submitted</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                        <tr>
                            <th style="width: 25%;">Title</th>
                            <th style="width: 15%;">Store</th>
                            <th style="width: 10%;">Status</th>
                            <?php if ($hasCategory): ?>
                                <th style="width: 10%;">Category</th>
                            <?php endif; ?>
                            <th style="width: 12%;">Submitted</th>
                            <th style="width: 20%;" class="d-none d-md-table-cell">Excerpt</th>
                            <th style="width: <?php echo $hasCategory ? '18%' : '28%'; ?>;">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($articles as $article): ?>
                            <tr>
                                <td style="max-width: 300px;">
                                    <strong>
                                        <a href="javascript:void(0)"
                                           onclick="viewArticle(<?php echo $article['id']; ?>)"
                                           class="text-decoration-none text-dark"
                                           style="cursor: pointer; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                           title="<?php echo htmlspecialchars($article['title']); ?>">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo htmlspecialchars($article['store_name']); ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'draft' => 'bg-secondary',
                                        'submitted' => 'bg-info',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger'
                                    ][$article['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> status-badge">
                                        <?php echo ucfirst($article['status']); ?>
                                    </span>
                                </td>
                                <?php if ($hasCategory): ?>
                                    <td><?php echo htmlspecialchars($article['category']); ?></td>
                                <?php endif; ?>
                                <td><?php echo format_ts($article['created_at']); ?></td>
                                <td class="article-excerpt d-none d-md-table-cell">
                                    <?php
                                    $excerpt = $article['excerpt'] ?: strip_tags($article['content']);
                                    echo htmlspecialchars(substr($excerpt, 0, 100)) . '...';
                                    ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                        <button class="btn btn-action btn-action-primary"
                                                onclick="viewArticle(<?php echo $article['id']; ?>)"
                                                title="View Article">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-action btn-action-secondary"
                                                onclick="showStatusModal(<?php echo $article['id']; ?>, '<?php echo $article['status']; ?>', '<?php echo htmlspecialchars($article['admin_notes'] ?? '', ENT_QUOTES); ?>')"
                                                title="Update Status">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="export_article_wordpress.php?id=<?php echo $article['id']; ?>"
                                           class="btn btn-action"
                                           style="background: #2c3e50; color: white;"
                                           title="Export for WordPress">
                                            <i class="bi bi-file-earmark-arrow-down"></i>
                                        </a>
                                        <a href="download_article_images.php?id=<?php echo $article['id']; ?>&action=download_all"
                                           class="btn btn-action btn-action-info"
                                           title="Download Images">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <a href="?delete=<?php echo $article['id']; ?>"
                                           class="btn btn-action btn-action-danger"
                                           onclick="return confirm('Delete this article?')"
                                           title="Delete Article">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

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

    <!-- Article View Modal -->
    <div class="modal fade" id="articleModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
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

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Article Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="article_id" id="statusArticleId">

                        <div class="mb-3">
                            <label for="statusSelect" class="form-label">Status</label>
                            <select name="status" id="statusSelect" class="form-select" required>
                                <option value="submitted">Submitted</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="adminNotes" class="form-label">Admin Notes (Optional)</label>
                            <textarea name="admin_notes" id="adminNotes" class="form-control" rows="3"
                                      placeholder="Notes will be visible to the store..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Animated counter for stat cards
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-number[data-count]');
            statNumbers.forEach(el => {
                const target = parseInt(el.getAttribute('data-count'));
                const duration = 1000;
                const step = target / (duration / 16);
                let current = 0;
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        el.textContent = target;
                        clearInterval(timer);
                    } else {
                        el.textContent = Math.floor(current);
                    }
                }, 16);
            });
        });

        function viewArticle(articleId) {
            fetch(`view_article_admin.php?id=${articleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('articleModalTitle').textContent = data.article.title;
                        document.getElementById('articleModalBody').innerHTML = `
                    <style>
                        .article-view-header {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 1.5rem;
                            margin: -1rem -1rem 1.5rem -1rem;
                            border-radius: 8px 8px 0 0;
                        }
                        .article-meta-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                            gap: 1rem;
                            margin-bottom: 1.5rem;
                        }
                        .meta-item {
                            background: #f8f9fa;
                            padding: 0.75rem 1rem;
                            border-radius: 8px;
                            border-left: 3px solid #667eea;
                        }
                        .meta-label {
                            font-size: 0.75rem;
                            color: #6c757d;
                            text-transform: uppercase;
                            font-weight: 600;
                            letter-spacing: 0.5px;
                            margin-bottom: 0.25rem;
                        }
                        .meta-value {
                            font-size: 0.95rem;
                            color: #2c3e50;
                            font-weight: 500;
                        }
                        .article-excerpt-box {
                            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                            padding: 1.25rem;
                            border-radius: 8px;
                            margin-bottom: 1.5rem;
                            font-style: italic;
                            color: #2c3e50;
                            border-left: 4px solid #667eea;
                        }
                        .admin-notes-box {
                            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
                            color: #2c3e50;
                            padding: 1rem 1.25rem;
                            border-radius: 8px;
                            margin-bottom: 1.5rem;
                            font-weight: 500;
                        }
                        .attachments-section {
                            background: #f8f9fa;
                            padding: 1.5rem;
                            border-radius: 8px;
                            margin-bottom: 1.5rem;
                        }
                        .attachments-header {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            margin-bottom: 1.25rem;
                            padding-bottom: 0.75rem;
                            border-bottom: 2px solid #dee2e6;
                        }
                        .attachments-header h5 {
                            margin: 0;
                            color: #2c3e50;
                            font-weight: 600;
                        }
                        .image-card {
                            background: white;
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                            transition: transform 0.2s, box-shadow 0.2s;
                        }
                        .image-card:hover {
                            transform: translateY(-4px);
                            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
                        }
                        .image-card img {
                            width: 100%;
                            height: 180px;
                            object-fit: cover;
                        }
                        .image-card-body {
                            padding: 0.75rem;
                        }
                        .image-filename {
                            font-size: 0.85rem;
                            color: #2c3e50;
                            margin-bottom: 0.5rem;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }
                        .download-btn {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            border: none;
                            padding: 0.5rem;
                            border-radius: 6px;
                            width: 100%;
                            font-size: 0.85rem;
                            font-weight: 500;
                            cursor: pointer;
                            transition: transform 0.2s;
                        }
                        .download-btn:hover {
                            transform: scale(1.05);
                        }
                        .download-all-btn {
                            background: #2c3e50;
                            color: white;
                            border: none;
                            padding: 0.5rem 1.25rem;
                            border-radius: 6px;
                            font-size: 0.9rem;
                            font-weight: 500;
                            cursor: pointer;
                            transition: background 0.2s;
                        }
                        .download-all-btn:hover {
                            background: #1a252f;
                        }
                        .article-content-section {
                            background: white;
                            padding: 2rem;
                            border-radius: 8px;
                            border: 1px solid #e9ecef;
                            line-height: 1.8;
                            color: #2c3e50;
                        }
                        .article-content-section h1,
                        .article-content-section h2,
                        .article-content-section h3 {
                            color: #2c3e50;
                            margin-top: 1.5rem;
                            margin-bottom: 1rem;
                        }
                        .article-content-section img {
                            max-width: 100%;
                            height: auto;
                            border-radius: 8px;
                            margin: 1rem 0;
                        }
                        .status-badge-large {
                            display: inline-block;
                            padding: 0.5rem 1rem;
                            border-radius: 20px;
                            font-weight: 600;
                            font-size: 0.9rem;
                        }
                    </style>

                    <!-- Article Header -->
                    <div class="article-view-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 style="margin: 0 0 0.5rem 0; opacity: 0.9; font-size: 0.9rem;">Article from</h5>
                                <h3 style="margin: 0; font-weight: 600;">${data.article.store_name}</h3>
                            </div>
                            <span class="status-badge-large bg-${data.statusClass}" style="background: white !important; color: #667eea !important;">
                                ${data.article.status.toUpperCase()}
                            </span>
                        </div>
                    </div>

                    <!-- Meta Information Grid -->
                    <div class="article-meta-grid">
                        <div class="meta-item">
                            <div class="meta-label">Submitted</div>
                            <div class="meta-value"><i class="bi bi-calendar3"></i> ${data.article.created_at}</div>
                        </div>
                        ${data.article.updated_at ? `
                        <div class="meta-item">
                            <div class="meta-label">Last Updated</div>
                            <div class="meta-value"><i class="bi bi-clock-history"></i> ${data.article.updated_at}</div>
                        </div>
                        ` : ''}
                        ${data.article.category ? `
                        <div class="meta-item">
                            <div class="meta-label">Category</div>
                            <div class="meta-value"><i class="bi bi-tag"></i> ${data.article.category}</div>
                        </div>
                        ` : ''}
                        ${data.article.tags ? `
                        <div class="meta-item">
                            <div class="meta-label">Tags</div>
                            <div class="meta-value"><i class="bi bi-tags"></i> ${data.article.tags}</div>
                        </div>
                        ` : ''}
                    </div>

                    <!-- Excerpt -->
                    ${data.article.excerpt ? `
                    <div class="article-excerpt-box">
                        <i class="bi bi-quote" style="font-size: 1.5rem; opacity: 0.5;"></i>
                        <div style="margin-top: 0.5rem;">${data.article.excerpt}</div>
                    </div>
                    ` : ''}

                    <!-- Admin Notes -->
                    ${data.article.admin_notes ? `
                    <div class="admin-notes-box">
                        <strong><i class="bi bi-info-circle"></i> Admin Notes:</strong>
                        <div style="margin-top: 0.5rem;">${data.article.admin_notes}</div>
                    </div>
                    ` : ''}

                    <!-- Attachments -->
                    ${data.article.images && data.article.images.length ? `
                    <div class="attachments-section">
                        <div class="attachments-header">
                            <h5><i class="bi bi-paperclip"></i> Attachments (${data.article.images.length})</h5>
                            <button class="download-all-btn" onclick="downloadAllImages(${articleId})">
                                <i class="bi bi-download"></i> Download All
                            </button>
                        </div>
                        <div class="row g-3">
                            ${data.article.images.map((img, idx) => `
                                <div class="col-md-3 col-sm-6">
                                    <div class="image-card">
                                        <a href="${img.url}" target="_blank">
                                            <img src="${img.thumb}" alt="${img.filename}"
                                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22180%22%3E%3Crect fill=%22%23f8f9fa%22 width=%22200%22 height=%22180%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%23adb5bd%22 font-size=%2214%22%3ENo Preview%3C/text%3E%3C/svg%3E'">
                                        </a>
                                        <div class="image-card-body">
                                            <div class="image-filename" title="${img.filename}">
                                                <i class="bi bi-file-earmark-image"></i> ${img.filename}
                                            </div>
                                            <button class="download-btn" onclick="downloadImage('${img.url}', '${img.filename}')">
                                                <i class="bi bi-download"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}

                    <!-- Article Content -->
                    <div class="article-content-section">
                        ${data.article.content}
                    </div>
                `;
                        new bootstrap.Modal(document.getElementById('articleModal')).show();
                    } else {
                        alert('Failed to load article');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load article');
                });
        }

        function showStatusModal(articleId, currentStatus, currentNotes) {
            document.getElementById('statusArticleId').value = articleId;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('adminNotes').value = currentNotes || '';
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        function downloadImage(url, filename) {
            fetch(url)
                .then(response => response.blob())
                .then(blob => {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                })
                .catch(error => {
                    console.error('Download failed:', error);
                    alert('Failed to download image');
                });
        }

        function downloadAllImages(articleId) {
            fetch(`download_article_images.php?id=${articleId}&action=download_all`)
                .then(response => {
                    if (!response.ok) throw new Error('Download failed');
                    return response.blob();
                })
                .then(blob => {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = `article_${articleId}_images.zip`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                })
                .catch(error => {
                    console.error('Download failed:', error);
                    alert('Failed to download images');
                });
        }
    </script>

<?php include __DIR__.'/footer.php'; ?>