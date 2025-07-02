<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare('INSERT INTO stores (name, pin, admin_email, drive_folder) VALUES (?, ?, ?, ?)');
        $stmt->execute([$_POST['name'], $_POST['pin'], $_POST['email'], $_POST['folder']]);
    }
    if (isset($_POST['delete'])) {
        $stmt = $pdo->prepare('DELETE FROM stores WHERE id=?');
        $stmt->execute([$_POST['id']]);
    }
}
$stores = $pdo->query('SELECT * FROM stores')->fetchAll(PDO::FETCH_ASSOC);
$active = 'stores';
include __DIR__.'/header.php';
<h4>Store Management</h4>
<table class="table table-striped">
<tr><th>Name</th><th>PIN</th><th>Email</th><th>Folder</th><th></th></tr>
<?php foreach ($stores as $s): ?>
<tr>
<td><?php echo htmlspecialchars($s['name']); ?></td>
<td><?php echo htmlspecialchars($s['pin']); ?></td>
<td><?php echo htmlspecialchars($s['admin_email']); ?></td>
<td><?php echo htmlspecialchars($s['drive_folder']); ?></td>
<td>
<form method="post" class="d-inline">
<input type="hidden" name="id" value="<?php echo $s['id']; ?>">
<button name="delete" class="btn btn-danger" onclick="return confirm('Delete?')">Del</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<h5 class="mt-4">Add Store</h5>
<form method="post">
<div class="mb-3"><label for="name" class="form-label">Name</label><input type="text" name="name" id="name" class="form-control" required></div>
<div class="mb-3"><label for="pin" class="form-label">PIN</label><input type="text" name="pin" id="pin" class="form-control" required></div>
<div class="mb-3"><label for="email" class="form-label">Admin Email</label><input type="email" name="email" id="email" class="form-control"></div>
<div class="mb-3"><label for="folder" class="form-label">Drive Folder ID</label><input type="text" name="folder" id="folder" class="form-control"></div>
<button class="btn btn-primary" name="add" type="submit">Add</button>
</form>
<?php include __DIR__.'/footer.php'; ?>

