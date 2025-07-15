<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_login();
$pdo = get_pdo();

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all'])) {
    $results = groundhogg_sync_admin_users();
}

$active = 'settings';
include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Sync Admin Users with Groundhogg</h4>
    <a href="settings.php" class="btn btn-sm btn-outline-secondary">Back to Settings</a>
</div>
<?php if (!empty($results)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Sync Results</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr><th>Email</th><th>Status</th><th>Message</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['email']); ?></td>
                            <td><?php echo $r['success'] ? '<span class="badge bg-success">Success</span>' : '<span class="badge bg-danger">Failed</span>'; ?></td>
                            <td><?php echo htmlspecialchars($r['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <h5 class="card-title">Sync All Admin Users</h5>
        <p class="card-text">This will sync all admin users with Groundhogg CRM.</p>
        <form method="post">
            <button type="submit" name="sync_all" class="btn btn-primary" onclick="return confirm('Sync all admin users with Groundhogg?');">Sync Admin Users</button>
        </form>
    </div>
</div>
<?php include __DIR__.'/footer.php'; ?>
