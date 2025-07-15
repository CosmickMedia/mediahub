<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_login();
$pdo=get_pdo();
$sql="SELECT s.id, s.name, SUM(CASE WHEN m.sender='store' AND m.read_by_admin=0 THEN 1 ELSE 0 END) AS unread
      FROM stores s
      LEFT JOIN store_messages m ON m.store_id=s.id
      GROUP BY s.id ORDER BY s.name";
$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($rows);

