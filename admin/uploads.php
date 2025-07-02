<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();

$where = [];
$params = [];
if (!empty($_GET['pin'])) {
    $where[] = 's.pin = ?';
    $params[] = $_GET['pin'];
}
if (!empty($_GET['date'])) {
    $where[] = 'DATE(u.created_at) = ?';
    $params[] = $_GET['date'];
}
$sql = 'SELECT u.*, s.name, s.pin FROM uploads u JOIN stores s ON u.store_id=s.id';
if ($where) {
    $sql .= ' WHERE '.implode(' AND ', $where);
}
$sql .= ' ORDER BY u.created_at DESC LIMIT 50';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
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
        <li class="nav-item"><a class="nav-link" href="stores.php">Stores</a></li>
        <li class="nav-item"><a class="nav-link active" href="uploads.php">Uploads</a></li>
        <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
<h4>Recent Uploads</h4>
<form method="get" class="row g-3 align-items-end">
<div class="col-md-4">
  <label for="pin" class="form-label">Store PIN</label>
  <input type="text" name="pin" id="pin" value="<?php echo htmlspecialchars($_GET['pin'] ?? ''); ?>" class="form-control">
</div>
<div class="col-md-4">
  <label for="date" class="form-label">Date</label>
  <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>" class="form-control">
</div>
<div class="col-md-4">
  <button class="btn btn-primary" type="submit">Filter</button>
</div>
</form>
<div class="row mt-4">
<?php foreach ($rows as $r): ?>
<div class="col-12 col-md-6 col-lg-4">
<div class="card mb-3">
<div class="card-body">
<h5 class="card-title"><?php echo htmlspecialchars($r['filename']); ?></h5>
<p class="card-text"><?php echo htmlspecialchars($r['description']); ?></p>
<p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($r['name']); ?> (<?php echo htmlspecialchars($r['pin']); ?>)</small></p>
<p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($r['created_at']); ?></small></p>
<a class="btn btn-secondary" href="download.php?id=<?php echo $r['id']; ?>" target="_blank">Download</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
