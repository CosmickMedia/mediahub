<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/settings.php';
require_once __DIR__.'/hootsuite_api.php';

function hootsuite_profiles_ensure_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS hootsuite_profiles (
        id VARCHAR(50) PRIMARY KEY,
        type VARCHAR(100) NULL,
        username VARCHAR(255) NULL,
        network VARCHAR(50) NULL,
        raw TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function hootsuite_update_profiles(bool $debug = false): array {
    $token = get_setting('hootsuite_access_token');
    if (!$token) {
        return [false, 'Missing access token'];
    }
    $profiles = hootsuite_get_social_profiles($token);
    $pdo = get_pdo();
    hootsuite_profiles_ensure_schema($pdo);

    $type_map = [
        'facebookpage'      => 'facebook',
        'instagrambusiness' => 'instagram',
        'threads'           => 'threads',
        'youtubechannel'    => 'youtube',
        'pinterest'         => 'pinterest',
        'twitter'           => 'x',
        'linkedincompany'   => 'linkedin',
        'tiktokbusiness'    => 'tiktok',
    ];

    // Get existing profile IDs from database
    $existing_ids = $pdo->query('SELECT id FROM hootsuite_profiles')->fetchAll(PDO::FETCH_COLUMN);
    $existing_set = array_flip($existing_ids); // Convert to set for fast lookup

    // Collect new profile IDs from API
    $new_ids = [];
    foreach ($profiles as $p) {
        $id = $p['id'] ?? null;
        if ($id) $new_ids[] = $id;
    }

    // Check if we got any profiles from API
    if (empty($new_ids) && empty($existing_ids)) {
        // Both are empty - this is an error condition
        return [false, 'No profiles found in Hootsuite API. Check access token and permissions.'];
    }

    if (empty($new_ids) && !empty($existing_ids)) {
        // API returned nothing but we have profiles in DB - likely API error
        return [false, 'Hootsuite API returned no profiles. This may indicate an authentication or permission issue.'];
    }

    // Check if profiles are already up to date
    sort($existing_ids);
    $sorted_new_ids = $new_ids;
    sort($sorted_new_ids);
    if ($existing_ids === $sorted_new_ids) {
        $count = count($existing_ids);
        return [true, "Profiles already up to date ($count profiles)"];
    }

    // Log what we're about to sync
    if ($debug) {
        error_log("Hootsuite: Syncing " . count($new_ids) . " profiles from API (currently have " . count($existing_ids) . " in DB)");
    }

    try {
        $pdo->beginTransaction();

        // Prepare UPSERT statement (INSERT or UPDATE if exists)
        // This preserves existing profiles and their relationships with posts
        $upsert_stmt = $pdo->prepare('
            INSERT INTO hootsuite_profiles (id, type, username, network, raw)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                type = VALUES(type),
                username = VALUES(username),
                network = VALUES(network),
                raw = VALUES(raw)
        ');

        $updated_count = 0;
        $added_count = 0;

        // Insert or update each profile from API
        foreach ($profiles as $p) {
            $id = $p['id'] ?? null;
            if (!$id) continue;

            $type = strtolower($p['type'] ?? '');
            $username = $p['socialNetworkUsername'] ?? '';
            $network = $type_map[$type] ?? null;
            $raw = json_encode($p);

            $upsert_stmt->execute([$id, $type, $username, $network, $raw]);

            // Track if this was an update or insert
            if (isset($existing_set[$id])) {
                $updated_count++;
                unset($existing_set[$id]); // Remove from set to track deletions
            } else {
                $added_count++;
            }
        }

        // Delete profiles that no longer exist in Hootsuite
        // Note: If posts reference these profiles, CASCADE DELETE will remove the posts too
        $deleted_count = 0;
        if (!empty($existing_set)) {
            $to_delete = array_keys($existing_set);
            $placeholders = implode(',', array_fill(0, count($to_delete), '?'));
            $delete_stmt = $pdo->prepare("DELETE FROM hootsuite_profiles WHERE id IN ($placeholders)");
            $delete_stmt->execute($to_delete);
            $deleted_count = count($to_delete);

            // Log deleted profiles for audit
            if ($debug) {
                error_log('Hootsuite: Deleted profiles that no longer exist in API: ' . implode(', ', $to_delete));
            }
        }

        $pdo->commit();

        // Build success message
        $messages = [];
        if ($added_count > 0) $messages[] = "added $added_count";
        if ($updated_count > 0) $messages[] = "updated $updated_count";
        if ($deleted_count > 0) $messages[] = "removed $deleted_count";

        $summary = implode(', ', $messages);

        // Log detailed results if debug enabled
        if ($debug) {
            $total = count($new_ids);
            error_log("Hootsuite: Sync complete - Total: $total, Added: $added_count, Updated: $updated_count, Deleted: $deleted_count");
        }

        return [true, "Profiles synced: $summary"];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($debug) {
            return [false, 'DB Error: '.$e->getMessage()];
        }
        return [false, 'Failed to update profiles'];
    }
}
?>
