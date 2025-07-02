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
$active = 'uploads';
include __DIR__.'/header.php';
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
<?php include __DIR__.'/footer.php'; ?>

