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
$profiles = hootsuite_get_social_profiles($token);
$out = [];
foreach ($profiles as $p) {
    $username = $p['socialNetworkUsername'] ?? '';
    $type = $p['type'] ?? '';
    $label = trim($username) !== '' ? $username . ' (' . $type . ')' : $type;
    $out[] = [
        'id' => $p['id'] ?? null,
        'name' => $label
    ];
}
echo json_encode($out);

