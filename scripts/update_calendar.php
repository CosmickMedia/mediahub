<?php
require_once __DIR__.'/../lib/calendar.php';

$force = in_array('--force', $argv);
[$ok, $msg] = calendar_update($force);
echo ($ok ? 'SUCCESS: ' : 'ERROR: ') . $msg . "\n";
