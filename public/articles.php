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
            // Check if category column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM articles LIKE 'category'");
            $hasCategory = $checkColumn->fetch() !== false;

            if ($hasCategory) {
                $stmt = $pdo->prepare('INSERT INTO articles (store_id, title, content, excerpt, category, tags, status, created_at, ip) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
                $stmt->execute([
                    $store_id,
                    $title,
                    $content,
                    $excerpt,
                    $category,
                    $tags,
                    'submitted',
                    $_SERVER['REMOTE_ADDR']
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO articles (store_id, title, content, excerpt, status, created_at, ip) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
                $stmt->execute([
                    $store_id,
                    $title,
                    $content,
                    $excerpt,
                    'submitted',
                    $_SERVER['REMOTE_ADDR']
                ]);
            }

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
$per_page = 9; // 3x3 grid
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

// Get saved draft from localStorage (handled by JavaScript)

include __DIR__.'/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">


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
            <div class="stat-card total animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-file-text-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_articles']; ?>">0</div>
                <div class="stat-label">Total Articles</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card pending animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['pending_articles']; ?>">0</div>
                <div class="stat-label">Pending Review</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card approved animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['approved_articles']; ?>">0</div>
                <div class="stat-label">Approved</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card rejected animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['rejected_articles']; ?>">0</div>
                <div class="stat-label">Rejected</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card drafts animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['draft_articles']; ?>">0</div>
                <div class="stat-label">Drafts</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card rate animate__animated animate__fadeInUp delay-50">
                <div class="stat-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $approval_rate; ?>">0</div>
                <div class="stat-label">Approval Rate %</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="modern-tabs animate__animated animate__fadeIn delay-60">
            <a class="tab-link <?php echo $tab === 'submit' ? 'active' : ''; ?>" href="?tab=submit">
                <i class="bi bi-pencil-square"></i>
                Submit Article
            </a>
            <a class="tab-link <?php echo $tab === 'history' ? 'active' : ''; ?>" href="?tab=history">
                <i class="bi bi-clock-history"></i>
                Article History
                <?php if ($total_count > 0): ?>
                    <span class="tab-badge"><?php echo $total_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ($tab === 'submit'): ?>
            <!-- Submit Article Tab -->
            <div class="article-form-section animate__animated animate__fadeIn delay-70">
                <div class="form-header">
                    <h3 class="form-title">Submit New Article</h3>
                    <p class="form-description">Share your story, news, or press release with us</p>
                </div>

                <form method="post" id="articleForm">
                    <input type="hidden" name="submit_article" value="1">

                    <div class="form-grid">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title" class="form-label">
                                    <i class="bi bi-type"></i> Article Title *
                                </label>
                                <input type="text"
                                       class="form-control-modern"
                                       id="title"
                                       name="title"
                                       required
                                       placeholder="Enter a compelling title"
                                       maxlength="255">
                                <div class="char-counter">
                                    <span id="titleCount">0</span> / 255
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-folder"></i> Category *
                                </label>
                                <div class="category-grid">
                                    <div class="category-option">
                                        <input type="radio" name="category" id="cat-blog" value="blog" checked>
                                        <label for="cat-blog" class="category-label">
                                            <i class="bi bi-journal-text category-icon"></i>
                                            <span>Blog</span>
                                        </label>
                                    </div>
                                    <div class="category-option">
                                        <input type="radio" name="category" id="cat-news" value="news">
                                        <label for="cat-news" class="category-label">
                                            <i class="bi bi-newspaper category-icon"></i>
                                            <span>News</span>
                                        </label>
                                    </div>
                                    <div class="category-option">
                                        <input type="radio" name="category" id="cat-press" value="press">
                                        <label for="cat-press" class="category-label">
                                            <i class="bi bi-megaphone category-icon"></i>
                                            <span>Press</span>
                                        </label>
                                    </div>
                                    <div class="category-option">
                                        <input type="radio" name="category" id="cat-story" value="story">
                                        <label for="cat-story" class="category-label">
                                            <i class="bi bi-book category-icon"></i>
                                            <span>Story</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="excerpt" class="form-label">
                                <i class="bi bi-text-paragraph"></i> Brief Description (Optional)
                            </label>
                            <textarea class="form-control-modern"
                                      id="excerpt"
                                      name="excerpt"
                                      rows="3"
                                      placeholder="Brief summary of your article (optional)"
                                      maxlength="500"></textarea>
                            <div class="char-counter">
                                <span id="excerptCount">0</span> / 500
                            </div>
                        </div>

                        <div class="form-group tag-input-wrapper">
                            <label for="tags" class="form-label">
                                <i class="bi bi-tags"></i> Tags (Optional)
                            </label>
                            <input type="text"
                                   class="form-control-modern"
                                   id="tags"
                                   name="tags"
                                   placeholder="Add tags separated by commas (e.g., marketing, social media, tips)">
                            <div class="tag-suggestions" id="tagSuggestions"></div>
                        </div>

                        <div class="form-group">
                            <label for="content" class="form-label">
                                <i class="bi bi-file-text"></i> Article Content *
                            </label>
                            <textarea class="form-control-modern" id="content" name="content" required></textarea>
                            <div class="form-text">
                                You can paste content from Word, Google Docs, or other sources. Formatting will be preserved.
                            </div>
                        </div>
                    </div>

                    <div class="article-actions">
                        <button type="submit" class="btn-modern btn-modern-primary" id="submitBtn">
                            <i class="bi bi-send"></i>
                            Submit Article
                        </button>
                        <button type="button" class="btn-modern btn-modern-secondary" onclick="saveDraft()">
                            <i class="bi bi-save"></i>
                            Save Draft
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
                             style="animation-delay: <?php echo min($index * 0.1, 0.5); ?>s">
                            <div class="article-header">
                                <div class="article-meta">
                                <span class="article-category">
                                    <i class="bi <?php echo $categoryIcons[$category] ?? 'bi-file-text'; ?>"></i>
                                    <?php echo ucfirst($category); ?>
                                </span>
                                    <span class="article-status status-<?php echo $article['status']; ?>">
                                    <?php echo ucfirst($article['status']); ?>
                                </span>
                                </div>
                            </div>

                            <div class="article-body">
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
                                    <div class="article-tags mb-2">
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
                                    <div class="article-actions-btn">
                                        <button class="action-btn view-btn"
                                                onclick="viewArticle(<?php echo $article['id']; ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <?php if ($article['status'] === 'draft'): ?>
                                            <a href="?tab=submit&edit=<?php echo $article['id']; ?>"
                                               class="action-btn edit-btn">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($article['admin_notes'])): ?>
                                <div class="admin-notes">
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
        <?php endif; ?>

        <!-- Autosave Indicator -->
        <div class="autosave-indicator" id="autosaveIndicator">
            <i class="bi bi-check-circle"></i>
            <span>Draft saved</span>
        </div>
    </div>

    <!-- Article View Modal -->
    <div class="modal fade" id="articleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-article modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-article">
                    <h5 class="modal-title modal-title-article" id="articleModalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-body-article" id="articleModalBody">
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
                    suffix: counter.closest('.stat-card').classList.contains('rate') ? '%' : ''
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
        });

        // Initialize CKEditor
        if (document.querySelector('#content')) {
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

                    // Update word count
                    const updateWordCount = () => {
                        const text = editor.getData().replace(/<[^>]*>/g, ' ').trim();
                        const words = text.split(/\s+/).filter(word => word.length > 0);
                        // You can add a word count display if needed
                    };

                    editor.model.document.on('change:data', () => {
                        updateWordCount();
                        triggerAutosave();
                    });
                    updateWordCount();

                    // Load draft on page load
                    loadDraft();
                })
                .catch(error => {
                    console.error(error);
                });
        }

        // Autosave functionality
        function triggerAutosave() {
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(saveDraft, 2000); // Save after 2 seconds of inactivity
        }

        // Save draft function
        function saveDraft(showIndicator = true) {
            if (!document.getElementById('title')) return;

            const title = document.getElementById('title').value;
            const excerpt = document.getElementById('excerpt').value;
            const content = editor ? editor.getData() : '';
            const category = document.querySelector('input[name="category"]:checked')?.value || 'blog';
            const tags = document.getElementById('tags').value;

            if (!title && !content) return;

            // Store in localStorage
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
                // Show autosave indicator
                const indicator = document.getElementById('autosaveIndicator');
                indicator.classList.add('show', 'success');
                setTimeout(() => {
                    indicator.classList.remove('show');
                }, 2000);
            }
        }

        // Load draft on page load
        function loadDraft() {
            const draft = localStorage.getItem('articleDraft');
            if (draft && document.getElementById('title') && !document.getElementById('title').value) {
                const draftData = JSON.parse(draft);
                const savedDate = new Date(draftData.savedAt);

                if (confirm(`Restore draft from ${savedDate.toLocaleDateString()} ${savedDate.toLocaleTimeString()}?`)) {
                    document.getElementById('title').value = draftData.title;
                    document.getElementById('excerpt').value = draftData.excerpt;
                    document.getElementById('tags').value = draftData.tags;

                    // Set category
                    const categoryInput = document.querySelector(`input[name="category"][value="${draftData.category}"]`);
                    if (categoryInput) categoryInput.checked = true;

                    if (editor) {
                        editor.setData(draftData.content);
                    }

                    // Update character counts
                    document.getElementById('titleCount').textContent = draftData.title.length;
                    document.getElementById('excerptCount').textContent = draftData.excerpt.length;
                }
            }
        }

        // Form submission
        if (document.getElementById('articleForm')) {
            document.getElementById('articleForm').addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="loading-spinner"></span> Submitting...';

                // Clear draft after successful submission
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

                        let metaHtml = '<div class="article-modal-meta">';
                        metaHtml += `<div class="meta-item"><i class="bi bi-calendar3"></i> ${article.created_at}</div>`;
                        metaHtml += `<div class="meta-item"><i class="bi bi-tag"></i> ${article.status}</div>`;
                        if (article.category) {
                            metaHtml += `<div class="meta-item"><i class="bi bi-folder"></i> ${article.category}</div>`;
                        }
                        metaHtml += '</div>';

                        let contentHtml = '<div class="article-modal-content">';
                        contentHtml += article.content;
                        contentHtml += '</div>';

                        if (article.admin_notes) {
                            contentHtml += '<div class="admin-notes mt-3">';
                            contentHtml += '<strong>Admin Notes:</strong> ' + article.admin_notes;
                            contentHtml += '</div>';
                        }

                        document.getElementById('articleModalBody').innerHTML = metaHtml + contentHtml;
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

        // Tag suggestions
        const tagInput = document.getElementById('tags');
        const tagSuggestions = document.getElementById('tagSuggestions');
        const commonTags = [
            'marketing', 'social media', 'content', 'strategy', 'tips',
            'business', 'branding', 'digital', 'seo', 'advertising',
            'promotion', 'engagement', 'analytics', 'trends', 'campaign'
        ];

        if (tagInput) {
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