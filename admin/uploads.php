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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
</head>
<body class="container">
<h4>Recent Uploads</h4>
<form method="get" class="row">
<div class="input-field col s4"><input type="text" name="pin" id="pin" value="<?php echo htmlspecialchars($_GET['pin'] ?? ''); ?>"><label for="pin">Store PIN</label></div>
<div class="input-field col s4"><input type="date" name="date" id="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>"></div>
<div class="input-field col s4"><button class="btn" type="submit">Filter</button></div>
</form>
<div class="row">
<?php foreach ($rows as $r): ?>
<div class="col s12 m6 l4">
<div class="card">
<div class="card-content">
<span class="card-title"><?php echo htmlspecialchars($r['filename']); ?></span>
<p><?php echo htmlspecialchars($r['description']); ?></p>
<p><?php echo htmlspecialchars($r['name']); ?> (<?php echo htmlspecialchars($r['pin']); ?>)</p>
<p><?php echo htmlspecialchars($r['created_at']); ?></p>
</div>
<div class="card-action">
<a href="download.php?id=<?php echo $r['id']; ?>" target="_blank">Download</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
