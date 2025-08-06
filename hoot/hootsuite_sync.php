<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/settings.php';

function hootsuite_update(bool $force = false, bool $debug = false): array {
    $enabled = get_setting('hootsuite_enabled');
    if ($enabled !== '1') {
        return [false, 'Hootsuite integration disabled'];
    }
    $interval = (int)(get_setting('hootsuite_update_interval') ?: 24);
    $last = get_setting('hootsuite_last_update');
    if (!$force && $last && (time() - strtotime($last) < $interval * 3600)) {
        return [false, 'Update not required yet'];
    }

    // Placeholder for real sync logic
    set_setting('hootsuite_last_update', date('Y-m-d H:i:s'));
    $msg = $force ? 'Forced Hootsuite sync executed' : 'Hootsuite sync executed';
    if ($debug) {
        $token = get_setting('hootsuite_access_token');
        $last = get_setting('hootsuite_token_last_refresh');
        $msg .= ' | token snippet: ' . ($token ? substr($token, 0, 8) . '...' : 'none');
        $msg .= ' | last refresh: ' . ($last ?: 'never');
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
        $msg .= ' | client_id snippet: ' . substr($id, 0, 8) . '...';
    }
    return [true, $msg];
}
