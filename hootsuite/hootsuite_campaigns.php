<?php
require_once __DIR__.'/../lib/settings.php';
require_once __DIR__.'/hootsuite_api.php';
header('Content-Type: application/json');
$token = get_setting('hootsuite_access_token');
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing access token']);
    exit;
}
$campaigns = hootsuite_get_campaigns($token);
$out = [];
foreach ($campaigns as $c) {
    $out[] = [
        'id' => $c['id'] ?? null,
        'name' => $c['name'] ?? ''
    ];
}
echo json_encode($out);

