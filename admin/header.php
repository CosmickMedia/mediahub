<?php
if (!isset($active)) { $active = ''; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Content App Library</title>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .navbar-brand { font-weight: 600; }
        .navbar { background-color: #2c3e50 !important; }
        body { background-color: #f8f9fa; }
        .card { border: none; }
        .btn-primary { background-color: #2c3e50; border-color: #2c3e50; }
        .btn-primary:hover { background-color: #1a252f; border-color: #1a252f; }
        .btn-outline-primary { color: #2c3e50; border-color: #2c3e50; }
        .btn-outline-primary:hover { background-color: #2c3e50; border-color: #2c3e50; }
        a { color: #2c3e50; }
        a:hover { color: #1a252f; }
        .page-link { color: #2c3e50; }
        .page-item.active .page-link { background-color: #2c3e50; border-color: #2c3e50; }
        .bg-primary { background-color: #2c3e50 !important; }
        .bg-warning { background-color: #d39e00 !important; color: #fff !important; }
        .bg-info { background-color: #17a2b8 !important; color: #fff !important; }
        .text-primary { color: #2c3e50 !important; }
        .clickable-card { cursor: pointer; transition: transform 0.2s; }
        .clickable-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .navbar-logo { height: 35px; width: auto; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="/assets/images/mediahub-admin-logo.png" alt="MediaHub Admin" class="navbar-logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link<?php if($active==='dashboard') echo ' active'; ?>" href="index.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link<?php if($active==='stores') echo ' active'; ?>" href="stores.php">Stores</a></li>
                <li class="nav-item"><a class="nav-link<?php if($active==='uploads') echo ' active'; ?>" href="uploads.php">Content Review</a></li>
                <li class="nav-item"><a class="nav-link<?php if($active==='messages') echo ' active'; ?>" href="messages.php">Messages</a></li>
                <li class="nav-item"><a class="nav-link<?php if($active==='chat') echo ' active'; ?>" href="chat.php">Chat</a></li>
                <li class="nav-item"><a class="nav-link<?php if($active==='settings') echo ' active'; ?>" href="settings.php">Settings</a></li>
                <li class="nav-item"><a class="nav-link<?php if($active==='users') echo ' active'; ?>" href="users.php">Users</a></li>
            </ul>
            <div class="ms-auto text-end small text-white">
                Logged in as: <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?><br>
                <a href="logout.php" class="text-white text-decoration-none">Logout</a>
            </div>
        </div>
    </div>
</nav>
<div class="container pb-5">