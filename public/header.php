<?php
if (!isset($_SESSION)) { session_start(); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Content App Library</title>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .navbar-brand { font-weight: 600; }
        .navbar { background-color: #2c3e50 !important; }
        body { background-color: #f8f9fa; }
        .card { border: none; }
        .btn-primary { background-color: #3498db; border-color: #3498db; }
        .btn-primary:hover { background-color: #2980b9; border-color: #2980b9; }
        .navbar-logo { height: 30px; width: auto; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="/assets/images/mediahub-logo.png" alt="MediaHub" class="navbar-logo me-2">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPublic" aria-controls="navbarPublic" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarPublic">
            <ul class="navbar-nav ms-auto">
                <?php if(isset($_SESSION['store_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link disabled" href="#">
                            <i class="bi bi-shop"></i> <?php echo htmlspecialchars($_SESSION['store_name'] ?? 'Store'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="bi bi-clock-history"></i> History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendar.php">
                            <i class="bi bi-calendar-event"></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="marketing.php">
                            <i class="bi bi-graph-up"></i> Marketing Report
                        </a>
                    </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat-dots"></i> Chat
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifyCount" style="display:none;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=1">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">