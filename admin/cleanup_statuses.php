<?php
/**
 * One-time cleanup script to remove duplicate upload statuses
 * and reassign uploads to canonical status IDs.
 *
 * Run this once, then delete this file.
 */

require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();

$pdo = get_pdo();

// Mapping: duplicate ID => canonical ID to keep
$mapping = [
    76 => 1,  // Reviewed duplicates -> 1
    79 => 1,
    77 => 2,  // Pending Submission duplicates -> 2
    80 => 2,
    78 => 3,  // Scheduled duplicates -> 3
    81 => 3,
];

$results = [];

echo "<!DOCTYPE html><html><head><title>Status Cleanup</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee;} ";
echo ".success{color:#4ade80;} .info{color:#60a5fa;} .warn{color:#fbbf24;} h1{color:#a78bfa;}</style></head><body>";
echo "<h1>Upload Status Cleanup</h1>";

// First, show current state
echo "<h2>Current Statuses:</h2>";
$statuses = $pdo->query('SELECT id, name, color FROM upload_statuses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach ($statuses as $s) {
    echo "ID {$s['id']}: {$s['name']} ({$s['color']})\n";
}
echo "</pre>";

// Check if cleanup is needed
$duplicateIds = array_keys($mapping);
$placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM upload_statuses WHERE id IN ($placeholders)");
$stmt->execute($duplicateIds);
$duplicateCount = $stmt->fetchColumn();

if ($duplicateCount == 0) {
    echo "<p class='success'>No duplicate statuses found. Cleanup already complete or not needed.</p>";
    echo "</body></html>";
    exit;
}

// Perform cleanup if confirmed
if (isset($_POST['confirm'])) {
    echo "<h2>Running Cleanup...</h2>";

    $pdo->beginTransaction();

    try {
        foreach ($mapping as $oldId => $newId) {
            // Check if old status exists
            $stmt = $pdo->prepare('SELECT name FROM upload_statuses WHERE id = ?');
            $stmt->execute([$oldId]);
            $oldStatus = $stmt->fetchColumn();

            if (!$oldStatus) {
                echo "<p class='info'>Status ID $oldId doesn't exist, skipping...</p>";
                continue;
            }

            // Count uploads to reassign
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE status_id = ?');
            $stmt->execute([$oldId]);
            $uploadCount = $stmt->fetchColumn();

            // Reassign uploads
            $stmt = $pdo->prepare('UPDATE uploads SET status_id = ? WHERE status_id = ?');
            $stmt->execute([$newId, $oldId]);
            echo "<p class='success'>Reassigned $uploadCount uploads from status $oldId to $newId</p>";

            // Clean history - old_status_id
            $stmt = $pdo->prepare('UPDATE upload_status_history SET old_status_id = ? WHERE old_status_id = ?');
            $stmt->execute([$newId, $oldId]);

            // Clean history - new_status_id
            $stmt = $pdo->prepare('UPDATE upload_status_history SET new_status_id = ? WHERE new_status_id = ?');
            $stmt->execute([$newId, $oldId]);

            // Delete duplicate status
            $stmt = $pdo->prepare('DELETE FROM upload_statuses WHERE id = ?');
            $stmt->execute([$oldId]);
            echo "<p class='success'>Deleted duplicate status ID $oldId ($oldStatus)</p>";
        }

        $pdo->commit();
        echo "<h2 class='success'>Cleanup Complete!</h2>";

        // Show final state
        echo "<h2>Final Statuses:</h2>";
        $statuses = $pdo->query('SELECT id, name, color FROM upload_statuses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($statuses as $s) {
            echo "ID {$s['id']}: {$s['name']} ({$s['color']})\n";
        }
        echo "</pre>";

        echo "<p class='warn'>You can now delete this cleanup script file.</p>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Transaction rolled back. No changes made.</p>";
    }

} else {
    // Show confirmation form
    echo "<h2>Cleanup Plan:</h2>";
    echo "<ul>";
    foreach ($mapping as $oldId => $newId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE status_id = ?');
        $stmt->execute([$oldId]);
        $count = $stmt->fetchColumn();
        echo "<li>Merge status ID $oldId → $newId ($count uploads will be reassigned)</li>";
    }
    echo "</ul>";

    echo "<form method='post'>";
    echo "<p class='warn'>This will permanently delete duplicate statuses and reassign uploads.</p>";
    echo "<button type='submit' name='confirm' value='1' style='padding:10px 20px;background:#4ade80;color:black;border:none;cursor:pointer;font-size:16px;'>Confirm Cleanup</button>";
    echo "</form>";
}

echo "</body></html>";
