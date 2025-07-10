<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';

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
            $stmt = $pdo->prepare('INSERT INTO articles (store_id, title, content, excerpt, status, created_at, ip) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
            $stmt->execute([
                $store_id,
                $title,
                $content,
                $excerpt,
                'submitted',
                $_SERVER['REMOTE_ADDR']
            ]);

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
                $confirmMessage .= "Article Title: $title\n\n";
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

// Get store's articles with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE store_id = ?');
$stmt->execute([$store_id]);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get articles
$stmt = $pdo->prepare("
    SELECT * FROM articles 
    WHERE store_id = ? 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$store_id]);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active tab
$tab = $_GET['tab'] ?? 'submit';

include __DIR__.'/header.php';
?>

    <style>
        .ck-editor__editable {
            min-height: 400px;
        }
        .article-status {
            font-size: 0.875rem;
            font-weight: 500;
        }
        .article-card {
            transition: all 0.3s ease;
        }
        .article-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Content Articles - <?php echo htmlspecialchars($store_name); ?></h2>
        <a href="index.php" class="btn btn-secondary">Back to Uploads</a>
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

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'submit' ? 'active' : ''; ?>" href="?tab=submit">
                <i class="bi bi-pencil-square"></i> Submit Article
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'history' ? 'active' : ''; ?>" href="?tab=history">
                <i class="bi bi-clock-history"></i> Article History
                <?php if ($total_count > 0): ?>
                    <span class="badge bg-primary"><?php echo $total_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

<?php if ($tab === 'submit'): ?>
    <!-- Submit Article Tab -->
    <div class="card">
        <div class="card-body">
            <h4 class="card-title mb-4">Submit New Article</h4>
            <form method="post" id="articleForm">
                <input type="hidden" name="submit_article" value="1">

                <div class="mb-3">
                    <label for="title" class="form-label">Article Title *</label>
                    <input type="text" class="form-control" id="title" name="title" required
                           placeholder="Enter your article title" maxlength="255">
                </div>

                <div class="mb-3">
                    <label for="excerpt" class="form-label">Brief Description (Optional)</label>
                    <textarea class="form-control" id="excerpt" name="excerpt" rows="3"
                              placeholder="Brief summary of your article (optional)" maxlength="500"></textarea>
                    <div class="form-text">This will be shown in article listings</div>
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">Article Content *</label>
                    <textarea class="form-control" id="content" name="content" required></textarea>
                    <div class="form-text">You can paste content from Word, Google Docs, or other sources. Formatting will be preserved.</div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            Submit Article
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="saveDraft()">Save Draft</button>
                    </div>
                    <div class="text-muted">
                        <small>Word count: <span id="wordCount">0</span></small>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Article History Tab -->
    <?php if (empty($articles)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No articles submitted yet.
            <a href="?tab=submit">Submit your first article</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($articles as $article): ?>
                <div class="col-md-6 mb-4">
                    <div class="card article-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                                <?php
                                $statusClass = [
                                    'draft' => 'bg-secondary',
                                    'submitted' => 'bg-info',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger'
                                ][$article['status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $statusClass; ?> article-status">
                                    <?php echo ucfirst($article['status']); ?>
                                </span>
                            </div>

                            <p class="card-text text-muted">
                                <?php
                                $excerpt = $article['excerpt'] ?: strip_tags($article['content']);
                                echo htmlspecialchars(substr($excerpt, 0, 150)) . '...';
                                ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> <?php echo date('M d, Y', strtotime($article['created_at'])); ?>
                                </small>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewArticle(<?php echo $article['id']; ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php if ($article['status'] === 'draft'): ?>
                                        <a href="?tab=submit&edit=<?php echo $article['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($article['admin_notes'])): ?>
                                <div class="alert alert-warning mt-3 mb-0 py-2 px-3">
                                    <small><strong>Admin Notes:</strong> <?php echo htmlspecialchars($article['admin_notes']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?tab=history&page=<?php echo $page - 1; ?>">Previous</a>
                    </li>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?tab=history&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?tab=history&page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
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

    <!-- CKEditor CDN -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

    <script>
        let editor;

        // Initialize CKEditor
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
                    document.getElementById('wordCount').textContent = words.length;
                };

                editor.model.document.on('change:data', updateWordCount);
                updateWordCount();
            })
            .catch(error => {
                console.error(error);
            });

        // Form submission
        document.getElementById('articleForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.querySelector('.spinner-border').classList.remove('d-none');
        });

        // Save draft function
        function saveDraft() {
            const title = document.getElementById('title').value;
            const excerpt = document.getElementById('excerpt').value;
            const content = editor.getData();

            if (!title || !content) {
                alert('Please enter a title and content before saving as draft.');
                return;
            }

            // Store in localStorage
            const draft = {
                title: title,
                excerpt: excerpt,
                content: content,
                savedAt: new Date().toISOString()
            };

            localStorage.setItem('articleDraft', JSON.stringify(draft));
            alert('Draft saved! It will be restored when you return to this page.');
        }

        // Load draft on page load
        document.addEventListener('DOMContentLoaded', function() {
            const draft = localStorage.getItem('articleDraft');
            if (draft && !document.getElementById('title').value) {
                const draftData = JSON.parse(draft);
                const savedDate = new Date(draftData.savedAt);

                if (confirm(`Restore draft from ${savedDate.toLocaleDateString()} ${savedDate.toLocaleTimeString()}?`)) {
                    document.getElementById('title').value = draftData.title;
                    document.getElementById('excerpt').value = draftData.excerpt;
                    if (editor) {
                        editor.setData(draftData.content);
                    }
                }
            }
        });

        // View article function
        function viewArticle(articleId) {
            fetch(`view_article.php?id=${articleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('articleModalTitle').textContent = data.article.title;
                        document.getElementById('articleModalBody').innerHTML = `
                    <div class="article-meta mb-3">
                        <small class="text-muted">
                            <i class="bi bi-calendar"></i> ${data.article.created_at} |
                            <i class="bi bi-tag"></i> Status: ${data.article.status}
                        </small>
                    </div>
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
    </script>

<?php include __DIR__.'/footer.php'; ?>