<?php
if (!isset($active)) { $active = ''; }
$version = trim(file_get_contents(__DIR__.'/../VERSION'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - MediaHub</title>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Montserrat Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Sweet Alert 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <?php if ($active === 'broadcasts'): ?>
        <!-- Tom Select CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.bootstrap5.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="inc/css/style.css?v=<?php echo $version; ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png" sizes="16x16">

</head>
<body>
<!-- Loading Screen -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Admin Navbar -->
<nav class="navbar navbar-expand-lg navbar-admin" id="adminNavbar">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="index.php">
            <div class="navbar-brand-content">
                <img src="/assets/images/mediahub-admin-logo.png" alt="MediaHub Admin" class="navbar-logo">
                <!--div class="navbar-title d-none d-md-block">
                    <div class="navbar-title-main">MediaHub</div>
                    <div class="navbar-title-sub">Admin Panel</div>
                </div-->
            </div>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler d-lg-none" type="button" onclick="toggleMobileMenu()">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Desktop Navigation -->
        <div class="navbar-nav d-none d-lg-flex">
            <a class="nav-link<?php if($active==='dashboard') echo ' active'; ?>" href="index.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link<?php if($active==='stores') echo ' active'; ?>" href="stores.php">
                <i class="bi bi-shop"></i>
                <span>Stores</span>
            </a>
            <a class="nav-link<?php if($active==='uploads') echo ' active'; ?>" href="uploads.php">
                <i class="bi bi-cloud-upload"></i>
                <span>Content</span>
            </a>
            <a class="nav-link<?php if($active==='broadcasts') echo ' active'; ?>" href="broadcasts.php">
                <i class="bi bi-megaphone"></i>
                <span>Broadcasts</span>
            </a>
            <a class="nav-link<?php if($active==='chat') echo ' active'; ?>" href="chat.php">
                <i class="bi bi-chat-dots"></i>
                <span>Chat</span>
            </a>
            <a class="nav-link<?php if($active==='settings') echo ' active'; ?>" href="settings.php">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
            <a class="nav-link<?php if($active==='users') echo ' active'; ?>" href="users.php">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
        </div>

        <!-- Admin User Section -->
        <div class="admin-user-section">
            <!-- Notifications -->
            <div class="notification-container">
                <div class="notification-bell" id="notifyWrap" title="Notifications">
                    <i class="bi bi-bell notification-icon" id="notifyBell"></i>
                    <span class="notification-badge" id="notifyCount">0</span>
                </div>
            </div>

            <!-- User Info -->
            <div class="admin-user-info">
                <div class="admin-avatar">
                    <?php
                    $firstName = $_SESSION['first_name'] ?? 'A';
                    $lastName = $_SESSION['last_name'] ?? 'U';
                    echo strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                    ?>
                </div>
                <div class="admin-details">
                    <div class="admin-name">
                        <?php
                        $fullName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
                        echo htmlspecialchars($fullName ?: $_SESSION['username'] ?? 'Admin');
                        ?>
                    </div>
                    <div class="admin-role">Administrator</div>
                </div>
            </div>

            <!-- Logout -->
            <a href="logout.php" class="logout-btn" title="Logout" onclick="return confirmLogout()">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>

        <!-- Mobile Menu -->
        <div class="mobile-menu d-lg-none" id="mobileMenu">
            <a class="nav-link<?php if($active==='dashboard') echo ' active'; ?>" href="index.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link<?php if($active==='stores') echo ' active'; ?>" href="stores.php">
                <i class="bi bi-shop"></i>
                <span>Stores</span>
            </a>
            <a class="nav-link<?php if($active==='uploads') echo ' active'; ?>" href="uploads.php">
                <i class="bi bi-cloud-upload"></i>
                <span>Content Review</span>
            </a>
            <a class="nav-link<?php if($active==='broadcasts') echo ' active'; ?>" href="broadcasts.php">
                <i class="bi bi-megaphone"></i>
                <span>Broadcasts</span>
            </a>
            <a class="nav-link<?php if($active==='chat') echo ' active'; ?>" href="chat.php">
                <i class="bi bi-chat-dots"></i>
                <span>Chat</span>
            </a>
            <a class="nav-link<?php if($active==='settings') echo ' active'; ?>" href="settings.php">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
            <a class="nav-link<?php if($active==='users') echo ' active'; ?>" href="users.php">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
        </div>
    </div>
</nav>

<!-- Scroll to Top Button -->
<button class="scroll-top-btn" id="scrollTopBtn" onclick="scrollToTop()">
    <i class="bi bi-arrow-up"></i>
</button>

<div class="admin-container page-transition">