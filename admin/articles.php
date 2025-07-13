<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

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

    <style>
        .article-excerpt {
            max-width: 500px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .status-badge {
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Article Management</h4>
        <div class="d-flex gap-2">
            <span class="badge bg-secondary">Total: <?php echo $stats['total']; ?></span>
            <span class="badge bg-info">Pending: <?php echo $stats['pending']; ?></span>
            <span class="badge bg-success">Approved: <?php echo $stats['approved']; ?></span>
            <span class="badge bg-danger">Rejected: <?php echo $stats['rejected']; ?></span>
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
    <form method="get" class="card mb-4">
        <div class="card-body">
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
        </div>
    </form>

    <!-- Articles Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($articles)): ?>
                <p class="text-muted text-center py-4">No articles found</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Store</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Excerpt</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($articles as $article): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($article['title']); ?></strong>
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
                                <td><?php echo format_ts($article['created_at']); ?></td>
                                <td class="article-excerpt">
                                    <?php
                                    $excerpt = $article['excerpt'] ?: strip_tags($article['content']);
                                    echo htmlspecialchars(substr($excerpt, 0, 100)) . '...';
                                    ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-primary" onclick="viewArticle(<?php echo $article['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" onclick="showStatusModal(<?php echo $article['id']; ?>, '<?php echo $article['status']; ?>', '<?php echo htmlspecialchars($article['admin_notes'] ?? '', ENT_QUOTES); ?>')">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="?delete=<?php echo $article['id']; ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this article?')">
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
        function viewArticle(articleId) {
            fetch(`view_article_admin.php?id=${articleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('articleModalTitle').textContent = data.article.title;
                        document.getElementById('articleModalBody').innerHTML = `
                    <div class="article-meta mb-3">
                        <p class="mb-1">
                            <strong>Store:</strong> ${data.article.store_name} |
                            <strong>Status:</strong> <span class="badge bg-${data.statusClass}">${data.article.status}</span>
                        </p>
                        <p class="mb-1">
                            <strong>Submitted:</strong> ${data.article.created_at}
                            ${data.article.updated_at ? ` | <strong>Updated:</strong> ${data.article.updated_at}` : ''}
                        </p>
                        ${data.article.excerpt ? `<p class="mb-1"><strong>Excerpt:</strong> ${data.article.excerpt}</p>` : ''}
                        ${data.article.admin_notes ? `
                            <div class="alert alert-warning py-2 px-3">
                                <strong>Admin Notes:</strong> ${data.article.admin_notes}
                            </div>
                        ` : ''}
                    </div>
                    <hr>
                    <div class="article-content">
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
    </script>

<?php include __DIR__.'/footer.php'; ?>