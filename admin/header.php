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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="inc/css/style.css?v=<?php echo $version; ?>">

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
            --admin-navbar-height: 70px;
        }

        /* Base Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
            padding-top: var(--admin-navbar-height);
        }

        /* Admin Navbar */
        .navbar-admin {
            background: white;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 0;
            min-height: var(--admin-navbar-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            transition: var(--transition);
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
        }

        .navbar-admin .navbar-logo {
            height: 40px;
            width: auto;
            transition: var(--transition);
        }

        .navbar-admin .navbar-logo:hover {
            transform: scale(1.05);
        }

        .navbar-admin .navbar-nav {
            flex-direction: row;
            gap: 0.5rem;
        }

        .navbar-admin .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.95rem;
            border-radius: 12px;
            transition: var(--transition);
            position: relative;
        }

        .navbar-admin .nav-link:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .navbar-admin .nav-link.active {
            color: white;
            background: var(--primary-gradient);
        }

        .navbar-admin .nav-link i {
            font-size: 1.1rem;
        }

        /* Admin User Info */
        #adminUserInfo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #2c3e50 !important;
        }

        #adminUserInfo a {
            color: #6c757d !important;
            text-decoration: none;
            transition: var(--transition);
        }

        #adminUserInfo a:hover {
            color: #667eea !important;
            transform: translateX(2px);
        }

        /* Notification Bell */
        #notifyWrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            cursor: pointer;
            transition: var(--transition);
        }

        #notifyWrap:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        #notifyWrap:hover #notifyBell {
            color: white;
        }

        #notifyBell {
            font-size: 1.25rem;
            color: #6c757d;
            transition: var(--transition);
        }

        #notifyCount {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #dc3545 !important;
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
            display: none;
        }

        /* Version Badge */
        .version-badge {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            background: white;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            color: #6c757d;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        /* Container */
        .admin-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Responsive Navbar */
        @media (max-width: 992px) {
            .navbar-admin .navbar-nav {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 1rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                display: none;
            }

            .navbar-admin .navbar-toggler {
                display: block;
            }

            .navbar-admin .navbar-collapse.show .navbar-nav {
                display: flex;
            }

            #adminUserInfo span.me-2 {
                display: none;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-admin">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="/assets/images/mediahub-admin-logo.png" alt="MediaHub Admin" class="navbar-logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?php if($active==='dashboard') echo ' active'; ?>" href="index.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($active==='stores') echo ' active'; ?>" href="stores.php">
                        <i class="bi bi-shop"></i>
                        <span>Stores</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($active==='uploads') echo ' active'; ?>" href="uploads.php">
                        <i class="bi bi-cloud-upload"></i>
                        <span>Content Review</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($active==='messages') echo ' active'; ?>" href="messages.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Broadcasts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($active==='chat') echo ' active'; ?>" href="chat.php">
                        <i class="bi bi-chat-dots"></i>
                        <span>Chat</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($active==='settings') echo ' active'; ?>" href="settings.php">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($active==='users') echo ' active'; ?>" href="users.php">
                        <i class="bi bi-people"></i>
                        <span>Users</span>
                    </a>
                </li>
            </ul>
            <div id="adminUserInfo" class="ms-auto text-end d-flex align-items-center">
                <span class="me-2">Logged in as: <?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))); ?></span>
                <span id="notifyWrap" class="position-relative">
                    <i class="bi bi-bell" id="notifyBell"></i>
                    <span class="position-absolute start-100 translate-middle badge rounded-pill bg-danger" id="notifyCount">0</span>
                </span>
                <a href="logout.php" class="ms-3">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</nav>
<div class="admin-container">