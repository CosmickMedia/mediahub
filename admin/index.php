<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$active = 'dashboard';
include __DIR__.'/header.php';
?>
<h4>Admin Dashboard</h4>
<ul class="list-group">
  <li class="list-group-item"><a href="stores.php">Store Management</a></li>
  <li class="list-group-item"><a href="uploads.php">Content Review</a></li>
  <li class="list-group-item"><a href="settings.php">Settings</a></li>
  <li class="list-group-item"><a href="logout.php">Logout</a></li>
</ul>
<?php include __DIR__.'/footer.php'; ?>

