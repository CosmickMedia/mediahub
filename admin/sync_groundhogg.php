<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_login();
$pdo = get_pdo();

$results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all'])) {
    // Get all stores
    $stores = $pdo->query('SELECT id, name FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stores as $store) {
        [$success, $storeResults] = groundhogg_sync_store_contacts($store['id']);

        foreach ($storeResults as $result) {
            $results[] = [
                'store' => $store['name'],
                'email' => $result['email'],
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }
    }
}

$active = 'settings';
include __DIR__.'/header.php';
?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Sync Stores with Groundhogg</h4>
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
                    <tr>
                        <th>Store</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['store']); ?></td>
                            <td><?php echo htmlspecialchars($result['email']); ?></td>
                            <td>
                                <?php if ($result['success']): ?>
                                    <span class="badge bg-success">Success</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($result['message']); ?></td>
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
            <h5 class="card-title">Sync All Store Contacts</h5>
            <p class="card-text">
                This will sync all stores and their users with Groundhogg CRM.
                Only stores with email addresses will be synced.
            </p>
            <form method="post">
                <button type="submit" name="sync_all" class="btn btn-primary"
                        onclick="return confirm('This will sync all stores with Groundhogg. Continue?')">
                    Sync All Stores
                </button>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Debugging Information</h5>
        </div>
        <div class="card-body">
            <p>If syncing is not working, check the following:</p>
            <ol>
                <li>Ensure all Groundhogg settings are configured in <a href="settings.php">Settings</a></li>
                <li>Test the connection using the "Test Connection" button</li>
                <li>Enable debug logging in settings to see detailed API communication</li>
                <li>Check the log file at <code>logs/groundhogg.log</code> if debug is enabled</li>
            </ol>

            <?php if (groundhogg_debug_enabled()): ?>
                <div class="alert alert-info">
                    <strong>Debug Mode Enabled</strong> - Check <code>logs/groundhogg.log</code> for detailed information.
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>