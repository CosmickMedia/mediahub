<?php
/**
 * Minimal Hootsuite API helper. Currently only fetches scheduled messages.
 */
function hootsuite_get_scheduled_posts(string $token): array {
    if (empty($token)) {
        return [];
    }

    $url = 'https://platform.hootsuite.com/v1/messages?state=schedule';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return [];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['data'])) {
        return [];
    }
    return $data['data'];
}
