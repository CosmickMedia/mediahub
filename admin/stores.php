<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Check if PIN already exists
        $stmt = $pdo->prepare('SELECT id FROM stores WHERE pin = ?');
        $stmt->execute([$_POST['pin']]);
        if ($stmt->fetch()) {
            $errors[] = 'PIN already exists';
        } else {
            $stmt = $pdo->prepare('INSERT INTO stores (name, pin, admin_email, drive_folder, hootsuite_token, first_name, last_name, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $_POST['name'],
                $_POST['pin'],
                $_POST['email'],
                $_POST['folder'],
                $_POST['hootsuite_token'],
                $_POST['first_name'] ?? null,
                $_POST['last_name'] ?? null,
                $_POST['phone'] ?? null,
                $_POST['address'] ?? null
            ]);
            $success[] = 'Store added successfully';
        }
    }
    if (isset($_POST['delete'])) {
        // Check if store has uploads
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE store_id = ?');
        $stmt->execute([$_POST['id']]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $errors[] = 'Cannot delete store with existing uploads';
        } else {
            $stmt = $pdo->prepare('DELETE FROM stores WHERE id=?');
            $stmt->execute([$_POST['id']]);
            $success[] = 'Store deleted successfully';
        }
    }
}

// Get stores sorted by name
$stores = $pdo->query('SELECT s.*, COUNT(u.id) as upload_count 
                       FROM stores s 
                       LEFT JOIN uploads u ON s.id = u.store_id 
                       GROUP BY s.id 
                       ORDER BY s.name ASC')->fetchAll(PDO::FETCH_ASSOC);

$active = 'stores';
include __DIR__.'/header.php';
?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Store Management</h4>
        <span class="badge bg-secondary">Total Stores: <?php echo count($stores); ?></span>
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

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>Store Name</th>
                        <th>PIN</th>
                        <th>Admin Email</th>
                        <th>Drive Folder ID</th>
                        <th>Hootsuite Token</th>
                        <th>Uploads</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stores as $s): ?>
                        <tr>
                            <td><strong><a href="edit_store.php?id=<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></a></strong></td>
                            <td><code><?php echo htmlspecialchars($s['pin']); ?></code></td>
                            <td><?php echo htmlspecialchars($s['admin_email']); ?></td>
                            <td>
                                <?php if ($s['drive_folder']): ?>
                                    <a href="https://drive.google.com/drive/folders/<?php echo $s['drive_folder']; ?>" target="_blank">
                                        <?php echo substr($s['drive_folder'], 0, 20); ?>...
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Auto-create on first upload</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $s['hootsuite_token'] ? '<span class="badge bg-success">Set</span>' : '<span class="badge bg-secondary">None</span>'; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $s['upload_count']; ?></span>
                            </td>
                            <td>
                                <a href="uploads.php?store_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary">
                                    View Uploads
                                </a>
                                <a href="edit_store.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-secondary">
                                    Edit
                                </a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <button name="delete" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Delete this store? This cannot be undone.')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Add New Store</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Store Name *</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="pin" class="form-label">PIN (Access Code) *</label>
                    <input type="text" name="pin" id="pin" class="form-control" required
                           pattern="[A-Za-z0-9]{4,}" title="At least 4 alphanumeric characters">
                    <div class="form-text">Unique code for store access</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Admin Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                    <div class="form-text">For notifications specific to this store</div>
                </div>
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" name="first_name" id="first_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="last_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" name="phone" id="phone" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" name="address" id="address" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="folder" class="form-label">Drive Folder ID</label>
                    <input type="text" name="folder" id="folder" class="form-control">
                    <div class="form-text">Leave blank to auto-create on first upload</div>
                </div>
                <div class="col-md-6">
                    <label for="hootsuite_token" class="form-label">Hootsuite Access Token</label>
                    <input type="text" name="hootsuite_token" id="hootsuite_token" class="form-control">
                    <div class="form-text">Optional: token used to fetch scheduled posts</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" name="add" type="submit">Add Store</button>
                </div>
            </form>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>