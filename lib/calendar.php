<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/settings.php';
require_once __DIR__.'/sheets.php';

function calendar_ensure_schema(PDO $pdo): void {
    try {
        $pdo->query('SELECT post_id FROM calendar LIMIT 1');
        return; // table is up to date
    } catch (PDOException $e) {
        // old schema, attempt upgrade
    }

    try {
        $pdo->exec("ALTER TABLE calendar CHANGE ext_id post_id VARCHAR(50) NOT NULL");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE calendar CHANGE scheduled_time scheduled_send_time DATETIME");
    } catch (PDOException $e) {}

    $columns = [
        'state VARCHAR(50)',
        'social_profile_id VARCHAR(50)',
        'media_urls TEXT',
        'media_thumb_urls TEXT',
        'media TEXT',
        'webhook_urls TEXT',
        'tags TEXT',
        'targeting TEXT',
        'privacy TEXT',
        'location TEXT',
        'email_notification TEXT',
        'post_url TEXT',
        'post_id_external VARCHAR(50)',
        'reviewers TEXT',
        'created_by_member_id VARCHAR(50)',
        'last_updated_by_member_id VARCHAR(50)',
        'extended_info TEXT',
        'sequence_number INT',
        'imt_length INT',
        'imt_index INT'
    ];
    foreach ($columns as $col) {
        try { $pdo->exec("ALTER TABLE calendar ADD COLUMN $col"); } catch (PDOException $e) {}
    }
}

