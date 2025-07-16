<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/settings.php';
require_once __DIR__.'/sheets.php';

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

    if ($sheetId) {
        try {
            $rows = sheets_fetch_rows($sheetId, $sheetRange);
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    } else {
        $csv = @file_get_contents($sheetUrl);
        if ($csv === false) {
            return [false, 'Failed to fetch sheet'];
        }
        $rows = array_map('str_getcsv', preg_split("/\r?\n/", trim($csv)));
    }

    $pdo = get_pdo();
    $inserted = 0;
    $storeStmt = $pdo->prepare('SELECT id FROM stores WHERE LOWER(hootsuite_campaign_tag)=?');
    $checkStmt = $pdo->prepare('SELECT id FROM calendar WHERE ext_id=?');
    $insStmt = $pdo->prepare('INSERT INTO calendar (ext_id, store_id, text, scheduled_time, raw_json) VALUES (?, ?, ?, ?, ?)');

    foreach ($rows as $row) {
        if (!isset($row[0])) continue;
        $post = json_decode($row[0], true);
        if (!$post || empty($post['id'])) continue;
        $extId = $post['id'];
        $checkStmt->execute([$extId]);
        if ($checkStmt->fetch()) continue;
        $tags = $post['tags'] ?? [];
        if (!is_array($tags)) $tags = [];
        $store_id = null;
        foreach ($tags as $tag) {
            $storeStmt->execute([strtolower($tag)]);
            $sid = $storeStmt->fetchColumn();
            if ($sid) { $store_id = $sid; break; }
        }
        if (!$store_id) continue;
        $text = $post['text'] ?? '';
        $scheduled = $post['scheduledSendTime'] ?? null;
        if ($scheduled) $scheduled = date('Y-m-d H:i:s', strtotime($scheduled));
        $insStmt->execute([$extId, $store_id, $text, $scheduled, json_encode($post)]);
        $inserted++;
    }

    set_setting('calendar_last_update', date('Y-m-d H:i:s'));
    return [true, "Inserted $inserted posts"];
}

function calendar_get_posts(int $store_id): array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT text, scheduled_time FROM calendar WHERE store_id=? ORDER BY scheduled_time DESC');
    $stmt->execute([$store_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
