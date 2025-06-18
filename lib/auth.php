<?php
/** Simple authentication helpers using session */

function require_login() {
    session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function login($username, $password): bool {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username=?');
    $stmt->execute([$username]);
    if ($row = $stmt->fetch()) {
        if (password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            return true;
        }
    }
    return false;
}

function logout() {
    session_start();
    $_SESSION = [];
    session_destroy();
}
