<?php
require_once __DIR__.'/../hoot/hootsuite_sync.php';
[$ok, $msg] = hootsuite_update(false, false);
echo ($ok ? 'SUCCESS: ' : 'ERROR: ') . $msg . PHP_EOL;
