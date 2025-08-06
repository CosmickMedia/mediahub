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

    try {
        $pdo->beginTransaction();
        $pdo->exec('TRUNCATE TABLE hootsuite_profiles');
        $stmt = $pdo->prepare('INSERT INTO hootsuite_profiles (id, type, username, network, raw) VALUES (?, ?, ?, ?, ?)');
        foreach ($profiles as $p) {
            $id = $p['id'] ?? null;
            if (!$id) continue;
            $type = strtolower($p['type'] ?? '');
            $username = $p['socialNetworkUsername'] ?? '';
            $network = $type_map[$type] ?? null;
            $raw = json_encode($p);
            $stmt->execute([$id, $type, $username, $network, $raw]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($debug) {
            return [false, 'DB Error: '.$e->getMessage()];
        }
        return [false, 'Failed to update profiles'];
    }

    return [true, 'Updated '.count($profiles).' profiles'];
}
?>
