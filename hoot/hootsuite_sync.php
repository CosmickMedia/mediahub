<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/settings.php';

function hootsuite_update(bool $force = false, bool $debug = false): array {
    $enabled = get_setting('hootsuite_enabled');
    if ($enabled !== '1') {
        return [false, 'Hootsuite integration disabled'];
    }
    // Placeholder for real sync logic
    $msg = $force ? 'Forced Hootsuite sync executed' : 'Hootsuite sync executed';
    if ($debug) {
        $msg .= ' (debug mode)';
    }
    return [true, $msg];
}

function hootsuite_erase_all(): array {
    $pdo = get_pdo();
    try {
        $pdo->exec('TRUNCATE TABLE hootsuite_posts');
        return [true, 'All Hootsuite posts erased'];
    } catch (PDOException $e) {
        return [false, $e->getMessage()];
    }
}

function hootsuite_test_connection(bool $debug = false): array {
    $id = get_setting('hootsuite_client_id');
    $secret = get_setting('hootsuite_client_secret');
    $uri = get_setting('hootsuite_redirect_uri');
    if (!$id || !$secret || !$uri) {
        return [false, 'Missing Hootsuite OAuth credentials'];
    }
    // Real implementation would attempt an OAuth handshake
    $msg = 'OAuth settings present';
    if ($debug) {
        $msg .= ' (debug mode)';
    }
    return [true, $msg];
}
