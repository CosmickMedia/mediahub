<?php
require_once __DIR__.'/../lib/settings.php';
require_once __DIR__.'/../lib/hootsuite/refresh_token.php';

$enabled = get_setting('hootsuite_enabled');
if ($enabled !== '1') {
    echo "Hootsuite integration disabled" . PHP_EOL;
    exit;
}

$interval = (int)(get_setting('hootsuite_token_refresh_interval') ?: 24);
$last = get_setting('hootsuite_token_last_refresh');
if ($last && (time() - strtotime($last) < $interval * 3600)) {
    echo "Token refresh not required yet" . PHP_EOL;
    exit;
}

[$ok, $msg] = hootsuite_refresh_token(false);

echo ($ok ? 'SUCCESS: ' : 'ERROR: ') . $msg . PHP_EOL;
