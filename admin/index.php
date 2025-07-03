<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

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

// Recent uploads
$stmt = $pdo->query('
    SELECT u.*, s.name as store_name, u.mime, u.drive_id
    FROM uploads u 
    JOIN stores s ON u.store_id = s.id 
    ORDER BY u.created_at DESC 
    LIMIT 5
');
$recent_uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active messages count
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM store_messages WHERE is_reply = 0 OR is_reply IS NULL');
    $stats['active_messages'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Fallback if is_reply column doesn't exist
    $stmt = $pdo->query('SELECT COUNT(*) FROM store_messages');
    $stats['active_messages'] = $stmt->fetchColumn();
}

$active = 'dashboard';
include __DIR__.'/header.php';
?>

    <h4 class="mb-4">Admin Dashboard</h4>

    <div class="row mb-4">
        <div class="col-md-3">
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
        <div class="col-md-3">
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
        <div class="col-md-3">
            <a href="uploads.php?date_range=day" class="text-decoration-none">
                <div class="card text-white bg-info clickable-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-calendar-day"></i> Today's Uploads
                        </h5>
                        <p class="card-text display-6"><?php echo $stats['uploads_today']; ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
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
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
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
                                        <td><?php echo date('H:i', strtotime($upload['created_at'])); ?></td>
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
        </div>

        <div class="col-lg-4">
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
                            <i class="bi bi-cloud-upload"></i> Review Content
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