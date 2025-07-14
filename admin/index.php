<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$quick_errors = [];
$quick_success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_message'])) {
    $message = trim($_POST['quick_message']);
    $store_id = $_POST['quick_store_id'] ?? null;

    if ($message === '') {
        $quick_errors[] = 'Message cannot be empty';
    } else {
        if ($store_id === 'all' || empty($store_id)) {
            $store_id = null;
        }

        $stmt = $pdo->prepare("INSERT INTO store_messages (store_id, sender, message, created_at) VALUES (?, 'admin', ?, NOW())");
        $stmt->execute([$store_id, $message]);

        $emailSettings = [];
        $settingsQuery = $pdo->query("SELECT name, value FROM settings WHERE name IN ('email_from_name', 'email_from_address', 'store_message_subject')");
        while ($row = $settingsQuery->fetch()) {
            $emailSettings[$row['name']] = $row['value'];
        }

        $fromName = $emailSettings['email_from_name'] ?? 'Cosmick Media';
        $fromAddress = $emailSettings['email_from_address'] ?? 'noreply@cosmickmedia.com';
        $subjectTemplate = $emailSettings['store_message_subject'] ?? 'New message from Cosmick Media';

        $headers = "From: $fromName <$fromAddress>\r\n";
        $headers .= "Reply-To: $fromAddress\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
        $loginUrl = $baseUrl . '/public/index.php';

        if ($store_id) {
            $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
            $stmt->execute([$store_id]);
            $store = $stmt->fetch();

            if ($store && !empty($store['admin_email'])) {
                $subject = str_replace('{store_name}', $store['name'], $subjectTemplate);

                $body = "Dear {$store['name']},\n\n";
                $body .= "You have a new message from Cosmick Media:\n\n";
                $body .= "=====================================\n";
                $body .= $message . "\n";
                $body .= "=====================================\n\n";
                $body .= "To view this message and upload content, please visit:\n";
                $body .= $loginUrl . "\n\n";
                $body .= "Your PIN: {$store['pin']}\n\n";
                $body .= "Best regards,\n$fromName";

                mail($store['admin_email'], $subject, $body, $headers);
            }
        } else {
            $stores = $pdo->query('SELECT * FROM stores WHERE admin_email IS NOT NULL AND admin_email != ""')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($stores as $store) {
                $subject = str_replace('{store_name}', $store['name'], $subjectTemplate);

                $body = "Dear {$store['name']},\n\n";
                $body .= "You have a new message from Cosmick Media:\n\n";
                $body .= "=====================================\n";
                $body .= $message . "\n";
                $body .= "=====================================\n\n";
                $body .= "To view this message and upload content, please visit:\n";
                $body .= $loginUrl . "\n\n";
                $body .= "Your PIN: {$store['pin']}\n\n";
                $body .= "Best regards,\n$fromName";

                mail($store['admin_email'], $subject, $body, $headers);
            }
        }

        $quick_success[] = 'Message posted successfully';
    }
}

// Get statistics
$stats = [];

// Total stores
$stmt = $pdo->query('SELECT COUNT(*) FROM stores');
$stats['total_stores'] = $stmt->fetchColumn();

// Total uploads
$stmt = $pdo->query('SELECT COUNT(*) FROM uploads');
$stats['total_uploads'] = $stmt->fetchColumn();

// Uploads today
$stmt = $pdo->query('SELECT COUNT(*) FROM uploads WHERE DATE(created_at) = CURDATE()');
$stats['uploads_today'] = $stmt->fetchColumn();

