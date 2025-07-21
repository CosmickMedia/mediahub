<?php
if (!isset($_SESSION)) { session_start(); }

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get unread messages count for notification
$unread_count = 0;
if (isset($_SESSION['store_id'])) {
    require_once __DIR__.'/../lib/db.php';
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM store_messages WHERE store_id = ? AND sender = 'admin' AND read_by_store = 0");
    $stmt->execute([$_SESSION['store_id']]);
    $unread_count = $stmt->fetchColumn();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaHub - Content Management Platform</title>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Montserrat Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="inc/css/style.css">
    <?php if(isset($extra_head)) echo $extra_head; ?>

    <style>
        /* Modern Header Styles */
        :root {
            --header-height: 70px;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --header-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Navbar Styles */
        .navbar-modern {
            background: white;
            box-shadow: var(--header-shadow);
            padding: 0;
            min-height: var(--header-height);
            transition: var(--transition);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }

        .navbar-modern.scrolled {
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
        }

        /* Container */
        .navbar-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height);
        }

        /* Logo Section */
        .navbar-logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-logo {
            height: 40px;
            width: auto;
            transition: var(--transition);
        }

        .navbar-logo:hover {
            transform: scale(1.05);
        }

        .navbar-divider {
            width: 2px;
            height: 30px;
            background: #e9ecef;
            margin: 0 0.5rem;
        }

        .navbar-store-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation Menu */
        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-item-modern {
            position: relative;
        }

        .nav-link-modern {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            border-radius: 12px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .nav-link-modern:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .nav-link-modern.active {
            color: white;
            background: var(--primary-gradient);
        }

        .nav-link-modern i {
            font-size: 1.1rem;
        }

        .nav-link-modern .nav-badge {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
        }

        /* User Section */
        .navbar-user-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        /* Notification Bell */
        .notification-wrapper {
            position: relative;
        }

        .notification-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .notification-btn:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .notification-btn.has-notifications {
            animation: bellShake 2s ease-in-out infinite;
        }

        @keyframes bellShake {
            0%, 90%, 100% { transform: rotate(0deg); }
            80%, 85% { transform: rotate(-10deg); }
            82.5%, 87.5% { transform: rotate(10deg); }
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border-radius: 25px;
            transition: var(--transition);
        }

        .user-info:hover {
            background: #e9ecef;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.75rem;
            color: #6c757d;
        }

        /* Logout Button */
        .logout-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fee2e2;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px) rotate(-10deg);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            width: 40px;
            height: 40px;
            border: none;
            background: #f8f9fa;
            border-radius: 10px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            background: #e9ecef;
        }

        .mobile-menu-toggle span {
            width: 20px;
            height: 2px;
            background: #2c3e50;
            transition: var(--transition);
        }

        .mobile-menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }

        .mobile-menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
        }

        /* Mobile Menu */
        .mobile-menu {
            display: none;
            position: fixed;
            top: var(--header-height);
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-height: calc(100vh - var(--header-height));
            overflow-y: auto;
            z-index: 1029;
        }

        .mobile-menu.active {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mobile-menu-list {
            list-style: none;
            padding: 1rem;
            margin: 0;
        }

        .mobile-menu-item {
            margin-bottom: 0.5rem;
        }

        .mobile-menu-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            border-radius: 12px;
            transition: var(--transition);
        }

        .mobile-menu-link:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .mobile-menu-link.active {
            background: var(--primary-gradient);
            color: white;
        }

        .mobile-user-section {
            padding: 1rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Page Content Spacing */
        body {
            padding-top: var(--header-height);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .navbar-container {
                padding: 0 1rem;
            }

            .navbar-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: flex;
            }

            .navbar-divider {
                display: none;
            }

            .navbar-store-name {
                font-size: .5rem;
            }

            .user-info {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .navbar-logo {
                height: 30px;
            }

            .user-details {
                display: none;
            }
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 320px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            z-index: 1050;
        }

        .notification-dropdown.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-dropdown-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-dropdown-title {
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .notification-dropdown-body {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-empty {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #6c757d;
        }

        .notification-dropdown-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }

        .notification-dropdown-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .notification-dropdown-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<nav class="navbar-modern" id="modernNavbar">
    <div class="navbar-container">
        <!-- Logo Section -->
        <div class="navbar-logo-section">
            <a href="index.php">
                <img src="/assets/images/mediahub-logo-black.png" alt="MediaHub" class="navbar-logo">
            </a>
            <!--?php if(isset($_SESSION['store_name'])): ?>
                <div class="navbar-divider"></div>
                <span class="navbar-store-name"><?php echo htmlspecialchars($_SESSION['store_name']); ?></span-->
            <!--?php endif; ?-->
        </div>

        <!-- Desktop Navigation -->
        <?php if(isset($_SESSION['store_id'])): ?>
            <ul class="navbar-menu">
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'history.php' ? 'active' : ''; ?>" href="history.php">
                        <i class="bi bi-clock-history"></i>
                        <span>History</span>
                    </a>
                </li>
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Calendar</span>
                    </a>
                </li>
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'articles.php' ? 'active' : ''; ?>" href="articles.php">
                        <i class="bi bi-pencil-square"></i>
                        <span>Articles</span>
                        <?php
                        $article_count = 0;
                        try {
                            $stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE store_id = ?');
                            $stmt->execute([$_SESSION['store_id']]);
                            $article_count = $stmt->fetchColumn();
                        } catch (Exception $e) {}
                        if ($article_count > 0):
                            ?>
                            <span class="nav-badge"><?php echo $article_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'marketing.php' ? 'active' : ''; ?>" href="marketing.php">
                        <i class="bi bi-graph-up"></i>
                        <span>Marketing</span>
                    </a>
                </li>
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                        <i class="bi bi-chat-dots"></i>
                        <span>Messages</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="nav-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <!-- User Section -->
            <div class="navbar-user-section">
                <!-- Notification Bell -->
                <div class="notification-wrapper">
                    <button class="notification-btn <?php echo $unread_count > 0 ? 'has-notifications' : ''; ?>" id="notificationBtn">
                        <i class="bi bi-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge" id="notifyCount"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>

                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-dropdown-header">
                            <h6 class="notification-dropdown-title">Notifications</h6>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_count; ?> New</span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown-body">
                            <?php if ($unread_count > 0): ?>
                                <div class="notification-item">
                                    <i class="bi bi-chat-dots text-primary me-2"></i>
                                    You have <?php echo $unread_count; ?> unread message<?php echo $unread_count > 1 ? 's' : ''; ?>
                                </div>
                            <?php else: ?>
                                <div class="notification-empty">
                                    <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
                                    No new notifications
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown-footer">
                            <a href="messages.php">View all messages</a>
                        </div>
                    </div>
                </div>

                <!-- User Info -->
                <div class="user-info">
                    <div class="user-avatar">
                        <?php
                        $initials = '';
                        if (!empty($_SESSION['store_first_name']) || !empty($_SESSION['store_last_name'])) {
                            $initials = strtoupper(substr($_SESSION['store_first_name'] ?? '', 0, 1) . substr($_SESSION['store_last_name'] ?? '', 0, 1));
                        } else {
                            $initials = strtoupper(substr($_SESSION['store_name'] ?? 'S', 0, 2));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name">
                            <?php echo htmlspecialchars(trim(($_SESSION['store_first_name'] ?? '') . ' ' . ($_SESSION['store_last_name'] ?? '')) ?: $_SESSION['store_name']); ?>
                        </span>
                        <span class="user-role">Store User</span>
                    </div>
                </div>

                <!-- Logout -->
                <a href="?logout=1" class="logout-btn" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>

                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div class="mobile-menu" id="mobileMenu">
                <ul class="mobile-menu-list">
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'history.php' ? 'active' : ''; ?>" href="history.php">
                            <i class="bi bi-clock-history"></i>
                            History
                        </a>
                    </li>
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php">
                            <i class="bi bi-calendar-event"></i>
                            Calendar
                        </a>
                    </li>
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'articles.php' ? 'active' : ''; ?>" href="articles.php">
                            <i class="bi bi-pencil-square"></i>
                            Articles
                            <?php if ($article_count > 0): ?>
                                <span class="nav-badge ms-auto"><?php echo $article_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'marketing.php' ? 'active' : ''; ?>" href="marketing.php">
                            <i class="bi bi-graph-up"></i>
                            Marketing Report
                        </a>
                    </li>
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                            <i class="bi bi-chat-dots"></i>
                            Messages
                            <?php if ($unread_count > 0): ?>
                                <span class="nav-badge ms-auto"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <div class="mobile-user-section">
                    <div class="user-info flex-1">
                        <div class="user-avatar">
                            <?php echo $initials; ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name">
                                <?php echo htmlspecialchars(trim(($_SESSION['store_first_name'] ?? '') . ' ' . ($_SESSION['store_last_name'] ?? '')) ?: $_SESSION['store_name']); ?>
                            </span>
                            <span class="user-role">Store User</span>
                        </div>
                    </div>
                    <a href="?logout=1" class="logout-btn" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</nav>

<script>
    // Header scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('modernNavbar');
        if (window.scrollY > 10) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenu = document.getElementById('mobileMenu');

    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            mobileMenu.classList.toggle('active');
        });
    }

    // Notification dropdown
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
    }

    // Check notifications periodically
    function checkNotifications() {
        fetch('notifications.php?ts=' + Date.now())
            .then(r => r.json())
            .then(d => {
                const notifyCount = document.getElementById('notifyCount');
                const notificationBtn = document.getElementById('notificationBtn');

                if (d.count > 0) {
                    if (notifyCount) {
                        notifyCount.textContent = d.count;
                        notifyCount.style.display = 'block';
                    }
                    notificationBtn.classList.add('has-notifications');
                } else {
                    if (notifyCount) {
                        notifyCount.style.display = 'none';
                    }
                    notificationBtn.classList.remove('has-notifications');
                }
            })
            .catch(err => console.error('Error checking notifications:', err));
    }

    // Initial check and periodic updates
    if (document.getElementById('notificationBtn')) {
        checkNotifications();
        setInterval(checkNotifications, 30000); // Check every 30 seconds
    }
</script>

<div class="container-fluid">