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
    <link rel="stylesheet" href="inc/css/style.css?v=<?php echo $version; ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/assets/images/favicon-16x16.png" sizes="16x16">

    <style>
        /* Root Variables */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --admin-navbar-height: 75px;
            --sidebar-width: 280px;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        /* Base Styles */
        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Montserrat', sans-serif;
            padding-top: var(--admin-navbar-height);
            min-height: 100vh;
            position: relative;
        }

        /* Loading Screen */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Admin Navbar */
        .navbar-admin {
            background: var(--primary-gradient);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 0;
            min-height: var(--admin-navbar-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            transition: var(--transition);
        }

        .navbar-admin.scrolled {
            background: var(--primary-gradient);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .navbar-admin .container-fluid {
            padding: 0 2rem;
            height: var(--admin-navbar-height);
            display: flex;
            align-items: center;
        }

        .navbar-admin .navbar-brand {
            display: flex;
            align-items: center;
            padding: 0;
            margin-right: 2rem;
            text-decoration: none;
        }

        .navbar-brand-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .navbar-logo {
            height: 45px;
            width: auto;
            transition: var(--transition);
        }

        .navbar-logo:hover {
            transform: translateY(-2px) scale(1.05);
        }

        .navbar-title {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .navbar-title-main {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            margin: 0;
        }

        .navbar-title-sub {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Navigation Links */
        .navbar-admin .navbar-nav {
            flex-direction: row;
            gap: 0.25rem;
        }

        .navbar-admin .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 12px;
            transition: var(--transition);
            position: relative;
            text-decoration: none;
            white-space: nowrap;
        }

        .navbar-admin .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .navbar-admin .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .navbar-admin .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
        }

        .navbar-admin .nav-link i {
            font-size: 1.1rem;
        }

        /* Admin User Section */
        .admin-user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #2c3e50;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            background: rgba(102, 126, 234, 0.05);
            transition: var(--transition);
        }

        .admin-user-info:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .admin-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .admin-details {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .admin-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
        }

        .admin-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* Notification Bell */
        .notification-container {
            position: relative;
        }

        .notification-bell {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .notification-bell:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .notification-bell:hover .notification-icon {
            color: white;
            animation: ring 0.5s ease-in-out;
        }

        .notification-icon {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
        }

        .notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            background: var(--danger-gradient);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(245, 87, 108, 0.4);
            display: none;
            animation: pulse 2s infinite;
        }

        @keyframes ring {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg); }
            75% { transform: rotate(10deg); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Logout Button */
        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .logout-btn:hover {
            background: var(--danger-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.3);
        }

        .logout-btn i {
            font-size: 1.25rem;
        }

        /* Mobile Navigation */
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: var(--transition);
            display: none;
        }

        .navbar-toggler:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
        }

        .navbar-toggler:hover .navbar-toggler-icon {
            filter: brightness(0) invert(1);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2833, 37, 41, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
            width: 20px;
            height: 20px;
        }

        /* Mobile Menu */
        .mobile-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border-radius: 0 0 20px 20px;
            padding: 1rem;
            transform: translateY(-10px);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
        }

        .mobile-menu.show {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }

        .mobile-menu .nav-link {
            width: 100%;
            margin-bottom: 0.5rem;
            padding: 1rem;
            border-radius: 12px;
            justify-content: flex-start;
        }

        .mobile-menu .nav-link:last-child {
            margin-bottom: 0;
        }

        /* Container */
        .admin-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
            min-height: calc(100vh - var(--admin-navbar-height));
        }

        /* Scroll to Top Button */
        .scroll-top-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            opacity: 0;
            visibility: hidden;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .scroll-top-btn.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
        }

        /* Theme Toggle - Hidden */
        .theme-toggle {
            display: none;
        }

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --glass-bg: rgba(33, 37, 41, 0.95);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .admin-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 992px) {
            .navbar-admin .navbar-nav {
                display: none;
            }

            .navbar-toggler {
                display: block;
            }

            .admin-details {
                display: none;
            }

            .admin-user-info {
                padding: 0.5rem;
                background: transparent;
            }

            .admin-user-section {
                gap: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .navbar-admin .container-fluid {
                padding: 0 1rem;
            }

            .admin-container {
                padding: 1rem;
            }

            .scroll-top-btn {
                bottom: 1rem;
                right: 1rem;
                width: 45px;
                height: 45px;
            }

            .navbar-title {
                display: none;
            }

            .admin-user-section {
                gap: 0.5rem;
            }

            .theme-toggle,
            .notification-container,
            .logout-btn {
                width: 40px;
                height: 40px;
            }

            .admin-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
            }
        }

        /* Page Loading Animation */
        .page-transition {
            animation: pageSlideIn 0.5s ease-out;
        }

        @keyframes pageSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a67d8, #6b46c1);
        }
    </style>
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