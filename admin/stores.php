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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
</head>
<body class="container">
<h4>Store Management</h4>
<table class="striped">
<tr><th>Name</th><th>PIN</th><th>Email</th><th>Folder</th><th></th></tr>
<?php foreach ($stores as $s): ?>
<tr>
<td><?php echo htmlspecialchars($s['name']); ?></td>
<td><?php echo htmlspecialchars($s['pin']); ?></td>
<td><?php echo htmlspecialchars($s['admin_email']); ?></td>
<td><?php echo htmlspecialchars($s['drive_folder']); ?></td>
<td>
<form method="post" style="display:inline">
<input type="hidden" name="id" value="<?php echo $s['id']; ?>">
<button name="delete" class="btn red" onclick="return confirm('Delete?')">Del</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<h5>Add Store</h5>
<form method="post">
<div class="input-field"><input type="text" name="name" id="name" required><label for="name">Name</label></div>
<div class="input-field"><input type="text" name="pin" id="pin" required><label for="pin">PIN</label></div>
<div class="input-field"><input type="email" name="email" id="email"><label for="email">Admin Email</label></div>
<div class="input-field"><input type="text" name="folder" id="folder"><label for="folder">Drive Folder ID</label></div>
<button class="btn" name="add" type="submit">Add</button>
</form>
</body>
</html>
