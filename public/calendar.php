<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/calendar.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/hootsuite.php';

session_start();

if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT name, hootsuite_token FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch();
$store_name = $store['name'];
$token = $store['hootsuite_token'] ?? null;

$profile_map = [];
foreach (hootsuite_get_social_profiles($token) as $prof) {
    if (!empty($prof['id'])) {
        $profile_map[$prof['id']] = $prof['type'] ?? '';
    }
}

$posts = calendar_get_posts($store_id);

$network_map = [];
foreach ($pdo->query('SELECT name, icon, color FROM social_networks') as $n) {
    $network_map[strtolower($n['name'])] = [
        'icon'  => $n['icon'],
        'color' => $n['color'],
        'name'  => $n['name']
    ];
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

    foreach ($tags as $t) {
        $clean = strtolower(trim($t, " \t#"));
        if (isset($network_map[$clean])) {
            $network_name = $network_map[$clean]['name'];
            if (!isset($network_counts[$network_name])) {
                $network_counts[$network_name] = 0;
            }
            $network_counts[$network_name]++;
            break;
        }
        foreach ($network_map as $key => $val) {
            if (strpos($clean, $key) !== false) {
                $network_name = $val['name'];
                if (!isset($network_counts[$network_name])) {
                    $network_counts[$network_name] = 0;
                }
                $network_counts[$network_name]++;
                break 2;
            }
        }
    }
}

$events = [];
foreach ($posts as $p) {
    $time = $p['scheduled_send_time'] ?? $p['scheduled_time'] ?? null;
    $img = '';
    $video = '';
    if (!empty($p['media_urls'])) {
        $urls = to_string_array($p['media_urls']);
        foreach ($urls as $u) {
            if (!$video && preg_match('/\.mp4(\?|$)/i', $u)) {
                $video = $u;
            } elseif (!$img) {
                $img = $u;
            }
        }
    }
    if (!$img && !$video && !empty($p['media_thumb_urls'])) {
        $urls = to_string_array($p['media_thumb_urls']);
        if (!empty($urls)) {
            $img = $urls[0];
        }
    }
    if ($img && str_starts_with($img, '/calendar_media/')) {
        $img = '/public' . $img;
    }
    if ($video && str_starts_with($video, '/calendar_media/')) {
        $video = '/public' . $video;
    }
    $tags = [];
    if (!empty($p['tags'])) {
        $tags = json_decode($p['tags'], true);
        if (!is_array($tags)) $tags = [];
    }
    $network = null;
    foreach ($tags as $t) {
        $clean = strtolower(trim($t, " \t#"));
        if (isset($network_map[$clean])) { $network = $network_map[$clean]; break; }
        foreach ($network_map as $key => $val) {
            if (strpos($clean, $key) !== false) { $network = $val; break 2; }
        }
    }
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
        'title' => $network_name ?: 'Post',
        'start' => $time,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'classNames' => $class ? [$class] : ['social-default'],
        'extendedProps' => [
            'image' => $video ? '' : $img,
            'video' => $video,
            'icon'  => $icon,
            'text'  => $p['text'] ?? '',
            'time'  => $time,
            'network' => $network_name,
            'tags' => $tags
        ]
    ];
}

