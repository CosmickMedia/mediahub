<?php
require_once __DIR__.'/hootsuite_profiles_sync.php';
[$ok, $msg] = hootsuite_update_profiles(false);
if (!$ok) {
    error_log('Hootsuite profile update failed: '.$msg);
}
?>
