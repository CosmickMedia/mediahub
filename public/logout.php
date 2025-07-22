<?php
require_once __DIR__.'/../lib/auth.php';

ensure_session();

// Clear store-related session data
unset($_SESSION['store_id']);
unset($_SESSION['store_pin']);
unset($_SESSION['store_name']);
unset($_SESSION['store_user_email']);
unset($_SESSION['store_first_name']);
unset($_SESSION['store_last_name']);

// Destroy the session completely
session_destroy();

header('Location: index.php');
exit;
