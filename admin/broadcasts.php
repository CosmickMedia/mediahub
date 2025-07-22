<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/config.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();
$config = get_config();

$success = [];
$errors = [];

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitize_message($_POST['message'] ?? '');
    $store_id = $_POST['store_id'] ?? null;

    if (empty($message)) {
        $errors[] = 'Message cannot be empty';
    } else {
        if ($store_id === 'all' || empty($store_id)) {
            $store_id = null; // NULL means global message
        }

        $stmt = $pdo->prepare("INSERT INTO store_messages (store_id, sender, message, created_at) VALUES (?, 'admin', ?, NOW())");
        $stmt->execute([$store_id, $message]);

        // Get email settings
        $emailSettings = [];
        $settingsQuery = $pdo->query("SELECT name, value FROM settings WHERE name IN ('email_from_name', 'email_from_address', 'store_message_subject')");
        while ($row = $settingsQuery->fetch()) {
            $emailSettings[$row['name']] = $row['value'];
        }

        $fromName = $emailSettings['email_from_name'] ?? 'Cosmick Media';
        $fromAddress = $emailSettings['email_from_address'] ?? 'noreply@cosmickmedia.com';
        $messageSubject = $emailSettings['store_message_subject'] ?? "New message from Cosmick Media";

        $headers = "From: $fromName <$fromAddress>\r\n";
        $headers .= "Reply-To: $fromAddress\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Get the base URL for the login link
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
        $loginUrl = $baseUrl . '/public/index.php';

        if ($store_id) {
            // Send to specific store
            $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
            $stmt->execute([$store_id]);
            $store = $stmt->fetch();

            if ($store && !empty($store['admin_email'])) {
                $subject = str_replace('{store_name}', $store['name'], $messageSubject);

                $emailBody = "Dear {$store['name']},\n\n";
                $emailBody .= "You have a new message from Cosmick Media:\n\n";
                $emailBody .= "=====================================\n";
                $emailBody .= $message . "\n";
                $emailBody .= "=====================================\n\n";
                $emailBody .= "To view this message and upload content, please visit:\n";
                $emailBody .= $loginUrl . "\n\n";
                $emailBody .= "Your PIN: {$store['pin']}\n\n";
                $emailBody .= "Best regards,\n$fromName";

                mail($store['admin_email'], $subject, $emailBody, $headers);
            }
        } else {
            // Send to all stores
            $stores_with_email = $pdo->query('SELECT * FROM stores WHERE admin_email IS NOT NULL AND admin_email != ""')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($stores_with_email as $store) {
                $subject = str_replace('{store_name}', $store['name'], $messageSubject);

                $emailBody = "Dear {$store['name']},\n\n";
                $emailBody .= "You have a new message from Cosmick Media:\n\n";
                $emailBody .= "=====================================\n";
                $emailBody .= $message . "\n";
                $emailBody .= "=====================================\n\n";
                $emailBody .= "To view this message and upload content, please visit:\n";
                $emailBody .= $loginUrl . "\n\n";
                $emailBody .= "Your PIN: {$store['pin']}\n\n";
                $emailBody .= "Best regards,\n$fromName";

                mail($store['admin_email'], $subject, $emailBody, $headers);
            }
        }

        $success[] = 'Message posted and email notifications sent successfully';
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare('DELETE FROM store_messages WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    header('Location: broadcasts.php');
    exit;
}

// Get all stores
$stores = $pdo->query('SELECT id, name FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
$stats['total_broadcasts'] = $pdo->query("SELECT COUNT(*) FROM store_messages WHERE sender='admin' AND (is_reply = 0 OR is_reply IS NULL) AND parent_id IS NULL")->fetchColumn();
$stats['global_messages'] = $pdo->query("SELECT COUNT(*) FROM store_messages WHERE sender='admin' AND store_id IS NULL")->fetchColumn();
$stats['store_messages'] = $pdo->query("SELECT COUNT(*) FROM store_messages WHERE sender='admin' AND store_id IS NOT NULL")->fetchColumn();
$stats['this_week'] = $pdo->query("SELECT COUNT(*) FROM store_messages WHERE sender='admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$stats['this_month'] = $pdo->query("SELECT COUNT(*) FROM store_messages WHERE sender='admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$stats['active_stores'] = $pdo->query("SELECT COUNT(DISTINCT store_id) FROM store_messages WHERE store_id IS NOT NULL")->fetchColumn();

// Pagination and fetch broadcast messages only
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$baseQuery = "FROM store_messages m LEFT JOIN stores s ON m.store_id = s.id
    WHERE m.sender='admin' AND (m.is_reply = 0 OR m.is_reply IS NULL)
      AND m.parent_id IS NULL AND m.upload_id IS NULL AND m.article_id IS NULL";

$stmt = $pdo->prepare("SELECT m.*, s.name as store_name $baseQuery ORDER BY m.created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = $pdo->query("SELECT COUNT(*) $baseQuery")->fetchColumn();
$total_pages = ceil($count / $per_page);

$active = 'broadcasts';
include __DIR__.'/header.php';
?>


    <div class="animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <h1 class="page-title">Store Broadcasts</h1>
            <p class="page-subtitle">Send messages to stores and manage communications</p>
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
                    <i class="bi bi-megaphone-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_broadcasts']; ?>">0</div>
                <div class="stat-label">Total Broadcasts</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card success animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-globe"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['global_messages']; ?>">0</div>
                <div class="stat-label">Global Messages</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card warning animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-shop"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['store_messages']; ?>">0</div>
                <div class="stat-label">Store Messages</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card info animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['this_week']; ?>">0</div>
                <div class="stat-label">This Week</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card danger animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-calendar-month"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['this_month']; ?>">0</div>
                <div class="stat-label">This Month</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card secondary animate__animated animate__fadeInUp delay-50">
                <div class="stat-icon">
                    <i class="bi bi-activity"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['active_stores']; ?>">0</div>
                <div class="stat-label">Active Stores</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Broadcast Form -->
            <div class="broadcast-card animate__animated animate__fadeIn delay-60">
                <div class="card-header-modern">
                    <h5 class="card-title-modern">
                        <i class="bi bi-send"></i>
                        Post New Broadcast
                    </h5>
                </div>
                <div class="card-body-modern">
                    <div class="info-card">
                        <div class="info-card-title">
                            <i class="bi bi-info-circle"></i> How it works
                        </div>
                        <div class="info-card-content">
                            Messages are displayed in the store dashboard and sent via email to store administrators.
                        </div>
                    </div>

                    <form method="post">
                        <div class="mb-4">
                            <label for="store_id" class="form-label-modern">
                                <i class="bi bi-shop"></i> Target Store
                            </label>
                            <select name="store_id" id="store_id" class="form-select form-select-modern">
                                <option value="all">🌍 All Stores (Global Broadcast)</option>
                                <optgroup label="Individual Stores">
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['id']; ?>">
                                            <?php echo htmlspecialchars($store['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <div class="form-text">Choose whether to send to all stores or a specific store</div>
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label-modern">
                                <i class="bi bi-chat-left-text"></i> Message
                            </label>
                            <textarea name="message" id="message"
                                      class="form-control form-control-modern"
                                      rows="6"
                                      placeholder="Enter your broadcast message here..."
                                      required
                                      maxlength="1000"></textarea>
                            <div class="character-counter">
                                <span id="charCount">0</span> / 1000 characters
                            </div>
                        </div>

                        <button type="submit" class="btn btn-broadcast">
                            <i class="bi bi-send-fill"></i> Send Broadcast
                        </button>
                    </form>
                </div>
            </div>

            <!-- Messages List -->
            <div class="messages-card animate__animated animate__fadeIn delay-70">
                <div class="card-header-modern">
                    <h5 class="card-title-modern">
                        <i class="bi bi-chat-square-text"></i>
                        Active Broadcasts
                    </h5>
                </div>

                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="bi bi-chat-square-dots"></i>
                        <h4>No active messages</h4>
                        <p>Post your first broadcast to get started</p>
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div class="message-badges">
                                        <?php if ($msg['store_id']): ?>
                                            <span class="store-badge">
                                            <i class="bi bi-shop me-1"></i>
                                            <?php echo htmlspecialchars($msg['store_name']); ?>
                                        </span>
                                        <?php else: ?>
                                            <span class="global-badge">
                                            <i class="bi bi-globe me-1"></i>
                                            All Stores
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="message-time">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo format_ts($msg['created_at']); ?>
                                </span>
                                </div>

                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>

                                <div class="message-actions">
                                    <a href="edit_broadcast.php?id=<?php echo $msg['id']; ?>"
                                       class="btn btn-action btn-action-secondary" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                        <span class="visually-hidden">Edit</span>
                                    </a>
                                    <a href="?delete=<?php echo $msg['id']; ?>&page=<?php echo $page; ?>"
                                       class="btn btn-action btn-action-danger"
                                       onclick="return confirm('Delete this message?')" title="Delete">
                                        <i class="bi bi-trash"></i>
                                        <span class="visually-hidden">Delete</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

                            for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="page-link-modern active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                       class="page-link-modern"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

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
    </div>

    <script>
        // Character counter
        const messageTextarea = document.getElementById('message');
        const charCount = document.getElementById('charCount');

        messageTextarea.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;

            if (count > 900) {
                charCount.style.color = '#dc3545';
            } else if (count > 800) {
                charCount.style.color = '#ffc107';
            } else {
                charCount.style.color = '#6c757d';
            }
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>