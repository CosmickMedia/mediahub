<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo = get_pdo();
$count = $pdo->query("SELECT COUNT(*) FROM store_messages WHERE sender='store' AND read_by_admin=0")->fetchColumn();
header('Content-Type: application/json');
echo json_encode(['count'=> (int)$count]);
