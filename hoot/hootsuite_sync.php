<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/settings.php';

function hootsuite_update(bool $force = false, bool $debug = false): array {
    $enabled = get_setting('hootsuite_enabled');
    if ($enabled !== '1') {
        return [false, 'Hootsuite integration disabled'];
    }
    $interval = (int)(get_setting('hootsuite_update_interval') ?: 24);
    $last = get_setting('hootsuite_last_update');
    if (!$force && $last && (time() - strtotime($last) < $interval * 3600)) {
        return [false, 'Update not required yet'];
    }

    $token = get_setting('hootsuite_access_token');
    if (!$token) {
        return [false, 'Missing access token'];
    }

    $startTime = date('c');
    $endTime = date('c', strtotime('+28 days'));
    $params = http_build_query([
        'state' => 'SCHEDULED',
        'limit' => 100,
        'startTime' => $startTime,
        'endTime' => $endTime,
    ]);
    $url = 'https://platform.hootsuite.com/v1/messages?' . $params;

    $messages = [];
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

        if ($curl_error) {
            return [false, 'cURL error: ' . $curl_error];
        }
        if ($code !== 200) {
            return [false, 'API error HTTP ' . $code . ($debug ? ' | ' . $response : '')];
        }

        $data = json_decode($response, true);
        $messages = array_merge($messages, $data['data'] ?? []);
        $url = $data['pagination']['next'] ?? null;
        $page++;
    } while ($url && $page < $max_pages);

    $pdo = get_pdo();
    try {
        $pdo->beginTransaction();
        $pdo->exec('TRUNCATE TABLE hootsuite_posts');
        $stmt = $pdo->prepare('INSERT INTO hootsuite_posts (post_id, store_id, text, scheduled_send_time, raw_json) VALUES (?, ?, ?, ?, ?)');
        foreach ($messages as $m) {
            $stmt->execute([
                $m['id'] ?? '',
                0,
                $m['text'] ?? null,
                isset($m['scheduledSendTime']) ? date('Y-m-d H:i:s', strtotime($m['scheduledSendTime'])) : null,
                json_encode($m)
            ]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        return [false, $e->getMessage()];
    }

    set_setting('hootsuite_last_update', date('Y-m-d H:i:s'));
    $msg = ($force ? 'Forced' : 'Automatic') . ' Hootsuite sync executed';
    if ($debug) {
        $msg .= ' | fetched ' . count($messages) . ' posts';
    }
    return [true, $msg];
}

function hootsuite_erase_all(): array {
    $pdo = get_pdo();
    try {
        $pdo->exec('TRUNCATE TABLE hootsuite_posts');
        return [true, 'All Hootsuite posts erased'];
    } catch (PDOException $e) {
        return [false, $e->getMessage()];
    }
}

function hootsuite_test_connection(bool $debug = false): array {
    $token = get_setting('hootsuite_access_token');
    if (!$token) {
        return [false, 'Missing access token'];
    }

    $ch = curl_init('https://platform.hootsuite.com/v1/me');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return [false, 'cURL error: ' . $curl_error];
    }
    if ($code === 200) {
        $msg = 'Connected to Hootsuite';
        if ($debug) {
            $msg .= ' | HTTP 200';
        }
        return [true, $msg];
    }
    $msg = 'API error HTTP ' . $code;
    if ($debug) {
        $msg .= ' | ' . $response;
    }
    return [false, $msg];
}
