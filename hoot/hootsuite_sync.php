<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/settings.php';
require_once __DIR__.'/../lib/config.php';
require_once __DIR__.'/../lib/helpers.php';

function hootsuite_ensure_schema(PDO $pdo): void {
    try {
        $pdo->query('SELECT media_thumb_urls FROM hootsuite_posts LIMIT 1');
        return; // schema up to date
    } catch (PDOException $e) {
        // continue to add missing columns
    }

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
        try { $pdo->exec("ALTER TABLE hootsuite_posts ADD COLUMN $col"); } catch (PDOException $e) {}
    }
}

function hootsuite_extract_media(array $post): array {
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

function hootsuite_cache_media(array $urls, string $dir, ?string $scheduled = null): array {
    $subPath = '';
    if ($scheduled) {
        $ts = strtotime($scheduled);
        if ($ts !== false) {
            $subPath = date('Y/m', $ts);
            $subDir = rtrim($dir, '/\\') . '/' . $subPath;
            if (!is_dir($subDir) && !mkdir($subDir, 0777, true) && !is_dir($subDir)) {
                error_log('Failed to create hootsuite media dir: ' . $subDir);
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
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $basename = sha1($url) . ($ext ? '.' . $ext : '');
        }

        $targetDir = rtrim($dir, '/\\') . ($subPath ? '/' . $subPath : '');
        $dest = $targetDir . '/' . $basename;

        if (!file_exists($dest)) {
            $data = @file_get_contents($url);
            if ($data === false) {
                error_log('Failed to download hootsuite media: ' . $url);
                continue;
            }
            file_put_contents($dest, $data);
        }

        $local[] = '/calendar_media' . ($subPath ? '/' . $subPath : '') . '/' . $basename;
    }

    return $local;
}

function hootsuite_update(bool $force = false, bool $debug = false): array {
    try {
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
        hootsuite_ensure_schema($pdo);
        $pdo->beginTransaction();
        if ($force) {
            $pdo->exec('DELETE FROM hootsuite_posts');
        }

        $storeMap = [];
        $profileMap = [];
        $campaignMap = [];
        $propMap = [];
        foreach ($pdo->query('SELECT id, hootsuite_campaign_tag, hootsuite_campaign_id, hootsuite_profile_ids, hootsuite_custom_property_key, hootsuite_custom_property_value FROM stores') as $row) {
            $norm = normalize_tag($row['hootsuite_campaign_tag'] ?? '');
            if ($norm !== '') {
                $storeMap[$norm] = (int)$row['id'];
            }
            if (!empty($row['hootsuite_campaign_id'])) {
                $campaignMap[(string)$row['hootsuite_campaign_id']] = (int)$row['id'];
            }
            foreach (to_string_array($row['hootsuite_profile_ids'] ?? null) as $pid) {
                $profileMap[$pid] = (int)$row['id'];
            }
            $ckey = $row['hootsuite_custom_property_key'] ?? null;
            $cval = $row['hootsuite_custom_property_value'] ?? null;
            if ($ckey && $cval) {
                if (!isset($propMap[$ckey])) { $propMap[$ckey] = []; }
                $propMap[$ckey][$cval] = (int)$row['id'];
            }
        }

        $stmt = $pdo->prepare('INSERT INTO hootsuite_posts (
            post_id, store_id, state, text, scheduled_send_time, social_profile_id,
            media_urls, media_thumb_urls, media, webhook_urls, tags, targeting, privacy, location,
            email_notification, post_url, post_id_external, reviewers,
            created_by_member_id, last_updated_by_member_id, extended_info,
            sequence_number, imt_length, imt_index, raw_json
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            store_id=VALUES(store_id), state=VALUES(state), text=VALUES(text), scheduled_send_time=VALUES(scheduled_send_time),
            social_profile_id=VALUES(social_profile_id), media_urls=VALUES(media_urls), media_thumb_urls=VALUES(media_thumb_urls),
            media=VALUES(media), webhook_urls=VALUES(webhook_urls), tags=VALUES(tags), targeting=VALUES(targeting),
            privacy=VALUES(privacy), location=VALUES(location), email_notification=VALUES(email_notification),
            post_url=VALUES(post_url), post_id_external=VALUES(post_id_external), reviewers=VALUES(reviewers),
            created_by_member_id=VALUES(created_by_member_id), last_updated_by_member_id=VALUES(last_updated_by_member_id),
            extended_info=VALUES(extended_info), sequence_number=VALUES(sequence_number), imt_length=VALUES(imt_length),
            imt_index=VALUES(imt_index), raw_json=VALUES(raw_json)');

        $inserted = 0;
        foreach ($messages as $m) {
            $postId = $m['id'] ?? null;
            if (!$postId) continue;

            $profileIds = [];
            if (isset($m['socialProfile']['id'])) { $profileIds[] = (string)$m['socialProfile']['id']; }
            $profileIds = array_merge($profileIds, to_string_array($m['socialProfileId'] ?? []));
            $profileIds = array_merge($profileIds, to_string_array($m['socialProfileIds'] ?? []));
            $profileIds = array_values(array_unique(array_filter($profileIds, 'strlen')));

            $store_id = null;
            foreach ($profileIds as $pid) {
                if (isset($profileMap[$pid])) { $store_id = $profileMap[$pid]; break; }
            }

            $campaignIds = to_string_array($m['campaignIds'] ?? null);
            if (!$store_id) {
                foreach ($campaignIds as $cid) {
                    if (isset($campaignMap[$cid])) { $store_id = $campaignMap[$cid]; break; }
                }
            }

            $customProps = $m['customProperties'] ?? [];
            if (!$store_id && is_array($customProps)) {
                foreach ($customProps as $prop) {
                    $pk = $prop['key'] ?? $prop['name'] ?? null;
                    $pv = $prop['value'] ?? null;
                    if ($pk !== null && $pv !== null && isset($propMap[$pk][$pv])) { $store_id = $propMap[$pk][$pv]; break; }
                }
            }

            $tags = $m['tags'] ?? [];
            if (!is_array($tags)) { $tags = []; } else { $tags = array_map('trim', $tags); }
            if (!$store_id) {
                foreach ($tags as $tag) {
                    $norm = normalize_tag($tag);
                    if ($norm !== '' && isset($storeMap[$norm])) { $store_id = $storeMap[$norm]; break; }
                }
            }
            if (!$store_id) continue;

            $social_profile_id = $profileIds[0] ?? null;
            $state = $m['state'] ?? null;
            $text = trim($m['text'] ?? '');
            $scheduled = $m['scheduledSendTime'] ?? null;
            if ($scheduled) $scheduled = date('Y-m-d H:i:s', strtotime($scheduled));

            [$urls, $thumbs, $media_arr] = hootsuite_extract_media($m);
            $cfg = get_config();
            $dir = $cfg['calendar_media_dir'] ?? null;
            if ($dir) {
                $urls = hootsuite_cache_media($urls, $dir, $scheduled);
                $thumbs = hootsuite_cache_media($thumbs, $dir, $scheduled);
            }
            $media_urls = implode(',', $urls);
            $media_thumb_urls = implode(',', $thumbs);
            $media = json_encode($media_arr);
            $webhook_urls = isset($m['webhookUrls']) ? json_encode($m['webhookUrls']) : null;
            $tags_json = json_encode($tags);
            $targeting = isset($m['targeting']) ? json_encode($m['targeting']) : null;
            $privacy = isset($m['privacy']) ? json_encode($m['privacy']) : null;
            $location = isset($m['location']) ? json_encode($m['location']) : null;
            $email_notification = isset($m['emailNotification']) ? json_encode($m['emailNotification']) : null;
            $post_url = isset($m['postUrl']) ? filter_var(trim($m['postUrl']), FILTER_SANITIZE_URL) : null;
            $post_id_external = isset($m['postId']) ? trim($m['postId']) : null;
            $reviewers = isset($m['reviewers']) ? json_encode($m['reviewers']) : null;
            $created_by_member_id = $m['createdByMember']['id'] ?? null;
            $last_updated_by_member_id = $m['lastUpdatedByMember']['id'] ?? null;
            $extended_info = isset($m['extendedInfo']) ? json_encode($m['extendedInfo']) : null;
            $sequence_number = $m['sequenceNumber'] ?? null;
            $imt_length = $m['__IMTLENGTH__'] ?? null;
            $imt_index = $m['__IMTINDEX__'] ?? null;

            $stmt->execute([
                $postId, $store_id, $state, $text, $scheduled, $social_profile_id,
                $media_urls, $media_thumb_urls, $media, $webhook_urls, $tags_json, $targeting, $privacy,
                $location, $email_notification, $post_url, $post_id_external, $reviewers,
                $created_by_member_id, $last_updated_by_member_id, $extended_info,
                $sequence_number, $imt_length, $imt_index, json_encode($m)
            ]);
            $inserted++;
        }

        $pdo->commit();
        set_setting('hootsuite_last_update', date('Y-m-d H:i:s'));
        $msg = ($force ? 'Forced' : 'Automatic') . ' Hootsuite sync executed';
        if ($debug) {
            $msg .= ' | fetched ' . count($messages) . ' posts, inserted ' . $inserted;
        }
        return [true, $msg];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, $e->getMessage()];
    }
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