$events_json = json_encode($events);

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
                <a href="index.php" class="btn btn-modern-primary">
                    <i class="bi bi-arrow-left"></i> Back to Upload
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

            <!-- Calendar -->
            <div class="calendar-wrapper animate__animated animate__fadeIn delay-30">
                <div id="calendar"></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Event Modal - for individual events -->
    <div class="modal fade" id="eventModalCalendar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.5rem; position: relative;">
                    <div class="modal-title w-100" id="eventModalTitle"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute !important; top: 1rem !important; right: 1rem !important; z-index: 99999 !important; background-color: white !important; color: black !important; opacity: 1 !important; border-radius: 50% !important; width: 2rem !important; height: 2rem !important;"></button>
                </div>
                <div class="modal-body" id="eventModalBody" style="padding: 0; overflow: hidden;"></div>
            </div>
        </div>
    </div>

    <!-- Day View Modal - for showing all events in a day -->
    <div class="modal fade" id="dayViewModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;">
                <div class="modal-header day-view-header" style="position: relative;">
                    <h5 class="modal-title" id="dayViewTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute !important; top: 1rem !important; right: 1rem !important; z-index: 99999 !important; background-color: white !important; color: black !important; opacity: 1 !important; border-radius: 50% !important; width: 2rem !important; height: 2rem !important;"></button>
                </div>
                <div class="modal-body" id="dayViewBody">
                    <!-- Events will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Override any conflicting styles -->
    <style>
        /* Force modal to be on top */
        .modal-backdrop {
            z-index: 9990 !important;
        }

        #eventModalCalendar {
            z-index: 9999 !important;
        }

        #eventModalCalendar .modal-dialog {
            z-index: 9999 !important;
        }

        #eventModalCalendar .modal-content {
            z-index: 9999 !important;
            position: relative !important;
        }

        #eventModalCalendar .btn-close {
            z-index: 99999 !important;
            position: absolute !important;
            cursor: pointer !important;
            pointer-events: auto !important;
        }

        /* Day View Modal Styles with same aggressive z-index */
        #dayViewModal {
            z-index: 9999 !important;
        }

        #dayViewModal .modal-dialog {
            max-width: 1000px;
            z-index: 9999 !important;
        }

        #dayViewModal .modal-content {
            z-index: 9999 !important;
            position: relative !important;
        }

        #dayViewModal .btn-close {
            z-index: 99999 !important;
            position: absolute !important;
            cursor: pointer !important;
            pointer-events: auto !important;
            padding: 0.5rem !important;
            margin: 0 !important;
            background-color: white !important;
            color: black !important;
            border-radius: 50% !important;
            opacity: 1 !important;
            width: 2rem !important;
            height: 2rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
        }

        #dayViewModal .btn-close:hover {
            opacity: 1 !important;
            transform: rotate(90deg);
            transition: all 0.3s ease;
        }

        #dayViewModal .btn-close:focus {
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
            outline: none !important;
        }

        /* Ensure both modals' close buttons work properly */
        .modal .btn-close {
            pointer-events: auto !important;
        }

        .day-view-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }

        #dayViewTitle {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .day-view-events {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .day-event-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .day-event-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .day-event-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .day-event-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .day-event-info {
            flex: 1;
        }

        .day-event-network {
            font-size: 1.125rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .day-event-time {
            font-size: 0.875rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .day-event-time i {
            font-size: 0.875rem;
        }

        .day-event-content {
            display: flex;
            gap: 1rem;
            align-items: start;
        }

        .day-event-media {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
            background: #f8f9fa;
        }

        .day-event-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .day-event-media .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .day-event-text {
            flex: 1;
            color: #495057;
            line-height: 1.6;
        }

        .day-event-tags {
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .day-event-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .no-events-message {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-events-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        /* Ensure modal is clickable */
        .modal-open #eventModalCalendar {
            pointer-events: auto !important;
        }

        #eventModalCalendar * {
            pointer-events: auto !important;
        }

        /* Force Day View Modal to be on top */
        #dayViewModal {
            z-index: 9999 !important;
        }

        #dayViewModal .modal-dialog {
            z-index: 9999 !important;
        }

        #dayViewModal .modal-content {
            z-index: 9999 !important;
            position: relative !important;
        }

        #dayViewModal .btn-close {
            z-index: 99999 !important;
            position: absolute !important;
            cursor: pointer !important;
            pointer-events: auto !important;
        }

        /* Ensure day view modal is clickable */
        .modal-open #dayViewModal {
            pointer-events: auto !important;
        }

        #dayViewModal * {
            pointer-events: auto !important;
        }

        /* Hide the default FullCalendar more popover */
        .fc-more-popover {
            display: none !important;
        }

        /* Custom more link styling */
        .fc-more-link {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white !important;
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 0.25rem;
            display: inline-block;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .fc-more-link:hover {
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
    </style>

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

            // Function to show event details
            window.showEventDetails = function(event) {
                var body = document.getElementById('eventModalBody');
                var title = document.getElementById('eventModalTitle');

                var titleHtml = '<div class="modal-title-content">';
                if(event.extendedProps.icon){
                    titleHtml += '<span class="modal-icon" style="background:' + event.backgroundColor + '"><i class="bi ' + event.extendedProps.icon + '"></i></span>';
                }
                titleHtml += '<div><h5 class="mb-0">' + event.title + '</h5>';
                if(event.extendedProps.time){
                    titleHtml += '<small class="text-white-50">' + new Date(event.extendedProps.time).toLocaleString() + '</small>';
                }
                titleHtml += '</div></div>';
                title.innerHTML = titleHtml;

                var html = '<div class="modal-event-layout">';

                // Left column - Media
                html += '<div class="modal-media-column">';
                if(event.extendedProps.video){
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
                html += '</div>';

                html += '</div></div>';

                body.innerHTML = html;

                // Show modal using Bootstrap's method
                var myModal = new bootstrap.Modal(document.getElementById('eventModalCalendar'), {
                    backdrop: 'static',
                    keyboard: false
                });
                myModal.show();

                // Force z-index after showing
                setTimeout(function() {
                    document.getElementById('eventModalCalendar').style.zIndex = '9999';
                    // Handle multiple backdrops - get the last one which should be for this modal
                    var backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 0) {
                        var lastBackdrop = backdrops[backdrops.length - 1];
                        lastBackdrop.style.zIndex = '9990';
                    }
                }, 100);
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
                        html += '<div class="day-event-network">' + event.title + '</div>';
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
                var dayModal = new bootstrap.Modal(document.getElementById('dayViewModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                dayModal.show();

                // Force z-index after showing (same fix as event modal)
                setTimeout(function() {
                    document.getElementById('dayViewModal').style.zIndex = '9999';
                    // Handle multiple backdrops - get the last one which should be for this modal
                    var backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 0) {
                        var lastBackdrop = backdrops[backdrops.length - 1];
                        lastBackdrop.style.zIndex = '9990';
                    }
                }, 100);
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
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>