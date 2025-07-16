<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/calendar.php';
require_once __DIR__.'/../lib/helpers.php';

session_start();

if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch();
$store_name = $store['name'];

$posts = calendar_get_posts($store_id);

include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Calendar - <?php echo htmlspecialchars($store_name); ?></h2>
    <a href="index.php" class="btn btn-primary">Back to Upload</a>
</div>

<?php if (empty($posts)): ?>
    <div class="alert alert-info">No scheduled posts found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>Message</th>
                <th>Scheduled Time</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($posts as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['text'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($p['scheduled_time'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__.'/footer.php'; ?>
