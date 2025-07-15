<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM store_messages WHERE id = ?');
$stmt->execute([$id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    header('Location: messages.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $text = sanitize_message($_POST['message'] ?? '');
    if ($text === '') {
        $errors[] = 'Message cannot be empty';
    } else {
        $upd = $pdo->prepare('UPDATE store_messages SET message=? WHERE id=?');
        $upd->execute([$text, $id]);
        $success = true;
        $message['message'] = $text;
    }
}

$active = 'messages';
include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Edit Broadcast</h4>
    <a href="messages.php" class="btn btn-sm btn-outline-secondary">Back</a>
</div>
<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Message updated successfully
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="4" required><?php echo htmlspecialchars($message['message']); ?></textarea>
            </div>
            <button class="btn btn-primary" name="save" type="submit">Save Changes</button>
        </form>
    </div>
</div>
<?php include __DIR__.'/footer.php'; ?>