// Uploads this week
$stmt = $pdo->query('SELECT COUNT(*) FROM uploads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$stats['uploads_week'] = $stmt->fetchColumn();

// Articles statistics
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM articles');
    $stats['total_articles'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'submitted'");
    $stats['pending_articles'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Table might not exist yet
    $stats['total_articles'] = 0;
    $stats['pending_articles'] = 0;
}

// Store users (main + additional)
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM stores WHERE admin_email IS NOT NULL AND admin_email <> ''");
    $stats['store_users'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['store_users'] = 0;
}
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM store_users');
    $stats['store_users'] += $stmt->fetchColumn();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Recent uploads
$stmt = $pdo->query('
    SELECT u.*, s.name as store_name, u.mime, u.drive_id
    FROM uploads u 
    JOIN stores s ON u.store_id = s.id 
    ORDER BY u.created_at DESC 
    LIMIT 5
');
$recent_uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent articles
$recent_articles = [];
try {
    $stmt = $pdo->query('
        SELECT a.*, s.name as store_name 
        FROM articles a 
        JOIN stores s ON a.store_id = s.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ');
    $recent_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
}

// Recent broadcast messages
$stmt = $pdo->query("SELECT message, created_at FROM store_messages WHERE store_id IS NULL ORDER BY created_at DESC LIMIT 5");
$recent_broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active messages count
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM store_messages WHERE is_reply = 0 OR is_reply IS NULL');
    $stats['active_messages'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Fallback if is_reply column doesn't exist
    $stmt = $pdo->query('SELECT COUNT(*) FROM store_messages');
    $stats['active_messages'] = $stmt->fetchColumn();
}

$stores = $pdo->query('SELECT id, name FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$active = 'dashboard';
include __DIR__.'/header.php';
?>

    <h4 class="mb-4">Admin Dashboard</h4>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 g-3 mb-4">
        <div class="col">
            <a href="stores.php" class="text-decoration-none">
                <div class="card text-white bg-primary clickable-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-shop"></i> Total Stores
                        </h5>
                        <p class="card-text display-6"><?php echo $stats['total_stores']; ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="uploads.php" class="text-decoration-none">
                <div class="card text-white bg-success clickable-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-cloud-upload"></i> Total Uploads
                        </h5>
                        <p class="card-text display-6"><?php echo $stats['total_uploads']; ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="articles.php" class="text-decoration-none">
                <div class="card text-white bg-info clickable-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-file-text"></i> Articles
                        </h5>
                        <p class="card-text display-6">
                            <?php echo $stats['total_articles']; ?>
                            <?php if ($stats['pending_articles'] > 0): ?>
                                <span class="fs-6">(<?php echo $stats['pending_articles']; ?> pending)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="messages.php" class="text-decoration-none">
                <div class="card text-white bg-warning clickable-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-chat-dots"></i> Active Messages
                        </h5>
                        <p class="card-text display-6"><?php echo $stats['active_messages']; ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="stores.php" class="text-decoration-none">
                <div class="card text-white bg-secondary clickable-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-people"></i> Store Users
                        </h5>
                        <p class="card-text display-6"><?php echo $stats['store_users']; ?></p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Recent Uploads Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Uploads</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_uploads)): ?>
                        <p class="text-muted">No recent uploads</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th style="width: 60px;">Preview</th>
                                    <th>Time</th>
                                    <th>Store</th>
                                    <th>File</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recent_uploads as $upload):
                                    $isVideo = strpos($upload['mime'], 'video') !== false;
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="thumbnail.php?id=<?php echo $upload['id']; ?>&size=small"
                                                 class="img-thumbnail"
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 alt="<?php echo htmlspecialchars($upload['filename']); ?>"
                                                 loading="lazy">
                                        </td>
                                        <td><?php echo format_ts($upload['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($upload['store_name']); ?></td>
                                        <td><?php echo htmlspecialchars($upload['filename']); ?></td>
                                        <td>
                                            <a href="https://drive.google.com/file/d/<?php echo $upload['drive_id']; ?>/view"
                                               target="_blank" class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="uploads.php" class="btn btn-primary">View All Uploads</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Articles Card -->
            <?php if (!empty($recent_articles)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Articles</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Store</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recent_articles as $article): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($article['title'], 0, 40)) . '...'; ?></td>
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
                                            <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($article['status']); ?>
                                        </span>
                                        </td>
                                        <td><?php echo format_ts($article['created_at']); ?></td>
                                        <td>
                                            <a href="articles.php" class="btn btn-sm btn-primary">Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="articles.php" class="btn btn-primary">View All Articles</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Broadcast Messages Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Broadcast Messages</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_broadcasts)): ?>
                        <p class="text-muted">No recent messages</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_broadcasts as $bm): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <span><?php echo htmlspecialchars($bm['message']); ?></span>
                                    <small class="text-muted ms-2"><?php echo format_ts($bm['created_at']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Quick Broadcast</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($quick_errors as $e): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($e); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($quick_success as $s): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($s); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                    <form method="post" class="mb-3">
                        <div class="mb-2">
                            <select name="quick_store_id" class="form-select">
                                <option value="all">All Stores</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <textarea name="quick_message" class="form-control" rows="3" placeholder="Message" required></textarea>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Send</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="stores.php" class="btn btn-primary">
                            <i class="bi bi-shop"></i> Manage Stores
                        </a>
                        <a href="uploads.php" class="btn btn-primary">
                            <i class="bi bi-cloud-upload"></i> Review Uploads
                        </a>
                        <a href="articles.php" class="btn btn-primary">
                            <i class="bi bi-file-text"></i> Review Articles
                            <?php if ($stats['pending_articles'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $stats['pending_articles']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="messages.php" class="btn btn-primary">
                            <i class="bi bi-chat-dots"></i> Post Messages
                        </a>
                        <a href="settings.php" class="btn btn-primary">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">This Week</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1">
                        <strong><?php echo $stats['uploads_week']; ?></strong> uploads
                    </p>
                    <div class="progress">
                        <?php
                        $avg_per_day = $stats['uploads_week'] / 7;
                        $progress = min(100, ($avg_per_day / 10) * 100); // Assuming 10 uploads/day is 100%
                        ?>
                        <div class="progress-bar bg-primary" role="progressbar"
                             style="width: <?php echo $progress; ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        Average: <?php echo number_format($avg_per_day, 1); ?> per day
                    </small>
                </div>
            </div>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>
