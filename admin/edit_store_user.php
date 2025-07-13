<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

$store_id = $_GET['store_id'] ?? 0;
$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare('SELECT * FROM store_users WHERE id=? AND store_id=?');
$stmt->execute([$id, $store_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: edit_store.php?id=' . urlencode($store_id));
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $email = trim($_POST['email'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    } else {
        $stmt = $pdo->prepare('UPDATE store_users SET email=?, first_name=?, last_name=? WHERE id=? AND store_id=?');
        $stmt->execute([$email, $first ?: null, $last ?: null, $id, $store_id]);
        $success = true;
        $stmt = $pdo->prepare('SELECT * FROM store_users WHERE id=? AND store_id=?');
        $stmt->execute([$id, $store_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$active = 'stores';
include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Edit Store User</h4>
    <a href="edit_store.php?id=<?php echo $store_id; ?>" class="btn btn-sm btn-outline-secondary">Back</a>
</div>
<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        User updated successfully
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>">
            </div>
            <div class="col-md-6">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>">
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" name="save_user" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__.'/footer.php'; ?>
