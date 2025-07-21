<?php
if (!isset($active)) { $active = ''; }
$version = trim(file_get_contents(__DIR__.'/../VERSION'));
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Montserrat Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/inc/css/style.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="inc/css/style.css?v=<?php echo $version; ?>">
    <style>
        /* Modern Header Styles copied from public section */
        :root {
            --header-height: 70px;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --header-shadow: 0 2px 20px rgba(0,0,0,0.1);
            --transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .navbar-modern {
            background:#fff;
            box-shadow:var(--header-shadow);
            padding:0;
            min-height:var(--header-height);
            transition:var(--transition);
            position:fixed;
            top:0;left:0;right:0;
            z-index:1030;
        }
        .navbar-modern.scrolled { box-shadow:0 4px 30px rgba(0,0,0,0.15); }
        .navbar-container {
            max-width:1600px;
            margin:0 auto;
            padding:0 2rem;
            display:flex;
            align-items:center;
            justify-content:space-between;
            height:var(--header-height);
        }
        .navbar-logo-section { display:flex;align-items:center;gap:1rem; }
        .navbar-logo { height:40px;width:auto;transition:var(--transition); }
        .navbar-logo:hover { transform:scale(1.05); }
        .navbar-menu { display:flex;align-items:center;gap:.5rem;margin:0;padding:0;list-style:none; }
        .nav-item-modern { position:relative; }
        .nav-link-modern {
            display:flex;align-items:center;gap:.5rem;
            padding:.75rem 1rem;color:#6c757d;text-decoration:none;
            font-weight:500;font-size:.95rem;border-radius:12px;
            transition:var(--transition);position:relative;overflow:hidden;
        }
        .nav-link-modern:hover { color:#667eea;background:rgba(102,126,234,0.1);transform:translateY(-2px); }
        .nav-link-modern.active { color:#fff;background:var(--primary-gradient); }
        .nav-link-modern i { font-size:1.1rem; }
        .navbar-user-section { display:flex;align-items:center;gap:1rem; }
        .user-info { display:flex;align-items:center;gap:.75rem;padding:.5rem 1rem;background:#f8f9fa;border-radius:25px;transition:var(--transition); }
        .user-info:hover { background:#e9ecef; }
        .user-avatar { width:36px;height:36px;border-radius:50%;background:var(--primary-gradient);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.9rem; }
        .user-details { display:flex;flex-direction:column; }
        .user-name { font-weight:600;color:#2c3e50;font-size:.9rem;line-height:1.2; }
        .user-role { font-size:.75rem;color:#6c757d; }
        .logout-btn { width:40px;height:40px;border-radius:50%;background:#fee2e2;border:none;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:1.1rem;cursor:pointer;transition:var(--transition);text-decoration:none; }
        .logout-btn:hover { background:#dc2626;color:#fff;transform:translateY(-2px) rotate(-10deg);box-shadow:0 5px 15px rgba(220,38,38,0.3); }
        .mobile-menu-toggle { display:none;width:40px;height:40px;border:none;background:#f8f9fa;border-radius:10px;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:var(--transition); }
        .mobile-menu-toggle span { width:20px;height:2px;background:#2c3e50;transition:var(--transition); }
        .mobile-menu-toggle.active span:nth-child(1) { transform:rotate(45deg) translate(5px,5px); }
        .mobile-menu-toggle.active span:nth-child(2) { opacity:0; }
        .mobile-menu-toggle.active span:nth-child(3) { transform:rotate(-45deg) translate(5px,-5px); }
        .mobile-menu { display:none;position:fixed;top:var(--header-height);left:0;right:0;background:#fff;box-shadow:0 4px 20px rgba(0,0,0,0.1);max-height:calc(100vh - var(--header-height));overflow-y:auto;z-index:1029; }
        .mobile-menu.active { display:block;animation:slideDown .3s ease-out; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-20px);} to{opacity:1;transform:translateY(0);} }
        .mobile-menu-list { list-style:none;padding:1rem;margin:0; }
        .mobile-menu-item { margin-bottom:.5rem; }
        .mobile-menu-link { display:flex;align-items:center;gap:.75rem;padding:1rem;color:#6c757d;text-decoration:none;font-weight:500;border-radius:12px;transition:var(--transition); }
        .mobile-menu-link:hover { background:#f8f9fa;color:#667eea; }
        .mobile-menu-link.active { background:var(--primary-gradient);color:#fff; }
        body { padding-top:var(--header-height); }
        @media (max-width:992px){
            .navbar-menu{display:none;}
            .mobile-menu-toggle{display:flex;}
        }
    </style>
</head>
<body>
<nav class="navbar-modern" id="modernNavbar">
    <div class="navbar-container">
        <div class="navbar-logo-section">
            <a href="index.php"><img src="/assets/images/mediahub-admin-logo.png" class="navbar-logo" alt="MediaHub Admin"></a>
        </div>
        <?php if (is_logged_in()): ?>
        <ul class="navbar-menu">
            <li class="nav-item-modern"><a class="nav-link-modern <?php if($active==='dashboard') echo 'active'; ?>" href="index.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
            <li class="nav-item-modern"><a class="nav-link-modern <?php if($active==='stores') echo 'active'; ?>" href="stores.php"><i class="bi bi-shop"></i><span>Stores</span></a></li>
            <li class="nav-item-modern"><a class="nav-link-modern <?php if($active==='uploads') echo 'active'; ?>" href="uploads.php"><i class="bi bi-upload"></i><span>Content Review</span></a></li>
            <li class="nav-item-modern"><a class="nav-link-modern <?php if($active==='messages') echo 'active'; ?>" href="messages.php"><i class="bi bi-broadcast-pin"></i><span>Broadcasts</span></a></li>
            <li class="nav-item-modern"><a class="nav-link-modern <?php if($active==='chat') echo 'active'; ?>" href="chat.php"><i class="bi bi-chat-dots"></i><span>Chat</span></a></li>
            <li class="nav-item-modern"><a class="nav-link-modern <?php if($active==='settings') echo 'active'; ?>" href="settings.php"><i class="bi bi-gear"></i><span>Settings</span></a></li>
            <li class="nav-item-modern"><a class="nav-link-modern <?php if($active==='users') echo 'active'; ?>" href="users.php"><i class="bi bi-people"></i><span>Users</span></a></li>
        </ul>
        <div class="navbar-user-section">
            <span id="notifyWrap" class="position-relative me-2">
                <i class="bi bi-bell" id="notifyBell"></i>
                <span class="position-absolute start-100 translate-middle badge rounded-pill bg-danger" id="notifyCount">0</span>
            </span>
            <div class="user-info" id="adminUserInfo">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['first_name']??'A',0,1).substr($_SESSION['last_name']??'',0,1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars(trim(($_SESSION['first_name']??'')." ".($_SESSION['last_name']??''))); ?></span>
                    <span class="user-role">Admin</span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            <button class="mobile-menu-toggle" id="mobileMenuToggle"><span></span><span></span><span></span></button>
        </div>
        <?php endif; ?>
    </div>
    <div class="mobile-menu" id="mobileMenu">
        <ul class="mobile-menu-list">
            <li class="mobile-menu-item"><a class="mobile-menu-link <?php if($active==='dashboard') echo 'active'; ?>" href="index.php"><i class="bi bi-speedometer2"></i>Dashboard</a></li>
            <li class="mobile-menu-item"><a class="mobile-menu-link <?php if($active==='stores') echo 'active'; ?>" href="stores.php"><i class="bi bi-shop"></i>Stores</a></li>
            <li class="mobile-menu-item"><a class="mobile-menu-link <?php if($active==='uploads') echo 'active'; ?>" href="uploads.php"><i class="bi bi-upload"></i>Review</a></li>
            <li class="mobile-menu-item"><a class="mobile-menu-link <?php if($active==='messages') echo 'active'; ?>" href="messages.php"><i class="bi bi-broadcast-pin"></i>Broadcasts</a></li>
            <li class="mobile-menu-item"><a class="mobile-menu-link <?php if($active==='chat') echo 'active'; ?>" href="chat.php"><i class="bi bi-chat-dots"></i>Chat</a></li>
            <li class="mobile-menu-item"><a class="mobile-menu-link <?php if($active==='settings') echo 'active'; ?>" href="settings.php"><i class="bi bi-gear"></i>Settings</a></li>
            <li class="mobile-menu-item"><a class="mobile-menu-link <?php if($active==='users') echo 'active'; ?>" href="users.php"><i class="bi bi-people"></i>Users</a></li>
        </ul>
    </div>
</nav>
<script>
    // Header scroll effect
    window.addEventListener('scroll', () => {
        const navbar = document.getElementById('modernNavbar');
        if (window.scrollY > 10) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    const mobileMenuToggle=document.getElementById('mobileMenuToggle');
    const mobileMenu=document.getElementById('mobileMenu');
    if(mobileMenuToggle&&mobileMenu){
        mobileMenuToggle.addEventListener('click',function(){
            this.classList.toggle('active');
            mobileMenu.classList.toggle('active');
        });
    }
</script>
<div class="container-fluid pb-5">
