<?php
require_once __DIR__.'/../lib/auth.php';
ensure_session();

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
$version = trim(file_get_contents(__DIR__.'/../VERSION'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no, maximum-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MediaHub">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#667eea">
    <title>MediaHub Cosmick Media</title>
    <meta name="robots" content="noindex, nofollow">
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Montserrat Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/common.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="inc/css/style.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="/assets/css/mobile-optimizations.css?v=<?php echo $version; ?>">
    <?php if(isset($extra_head)) echo $extra_head; ?>

    <!-- Favicons and App Icons -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/icon-512.png">

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
                    </a>
                </li>
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'marketing.php' ? 'active' : ''; ?>" href="marketing.php">
                        <i class="bi bi-graph-up"></i>
                        <span>Marketing</span>
                    </a>
                </li>
                <li class="nav-item-modern">
                    <a class="nav-link-modern <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                        <i class="bi bi-chat-dots"></i>
                        <span>Chat</span>
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
                            <a href="chat.php">View all messages</a>
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
                <a href="logout.php" class="logout-btn" title="Logout">
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
                        </a>
                    </li>
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'marketing.php' ? 'active' : ''; ?>" href="marketing.php">
                            <i class="bi bi-graph-up"></i>
                            Marketing Report
                        </a>
                    </li>
                    <li class="mobile-menu-item">
                        <a class="mobile-menu-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                            <i class="bi bi-chat-dots"></i>
                            Chat
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
                    <a href="logout.php" class="logout-btn" title="Logout">
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
        // Poll every 3 seconds for near real-time notifications
        setInterval(checkNotifications, 3000);
    }

    // Update CSS variable for header height
    function updateHeaderHeight() {
        const header = document.getElementById('modernNavbar');
        if (header) {
            document.documentElement.style.setProperty('--header-height', header.offsetHeight + 'px');
        }
    }
    window.addEventListener('load', updateHeaderHeight);
    window.addEventListener('resize', updateHeaderHeight);

    // Simplified modal scroll lock for mobile
    document.addEventListener('show.bs.modal', () => {
        document.body.style.overflow = 'hidden';
    });
    document.addEventListener('hidden.bs.modal', () => {
        document.body.style.overflow = '';
    });
</script>

<div class="container-fluid">