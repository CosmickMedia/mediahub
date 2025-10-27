<?php
/** Simple authentication helpers using session */

// Only set session configuration if session hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

// Start session if not already started
function ensure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        $isAdmin = isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
        $sessionName = $isAdmin ? 'cm_admin_session' : 'cm_public_session';
        $rememberCookie = $isAdmin ? 'cm_admin_remember' : 'cm_public_remember';
        session_name($sessionName);

        $lifetime = isset($_COOKIE[$rememberCookie]) ? 60 * 60 * 24 * 30 : 60 * 60 * 8; // 30 days with remember, 8 hours without
        if ($lifetime > 0) {
            ini_set('session.gc_maxlifetime', (string)$lifetime);
        }

        // Set session cookie parameters before starting session
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '', // Empty means current domain
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

function require_login() {
    ensure_session();

    if (empty($_SESSION['user_id'])) {
        // Store the requested URL to redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

        // Determine the correct path to login.php
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $login_path = '/admin/login.php';

        // If we're in a subdirectory, adjust the path
        if (strpos($script_dir, '/admin') === false) {
            $login_path = 'admin/login.php';
        }

        header('Location: ' . $login_path);
        exit;
    }
}

function login($username, $password): bool {
    ensure_session();

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, password, first_name, last_name FROM users WHERE username=?');
    $stmt->execute([$username]);
    if ($row = $stmt->fetch()) {
        if (password_verify($password, $row['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            $_SESSION['first_name'] = $row['first_name'] ?? '';
            $_SESSION['last_name'] = $row['last_name'] ?? '';

            return true;
        }
    }
    return false;
}

function logout() {
    ensure_session();

    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params["path"],
                'domain' => $params["domain"],
                'secure' => $params["secure"],
                'httponly' => $params["httponly"],
                'samesite' => $params["samesite"] ?? 'Lax'
            ]
        );
    }

    // Clear persistent login cookie
    $isAdmin = isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
    $rememberCookie = $isAdmin ? 'cm_admin_remember' : 'cm_public_remember';
    setcookie(
        $rememberCookie,
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    // Destroy the session
    session_destroy();
}

function login_with_google_email($email): bool {
    ensure_session();

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, first_name, last_name FROM users WHERE username=?');
    $stmt->execute([$email]);
    if ($row = $stmt->fetch()) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $email;
        $_SESSION['login_time'] = time();
        $_SESSION['first_name'] = $row['first_name'] ?? '';
        $_SESSION['last_name'] = $row['last_name'] ?? '';
        return true;
    }
    return false;
}

// Check if user is logged in (without redirect)
function is_logged_in(): bool {
    ensure_session();
    return !empty($_SESSION['user_id']);
}