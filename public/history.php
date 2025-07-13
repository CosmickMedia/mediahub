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

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE store_id = ?');
$stmt->execute([$store_id]);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get uploads
$stmt = $pdo->prepare("
    SELECT * FROM uploads 
    WHERE store_id = ? 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$store_id]);
$uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__.'/header.php';
?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Upload History - <?php echo htmlspecialchars($store_name); ?></h2>
        <a href="index.php" class="btn btn-primary">Back to Upload</a>
    </div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($uploads)): ?>
    <div class="alert alert-info">No uploads found.</div>
<?php else: ?>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th style="width: 100px;">Preview</th>
                <th>Date</th>
                <th>File Name</th>
                <th>Description</th>
                <th>Message</th>
                <th>Size</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($uploads as $upload):
                $isVideo = strpos($upload['mime'], 'video') !== false;
                ?>
                <tr>
                    <td>
                        <img src="thumbnail.php?id=<?php echo $upload['id']; ?>&size=small"
                             class="img-thumbnail"
                             style="width: 80px; height: 80px; object-fit: cover;"
                             alt="<?php echo htmlspecialchars($upload['filename']); ?>"
                             loading="lazy">
                    </td>
                    <td><?php echo format_ts($upload['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($upload['filename']); ?></td>
                    <td><?php echo htmlspecialchars($upload['description']); ?></td>
                    <td>
                        <?php if (!empty($upload['custom_message'])): ?>
                            <small><?php echo htmlspecialchars(substr($upload['custom_message'], 0, 50)); ?>...</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($upload['size'] / 1024 / 1024, 2); ?> MB</td>
                    <td><?php echo htmlspecialchars(explode('/', $upload['mime'])[0]); ?></td>
                    <td>
                        <a href="https://drive.google.com/file/d/<?php echo $upload['drive_id']; ?>/view"
                           target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                            <input type="hidden" name="delete_id" value="<?php echo $upload['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                </li>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php include __DIR__.'/footer.php'; ?>