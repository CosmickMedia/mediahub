<?php
require_once __DIR__.'/../lib/db.php';
header('Content-Type: application/json');
$pdo = get_pdo();
$rows = [];
try {
    $stmt = $pdo->query('SELECT id, username, type FROM hootsuite_profiles ORDER BY username');
    foreach ($stmt as $p) {
        $username = $p['username'] ?? '';
        $type = $p['type'] ?? '';
        $label = trim($username) !== '' ? $username . ' (' . $type . ')' : $type;
        $rows[] = [
            'id' => $p['id'],
            'name' => $label
        ];
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit;
}
echo json_encode($rows);

