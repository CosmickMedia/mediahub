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
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/material/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="stores.php">Stores</a></li>
        <li class="nav-item"><a class="nav-link" href="uploads.php">Uploads</a></li>
        <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