function calendar_update(bool $force = false): array {
    $sheetId = get_setting('calendar_sheet_id');
    $sheetRange = get_setting('calendar_sheet_range') ?: 'Sheet1!A:A';
    $sheetUrl = get_setting('calendar_sheet_url');
    if (!$sheetId && !$sheetUrl) {
        return [false, 'No calendar sheet configured'];
    }
    $interval = (int)(get_setting('calendar_update_interval') ?: 24);
    $last = get_setting('calendar_last_update');
    if (!$force && $last && (time() - strtotime($last) < $interval * 3600)) {
        return [false, 'Update not required yet'];
    }

    if ($sheetUrl) {
        if (preg_match('#docs.google.com\/spreadsheets\/d\/([^\/]+)#', $sheetUrl, $m)) {
            $gid = null;
            if (preg_match('#[?&]gid=(\d+)#', $sheetUrl, $g)) {
                $gid = $g[1];
            }
            $sheetUrl = 'https://docs.google.com/spreadsheets/d/' . $m[1] . '/export?format=csv';
            if ($gid !== null) {
                $sheetUrl .= '&gid=' . $gid;
            }
        }
        $csv = @file_get_contents($sheetUrl);
        if ($csv === false) {
            return [false, 'Failed to fetch sheet'];
        }
        $rows = array_map('str_getcsv', preg_split("/\r?\n/", trim($csv)));
    } elseif ($sheetId) {
        try {
            $rows = sheets_fetch_rows($sheetId, $sheetRange);
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }

    $pdo = get_pdo();
    calendar_ensure_schema($pdo);
    if ($force) {
        $pdo->exec('DELETE FROM calendar');
    }
    $inserted = 0;
    $storeStmt = $pdo->prepare('SELECT id FROM stores WHERE LOWER(hootsuite_campaign_tag)=?');
    $checkStmt = $pdo->prepare('SELECT id FROM calendar WHERE post_id=?');
    $insStmt = $pdo->prepare(
        'INSERT INTO calendar (
            post_id, store_id, state, text, scheduled_send_time, social_profile_id,
            media_urls, media_thumb_urls, media, webhook_urls, tags, targeting, privacy, location,
            email_notification, post_url, post_id_external, reviewers,
            created_by_member_id, last_updated_by_member_id, extended_info,
            sequence_number, imt_length, imt_index, raw_json
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    foreach ($rows as $row) {
        if (!isset($row[0])) continue;
        $post = json_decode($row[0], true);
        if (!$post || empty($post['id'])) continue;
        $postId = (string)$post['id'];
        $checkStmt->execute([$postId]);
        if ($checkStmt->fetch()) continue;

        $tags = $post['tags'] ?? [];
        if (!is_array($tags)) {
            $tags = [];
        } else {
            $tags = array_map(fn($t) => trim($t), $tags);
        }
        $store_id = null;
        foreach ($tags as $tag) {
            $storeStmt->execute([strtolower($tag)]);
            $sid = $storeStmt->fetchColumn();
            if ($sid) { $store_id = $sid; break; }
        }
        if (!$store_id) continue;

        $state = $post['state'] ?? null;
        $text = trim($post['text'] ?? '');
        $scheduled = $post['scheduledSendTime'] ?? null;
        if ($scheduled) $scheduled = date('Y-m-d H:i:s', strtotime($scheduled));
        $social_profile_id = $post['socialProfile']['id'] ?? null;
        $media_objs = $post['mediaUrls'] ?? ($post['media'] ?? []);
        if (!is_array($media_objs)) $media_objs = [];
        $urls = [];
        $thumbs = [];
        foreach ($media_objs as $m) {
            if (is_array($m)) {
                if (!empty($m['url'])) {
                    $urls[] = filter_var(trim($m['url']), FILTER_SANITIZE_URL);
                }
                if (!empty($m['thumbnailUrl'])) {
                    $thumbs[] = filter_var(trim($m['thumbnailUrl']), FILTER_SANITIZE_URL);
                }
            } elseif (is_string($m)) {
                $urls[] = filter_var(trim($m), FILTER_SANITIZE_URL);
            }
        }
        $media_urls = json_encode($urls);
        $media_thumb_urls = json_encode($thumbs);
        $media = json_encode($post['media'] ?? []);
        $webhook_urls = isset($post['webhookUrls']) ? json_encode($post['webhookUrls']) : null;
        $tags_json = json_encode($tags);
        $targeting = isset($post['targeting']) ? json_encode($post['targeting']) : null;
        $privacy = isset($post['privacy']) ? json_encode($post['privacy']) : null;
        $location = isset($post['location']) ? json_encode($post['location']) : null;
        $email_notification = isset($post['emailNotification']) ? json_encode($post['emailNotification']) : null;
        $post_url = isset($post['postUrl']) ? filter_var(trim($post['postUrl']), FILTER_SANITIZE_URL) : null;
        $post_id_external = isset($post['postId']) ? trim($post['postId']) : null;
        $reviewers = isset($post['reviewers']) ? json_encode($post['reviewers']) : null;
        $created_by_member_id = $post['createdByMember']['id'] ?? null;
        $last_updated_by_member_id = $post['lastUpdatedByMember']['id'] ?? null;
        $extended_info = isset($post['extendedInfo']) ? json_encode($post['extendedInfo']) : null;
        $sequence_number = $post['sequenceNumber'] ?? null;
        $imt_length = $post['__IMTLENGTH__'] ?? null;
        $imt_index = $post['__IMTINDEX__'] ?? null;

        $insStmt->execute([
            $postId, $store_id, $state, $text, $scheduled, $social_profile_id,
            $media_urls, $media_thumb_urls, $media, $webhook_urls, $tags_json, $targeting, $privacy,
            $location, $email_notification, $post_url, $post_id_external, $reviewers,
            $created_by_member_id, $last_updated_by_member_id, $extended_info,
            $sequence_number, $imt_length, $imt_index, json_encode($post)
        ]);
        $inserted++;
    }

    set_setting('calendar_last_update', date('Y-m-d H:i:s'));
    return [true, "Inserted $inserted posts"];
}

function calendar_get_posts(int $store_id): array {
    $pdo = get_pdo();
    calendar_ensure_schema($pdo);
    $column = 'scheduled_send_time';
    try {
        $pdo->query('SELECT scheduled_send_time FROM calendar LIMIT 1');
    } catch (PDOException $e) {
        $column = 'scheduled_time';
    }
    $stmt = $pdo->prepare("SELECT text, $column, social_profile_id, media_urls, media_thumb_urls, tags FROM calendar WHERE store_id=? ORDER BY $column DESC");
    $stmt->execute([$store_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
