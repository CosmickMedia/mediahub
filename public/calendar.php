<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/calendar.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/auth.php';

ensure_session();

if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

// Disable ALL caching to ensure the calendar is always up to date
header('Cache-Control: no-cache, no-store, must-revalidate, private, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('X-Accel-Expires: 0');
header('X-Accel-Buffering: no');

// Explicitly tell Kinsta to bypass full-page cache
header('X-Kinsta-Cache: BYPASS');

// Optional: also support query param override
if (isset($_GET['kinsta-cache-bypass'])) {
    header('X-Kinsta-Cache: BYPASS');
}


$store_id = $_SESSION['store_id'];
$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch();
$store_name = $store['name'];

$profile_map = [];
$res = $pdo->query('SELECT id, network FROM hootsuite_profiles');
foreach ($res as $prof) {
    if (!empty($prof['id']) && !empty($prof['network'])) {
        $profile_map[$prof['id']] = strtolower($prof['network']);
    }
}

$posts = calendar_get_posts($store_id);
$debug_mode = get_setting('hootsuite_debug') === '1';

$network_map = [];
foreach ($pdo->query('SELECT name, icon, color FROM social_networks') as $n) {
    $network_map[strtolower($n['name'])] = [
            'icon'  => $n['icon'],
            'color' => $n['color'],
            'name'  => $n['name']
    ];
}

$stmt = $pdo->prepare('SELECT hootsuite_profile_ids FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store_profile_ids = array_filter(array_map('trim', explode(',', (string)$stmt->fetchColumn())));
if ($store_profile_ids) {
    $placeholders = implode(',', array_fill(0, count($store_profile_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, username, network FROM hootsuite_profiles WHERE id IN ($placeholders) ORDER BY network, username");
    $stmt->execute($store_profile_ids);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $profiles = [];
}
$allow_schedule = count($profiles) > 0;
$current_user_id = $_SESSION['store_user_id'] ?? null;

// Map store user IDs to names for display
$user_name_map = [];
$stmt = $pdo->prepare('SELECT id, first_name, last_name, email FROM store_users WHERE store_id = ?');
$stmt->execute([$store_id]);
foreach ($stmt as $u) {
    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    if ($name === '') {
        $name = $u['email'] ?? '';
    }
    $user_name_map[$u['id']] = $name;
}

// Calculate analytics
$total_posts = count($posts);
$network_counts = [];
$upcoming_posts = 0;
$today = date('Y-m-d');

foreach ($posts as $p) {
    $time = $p['scheduled_send_time'] ?? $p['scheduled_time'] ?? null;
    if ($time && strtotime($time) >= strtotime($today)) {
        $upcoming_posts++;
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
            if (isset($network_map[$clean])) { $network_key = $clean; break; }
            foreach ($network_map as $key => $val) {
                if (strpos($clean, $key) !== false) { $network_key = $key; break 2; }
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
                $local = __DIR__ . '/calendar_media/' . $sub . $base;
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
    if (!$media_urls && !empty($p['media_thumb_urls'])) {
        $urls = to_string_array($p['media_thumb_urls']);
        foreach ($urls as $u) {
            $media_urls[] = $u;
            if (!$img) { $img = $u; }
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
            if (isset($network_map[$clean])) { $network_key = $clean; break; }
            foreach ($network_map as $key => $val) {
                if (strpos($clean, $key) !== false) { $network_key = $key; break 2; }
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
    // Special case for X
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
        // Convert datetime to ISO format for FullCalendar
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
                // Provide ISO datetime for display
                    'time'  => $time ? str_replace(' ', 'T', $time) : null,
                    'network' => $network_name,
                    'tags' => $tags,
                    'source' => $p['source'] ?? '',
                    'post_id' => $p['post_id'] ?? null,
                    'created_by_user_id' => $p['created_by_user_id'] ?? null,
                    'social_profile_id' => $p['social_profile_id'] ?? null,
                    'posted_by' => $user_name_map[$p['created_by_user_id'] ?? 0] ?? ''
            ]
    ];
}

$events_json = json_encode($events);
$extra_head = <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<link rel="stylesheet" href="/assets/css/calendar-mobile.css?v=1.5.7">
HTML;

include __DIR__.'/header.php';
?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <div class="calendar-container animate__animated animate__fadeIn">
        <!-- Header Section -->
        <div class="calendar-header">
            <div>
                <h2 class="calendar-title">Social Media Calendar</h2>
                <p class="calendar-subtitle"><?php echo htmlspecialchars($store_name); ?></p>
            </div>
            <div class="header-actions">

                <select id="viewSelector" class="view-selector">
                    <option value="dayGridMonth">Month View</option>
                    <option value="timeGridWeek">Week View</option>
                    <option value="timeGridDay">Day View</option>
                </select>

                <?php if ($allow_schedule): ?>
                    <button id="schedulePostBtn" class="btn btn-modern-primary">
                        <i class="bi bi-plus-circle"></i> <span class="btn-text">Schedule Post</span>
                    </button>
                <?php endif; ?>

                <a href="index.php" class="btn btn-modern-primary">
                    <i class="bi bi-arrow-left"></i> <span class="btn-text">Back to Upload</span>
                </a>
            </div>
        </div>

        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h3>No Scheduled Posts</h3>
                <p>Start scheduling your social media content to see it appear here.</p>
            </div>
        <?php else: ?>
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

                <div class="stat-card upcoming-posts animate__animated animate__fadeInUp delay-10">
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" data-count="<?php echo $upcoming_posts; ?>">0</div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                    <div class="stat-bg"></div>
                </div>

                <?php foreach ($network_counts as $network => $count):
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
                            $display_name = 'X (formerly Twitter)';
                        }
                        ?>
                        <div class="stat-card network-stat animate__animated animate__fadeInUp" style="animation-delay: <?php echo (array_search($network, array_keys($network_counts)) + 2) * 0.1; ?>s">
                            <div class="stat-icon" style="color: <?php echo $net_info['color']; ?>">
                                <i class="bi <?php echo !empty($net_info['icon']) ? $net_info['icon'] : 'bi-share'; ?>"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number" data-count="<?php echo $count; ?>">0</div>
                                <div class="stat-label"><?php echo $display_name; ?></div>
                            </div>
                            <div class="stat-bg" style="background: <?php echo $net_info['color']; ?>"></div>
                        </div>
                    <?php endif; endforeach; ?>
            </div>

            <!-- Mobile View Toggle -->
            <div class="view-toggle-mobile" id="viewToggleMobile">
                <button class="btn-toggle active" data-view="calendar">
                    <i class="bi bi-calendar3"></i>
                    Calendar
                </button>
                <button class="btn-toggle" data-view="list">
                    <i class="bi bi-list-ul"></i>
                    List
                </button>
            </div>

            <!-- Calendar -->
            <div class="calendar-wrapper animate__animated animate__fadeIn delay-30" id="calendarWrapper">
                <div id="calendar"></div>
            </div>

            <!-- List View -->
            <div class="calendar-list-view" id="listView">
                <!-- List items will be dynamically added here -->
            </div>
        <?php endif; ?>
    </div>

    <!-- Event Modal - for individual events -->
    <div class="modal fade" id="eventModalCalendar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                    <div class="modal-title w-100" id="eventModalTitle"></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="eventModalBody" style="padding: 0;"></div>
            </div>
        </div>
    </div>

    <!-- Day View Modal - for showing all events in a day -->
    <div class="modal fade" id="dayViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header day-view-header">
                    <h5 class="modal-title" id="dayViewTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="dayViewBody">
                    <!-- Events will be loaded here -->
                </div>
            </div>
        </div>
    </div>

<?php if ($allow_schedule): ?>
    <!-- Schedule Post Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="scheduleForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <div class="modal-header-content">
                            <h5 class="modal-title">
                                <i class="bi bi-calendar-plus"></i>
                                Schedule Post for <?php echo htmlspecialchars($store_name); ?>
                            </h5>
                            <p class="modal-subtitle">Posting as <?php echo htmlspecialchars($user_name_map[$current_user_id] ?? ''); ?></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="schedule-form-grid">
                            <!-- Post Content Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="bi bi-pencil-square"></i>
                                    <span>Post Content</span>
                                </div>
                                <div class="form-group">
                                    <label for="postText" class="form-label">
                                        What would you like to share?
                                        <span class="required">*</span>
                                    </label>
                                    <textarea
                                            class="form-control form-control-modern"
                                            id="postText"
                                            name="text"
                                            rows="5"
                                            placeholder="Write your post here..."
                                            required
                                            maxlength="500"></textarea>
                                    <div class="char-counter">
                                        <span id="charCount">0</span> / 500 characters
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="postHashtags" class="form-label">
                                        <i class="bi bi-hash"></i>
                                        Hashtags
                                    </label>
                                    <input
                                            type="text"
                                            class="form-control form-control-modern"
                                            id="postHashtags"
                                            name="hashtags"
                                            placeholder="Enter hashtags separated by commas (e.g., marketing, social, business)">
                                    <small class="form-text">Tip: Don't include the # symbol, we'll add it for you</small>
                                </div>
                            </div>

                            <!-- Schedule Settings -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="bi bi-clock"></i>
                                    <span>Schedule Settings</span>
                                </div>

                                <div class="form-group">
                                    <label for="postDate" class="form-label">
                                        Date
                                        <span class="required">*</span>
                                    </label>
                                    <div class="date-time-wrapper">
                                        <input
                                                type="text"
                                                class="form-control form-control-modern"
                                                id="postDate"
                                                placeholder="Select date"
                                                required>
                                        <i class="bi bi-calendar-event input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="postTime" class="form-label">
                                        Time
                                        <span class="required">*</span>
                                    </label>
                                    <div class="date-time-wrapper">
                                        <input
                                                type="text"
                                                class="form-control form-control-modern"
                                                id="postTime"
                                                placeholder="Select time"
                                                required>
                                        <i class="bi bi-clock input-icon"></i>
                                    </div>
                                </div>

                                <!-- Hidden combined field for backend -->
                                <input type="hidden" id="postSchedule" name="scheduled_time">
                                <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">

                                <div class="form-group">
                                    <label for="postProfiles" class="form-label">
                                        <i class="bi bi-share"></i>
                                        Social Profiles
                                        <span class="required">*</span>
                                    </label>
                                    <div class="profiles-selector">
                                        <?php foreach ($profiles as $prof):
                                            $networkLower = strtolower($prof['network'] ?? '');
                                            $networkInfo = $network_map[$networkLower] ?? null;
                                            $icon = $networkInfo['icon'] ?? 'bi-share';
                                            $color = $networkInfo['color'] ?? '#6c757d';
                                            ?>
                                            <label class="profile-checkbox">
                                                <input
                                                        type="checkbox"
                                                        name="profile_ids[]"
                                                        value="<?php echo htmlspecialchars($prof['id']); ?>"
                                                        class="profile-checkbox-input">
                                                <div class="profile-checkbox-label" style="--profile-color: <?php echo $color; ?>;">
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
                                    <div class="profiles-error" style="display: none;">
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
                                    <div class="media-upload-info">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i>
                                            <strong>Media Guidelines:</strong>
                                            <strong class="d-block mt-2">Images:</strong>
                                            <ul class="mb-2">
                                                <li>Accepted formats: JPG, PNG</li>
                                                <li>Maximum file size: 10MB (5MB recommended)</li>
                                                <li>Recommended: 1200x1200px square for best compatibility</li>
                                                <li>Alternative sizes: 1200x630px (landscape) or 1080x1350px (portrait)</li>
                                                <li><small class="text-muted">Note: Instagram requires square (1:1) or portrait (4:5) ratios</small></li>
                                            </ul>
                                            <strong class="d-block">Videos:</strong>
                                            <ul class="mb-0">
                                                <li>Accepted format: MP4</li>
                                                <li>Maximum file size: 10MB</li>
                                                <li>Duration: Under 60 seconds recommended</li>
                                                <li>Recommended resolution: 1080x1080px or 1920x1080px</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="media-upload-area">
                                        <input type="file" class="form-control" id="postMedia" name="media[]" accept="image/*,video/*" multiple style="display: none;">
                                        <div class="media-upload-content" id="mediaUploadContent">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                            <p class="upload-text">Click to upload or drag and drop</p>
                                            <p class="upload-subtext">PNG, JPG, GIF or MP4 (max. 10MB each, up to 4 files)</p>
                                        </div>
                                        <div class="media-preview-grid" id="mediaPreviewGrid" style="display: none;">
                                            <!-- Media previews will be added here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="post_id" id="postId">
                        <input type="hidden" name="action" id="postAction" value="create">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-modern-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-modern-primary" id="scheduleSubmitBtn">
                            <i class="bi bi-check-circle"></i>
                            <span id="submitBtnText">Schedule Post</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Confirmation Modal -->
    <div class="modal fade" id="scheduleSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="success-animation">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h4 class="mt-3">Post Scheduled Successfully!</h4>
                    <p class="text-muted" id="successMessage">Your post has been scheduled and will be published automatically.</p>
                    <button type="button" class="btn btn-modern-primary mt-3" data-bs-dismiss="modal">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="warning-animation">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h4 class="mt-3">Delete Scheduled Post?</h4>
                    <p class="text-muted">This action cannot be undone. The post will be permanently removed.</p>
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button type="button" class="btn btn-modern-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-modern-danger" id="confirmDeleteBtn">
                            <i class="bi bi-trash"></i>
                            Delete Post
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>
    <script>
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

            // Store all events globally for day view
            window.allEvents = <?php echo $events_json; ?>;
            window.debugMode = <?php echo $debug_mode ? 'true' : 'false'; ?>;
            window.currentUserId = <?php echo $current_user_id ? (int)$current_user_id : 'null'; ?>;

            // Initialize calendar
            var calEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 'auto',
                events: window.allEvents,
                eventContent: function(arg) {
                    var cont = document.createElement('div');
                    cont.className = 'modern-event-card';

                    // Header with network icon and name
                    var header = document.createElement('div');
                    header.className = 'event-header';

                    if (arg.event.extendedProps.icon) {
                        var iconSpan = document.createElement('span');
                        iconSpan.className = 'event-icon';
                        var icon = document.createElement('i');
                        icon.className = 'bi ' + arg.event.extendedProps.icon;
                        iconSpan.appendChild(icon);
                        header.appendChild(iconSpan);
                    }

                    var network = document.createElement('span');
                    network.className = 'event-network';
                    network.textContent = arg.event.title;
                    header.appendChild(network);

                    if (window.debugMode && arg.event.extendedProps.source) {
                        var src = document.createElement('span');
                        src.className = 'badge bg-secondary ms-2';
                        src.textContent = arg.event.extendedProps.source;
                        header.appendChild(src);
                    }

                    cont.appendChild(header);

                    // Media preview - only show if we have media
                    if (arg.event.extendedProps.image || arg.event.extendedProps.video) {
                        var mediaWrapper = document.createElement('div');
                        mediaWrapper.className = 'event-media-preview';

                        if (arg.event.extendedProps.video) {
                            var playOverlay = document.createElement('div');
                            playOverlay.className = 'video-overlay';
                            playOverlay.innerHTML = '<i class="bi bi-play-circle-fill"></i>';
                            mediaWrapper.appendChild(playOverlay);
                        }

                        var img = document.createElement('img');
                        img.src = arg.event.extendedProps.image || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgZmlsbD0iIzMzMyIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZmZmIiBmb250LXNpemU9IjE0IiBkeT0iLjNlbSI+VmlkZW8gUHJldmlldzwvdGV4dD48L3N2Zz4=';
                        mediaWrapper.appendChild(img);
                        cont.appendChild(mediaWrapper);
                    }

                    // Content preview
                    if (arg.event.extendedProps.text) {
                        var text = document.createElement('div');
                        text.className = 'event-content';
                        text.textContent = arg.event.extendedProps.text;
                        cont.appendChild(text);
                    }

                    // Time
                    var footer = document.createElement('div');
                    footer.className = 'event-footer';
                    var timeStr = new Date(arg.event.start).toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                    footer.innerHTML = '<i class="bi bi-clock"></i> ' + timeStr;
                    cont.appendChild(footer);

                    return { domNodes: [cont] };
                },
                eventClick: function(info){
                    showEventDetails(info.event);
                },
                dayMaxEvents: 2,
                moreLinkClick: function(info) {
                    // Hide any existing popovers
                    document.querySelectorAll('.fc-more-popover').forEach(function(el) {
                        el.style.display = 'none';
                    });

                    // Show custom day view modal
                    showDayView(info.date, info.allSegs);
                    return 'popover'; // This prevents the default behavior
                },
                moreLinkContent: function(args) {
                    return '+' + args.num + ' more';
                },
                eventMouseEnter: function(info) {
                    info.el.style.transform = 'translateY(-4px)';
                    info.el.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                },
                eventMouseLeave: function(info) {
                    info.el.style.transform = 'translateY(0)';
                    info.el.style.boxShadow = '';
                }
            });

            calendar.render();

            // Function to update combined scheduled time
            function updateScheduledTime() {
                var date = document.getElementById('postDate').value;
                var time = document.getElementById('postTime').value;

                if (date && time) {
                    // Convert to backend format (Y-m-d H:i)
                    var dateParts = date.split('/');
                    if(dateParts.length === 3) {
                        var formattedDate = dateParts[2] + '-' + dateParts[0].padStart(2, '0') + '-' + dateParts[1].padStart(2, '0');

                        // Parse time
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

            var scheduleBtn = document.getElementById('schedulePostBtn');
            var scheduleModalEl = document.getElementById('scheduleModal');
            var scheduleModal;

            if(scheduleModalEl){
                scheduleModal = new bootstrap.Modal(scheduleModalEl);

                // Initialize date and time pickers separately
                var datePicker = flatpickr("#postDate", {
                    dateFormat: "m/d/Y",  // American date format
                    minDate: "today",
                    disableMobile: true,
                    static: true
                });

                window.timePicker = flatpickr("#postTime", {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: "h:i K",  // 12-hour format with AM/PM
                    time_24hr: false,
                    disableMobile: true,
                    static: true,
                    defaultDate: "12:00 PM"
                });

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

                // Media upload handling - UPDATED FOR MULTIPLE FILES
                var mediaInput = document.getElementById('postMedia');
                var uploadContent = document.getElementById('mediaUploadContent');
                var mediaPreviewGrid = document.getElementById('mediaPreviewGrid');
                var uploadArea = document.querySelector('.media-upload-area');

                // Store selected files
                var selectedFiles = [];

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
                    // Filter and limit files
                    var validFiles = files.filter(function(file) {
                        return file.type.startsWith('image/') || file.type.startsWith('video/');
                    });

                    // Limit to 4 files total
                    if (selectedFiles.length + validFiles.length > 4) {
                        alert('You can upload a maximum of 4 media files');
                        validFiles = validFiles.slice(0, 4 - selectedFiles.length);
                    }

                    // Add new files to selected files
                    selectedFiles = selectedFiles.concat(validFiles);

                    // Update the file input
                    var dt = new DataTransfer();
                    selectedFiles.forEach(function(file) {
                        dt.items.add(file);
                    });
                    mediaInput.files = dt.files;

                    // Display previews
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

                        var removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'remove-media-item';
                        removeBtn.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
                        removeBtn.onclick = function() {
                            removeMediaItem(index);
                        };

                        if (file.type.startsWith('image/')) {
                            var img = document.createElement('img');
                            img.className = 'media-preview-image';

                            var reader = new FileReader();
                            reader.onload = function(e) {
                                img.src = e.target.result;
                            };
                            reader.readAsDataURL(file);

                            previewItem.appendChild(img);
                        } else if (file.type.startsWith('video/')) {
                            var video = document.createElement('video');
                            video.className = 'media-preview-video';
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
                        fileName.textContent = file.name;
                        previewItem.appendChild(fileName);

                        mediaPreviewGrid.appendChild(previewItem);
                    });
                }

                function removeMediaItem(index) {
                    selectedFiles.splice(index, 1);

                    // Update the file input
                    var dt = new DataTransfer();
                    selectedFiles.forEach(function(file) {
                        dt.items.add(file);
                    });
                    mediaInput.files = dt.files;

                    displayMediaPreviews();
                }

                if(scheduleBtn){
                    scheduleBtn.addEventListener('click', function(){
                        openScheduleModal();
                    });
                }
            }

            function openScheduleModal(eventObj){
                if(!scheduleModal) return;
                var form = document.getElementById('scheduleForm');
                form.reset();

                // Clear media
                selectedFiles = [];
                if (window.displayMediaPreviews) {
                    window.displayMediaPreviews();
                }

                // Clear checkboxes
                document.querySelectorAll('.profile-checkbox-input').forEach(function(cb) {
                    cb.checked = false;
                });

                // Clear date/time pickers
                document.getElementById('postDate').value = '';
                document.getElementById('postTime').value = '12:00 PM';
                if (window.timePicker) {
                    window.timePicker.setDate('12:00 PM', false);
                }
                document.getElementById('postSchedule').value = '';

                document.getElementById('postAction').value = 'create';
                document.getElementById('postId').value = '';

                if(eventObj){
                    document.getElementById('postText').value = eventObj.extendedProps.text || '';
                    if(eventObj.extendedProps.time){
                        // Parse backend time to separate date and time fields
                        var datetime = new Date(eventObj.extendedProps.time.replace('Z','').replace('T', ' '));

                        // Set date in American format
                        var month = (datetime.getMonth() + 1).toString();
                        var day = datetime.getDate().toString();
                        var year = datetime.getFullYear();
                        document.getElementById('postDate').value = month + '/' + day + '/' + year;

                        // Set time in 12-hour format
                        var hours = datetime.getHours();
                        var minutes = datetime.getMinutes();
                        var ampm = hours >= 12 ? 'PM' : 'AM';
                        hours = hours % 12;
                        hours = hours ? hours : 12;
                        var timeValue = hours + ':' + minutes.toString().padStart(2, '0') + ' ' + ampm;
                        document.getElementById('postTime').value = timeValue;
                        if (window.timePicker) {
                            window.timePicker.setDate(timeValue, false);
                        }
                    }
                    if(eventObj.extendedProps.social_profile_id){
                        var checkbox = document.querySelector('.profile-checkbox-input[value="' + eventObj.extendedProps.social_profile_id + '"]');
                        if(checkbox) checkbox.checked = true;
                    }
                    if(eventObj.extendedProps.tags){
                        document.getElementById('postHashtags').value = eventObj.extendedProps.tags.join(',');
                    }
                    document.getElementById('postId').value = eventObj.extendedProps.post_id || '';
                    document.getElementById('postAction').value = 'update';
                }
                scheduleModal.show();
            }

            var scheduleForm = document.getElementById('scheduleForm');
            if(scheduleForm){
                scheduleForm.addEventListener('submit', function(e){
                    e.preventDefault();

                    // Validate at least one profile is selected
                    var checkedProfiles = document.querySelectorAll('.profile-checkbox-input:checked');
                    if (checkedProfiles.length === 0) {
                        var errorDiv = document.querySelector('.profiles-error');
                        if(errorDiv) errorDiv.style.display = 'block';
                        return;
                    } else {
                        var errorDiv = document.querySelector('.profiles-error');
                        if(errorDiv) errorDiv.style.display = 'none';
                    }

                    // Update combined datetime field
                    updateScheduledTime();

                    // Show loading state
                    var submitBtn = document.getElementById('scheduleSubmitBtn');
                    var originalText = submitBtn ? submitBtn.innerHTML : 'Save';
                    if(submitBtn) {
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scheduling...';
                        submitBtn.disabled = true;
                    }

                    var formData = new FormData(this);

                    fetch('hootsuite_post.php', { method:'POST', body: formData })
                        .then(r=>r.json())
                        .then(function(res){
                            if(submitBtn) {
                                submitBtn.innerHTML = originalText;
                                submitBtn.disabled = false;
                            }

                            if(res.success){
                                scheduleModal.hide();

                                // Show success modal if it exists
                                var successModalEl = document.getElementById('scheduleSuccessModal');
                                if(successModalEl) {
                                    var successModal = new bootstrap.Modal(successModalEl);
                                    successModal.show();

                                    // Force reload after modal is closed
                                    successModalEl.addEventListener('hidden.bs.modal', function () {
                                        // Force hard reload bypassing cache
                                        window.location.href = window.location.href + '?t=' + Date.now();
                                    }, { once: true });
                                } else {
                                    // If no modal, reload immediately
                                    window.location.href = window.location.href + '?t=' + Date.now();
                                }

                                if(res.events){
                                    res.events.forEach(function(ev){
                                        if(ev.id){
                                            var ex = calendar.getEventById(ev.id);
                                            if(ex) ex.remove();
                                        }
                                        calendar.addEvent(ev);
                                    });
                                } else if(res.event){
                                    if(res.event.id){
                                        var ex = calendar.getEventById(res.event.id);
                                        if(ex) ex.remove();
                                    }
                                    calendar.addEvent(res.event);
                                }

                                // Reset form
                                scheduleForm.reset();
                                document.querySelectorAll('.profile-checkbox-input').forEach(function(cb) {
                                    cb.checked = false;
                                });
                                selectedFiles = [];
                                if (window.displayMediaPreviews) {
                                    window.displayMediaPreviews();
                                }
                            } else {
                                alert(res.error || 'Unable to save post');
                            }
                        }).catch(function(){
                        if(submitBtn) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                        alert('Unable to save post');
                    });
                });
            }

            // Delete post with custom modal
            window.deleteScheduledPost = function(eventObj) {
                window.pendingDeletePost = eventObj;

                var deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                deleteModal.show();
            };

            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                if (window.pendingDeletePost) {
                    var eventObj = window.pendingDeletePost;

                    var fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('post_id', eventObj.extendedProps.post_id);

                    fetch('hootsuite_post.php', {
                        method: 'POST',
                        body: fd
                    })
                        .then(r => r.json())
                        .then(function(res) {
                            if (res.success) {
                                var ev = calendar.getEventById(eventObj.extendedProps.post_id);
                                if (ev) ev.remove();

                                // Close both modals
                                bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
                                var eventModal = bootstrap.Modal.getInstance(document.getElementById('eventModalCalendar'));
                                if (eventModal) eventModal.hide();
                            } else {
                                alert(res.error || 'Unable to delete');
                            }
                        })
                        .catch(function() {
                            alert('Unable to delete');
                        });

                    window.pendingDeletePost = null;
                }
            });

            // Function to show event details
            window.showEventDetails = function(event) {
                var body = document.getElementById('eventModalBody');
                var title = document.getElementById('eventModalTitle');

                var titleHtml = '<div class="modal-title-content">';
                if(event.extendedProps.icon){
                    titleHtml += '<span class="modal-icon" style="background:' + event.backgroundColor + '"><i class="bi ' + event.extendedProps.icon + '"></i></span>';
                }
                titleHtml += '<div><h5 class="mb-0">' + event.title;
                if(window.debugMode && event.extendedProps.source){
                    titleHtml += ' <span class="badge bg-secondary ms-2">' + event.extendedProps.source + '</span>';
                }
                titleHtml += '</h5>';
                if(event.extendedProps.time){
                    titleHtml += '<small class="text-white-50">' + new Date(event.extendedProps.time).toLocaleString() + '</small>';
                }
                titleHtml += '</div></div>';
                title.innerHTML = titleHtml;

                var html = '<div class="modal-event-layout">';

                // Left column - Media
                html += '<div class="modal-media-column">';
                if(event.extendedProps.media_urls && event.extendedProps.media_urls.length){
                    html += '<div class="media-scroll">';
                    event.extendedProps.media_urls.forEach(function(url){
                        if(/\.mp4(\?|$)/i.test(url)){
                            html += '<video controls class="media-item"><source src="'+url+'" type="video/mp4"></video>';
                        } else {
                            html += '<img src="'+url+'" class="media-item">';
                        }
                    });
                    html += '</div>';
                } else if(event.extendedProps.video){
                    html += '<video controls class="w-100"><source src="'+event.extendedProps.video+'" type="video/mp4"></video>';
                } else if(event.extendedProps.image){
                    html += '<img src="'+event.extendedProps.image+'" class="img-fluid">';
                } else {
                    html += '<div class="no-media"><i class="bi bi-image"></i><p>No media attached</p></div>';
                }
                html += '</div>';

                // Right column - Content and metadata
                html += '<div class="modal-content-column">';

                if(event.extendedProps.text){
                    html += '<div class="modal-text">' + event.extendedProps.text + '</div>';
                }

                if(event.extendedProps.tags && event.extendedProps.tags.length){
                    html += '<div class="meta-tags">';
                    event.extendedProps.tags.forEach(function(tag) {
                        html += '<span class="tag-badge"><i class="bi bi-hash"></i>' + tag + '</span>';
                    });
                    html += '</div>';
                }

                html += '<div class="meta-grid">';
                html += '<div class="meta-item"><i class="bi bi-calendar-event"></i><span>Scheduled</span><strong>' + new Date(event.extendedProps.time).toLocaleDateString() + '</strong></div>';
                html += '<div class="meta-item"><i class="bi bi-clock"></i><span>Time</span><strong>' + new Date(event.extendedProps.time).toLocaleTimeString() + '</strong></div>';
                html += '<div class="meta-item"><i class="bi bi-share"></i><span>Platform</span><strong>' + event.extendedProps.network + '</strong></div>';
                if(event.extendedProps.posted_by){
                    html += '<div class="meta-item"><i class="bi bi-person-circle"></i><span>Posted by</span><strong>' + event.extendedProps.posted_by + '</strong></div>';
                }
                html += '</div>';

                if(event.extendedProps.created_by_user_id && window.currentUserId && parseInt(event.extendedProps.created_by_user_id) === parseInt(window.currentUserId)){
                    html += '<div class="text-end mt-3">';
                    html += '<button class="btn btn-sm btn-primary me-2" id="editEventBtn">Edit</button>';
                    html += '<button class="btn btn-sm btn-danger" id="deleteEventBtn">Delete</button>';
                    html += '</div>';
                }

                html += '</div></div>';

                body.innerHTML = html;

                if(event.extendedProps.created_by_user_id && window.currentUserId && parseInt(event.extendedProps.created_by_user_id) === parseInt(window.currentUserId)){
                    document.getElementById('editEventBtn').addEventListener('click', function(){
                        openScheduleModal(event);
                    });
                    document.getElementById('deleteEventBtn').addEventListener('click', function(){
                        deleteScheduledPost(event);
                    });
                }

                // Show modal using Bootstrap's method
                var myModal = new bootstrap.Modal(document.getElementById('eventModalCalendar'));
                myModal.show();
            };

            // Function to show day view
            window.showDayView = function(date, segments) {
                // Get all events for this specific day
                var dayEvents = [];
                var dateStr = date.toISOString().split('T')[0];

                window.allEvents.forEach(function(event) {
                    var eventDate = new Date(event.start).toISOString().split('T')[0];
                    if (eventDate === dateStr) {
                        dayEvents.push(event);
                    }
                });

                // Sort events by time
                dayEvents.sort(function(a, b) {
                    return new Date(a.start) - new Date(b.start);
                });

                // Set modal title
                var titleEl = document.getElementById('dayViewTitle');
                titleEl.innerHTML = '<i class="bi bi-calendar3"></i> ' + date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) + ' <span class="badge bg-white text-primary ms-2">' + dayEvents.length + ' posts</span>';

                // Build day view content
                var bodyEl = document.getElementById('dayViewBody');
                var html = '<div class="day-view-events">';

                if (dayEvents.length === 0) {
                    html += '<div class="no-events-message">';
                    html += '<i class="bi bi-calendar-x"></i>';
                    html += '<h5>No posts scheduled for this day</h5>';
                    html += '</div>';
                } else {
                    dayEvents.forEach(function(event) {
                        var time = new Date(event.start);
                        var networkClass = event.classNames ? event.classNames[0] : 'social-default';

                        html += '<div class="day-event-item" onclick="closeDayViewAndShowEvent(\'' + event.start + '\')">';

                        // Header
                        html += '<div class="day-event-header">';
                        html += '<div class="day-event-icon ' + networkClass + '" style="background: ' + event.backgroundColor + '">';
                        html += '<i class="bi ' + event.extendedProps.icon + '"></i>';
                        html += '</div>';
                        html += '<div class="day-event-info">';
                        html += '<div class="day-event-network">' + event.title;
                        if (window.debugMode && event.extendedProps.source) {
                            html += ' <span class="badge bg-secondary ms-2">' + event.extendedProps.source + '</span>';
                        }
                        html += '</div>';
                        html += '<div class="day-event-time">';
                        html += '<i class="bi bi-clock"></i> ' + time.toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';

                        // Content
                        html += '<div class="day-event-content">';

                        // Media
                        if (event.extendedProps.image || event.extendedProps.video) {
                            html += '<div class="day-event-media">';
                            if (event.extendedProps.video) {
                                html += '<div class="video-overlay"><i class="bi bi-play-circle-fill"></i></div>';
                            }
                            html += '<img src="' + (event.extendedProps.image || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgZmlsbD0iIzMzMyIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZmZmIiBmb250LXNpemU9IjE0IiBkeT0iLjNlbSI+VmlkZW8gUHJldmlldzwvdGV4dD48L3N2Zz4=') + '">';
                            html += '</div>';
                        }

                        // Text
                        if (event.extendedProps.text) {
                            html += '<div class="day-event-text">' + event.extendedProps.text.substring(0, 200);
                            if (event.extendedProps.text.length > 200) {
                                html += '...';
                            }
                            html += '</div>';
                        }

                        html += '</div>';

                        // Tags
                        if (event.extendedProps.tags && event.extendedProps.tags.length) {
                            html += '<div class="day-event-tags">';
                            event.extendedProps.tags.forEach(function(tag) {
                                html += '<span class="day-event-tag"><i class="bi bi-hash"></i>' + tag + '</span>';
                            });
                            html += '</div>';
                        }

                        html += '</div>';
                    });
                }

                html += '</div>';
                bodyEl.innerHTML = html;

                // Show the day view modal
                var dayModal = new bootstrap.Modal(document.getElementById('dayViewModal'));
                dayModal.show();
            };

            // Function to close day view and show event details
            window.closeDayViewAndShowEvent = function(eventStart) {
                // Close day view modal
                var dayModal = bootstrap.Modal.getInstance(document.getElementById('dayViewModal'));
                dayModal.hide();

                // Find the event
                var event = window.allEvents.find(function(e) {
                    return e.start === eventStart;
                });

                if (event) {
                    // Wait for modal to close then show event details
                    setTimeout(function() {
                        // Create event object with same structure as FullCalendar event
                        var eventObj = {
                            title: event.title,
                            backgroundColor: event.backgroundColor,
                            extendedProps: event.extendedProps
                        };
                        showEventDetails(eventObj);
                    }, 300);
                }
            };

            // Custom view selector functionality
            const viewSelector = document.getElementById('viewSelector');
            if (viewSelector) {
                // Hide the default view buttons
                const toolbar = document.querySelector('.fc-toolbar-chunk:last-child');
                if (toolbar) {
                    toolbar.style.display = 'none';
                }

                viewSelector.addEventListener('change', function() {
                    calendar.changeView(this.value);
                });
            }

            // Make selected files accessible globally for modal
            window.selectedFiles = [];
            window.displayMediaPreviews = displayMediaPreviews;

            // List View Functionality
            function initializeListView() {
                const viewToggle = document.getElementById('viewToggleMobile');
                const calendarWrapper = document.getElementById('calendarWrapper');
                const listView = document.getElementById('listView');

                if (!viewToggle) return;

                // Toggle view buttons
                const toggleButtons = viewToggle.querySelectorAll('.btn-toggle');
                toggleButtons.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const view = this.getAttribute('data-view');

                        // Update active state
                        toggleButtons.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');

                        // Show/hide views
                        if (view === 'list') {
                            calendarWrapper.style.display = 'none';
                            listView.style.display = 'block';
                            listView.classList.add('active');
                            renderListView();
                        } else {
                            calendarWrapper.style.display = 'block';
                            listView.style.display = 'none';
                            listView.classList.remove('active');
                        }
                    });
                });
            }

            function renderListView() {
                const listView = document.getElementById('listView');
                if (!listView || !window.allEvents) return;

                // Group events by date
                const eventsByDate = {};
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                window.allEvents.forEach(event => {
                    const eventDate = new Date(event.start);
                    const dateKey = eventDate.toISOString().split('T')[0];

                    if (!eventsByDate[dateKey]) {
                        eventsByDate[dateKey] = [];
                    }
                    eventsByDate[dateKey].push(event);
                });

                // Sort dates
                const sortedDates = Object.keys(eventsByDate).sort();

                // Build HTML
                let html = '';

                if (sortedDates.length === 0) {
                    html = '<div class="list-empty-state">';
                    html += '<i class="bi bi-calendar-x"></i>';
                    html += '<h4>No Scheduled Posts</h4>';
                    html += '<p>Start scheduling your social media content to see it appear here.</p>';
                    html += '</div>';
                } else {
                    sortedDates.forEach(dateKey => {
                        const date = new Date(dateKey + 'T00:00:00');
                        const events = eventsByDate[dateKey];
                        const isToday = date.toDateString() === today.toDateString();

                        // Sort events by time
                        events.sort((a, b) => new Date(a.start) - new Date(b.start));

                        html += '<div class="list-date-group">';

                        // Date header
                        html += '<div class="list-date-header">';
                        html += '<div>';
                        html += '<h3>' + date.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' }) + '</h3>';
                        html += '<div class="date-full">' + date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) + '</div>';
                        html += '</div>';
                        if (isToday) {
                            html += '<span class="list-today-badge">Today</span>';
                        }
                        html += '<span class="post-count">' + events.length + ' post' + (events.length > 1 ? 's' : '') + '</span>';
                        html += '</div>';

                        // Events
                        events.forEach(event => {
                            const time = new Date(event.start);
                            const hours = time.getHours();
                            const minutes = time.getMinutes();
                            const ampm = hours >= 12 ? 'PM' : 'AM';
                            const displayHours = hours % 12 || 12;
                            const displayTime = displayHours + ':' + minutes.toString().padStart(2, '0');

                            // Get network info
                            const networkName = event.title;
                            const icon = event.extendedProps.icon || 'bi-share';
                            const color = event.backgroundColor || '#6c757d';

                            html += '<div class="list-event-item" style="--event-color: ' + color + '" onclick="showEventDetailsFromList(\'' + event.start + '\')">';

                            // Time
                            html += '<div class="list-event-time">';
                            html += '<span class="time-hour">' + displayTime + '</span>';
                            html += '<span class="time-period">' + ampm + '</span>';
                            html += '</div>';

                            // Content
                            html += '<div class="list-event-content">';

                            // Header
                            html += '<div class="list-event-header">';
                            html += '<span class="list-event-network" style="--event-color: ' + color + '; --event-color-bg: ' + color + '20;">';
                            html += '<i class="bi ' + icon + '"></i> ' + networkName;
                            html += '</span>';
                            html += '</div>';

                            // Text
                            if (event.extendedProps.text) {
                                html += '<div class="list-event-text">' + event.extendedProps.text + '</div>';
                            }

                            // Media preview
                            if (event.extendedProps.media_urls && event.extendedProps.media_urls.length > 0) {
                                html += '<div class="list-event-media">';
                                const mediaCount = event.extendedProps.media_urls.length;
                                const previewCount = Math.min(3, mediaCount);

                                for (let i = 0; i < previewCount; i++) {
                                    const url = event.extendedProps.media_urls[i];
                                    if (/\.mp4(\?|$)/i.test(url)) {
                                        html += '<div class="media-preview video-preview"><i class="bi bi-play-circle-fill"></i></div>';
                                    } else {
                                        html += '<img src="' + url + '" alt="Media">';
                                    }
                                }

                                if (mediaCount > 3) {
                                    html += '<div class="media-count">+' + (mediaCount - 3) + '</div>';
                                }

                                html += '</div>';
                            }

                            // Tags
                            if (event.extendedProps.tags && event.extendedProps.tags.length > 0) {
                                html += '<div class="list-event-tags">';
                                event.extendedProps.tags.forEach(tag => {
                                    html += '<span class="list-event-tag">#' + tag + '</span>';
                                });
                                html += '</div>';
                            }

                            html += '</div>'; // list-event-content
                            html += '</div>'; // list-event-item
                        });

                        html += '</div>'; // list-date-group
                    });
                }

                listView.innerHTML = html;
            }

            // Show event details from list view
            window.showEventDetailsFromList = function(eventStart) {
                const event = window.allEvents.find(e => e.start === eventStart);
                if (event) {
                    const eventObj = {
                        title: event.title,
                        backgroundColor: event.backgroundColor,
                        extendedProps: event.extendedProps
                    };
                    showEventDetails(eventObj);
                }
            };

            // Initialize list view
            initializeListView();
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>