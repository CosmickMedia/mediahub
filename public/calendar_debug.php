<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__.'/../lib/db.php';
    require_once __DIR__.'/../lib/auth.php';
    require_once __DIR__.'/../lib/settings.php';

    ensure_session();

    if (!isset($_SESSION['store_id'])) {
        die('Not logged in as a store. Please <a href="index.php">login first</a>.');
    }

    $store_id = $_SESSION['store_id'];
    $pdo = get_pdo();

    echo "<!DOCTYPE html><html><head><title>Calendar Debug</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        table { border-collapse: collapse; margin: 20px 0; background: white; width: 100%; max-width: 800px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .good { color: green; font-weight: bold; }
        .bad { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .highlight { background: #fff3cd; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style></head><body>";

    echo "<h1>Calendar Debug for Store ID: $store_id</h1>";

    // Check store info
    echo "<h2>1. Store Information</h2>";
    $stmt = $pdo->prepare('SELECT id, name, hootsuite_profile_ids FROM stores WHERE id = ?');
    $stmt->execute([$store_id]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        die("<p class='bad'>ERROR: Store not found in database!</p>");
    }

    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
    echo "<tr><td>Store ID</td><td>" . htmlspecialchars($store['id']) . "</td><td class='good'>✓</td></tr>";
    echo "<tr><td>Store Name</td><td>" . htmlspecialchars($store['name']) . "</td><td class='good'>✓</td></tr>";

    $has_profiles = !empty($store['hootsuite_profile_ids']) && trim($store['hootsuite_profile_ids']) !== '';
    $profile_status = $has_profiles ? 'good">✓' : 'bad">⚠ EMPTY - THIS IS THE PROBLEM!';
    echo "<tr" . ($has_profiles ? "" : " class='highlight'") . ">";
    echo "<td>Profile IDs (raw)</td>";
    echo "<td>" . htmlspecialchars($store['hootsuite_profile_ids'] ?: 'EMPTY') . "</td>";
    echo "<td class='" . $profile_status . "</td>";
    echo "</tr>";
    echo "</table>";

    // Parse profile IDs
    echo "<h2>2. Profile IDs Parsing</h2>";
    $store_profile_ids = array_filter(array_map('trim', explode(',', (string)$store['hootsuite_profile_ids'])));
    echo "<table>";
    echo "<tr><th>Parsed Profile IDs</th><th>Count</th><th>Status</th></tr>";
    $count_status = count($store_profile_ids) > 0 ? 'good">✓' : 'bad">⚠ NO PROFILES - Schedule Post button will be disabled!';
    echo "<tr>";
    echo "<td>" . htmlspecialchars(implode(', ', $store_profile_ids) ?: 'None') . "</td>";
    echo "<td>" . count($store_profile_ids) . "</td>";
    echo "<td class='" . $count_status . "</td>";
    echo "</tr>";
    echo "</table>";

    // Check if profiles exist in hootsuite_profiles table
    echo "<h2>3. Profiles in Database</h2>";
    if ($store_profile_ids) {
        $placeholders = implode(',', array_fill(0, count($store_profile_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, username, network FROM hootsuite_profiles WHERE id IN ($placeholders)");
        $stmt->execute($store_profile_ids);
        $db_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>Profile ID</th><th>Network</th><th>Username</th></tr>";
        if (empty($db_profiles)) {
            echo "<tr class='highlight'><td colspan='3' class='bad'>⚠ NO PROFILES FOUND IN DATABASE - Profile IDs don't match any records!</td></tr>";
        } else {
            foreach ($db_profiles as $p) {
                echo "<tr><td>" . htmlspecialchars($p['id']) . "</td><td>" . htmlspecialchars($p['network']) . "</td><td>" . htmlspecialchars($p['username']) . "</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<p class='bad'>⚠ No profile IDs configured for this store.</p>";
        echo "<p><strong>Fix:</strong> Go to <a href='/admin/edit_store.php?id=$store_id' target='_blank'>Store Settings</a> and select social media profiles.</p>";
    }

    // Check social networks status
    echo "<h2>4. Social Networks Status</h2>";
    $networks = $pdo->query('SELECT id, name, enabled FROM social_networks ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    echo "<table>";
    echo "<tr><th>Network ID</th><th>Name</th><th>Enabled</th></tr>";
    $disabled_count = 0;
    foreach ($networks as $n) {
        $enabled = $n['enabled'] == 1;
        if (!$enabled) $disabled_count++;
        $status_class = $enabled ? 'good' : 'bad';
        $status_text = $enabled ? '✓ Enabled' : '⚠ Disabled';
        echo "<tr" . (!$enabled ? " class='highlight'" : "") . ">";
        echo "<td>" . htmlspecialchars($n['id']) . "</td>";
        echo "<td>" . htmlspecialchars($n['name']) . "</td>";
        echo "<td class='$status_class'>$status_text</td>";
        echo "</tr>";
    }
    echo "</table>";

    if ($disabled_count > 0) {
        echo "<p class='warning'>⚠ $disabled_count network(s) disabled. If your profiles use these networks, they won't be available for scheduling.</p>";
        echo "<p><strong>Fix:</strong> Go to <a href='/admin/settings.php' target='_blank'>Settings → Social Networks Management</a> and enable the networks you need.</p>";
    }

    // Check final query (the one that determines $allow_schedule)
    echo "<h2>5. Final Profile Check (With Network Filter)</h2>";
    echo "<p>This is the exact query used by calendar.php to determine if the Schedule Post button is enabled:</p>";
    if ($store_profile_ids) {
        $placeholders = implode(',', array_fill(0, count($store_profile_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT hp.id, hp.username, hp.network
            FROM hootsuite_profiles hp
            LEFT JOIN social_networks sn ON LOWER(sn.name) = LOWER(hp.network)
            WHERE hp.id IN ($placeholders)
            AND (sn.enabled = 1 OR sn.enabled IS NULL)
            ORDER BY hp.network, hp.username
        ");
        $stmt->execute($store_profile_ids);
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>Profile ID</th><th>Network</th><th>Username</th></tr>";
        if (empty($profiles)) {
            echo "<tr class='highlight'><td colspan='3' class='bad'>⚠ NO PROFILES RETURNED</td></tr>";
            echo "</table>";
            echo "<p class='bad'><strong>This means:</strong> Either the profiles don't exist OR their social networks are disabled!</p>";
        } else {
            foreach ($profiles as $p) {
                echo "<tr class='good'><td>" . htmlspecialchars($p['id']) . "</td><td>" . htmlspecialchars($p['network']) . "</td><td>" . htmlspecialchars($p['username']) . "</td></tr>";
            }
            echo "</table>";
        }

        $allow_schedule = count($profiles) > 0;
        echo "<div style='background: " . ($allow_schedule ? "#d4edda" : "#f8d7da") . "; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3 style='margin-top: 0;'>Result: \$allow_schedule = " . ($allow_schedule ? "<span class='good'>TRUE ✓</span>" : "<span class='bad'>FALSE ⚠</span>") . "</h3>";
        if ($allow_schedule) {
            echo "<p class='good'>✓ Schedule Post button SHOULD be enabled on calendar.php</p>";
        } else {
            echo "<p class='bad'>⚠ Schedule Post button will be DISABLED on calendar.php</p>";
        }
        echo "</div>";
    } else {
        echo "<p class='bad'>⚠ No profile IDs to check</p>";
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3 style='margin-top: 0;'>Result: \$allow_schedule = <span class='bad'>FALSE ⚠</span></h3>";
        echo "<p class='bad'>⚠ Schedule Post button will be DISABLED because no profiles are configured</p>";
        echo "</div>";
    }

    // Check display settings
    echo "<h2>6. Display Settings (For Viewing Posts)</h2>";
    $calendar_display = get_setting('calendar_display_customer');
    $hootsuite_display = get_setting('hootsuite_display_customer');

    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
    $calendar_ok = $calendar_display === '1';
    $hootsuite_ok = $hootsuite_display === '1';

    $calendar_status = $calendar_ok ? 'good">✓ Enabled' : 'warning">⚠ Not enabled';
    echo "<tr" . ($calendar_ok ? "" : " class='highlight'") . ">";
    echo "<td>calendar_display_customer</td>";
    echo "<td>" . htmlspecialchars($calendar_display ?: 'NOT SET') . "</td>";
    echo "<td class='" . $calendar_status . "</td>";
    echo "</tr>";

    $hootsuite_status = $hootsuite_ok ? 'good">✓ Enabled' : 'warning">⚠ Not enabled';
    echo "<tr" . ($hootsuite_ok ? "" : " class='highlight'") . ">";
    echo "<td>hootsuite_display_customer</td>";
    echo "<td>" . htmlspecialchars($hootsuite_display ?: 'NOT SET') . "</td>";
    echo "<td class='" . $hootsuite_status . "</td>";
    echo "</tr>";
    echo "</table>";

    if (!$calendar_ok && !$hootsuite_ok) {
        echo "<p class='bad'>⚠ <strong>Both display settings are disabled!</strong> The calendar will show \"No Scheduled Posts\" even if posts exist.</p>";
        echo "<p><strong>Fix:</strong> Go to <a href='/admin/settings.php' target='_blank'>Settings</a> and enable \"Display on customer calendar\" under Calendar Import or Social Media Integration.</p>";
    } else {
        echo "<p class='good'>✓ At least one display setting is enabled - posts should be visible if they exist.</p>";
    }

    // Summary
    echo "<h2>Summary & Recommended Actions</h2>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #0066cc; margin: 20px 0;'>";

    $issues = [];
    if (count($store_profile_ids) === 0) {
        $issues[] = "No social media profiles configured for this store";
    }
    if ($store_profile_ids && empty($profiles)) {
        $issues[] = "Configured profiles don't exist or their networks are disabled";
    }
    if (!$calendar_ok && !$hootsuite_ok) {
        $issues[] = "Display settings are disabled - won't show existing posts";
    }

    if (empty($issues)) {
        echo "<p class='good' style='font-size: 18px;'>✓ Everything looks good! Calendar should work properly.</p>";
    } else {
        echo "<p class='bad' style='font-size: 18px;'>⚠ Issues Found:</p>";
        echo "<ol>";
        foreach ($issues as $issue) {
            echo "<li class='bad'>$issue</li>";
        }
        echo "</ol>";

        echo "<p><strong>Recommended fixes:</strong></p>";
        echo "<ol>";
        if (count($store_profile_ids) === 0) {
            echo "<li>Go to <a href='/admin/edit_store.php?id=$store_id' target='_blank'>Store Settings</a> and select social media profiles</li>";
        }
        if ($disabled_count > 0 && $store_profile_ids) {
            echo "<li>Go to <a href='/admin/settings.php' target='_blank'>Settings → Social Networks Management</a> and enable the networks</li>";
        }
        if (!$calendar_ok && !$hootsuite_ok) {
            echo "<li>Go to <a href='/admin/settings.php' target='_blank'>Settings</a> and enable \"Display on customer calendar\"</li>";
        }
        echo "</ol>";
    }
    echo "</div>";

    echo "<hr><p><a href='calendar.php'>← Back to Calendar</a> | <a href='/admin/edit_store.php?id=$store_id' target='_blank'>Edit Store Settings</a> | <a href='/admin/settings.php' target='_blank'>Global Settings</a></p>";

    echo "</body></html>";

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
