<?php
require_once __DIR__.'/../lib/auth.php';

// Use common logout routine to fully clear the session
logout();

header('Location: index.php');
exit;
