<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

$success = [];
$errors = [];

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $store_id = $_POST['store_id'] ?? null;

    if (empty($message)) {
        $errors[] = 'Message cannot be empty';
    } else {
        if ($store_id === 'all' || empty($store_id)) {
            $store_id = null; // NULL means global message
        }

        $stmt = $pdo->prepare('INSERT INTO store_messages (store_id, message, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$store_id, $message]);

        $success[] = 'Message posted successfully';
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare('DELETE FROM store_messages WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    header('Location: messages.php');
    exit;
}

// Get all stores
$stores = $pdo->query('SELECT id, name FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Get existing messages
$messages = $pdo->query('
    SELECT m.*, s.name as store_name 
    FROM store_messages m 
    LEFT JOIN stores s ON m.store_id = s.id 
    ORDER BY m.created_at DESC
')->fetchAll(PDO::FETCH_ASSOC);

$active = 'messages';
include __DIR__.'/header.php';
?>

    <h4>Store Messages</h4>

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

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Post New Message</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="store_id" class="form-label">Target Store</label>
                            <select name="store_id" id="store_id" class="form-select">
                                <option value="all">All Stores (Global Message)</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>">
                                        <?php echo htmlspecialchars($store['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea name="message" id="message" class="form-control" rows="4"
                                      placeholder="Enter your message here..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Post Message</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Active Messages</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <p class="text-muted">No active messages</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($messages as $msg): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php if ($msg['store_id']): ?>
                                                <span class="badge bg-info">
                                            <?php echo htmlspecialchars($msg['store_name']); ?>
                                        </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">All Stores</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small><?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    <a href="?delete=<?php echo $msg['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this message?')">Delete</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>