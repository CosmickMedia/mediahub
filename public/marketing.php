<?php
require_once __DIR__.'/../lib/db.php';
session_start();
if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT marketing_report_url, name FROM stores WHERE id = ?');
$stmt->execute([$_SESSION['store_id']]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);
$url = $store['marketing_report_url'];
$store_name = $store['name'];
include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Marketing Report - <?php echo htmlspecialchars($store_name); ?></h2>
    <a href="index.php" class="btn btn-primary">Back to Upload</a>
</div>
<?php if ($url): ?>
    <iframe id="reportFrame" src="<?php echo htmlspecialchars($url); ?>" style="width:100%; border:0;" allowfullscreen></iframe>
    <script>
        function resizeFrame(){
            var iframe=document.getElementById('reportFrame');
            iframe.style.height=(window.innerHeight-iframe.getBoundingClientRect().top-20)+"px";
        }
        window.addEventListener('load',resizeFrame);
        window.addEventListener('resize',resizeFrame);
    </script>
<?php else: ?>
    <div class="alert alert-warning">Marketing report not setup yet.</div>
<?php endif; ?>
<?php include __DIR__.'/footer.php'; ?>
