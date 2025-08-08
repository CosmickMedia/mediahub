<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/calendar.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/settings.php';

require_login();
$active = 'calendars';
$pdo = get_pdo();

// Get selected store ID from query param or session
$selected_store_id = $_GET['store_id'] ?? $_SESSION['admin_selected_store_id'] ?? null;
if ($selected_store_id) {
    $_SESSION['admin_selected_store_id'] = $selected_store_id;
}

// Fetch all stores for the selector
$stores_stmt = $pdo->query('SELECT id, name, hootsuite_profile_ids FROM stores ORDER BY name');
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare profile map
$profile_map = [];
$res = $pdo->query('SELECT id, network FROM hootsuite_profiles');
foreach ($res as $prof) {
    if (!empty($prof['id']) && !empty($prof['network'])) {
        $profile_map[$prof['id']] = strtolower($prof['network']);
    }
}

// Prepare network map
$network_map = [];
foreach ($pdo->query('SELECT name, icon, color FROM social_networks') as $n) {
    $network_map[strtolower($n['name'])] = [
        'icon'  => $n['icon'],
        'color' => $n['color'],
        'name'  => $n['name']
    ];
}

// Get posts based on selection
if ($selected_store_id) {
    $posts = calendar_get_posts($selected_store_id);
    $store_stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
    $store_stmt->execute([$selected_store_id]);
    $current_store = $store_stmt->fetch();

    // Get profiles for selected store
    $store_profile_ids = array_filter(array_map('trim', explode(',', (string)$current_store['hootsuite_profile_ids'])));
    if ($store_profile_ids) {
        $placeholders = implode(',', array_fill(0, count($store_profile_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, username, network FROM hootsuite_profiles WHERE id IN ($placeholders) ORDER BY network, username");
        $stmt->execute($store_profile_ids);
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $profiles = [];
    }
} else {
    // Get all posts across all stores
    $posts = [];
    foreach ($stores as $store) {
        $store_posts = calendar_get_posts($store['id']);
        foreach ($store_posts as &$post) {
            $post['store_name'] = $store['name'];
            $post['store_id'] = $store['id'];
        }
        $posts = array_merge($posts, $store_posts);
    }
    $profiles = [];
    $current_store = null;
}

// Sort posts by scheduled time
usort($posts, function($a, $b) {
    $timeA = $a['scheduled_send_time'] ?? $a['scheduled_time'] ?? '';
    $timeB = $b['scheduled_send_time'] ?? $b['scheduled_time'] ?? '';
    return strcmp($timeA, $timeB);
});

// Calculate analytics
$total_posts = count($posts);
$network_counts = [];
$upcoming_posts = 0;
$today = date('Y-m-d');
$posts_by_store = [];

foreach ($posts as $p) {
    $time = $p['scheduled_send_time'] ?? $p['scheduled_time'] ?? null;
    if ($time && strtotime($time) >= strtotime($today)) {
        $upcoming_posts++;
    }

    // Count by store
    $store_name = $p['store_name'] ?? ($current_store['name'] ?? 'Unknown');
    if (!isset($posts_by_store[$store_name])) {
        $posts_by_store[$store_name] = 0;
    }
    $posts_by_store[$store_name]++;

    // Network counting logic
    $tags = [];
    if (!empty($p['tags'])) {
        $tags = json_decode($p['tags'], true);
        if (!is_array($tags)) $tags = [];
    }

    $network_key = null;
    $profile_id = $p['social_profile_id'] ?? null;
    if ($profile_id && isset($profile_map[$profile_id])) {
        $network_key = $profile_map[$profile_id];
    }

    if ($network_key === null) {
        foreach ($tags as $t) {
            $clean = strtolower(trim($t, " \t#"));
            if (isset($network_map[$clean])) {
                $network_key = $clean;
                break;
            }
            foreach ($network_map as $key => $val) {
                if (strpos($clean, $key) !== false) {
                    $network_key = $key;
                    break 2;
                }
            }
        }
    }

    if ($network_key !== null && isset($network_map[$network_key])) {
        $network_name = $network_map[$network_key]['name'];
        if (!isset($network_counts[$network_name])) {
            $network_counts[$network_name] = 0;
        }
        $network_counts[$network_name]++;
    }
}

// Build events for calendar
$events = [];
foreach ($posts as $p) {
    $time = $p['scheduled_send_time'] ?? $p['scheduled_time'] ?? null;
    $img = '';
    $video = '';
    $media_urls = [];

    if (!empty($p['media_urls'])) {
        $urls = to_string_array($p['media_urls']);
        foreach ($urls as $u) {
            $orig = $u;
            if (str_starts_with($u, '/calendar_media/')) {
                $u = '/public' . $u;
            } elseif (preg_match('/^https?:/', $u)) {
                $sub = '';
                if ($time && ($ts = strtotime($time)) !== false) {
                    $sub = date('Y/m', $ts) . '/';
                }
                $base = basename(parse_url($u, PHP_URL_PATH) ?? '');
                $local = __DIR__ . '/../public/calendar_media/' . $sub . $base;
                if (is_file($local)) {
                    $u = '/public/calendar_media/' . $sub . $base;
                }
            }
            $media_urls[] = $u;
            if (!$video && preg_match('/\.mp4(\?|$)/i', $orig)) {
                $video = $u;
            } elseif (!$img) {
                $img = $u;
            }
        }
    }

    $tags = [];
    if (!empty($p['tags'])) {
        $tags = json_decode($p['tags'], true);
        if (!is_array($tags)) $tags = [];
    }

    $network_key = null;
    $profile_id = $p['social_profile_id'] ?? null;
    if ($profile_id && isset($profile_map[$profile_id])) {
        $network_key = $profile_map[$profile_id];
    }

    if ($network_key === null) {
        foreach ($tags as $t) {
            $clean = strtolower(trim($t, " \t#"));
            if (isset($network_map[$clean])) {
                $network_key = $clean;
                break;
            }
            foreach ($network_map as $key => $val) {
                if (strpos($clean, $key) !== false) {
                    $network_key = $key;
                    break 2;
                }
            }
        }
    }

    $network = $network_key !== null ? ($network_map[$network_key] ?? null) : null;
    $icon = $network['icon'] ?? 'bi-share';
    $color = $network['color'] ?? '#adb5bd';
    $network_name = $network['name'] ?? '';
    if ($network_name !== '') {
        $network_name = ucfirst($network_name);
    }
    if (strtolower($network_name) === 'x') {
        $network_name = 'X (formerly Twitter)';
    }

    $class = '';
    if ($network_name) {
        $class = 'social-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($network['name'] ?? ''));
    }

    $events[] = [
        'id' => $p['post_id'] ?? null,
        'title' => $network_name ?: 'Post',
        'start' => $time ? str_replace(' ', 'T', $time) : null,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'classNames' => $class ? [$class] : ['social-default'],
        'extendedProps' => [
            'image' => $video ? '' : $img,
            'video' => $video,
            'media_urls' => $media_urls,
            'icon'  => $icon,
            'text'  => $p['text'] ?? '',
            'time'  => $time ? str_replace(' ', 'T', $time) : null,
            'network' => $network_name,
            'tags' => $tags,
            'source' => $p['source'] ?? '',
            'post_id' => $p['post_id'] ?? null,
            'social_profile_id' => $p['social_profile_id'] ?? null,
            'store_name' => $p['store_name'] ?? ($current_store['name'] ?? ''),
            'store_id' => $p['store_id'] ?? $selected_store_id
        ]
    ];
}

$events_json = json_encode($events);
$allow_schedule = count($profiles) > 0;

include __DIR__.'/header.php';
?>

    <style>
        /* Admin Calendar Specific Styles */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-calendar-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Store Selector */
        .store-selector-section {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .store-selector-label {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .store-selector-label i {
            color: #667eea;
            font-size: 1.25rem;
        }

        .store-selector-wrapper {
            flex: 1;
            min-width: 300px;
        }

        .store-selector {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
            background: white;
            color: #2c3e50;
            cursor: pointer;
        }

        .store-selector:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .store-selector option {
            padding: 0.5rem;
        }

        .store-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.25rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
        }

        .store-info-label {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .store-info-value {
            font-weight: 600;
            color: #2c3e50;
        }

        /* Calendar Header */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .calendar-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .calendar-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            margin: 0.25rem 0 0 0;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .view-selector {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            background: white;
            font-weight: 500;
            color: #2c3e50;
            cursor: pointer;
            transition: var(--transition);
        }

        .view-selector:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        /* Analytics Dashboard */
        .analytics-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-card .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .stat-card.total-posts .stat-icon { color: #667eea; }
        .stat-card.upcoming-posts .stat-icon { color: #4facfe; }
        .stat-card.network-stat .stat-icon { color: var(--network-color); }
        .stat-card.stores-stat .stat-icon { color: #fa709a; }

        .stat-card .stat-content {
            position: relative;
            z-index: 1;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .stat-card .stat-bg {
            position: absolute;
            right: -20px;
            bottom: -20px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            opacity: 0.1;
        }

        /* Calendar Wrapper */
        .calendar-wrapper {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
        }

        /* Event Modal Customization for Admin */
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .store-indicator {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Quick Actions for Admin */
        .admin-quick-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .admin-action-btn {
            flex: 1;
            padding: 0.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .admin-edit-btn {
            background: #fef3c7;
            color: #92400e;
        }

        .admin-edit-btn:hover {
            background: #92400e;
            color: white;
            transform: translateY(-2px);
        }

        .admin-delete-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        .admin-delete-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
        }

        .admin-post-as-btn {
            background: #e0f2fe;
            color: #0369a1;
        }

        .admin-post-as-btn:hover {
            background: #0369a1;
            color: white;
            transform: translateY(-2px);
        }

        /* Store Pills */
        .store-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .store-pill {
            padding: 0.5rem 1rem;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6c757d;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .store-pill:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }

        .store-pill.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6c757d;
        }

        /* FullCalendar Overrides */
        #calendar {
            width: 100%;
            margin: 0 auto;
        }

        .fc-toolbar-title {
            color: #2c3e50;
            font-size: 1.75rem !important;
            font-weight: 600 !important;
        }

        .fc .fc-button-primary {
            background-color: #2c3e50 !important;
            border-color: #2c3e50 !important;
            border-radius: 10px !important;
            padding: 0.5rem 1rem !important;
            font-weight: 500 !important;
            transition: var(--transition) !important;
        }

        .fc .fc-button-primary:hover {
            background: var(--primary-gradient) !important;
            border-color: transparent !important;
        }

        .fc-col-header-cell {
            background-color: #f5f5f5;
            padding: 1rem 0;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .fc-daygrid-day {
            min-height: 140px;
        }

        .fc-daygrid-day.fc-day-today {
            background: rgba(102, 126, 234, 0.05);
        }

        /* Modern Event Card */
        .fc-daygrid-event {
            border: none !important;
            border-radius: 12px;
            margin: 0.5rem 0.25rem !important;
            padding: 0 !important;
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
            width: calc(100% - 0.5rem);
            max-width: 100%;
        }

        .fc-daygrid-event:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .modern-event-card {
            padding: 0.75rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            color: white;
            width: 100%;
            overflow: hidden;
        }

        /* Modal overrides */
        #eventModalCalendar,
        #dayViewModal,
        #scheduleModal {
            z-index: 10000 !important;
        }

        .modal-backdrop {
            z-index: 9999 !important;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .store-selector-section {
                flex-direction: column;
                align-items: stretch;
            }

            .store-selector-wrapper {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .admin-calendar-container {
                padding: 1rem;
            }

            .calendar-header {
                text-align: center;
            }

            .calendar-title {
                font-size: 1.5rem;
            }

            .analytics-dashboard {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }
        }
    </style>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

    <div class="admin-calendar-container">
        <!-- Store Selector Section -->
        <div class="store-selector-section">
            <div class="store-selector-label">
                <i class="bi bi-shop"></i>
                <span>Select Store</span>
            </div>
            <div class="store-selector-wrapper">
                <select class="store-selector" id="storeSelector" onchange="switchStore(this.value)">
                    <option value="">All Stores (<?php echo count($stores); ?> total)</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo $store['id']; ?>" <?php echo $selected_store_id == $store['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($store['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($current_store): ?>
                <div class="store-info">
                    <span class="store-info-label">Current Store:</span>
                    <span class="store-info-value"><?php echo htmlspecialchars($current_store['name']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Store Navigation Pills -->
        <?php if (!$selected_store_id && count($stores) > 1): ?>
            <div class="store-pills">
                <span style="font-weight: 600; margin-right: 1rem;">Quick Navigation:</span>
                <?php foreach (array_slice($stores, 0, 10) as $store): ?>
                    <a href="?store_id=<?php echo $store['id']; ?>" class="store-pill">
                        <?php echo htmlspecialchars($store['name']); ?>
                    </a>
                <?php endforeach; ?>
                <?php if (count($stores) > 10): ?>
                    <span class="store-pill" style="cursor: default; background: #f8f9fa;">+<?php echo count($stores) - 10; ?> more</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Calendar Header -->
        <div class="calendar-header">
            <div>
                <h2 class="calendar-title">Calendar Management</h2>
                <p class="calendar-subtitle">
                    <?php if ($current_store): ?>
                        Managing: <?php echo htmlspecialchars($current_store['name']); ?>
                    <?php else: ?>
                        Viewing all stores (<?php echo $total_posts; ?> total posts)
                    <?php endif; ?>
                </p>
            </div>
            <div class="header-actions">
                <select id="viewSelector" class="view-selector">
                    <option value="dayGridMonth">Month View</option>
                    <option value="timeGridWeek">Week View</option>
                    <option value="timeGridDay">Day View</option>
                    <option value="listWeek">List View</option>
                </select>

                <?php if ($allow_schedule && $current_store): ?>
                    <button id="schedulePostBtn" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Schedule Post
                    </button>
                <?php endif; ?>

                <button onclick="refreshCalendar()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Analytics Dashboard -->
        <div class="analytics-dashboard">
            <div class="stat-card total-posts animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" data-count="<?php echo $total_posts; ?>">0</div>
                    <div class="stat-label">Total Posts</div>
                </div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card upcoming-posts animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" data-count="<?php echo $upcoming_posts; ?>">0</div>
                    <div class="stat-label">Upcoming</div>
                </div>
                <div class="stat-bg" style="background: var(--success-gradient);"></div>
            </div>

            <?php if (!$selected_store_id && count($posts_by_store) > 0): ?>
                <div class="stat-card stores-stat animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <div class="stat-icon">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" data-count="<?php echo count($posts_by_store); ?>">0</div>
                        <div class="stat-label">Active Stores</div>
                    </div>
                    <div class="stat-bg" style="background: var(--warning-gradient);"></div>
                </div>
            <?php endif; ?>

            <?php
            $delay = 0.3;
            foreach ($network_counts as $network => $count):
                $net_info = null;
                foreach ($network_map as $n) {
                    if (strcasecmp($n['name'], $network) === 0) {
                        $net_info = $n;
                        break;
                    }
                }
                if ($net_info):
                    $display_name = $network;
                    if (strtolower($network) === 'x') {
                        $display_name = 'X';
                    }
                    ?>
                    <div class="stat-card network-stat animate__animated animate__fadeInUp" style="animation-delay: <?php echo $delay; ?>s; --network-color: <?php echo $net_info['color']; ?>;">
                        <div class="stat-icon" style="color: <?php echo $net_info['color']; ?>">
                            <i class="bi <?php echo !empty($net_info['icon']) ? $net_info['icon'] : 'bi-share'; ?>"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" data-count="<?php echo $count; ?>">0</div>
                            <div class="stat-label"><?php echo $display_name; ?></div>
                        </div>
                        <div class="stat-bg" style="background: <?php echo $net_info['color']; ?>"></div>
                    </div>
                    <?php
                    $delay += 0.1;
                endif;
            endforeach;
            ?>
        </div>

        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h3>No Scheduled Posts</h3>
                <p>
                    <?php if ($selected_store_id): ?>
                        This store doesn't have any scheduled posts yet.
                    <?php else: ?>
                        Select a store to view and manage their calendar.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Calendar -->
            <div class="calendar-wrapper animate__animated animate__fadeIn">
                <div id="calendar"></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Event Modal -->
    <div class="modal fade" id="eventModalCalendar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.5rem;">
                    <div class="modal-title w-100" id="eventModalTitle"></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventModalBody" style="padding: 0;"></div>
            </div>
        </div>
    </div>

    <!-- Day View Modal -->
    <div class="modal fade" id="dayViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.5rem;">
                    <h5 class="modal-title" id="dayViewTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="dayViewBody"></div>
            </div>
        </div>
    </div>

<?php if ($allow_schedule && $current_store): ?>
    <!-- Schedule Post Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="scheduleForm" enctype="multipart/form-data">
                    <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                        <div class="modal-header-content w-100">
                            <h5 class="modal-title">
                                <i class="bi bi-calendar-plus"></i>
                                Schedule Post for <?php echo htmlspecialchars($current_store['name']); ?>
                            </h5>
                            <p class="modal-subtitle">Posting as Administrator</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="schedule-form-grid">
                            <!-- Post Content Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="bi bi-pencil-square"></i>
                                    <span>Post Content</span>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="postText" class="form-label">
                                        Message <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" id="postText" name="text" rows="5" required maxlength="500"></textarea>
                                    <div class="char-counter text-end mt-1">
                                        <span id="charCount">0</span> / 500
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="postHashtags" class="form-label">
                                        <i class="bi bi-hash"></i> Hashtags
                                    </label>
                                    <input type="text" class="form-control" id="postHashtags" name="hashtags"
                                           placeholder="Enter hashtags separated by commas">
                                    <small class="form-text text-muted">Don't include the # symbol</small>
                                </div>
                            </div>

                            <!-- Schedule Settings -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="bi bi-clock"></i>
                                    <span>Schedule Settings</span>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="postDate" class="form-label">
                                        Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="postDate" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="postTime" class="form-label">
                                        Time <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="postTime" required>
                                </div>

                                <input type="hidden" id="postSchedule" name="scheduled_time">
                                <input type="hidden" name="store_id" value="<?php echo $selected_store_id; ?>">
                                <input type="hidden" name="admin_post" value="1">

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="bi bi-share"></i> Social Profiles <span class="text-danger">*</span>
                                    </label>
                                    <div class="profiles-selector">
                                        <?php foreach ($profiles as $prof):
                                            $networkLower = strtolower($prof['network'] ?? '');
                                            $networkInfo = $network_map[$networkLower] ?? null;
                                            $icon = $networkInfo['icon'] ?? 'bi-share';
                                            $color = $networkInfo['color'] ?? '#6c757d';
                                            ?>
                                            <label class="profile-checkbox">
                                                <input type="checkbox" name="profile_ids[]"
                                                       value="<?php echo htmlspecialchars($prof['id']); ?>"
                                                       class="profile-checkbox-input">
                                                <div class="profile-checkbox-label" style="--profile-color: <?php echo $color; ?>">
                                                    <i class="bi <?php echo $icon; ?>"></i>
                                                    <div class="profile-info">
                                                        <span class="profile-network"><?php echo htmlspecialchars($prof['network'] ?? ''); ?></span>
                                                        <span class="profile-username">@<?php echo htmlspecialchars($prof['username'] ?? ''); ?></span>
                                                    </div>
                                                    <i class="bi bi-check-circle-fill check-icon"></i>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="profiles-error text-danger" style="display: none;">
                                        Please select at least one social profile
                                    </div>
                                </div>
                            </div>

                            <!-- Media Upload -->
                            <div class="form-section full-width">
                                <div class="section-header">
                                    <i class="bi bi-image"></i>
                                    <span>Media Attachments</span>
                                </div>
                                <div class="form-group">
                                    <div class="media-upload-area">
                                        <input type="file" class="form-control" id="postMedia" name="media[]"
                                               accept="image/*,video/*" multiple style="display: none;">
                                        <div class="media-upload-content text-center py-5" id="mediaUploadContent">
                                            <i class="bi bi-cloud-arrow-up" style="font-size: 3rem; color: #667eea;"></i>
                                            <p class="mt-2">Click to upload or drag and drop</p>
                                            <p class="text-muted small">PNG, JPG, GIF or MP4 (max. 10MB each)</p>
                                        </div>
                                        <div class="media-preview-grid" id="mediaPreviewGrid" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="post_id" id="postId">
                        <input type="hidden" name="action" id="postAction" value="create">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="scheduleSubmitBtn">
                            <i class="bi bi-check-circle"></i>
                            <span id="submitBtnText">Schedule Post</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>

    <script>
        // Store management
        function switchStore(storeId) {
            window.location.href = '?store_id=' + storeId;
        }

        function refreshCalendar() {
            window.location.reload();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Animate counters
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const animation = new countUp.CountUp(counter, target, {
                    duration: 2,
                    useEasing: true,
                    useGrouping: true
                });
                if (!animation.error) {
                    animation.start();
                }
            });

            // Store events and configurations
            window.allEvents = <?php echo $events_json; ?>;
            window.currentStoreId = <?php echo $selected_store_id ? $selected_store_id : 'null'; ?>;
            window.isAdmin = true;

            // Initialize calendar
            var calEl = document.getElementById('calendar');
            if (calEl) {
                var calendar = new FullCalendar.Calendar(calEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    height: 'auto',
                    events: window.allEvents,
                    eventContent: function(arg) {
                        var cont = document.createElement('div');
                        cont.className = 'modern-event-card';

                        // Header with network icon and name
                        var header = document.createElement('div');
                        header.className = 'event-header';
                        header.style.display = 'flex';
                        header.style.alignItems = 'center';
                        header.style.gap = '0.5rem';
                        header.style.marginBottom = '0.25rem';

                        if (arg.event.extendedProps.icon) {
                            var iconSpan = document.createElement('span');
                            iconSpan.className = 'event-icon';
                            iconSpan.style.width = '24px';
                            iconSpan.style.height = '24px';
                            iconSpan.style.background = 'rgba(255, 255, 255, 0.2)';
                            iconSpan.style.borderRadius = '50%';
                            iconSpan.style.display = 'flex';
                            iconSpan.style.alignItems = 'center';
                            iconSpan.style.justifyContent = 'center';
                            iconSpan.style.flexShrink = '0';
                            var icon = document.createElement('i');
                            icon.className = 'bi ' + arg.event.extendedProps.icon;
                            icon.style.fontSize = '0.875rem';
                            iconSpan.appendChild(icon);
                            header.appendChild(iconSpan);
                        }

                        var network = document.createElement('span');
                        network.className = 'event-network';
                        network.style.fontWeight = '600';
                        network.style.fontSize = '0.875rem';
                        network.style.overflow = 'hidden';
                        network.style.textOverflow = 'ellipsis';
                        network.style.whiteSpace = 'nowrap';
                        network.textContent = arg.event.title;
                        header.appendChild(network);

                        cont.appendChild(header);

                        // Show store name if viewing all stores
                        if (!window.currentStoreId && arg.event.extendedProps.store_name) {
                            var storeTag = document.createElement('div');
                            storeTag.style.fontSize = '0.7rem';
                            storeTag.style.background = 'rgba(255, 255, 255, 0.2)';
                            storeTag.style.padding = '0.25rem 0.5rem';
                            storeTag.style.borderRadius = '10px';
                            storeTag.style.marginBottom = '0.25rem';
                            storeTag.textContent = arg.event.extendedProps.store_name;
                            cont.appendChild(storeTag);
                        }

                        // Content preview
                        if (arg.event.extendedProps.text) {
                            var text = document.createElement('div');
                            text.className = 'event-content';
                            text.style.fontSize = '0.75rem';
                            text.style.lineHeight = '1.4';
                            text.style.overflow = 'hidden';
                            text.style.display = '-webkit-box';
                            text.style.webkitLineClamp = '2';
                            text.style.webkitBoxOrient = 'vertical';
                            text.style.opacity = '0.95';
                            text.textContent = arg.event.extendedProps.text;
                            cont.appendChild(text);
                        }

                        // Time
                        var footer = document.createElement('div');
                        footer.className = 'event-footer';
                        footer.style.fontSize = '0.7rem';
                        footer.style.opacity = '0.85';
                        footer.style.marginTop = 'auto';
                        footer.style.display = 'flex';
                        footer.style.alignItems = 'center';
                        footer.style.gap = '0.25rem';
                        var timeStr = new Date(arg.event.start).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                        footer.innerHTML = '<i class="bi bi-clock" style="font-size: 0.75rem;"></i> ' + timeStr;
                        cont.appendChild(footer);

                        return { domNodes: [cont] };
                    },
                    eventClick: function(info) {
                        showEventDetails(info.event);
                    },
                    dayMaxEvents: 3,
                    moreLinkClick: function(info) {
                        showDayView(info.date, info.allSegs);
                        return 'popover';
                    }
                });

                calendar.render();

                // View selector
                const viewSelector = document.getElementById('viewSelector');
                if (viewSelector) {
                    viewSelector.addEventListener('change', function() {
                        calendar.changeView(this.value);
                    });
                }
            }

            // Show event details function
            window.showEventDetails = function(event) {
                var body = document.getElementById('eventModalBody');
                var title = document.getElementById('eventModalTitle');

                var titleHtml = '<div style="display: flex; align-items: center; gap: 1rem;">';
                if (event.extendedProps.icon) {
                    titleHtml += '<span style="background:' + event.backgroundColor + '; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">';
                    titleHtml += '<i class="bi ' + event.extendedProps.icon + '" style="font-size: 1.5rem; color: white;"></i></span>';
                }
                titleHtml += '<div><h5 class="mb-0">' + event.title;
                if (event.extendedProps.store_name) {
                    titleHtml += '<span class="admin-badge"><i class="bi bi-shop"></i> ' + event.extendedProps.store_name + '</span>';
                }
                titleHtml += '</h5>';
                if (event.extendedProps.time) {
                    titleHtml += '<small class="text-white-50">' + new Date(event.extendedProps.time).toLocaleString() + '</small>';
                }
                titleHtml += '</div></div>';
                title.innerHTML = titleHtml;

                var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; height: 100%;">';

                // Left column - Media
                html += '<div style="background: #f8f9fa; padding: 2rem; display: flex; align-items: center; justify-content: center; border-right: 1px solid #e0e0e0;">';
                if (event.extendedProps.media_urls && event.extendedProps.media_urls.length) {
                    html += '<div style="max-height: 60vh; overflow-y: auto;">';
                    event.extendedProps.media_urls.forEach(function(url) {
                        if (/\.mp4(\?|$)/i.test(url)) {
                            html += '<video controls style="max-width: 100%; max-height: 60vh;"><source src="' + url + '" type="video/mp4"></video>';
                        } else {
                            html += '<img src="' + url + '" style="max-width: 100%; max-height: 60vh; margin-bottom: 1rem;">';
                        }
                    });
                    html += '</div>';
                } else {
                    html += '<div class="text-center text-muted"><i class="bi bi-image" style="font-size: 4rem;"></i><p>No media attached</p></div>';
                }
                html += '</div>';

                // Right column - Content
                html += '<div style="padding: 2rem; overflow-y: auto;">';

                if (event.extendedProps.text) {
                    html += '<div style="background: #f8f9fa; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">';
                    html += '<h6><i class="bi bi-chat-quote"></i> Message</h6>';
                    html += '<p style="white-space: pre-wrap;">' + event.extendedProps.text + '</p>';
                    html += '</div>';
                }

                if (event.extendedProps.tags && event.extendedProps.tags.length) {
                    html += '<div style="margin-bottom: 1.5rem;">';
                    html += '<h6><i class="bi bi-tags"></i> Tags</h6>';
                    event.extendedProps.tags.forEach(function(tag) {
                        html += '<span style="display: inline-block; padding: 0.25rem 0.75rem; background: rgba(102, 126, 234, 0.1); color: #667eea; border-radius: 20px; margin-right: 0.5rem; margin-bottom: 0.5rem;">#' + tag + '</span>';
                    });
                    html += '</div>';
                }

                // Admin actions
                html += '<div class="admin-quick-actions">';
                html += '<button class="admin-action-btn admin-edit-btn" onclick="editPost(\'' + event.extendedProps.post_id + '\', ' + event.extendedProps.store_id + ')"><i class="bi bi-pencil"></i> Edit</button>';
                html += '<button class="admin-action-btn admin-delete-btn" onclick="deletePost(\'' + event.extendedProps.post_id + '\', ' + event.extendedProps.store_id + ')"><i class="bi bi-trash"></i> Delete</button>';
                html += '</div>';

                html += '</div></div>';

                body.innerHTML = html;

                var myModal = new bootstrap.Modal(document.getElementById('eventModalCalendar'));
                myModal.show();
            };

            // Show day view
            window.showDayView = function(date, segments) {
                var dayEvents = [];
                var dateStr = date.toISOString().split('T')[0];

                window.allEvents.forEach(function(event) {
                    var eventDate = new Date(event.start).toISOString().split('T')[0];
                    if (eventDate === dateStr) {
                        dayEvents.push(event);
                    }
                });

                dayEvents.sort(function(a, b) {
                    return new Date(a.start) - new Date(b.start);
                });

                var titleEl = document.getElementById('dayViewTitle');
                titleEl.innerHTML = '<i class="bi bi-calendar3"></i> ' + date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) + ' <span class="badge bg-white text-primary ms-2">' + dayEvents.length + ' posts</span>';

                var bodyEl = document.getElementById('dayViewBody');
                var html = '<div style="padding: 1.5rem;">';

                if (dayEvents.length === 0) {
                    html += '<div class="text-center text-muted"><i class="bi bi-calendar-x" style="font-size: 3rem;"></i><h5>No posts scheduled for this day</h5></div>';
                } else {
                    dayEvents.forEach(function(event) {
                        var time = new Date(event.start);

                        html += '<div style="background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); cursor: pointer;" onclick="closeDayViewAndShowEvent(\'' + event.start + '\')">';

                        // Header
                        html += '<div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">';
                        html += '<div style="width: 48px; height: 48px; border-radius: 12px; background: ' + event.backgroundColor + '; display: flex; align-items: center; justify-content: center; color: white;">';
                        html += '<i class="bi ' + event.extendedProps.icon + '" style="font-size: 1.5rem;"></i>';
                        html += '</div>';
                        html += '<div style="flex: 1;">';
                        html += '<div style="font-weight: 600;">' + event.title;
                        if (event.extendedProps.store_name && !window.currentStoreId) {
                            html += ' <span style="background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; margin-left: 0.5rem;">' + event.extendedProps.store_name + '</span>';
                        }
                        html += '</div>';
                        html += '<div style="font-size: 0.875rem; color: #6c757d;"><i class="bi bi-clock"></i> ' + time.toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        }) + '</div>';
                        html += '</div>';
                        html += '</div>';

                        // Content
                        if (event.extendedProps.text) {
                            html += '<div style="color: #495057; line-height: 1.6;">' + event.extendedProps.text.substring(0, 200);
                            if (event.extendedProps.text.length > 200) {
                                html += '...';
                            }
                            html += '</div>';
                        }

                        html += '</div>';
                    });
                }

                html += '</div>';
                bodyEl.innerHTML = html;

                var dayModal = new bootstrap.Modal(document.getElementById('dayViewModal'));
                dayModal.show();
            };

            window.closeDayViewAndShowEvent = function(eventStart) {
                var dayModal = bootstrap.Modal.getInstance(document.getElementById('dayViewModal'));
                dayModal.hide();

                var event = window.allEvents.find(function(e) {
                    return e.start === eventStart;
                });

                if (event) {
                    setTimeout(function() {
                        var eventObj = {
                            title: event.title,
                            backgroundColor: event.backgroundColor,
                            extendedProps: event.extendedProps
                        };
                        showEventDetails(eventObj);
                    }, 300);
                }
            };

            // Admin functions
            window.editPost = function(postId, storeId) {
                // First switch to the store if not already selected
                if (storeId && storeId != window.currentStoreId) {
                    window.location.href = '?store_id=' + storeId + '&edit=' + postId;
                    return;
                }

                // Find the event data
                var event = window.allEvents.find(function(e) {
                    return e.extendedProps.post_id === postId;
                });

                if (event && window.openScheduleModal) {
                    // Close the event modal first
                    var eventModal = bootstrap.Modal.getInstance(document.getElementById('eventModalCalendar'));
                    if (eventModal) eventModal.hide();

                    setTimeout(function() {
                        window.openScheduleModal(event);
                    }, 300);
                }
            };

            window.deletePost = function(postId, storeId) {
                if (confirm('Are you sure you want to delete this scheduled post?')) {
                    var fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('post_id', postId);
                    fd.append('store_id', storeId);
                    fd.append('admin_delete', '1');

                    fetch('../public/hootsuite_post.php', {
                        method: 'POST',
                        body: fd
                    })
                        .then(r => r.json())
                        .then(function(res) {
                            if (res.success) {
                                // Close modal and refresh
                                var eventModal = bootstrap.Modal.getInstance(document.getElementById('eventModalCalendar'));
                                if (eventModal) eventModal.hide();

                                setTimeout(function() {
                                    window.location.reload();
                                }, 300);
                            } else {
                                alert(res.error || 'Unable to delete post');
                            }
                        })
                        .catch(function() {
                            alert('Unable to delete post');
                        });
                }
            };

            <?php if ($allow_schedule && $current_store): ?>
            // Initialize schedule modal
            var scheduleModalEl = document.getElementById('scheduleModal');
            var scheduleModal = new bootstrap.Modal(scheduleModalEl);
            var selectedFiles = [];

            // Initialize date/time pickers
            flatpickr("#postDate", {
                dateFormat: "m/d/Y",
                minDate: "today",
                disableMobile: true
            });

            flatpickr("#postTime", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "h:i K",
                time_24hr: false,
                disableMobile: true,
                defaultDate: "12:00 PM"
            });

            // Update combined datetime
            function updateScheduledTime() {
                var date = document.getElementById('postDate').value;
                var time = document.getElementById('postTime').value;

                if (date && time) {
                    var dateParts = date.split('/');
                    if (dateParts.length === 3) {
                        var formattedDate = dateParts[2] + '-' + dateParts[0].padStart(2, '0') + '-' + dateParts[1].padStart(2, '0');

                        var timeParts = time.match(/(\d+):(\d+)\s*(AM|PM)/i);
                        if (timeParts) {
                            var hours = parseInt(timeParts[1]);
                            var minutes = timeParts[2];
                            var ampm = timeParts[3].toUpperCase();

                            if (ampm === 'PM' && hours !== 12) hours += 12;
                            if (ampm === 'AM' && hours === 12) hours = 0;

                            var formattedTime = hours.toString().padStart(2, '0') + ':' + minutes;
                            document.getElementById('postSchedule').value = formattedDate + ' ' + formattedTime;
                        }
                    }
                }
            }

            document.getElementById('postDate').addEventListener('change', updateScheduledTime);
            document.getElementById('postTime').addEventListener('change', updateScheduledTime);

            // Character counter
            var textArea = document.getElementById('postText');
            var charCount = document.getElementById('charCount');
            if (textArea && charCount) {
                textArea.addEventListener('input', function() {
                    charCount.textContent = this.value.length;
                    if (this.value.length > 450) {
                        charCount.style.color = '#dc3545';
                    } else {
                        charCount.style.color = '#6c757d';
                    }
                });
            }

            // Media upload handling
            var mediaInput = document.getElementById('postMedia');
            var uploadContent = document.getElementById('mediaUploadContent');
            var mediaPreviewGrid = document.getElementById('mediaPreviewGrid');
            var uploadArea = document.querySelector('.media-upload-area');

            if (uploadContent) {
                uploadContent.addEventListener('click', function() {
                    mediaInput.click();
                });
            }

            // Drag and drop
            if (uploadArea) {
                uploadArea.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragging');
                });

                uploadArea.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragging');
                });

                uploadArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragging');

                    var files = Array.from(e.dataTransfer.files);
                    handleMultipleFiles(files);
                });
            }

            if (mediaInput) {
                mediaInput.addEventListener('change', function() {
                    var files = Array.from(this.files);
                    handleMultipleFiles(files);
                });
            }

            function handleMultipleFiles(files) {
                var validFiles = files.filter(function(file) {
                    return file.type.startsWith('image/') || file.type.startsWith('video/');
                });

                if (selectedFiles.length + validFiles.length > 4) {
                    alert('You can upload a maximum of 4 media files');
                    validFiles = validFiles.slice(0, 4 - selectedFiles.length);
                }

                selectedFiles = selectedFiles.concat(validFiles);

                var dt = new DataTransfer();
                selectedFiles.forEach(function(file) {
                    dt.items.add(file);
                });
                mediaInput.files = dt.files;

                displayMediaPreviews();
            }

            function displayMediaPreviews() {
                if (selectedFiles.length === 0) {
                    uploadContent.style.display = 'block';
                    mediaPreviewGrid.style.display = 'none';
                    mediaPreviewGrid.innerHTML = '';
                    return;
                }

                uploadContent.style.display = 'none';
                mediaPreviewGrid.style.display = 'grid';
                mediaPreviewGrid.innerHTML = '';

                selectedFiles.forEach(function(file, index) {
                    var previewItem = document.createElement('div');
                    previewItem.className = 'media-preview-item';
                    previewItem.style.position = 'relative';
                    previewItem.style.background = 'white';
                    previewItem.style.borderRadius = '8px';
                    previewItem.style.overflow = 'hidden';
                    previewItem.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.1)';

                    var removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'remove-media-item';
                    removeBtn.style.position = 'absolute';
                    removeBtn.style.top = '0.5rem';
                    removeBtn.style.right = '0.5rem';
                    removeBtn.style.background = 'white';
                    removeBtn.style.border = 'none';
                    removeBtn.style.borderRadius = '50%';
                    removeBtn.style.width = '1.75rem';
                    removeBtn.style.height = '1.75rem';
                    removeBtn.style.display = 'flex';
                    removeBtn.style.alignItems = 'center';
                    removeBtn.style.justifyContent = 'center';
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.2)';
                    removeBtn.style.zIndex = '10';
                    removeBtn.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
                    removeBtn.onclick = function() {
                        removeMediaItem(index);
                    };

                    if (file.type.startsWith('image/')) {
                        var img = document.createElement('img');
                        img.className = 'media-preview-image';
                        img.style.width = '100%';
                        img.style.height = '120px';
                        img.style.objectFit = 'cover';
                        img.style.display = 'block';

                        var reader = new FileReader();
                        reader.onload = function(e) {
                            img.src = e.target.result;
                        };
                        reader.readAsDataURL(file);

                        previewItem.appendChild(img);
                    } else if (file.type.startsWith('video/')) {
                        var video = document.createElement('video');
                        video.className = 'media-preview-video';
                        video.style.width = '100%';
                        video.style.height = '120px';
                        video.style.objectFit = 'cover';
                        video.style.display = 'block';
                        video.controls = true;

                        var reader = new FileReader();
                        reader.onload = function(e) {
                            video.src = e.target.result;
                        };
                        reader.readAsDataURL(file);

                        previewItem.appendChild(video);
                    }

                    previewItem.appendChild(removeBtn);

                    var fileName = document.createElement('div');
                    fileName.className = 'media-filename';
                    fileName.style.padding = '0.5rem';
                    fileName.style.fontSize = '0.75rem';
                    fileName.style.color = '#6c757d';
                    fileName.style.whiteSpace = 'nowrap';
                    fileName.style.overflow = 'hidden';
                    fileName.style.textOverflow = 'ellipsis';
                    fileName.style.background = 'white';
                    fileName.style.borderTop = '1px solid #e9ecef';
                    fileName.textContent = file.name;
                    previewItem.appendChild(fileName);

                    mediaPreviewGrid.appendChild(previewItem);
                });
            }

            function removeMediaItem(index) {
                selectedFiles.splice(index, 1);

                var dt = new DataTransfer();
                selectedFiles.forEach(function(file) {
                    dt.items.add(file);
                });
                mediaInput.files = dt.files;

                displayMediaPreviews();
            }

            // Open schedule modal
            window.openScheduleModal = function(eventObj) {
                var form = document.getElementById('scheduleForm');
                form.reset();

                selectedFiles = [];
                displayMediaPreviews();

                document.querySelectorAll('.profile-checkbox-input').forEach(function(cb) {
                    cb.checked = false;
                });

                document.getElementById('postDate').value = '';
                document.getElementById('postTime').value = '12:00 PM';
                document.getElementById('postSchedule').value = '';

                document.getElementById('postAction').value = 'create';
                document.getElementById('postId').value = '';

                if (eventObj) {
                    document.getElementById('postText').value = eventObj.extendedProps.text || '';

                    if (eventObj.extendedProps.time) {
                        var datetime = new Date(eventObj.extendedProps.time.replace('Z','').replace('T', ' '));

                        var month = (datetime.getMonth() + 1).toString();
                        var day = datetime.getDate().toString();
                        var year = datetime.getFullYear();
                        document.getElementById('postDate').value = month + '/' + day + '/' + year;

                        var hours = datetime.getHours();
                        var minutes = datetime.getMinutes();
                        var ampm = hours >= 12 ? 'PM' : 'AM';
                        hours = hours % 12;
                        hours = hours ? hours : 12;
                        var timeValue = hours + ':' + minutes.toString().padStart(2, '0') + ' ' + ampm;
                        document.getElementById('postTime').value = timeValue;
                    }

                    if (eventObj.extendedProps.social_profile_id) {
                        var checkbox = document.querySelector('.profile-checkbox-input[value="' + eventObj.extendedProps.social_profile_id + '"]');
                        if (checkbox) checkbox.checked = true;
                    }

                    if (eventObj.extendedProps.tags) {
                        document.getElementById('postHashtags').value = eventObj.extendedProps.tags.join(',');
                    }

                    document.getElementById('postId').value = eventObj.extendedProps.post_id || '';
                    document.getElementById('postAction').value = 'update';
                }

                scheduleModal.show();
            };

            // Schedule button
            var scheduleBtn = document.getElementById('schedulePostBtn');
            if (scheduleBtn) {
                scheduleBtn.addEventListener('click', function() {
                    openScheduleModal();
                });
            }

            // Form submission
            var scheduleForm = document.getElementById('scheduleForm');
            if (scheduleForm) {
                scheduleForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    var checkedProfiles = document.querySelectorAll('.profile-checkbox-input:checked');
                    if (checkedProfiles.length === 0) {
                        var errorDiv = document.querySelector('.profiles-error');
                        if (errorDiv) errorDiv.style.display = 'block';
                        return;
                    } else {
                        var errorDiv = document.querySelector('.profiles-error');
                        if (errorDiv) errorDiv.style.display = 'none';
                    }

                    updateScheduledTime();

                    var submitBtn = document.getElementById('scheduleSubmitBtn');
                    var originalText = submitBtn ? submitBtn.innerHTML : 'Save';
                    if (submitBtn) {
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scheduling...';
                        submitBtn.disabled = true;
                    }

                    var formData = new FormData(this);

                    fetch('../hootsuite_post.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(r => r.json())
                        .then(function(res) {
                            if (submitBtn) {
                                submitBtn.innerHTML = originalText;
                                submitBtn.disabled = false;
                            }

                            if (res.success) {
                                scheduleModal.hide();

                                // Show success message
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Post Scheduled!',
                                    text: 'The post has been scheduled successfully.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(function() {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: res.error || 'Unable to schedule post'
                                });
                            }
                        })
                        .catch(function() {
                            if (submitBtn) {
                                submitBtn.innerHTML = originalText;
                                submitBtn.disabled = false;
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Unable to schedule post'
                            });
                        });
                });
            }
            <?php endif; ?>

            // Check for edit parameter on page load
            <?php if (isset($_GET['edit']) && $current_store): ?>
            setTimeout(function() {
                var editPostId = '<?php echo htmlspecialchars($_GET['edit']); ?>';
                var event = window.allEvents.find(function(e) {
                    return e.extendedProps.post_id === editPostId;
                });
                if (event && window.openScheduleModal) {
                    window.openScheduleModal(event);
                }
            }, 500);
            <?php endif; ?>
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>