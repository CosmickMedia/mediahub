<?php
/**
 * Simple Hootsuite API helper functions used by the application.
 */

function hootsuite_get_social_profiles(?string $token): array {
    if (!$token) {
        return [];
    }
    $profiles = [];
    $url = 'https://platform.hootsuite.com/v1/socialProfiles?limit=100';
    $page = 0;
    $max_pages = 10;
    do {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        if ($curl_error || $code !== 200) {
            break;
        }
        $data = json_decode($response, true);
        $profiles = array_merge($profiles, $data['data'] ?? []);
        $url = $data['pagination']['next'] ?? null;
        $page++;
    } while ($url && $page < $max_pages);
    return $profiles;
}
