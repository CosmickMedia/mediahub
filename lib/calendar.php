<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/settings.php';
require_once __DIR__.'/sheets.php';
require_once __DIR__.'/helpers.php';

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

/**
 * Parse media related fields from a Hootsuite post and extract usable URLs.
 *
 * @param array $post Hootsuite post data
 * @return array Array with [urls, thumbs, media]
 */
function calendar_extract_media(array $post): array {
    $urls = [];
    $thumbs = [];

    $mediaUrls = maybe_json_decode($post['mediaUrls'] ?? []);
    $mediaThumbUrls = maybe_json_decode($post['mediaThumbUrls'] ?? []);
    $media = maybe_json_decode($post['media'] ?? []);

    if (!is_array($mediaUrls)) $mediaUrls = [];
    if (!is_array($mediaThumbUrls)) $mediaThumbUrls = [];
    if (!is_array($media)) $media = [];

    foreach ($mediaUrls as $m) {
        if (is_array($m)) {
            if (!empty($m['url']) && filter_var($m['url'], FILTER_VALIDATE_URL)) {
                $urls[] = filter_var(trim($m['url']), FILTER_SANITIZE_URL);
            }
            if (!empty($m['thumbnailUrl']) && filter_var($m['thumbnailUrl'], FILTER_VALIDATE_URL)) {
                $thumbs[] = filter_var(trim($m['thumbnailUrl']), FILTER_SANITIZE_URL);
            }
        } elseif (is_string($m) && filter_var($m, FILTER_VALIDATE_URL)) {
            $urls[] = filter_var(trim($m), FILTER_SANITIZE_URL);
        }
    }

    foreach ($mediaThumbUrls as $t) {
        if (is_string($t) && filter_var($t, FILTER_VALIDATE_URL)) {
            $thumbs[] = filter_var(trim($t), FILTER_SANITIZE_URL);
        }
    }

    foreach ($media as $m) {
        if (!is_array($m)) continue;

        if (!empty($m['url']) && filter_var($m['url'], FILTER_VALIDATE_URL)) {
            $urls[] = filter_var(trim($m['url']), FILTER_SANITIZE_URL);
        } elseif (!empty($m['id'])) {
            $decoded = base64_decode($m['id'], true);
            if ($decoded !== false && filter_var($decoded, FILTER_VALIDATE_URL)) {
                $urls[] = filter_var($decoded, FILTER_SANITIZE_URL);
            } elseif (filter_var($m['id'], FILTER_VALIDATE_URL)) {
                $urls[] = filter_var($m['id'], FILTER_SANITIZE_URL);
            }
        }

        if (!empty($m['thumbnailUrl']) && filter_var($m['thumbnailUrl'], FILTER_VALIDATE_URL)) {
            $thumbs[] = filter_var(trim($m['thumbnailUrl']), FILTER_SANITIZE_URL);
        } elseif (!empty($m['thumbnailId'])) {
            $decoded = base64_decode($m['thumbnailId'], true);
            if ($decoded !== false && filter_var($decoded, FILTER_VALIDATE_URL)) {
                $thumbs[] = filter_var($decoded, FILTER_SANITIZE_URL);
            } elseif (filter_var($m['thumbnailId'], FILTER_VALIDATE_URL)) {
                $thumbs[] = filter_var($m['thumbnailId'], FILTER_SANITIZE_URL);
            }
        }
    }

    $urls = array_values(array_unique(array_filter($urls)));
    $thumbs = array_values(array_unique(array_filter($thumbs)));

    return [$urls, $thumbs, $media];
}

/**
 * Download remote media URLs to a local directory.
 *
 * @param array       $urls     Remote URLs to download
 * @param string      $dir      Destination base directory
 * @param string|null $datetime Optional date (Y-m-d H:i:s) used for folder organization
 * @return array Array of local relative paths
 */
function calendar_cache_media(array $urls, string $dir, ?string $datetime = null): array {
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        error_log('Failed to create calendar media dir: ' . $dir);
        return [];
    }

    $subPath = '';
    if ($datetime) {
        $ts = strtotime($datetime);
        if ($ts !== false) {
            $subPath = date('Y/m', $ts);
            $subDir = rtrim($dir, '/\\') . '/' . $subPath;
            if (!is_dir($subDir) && !mkdir($subDir, 0777, true) && !is_dir($subDir)) {
                error_log('Failed to create calendar media dir: ' . $subDir);
            }
        }
    }

    $local = [];
    foreach ($urls as $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $basename = basename($path);
        $basename = $basename !== '' ? preg_replace('/[^A-Za-z0-9._-]/', '_', $basename) : '';
        if ($basename === '') {
            $ext  = pathinfo($path, PATHINFO_EXTENSION);
            $basename = sha1($url) . ($ext ? '.' . $ext : '');
        }

        $targetDir = rtrim($dir, '/\\') . ($subPath ? '/' . $subPath : '');
        $dest = $targetDir . '/' . $basename;

        if (!file_exists($dest)) {
            $data = @file_get_contents($url);
            if ($data === false) {
                error_log('Failed to download calendar media: ' . $url);
                continue;
            }
            file_put_contents($dest, $data);
        }

        $local[] = '/calendar_media' . ($subPath ? '/' . $subPath : '') . '/' . $basename;
    }

    return $local;
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
    $storeMap = [];
    foreach ($pdo->query('SELECT id, hootsuite_campaign_tag FROM stores') as $row) {
        $norm = normalize_tag($row['hootsuite_campaign_tag'] ?? '');
        if ($norm !== '') {
            $storeMap[$norm] = (int)$row['id'];
        }
    }
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
            $norm = normalize_tag($tag);
            if ($norm !== '' && isset($storeMap[$norm])) {
                $store_id = $storeMap[$norm];
                break;
            }
        }
        if (!$store_id) continue;

        $state = $post['state'] ?? null;
        $text = trim($post['text'] ?? '');
        $scheduled = $post['scheduledSendTime'] ?? null;
        if ($scheduled) $scheduled = date('Y-m-d H:i:s', strtotime($scheduled));
        $social_profile_id = $post['socialProfile']['id'] ?? null;

        [$urls, $thumbs, $media_arr] = calendar_extract_media($post);
        $cfg = get_config();
        $dir = $cfg['calendar_media_dir'] ?? null;
        if ($dir) {
            $urls = calendar_cache_media($urls, $dir, $scheduled);
            $thumbs = calendar_cache_media($thumbs, $dir, $scheduled);
        }
        $media_urls = implode(',', $urls);
        $media_thumb_urls = implode(',', $thumbs);
        $media = json_encode($media_arr);
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
