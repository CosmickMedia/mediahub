<?php
require_once __DIR__.'/../lib/db.php';
session_start();
if(!isset($_SESSION['store_id'])){http_response_code(403);exit;}
$pdo=get_pdo();
$count=$pdo->prepare("SELECT COUNT(*) FROM store_messages WHERE store_id=? AND sender='admin' AND read_by_store=0");
$count->execute([$_SESSION['store_id']]);
$cnt=$count->fetchColumn();
header('Content-Type: application/json');
echo json_encode(['count'=>(int)$cnt]);
