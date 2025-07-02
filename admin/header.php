<?php
if (!isset($active)) { $active = ''; }
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link<?php if($active==='dashboard') echo ' active'; ?>" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link<?php if($active==='stores') echo ' active'; ?>" href="stores.php">Stores</a></li>
        <li class="nav-item"><a class="nav-link<?php if($active==='uploads') echo ' active'; ?>" href="uploads.php">Uploads</a></li>
        <li class="nav-item"><a class="nav-link<?php if($active==='settings') echo ' active'; ?>" href="settings.php">Settings</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container">

