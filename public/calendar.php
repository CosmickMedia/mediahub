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

                <div class="stat-card upcoming-posts animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
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
            <div class="calendar-wrapper animate__animated animate__fadeIn" style="animation-delay: 0.3s">
                <div id="calendar"></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Event Modal - Moved outside of calendar container -->
    <div class="modal fade" id="eventModalCalendar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.5rem; position: relative;">
                    <div class="modal-title w-100" id="eventModalTitle"></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="position: absolute !important; top: 1rem !important; right: 1rem !important; z-index: 99999 !important; background: white !important; opacity: 1 !important; border-radius: 50% !important; width: 2rem !important; height: 2rem !important;"></button>
                </div>
                <div class="modal-body" id="eventModalBody" style="padding: 0; overflow: hidden;"></div>
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

        /* Ensure modal is clickable */
        .modal-open #eventModalCalendar {
            pointer-events: auto !important;
        }

        #eventModalCalendar * {
            pointer-events: auto !important;
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
                events: <?php echo $events_json; ?>,
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
                    var e = info.event;
                    var body = document.getElementById('eventModalBody');
                    var title = document.getElementById('eventModalTitle');

                    var titleHtml = '<div class="modal-title-content">';
                    if(e.extendedProps.icon){
                        titleHtml += '<span class="modal-icon" style="background:' + e.backgroundColor + '"><i class="bi ' + e.extendedProps.icon + '"></i></span>';
                    }
                    titleHtml += '<div><h5 class="mb-0">' + e.title + '</h5>';
                    if(e.extendedProps.time){
                        titleHtml += '<small class="text-white-50">' + new Date(e.extendedProps.time).toLocaleString() + '</small>';
                    }
                    titleHtml += '</div></div>';
                    title.innerHTML = titleHtml;

                    var html = '<div class="modal-event-layout">';

                    // Left column - Media
                    html += '<div class="modal-media-column">';
                    if(e.extendedProps.video){
                        html += '<video controls class="w-100"><source src="'+e.extendedProps.video+'" type="video/mp4"></video>';
                    } else if(e.extendedProps.image){
                        html += '<img src="'+e.extendedProps.image+'" class="img-fluid">';
                    } else {
                        html += '<div class="no-media"><i class="bi bi-image"></i><p>No media attached</p></div>';
                    }
                    html += '</div>';

                    // Right column - Content and metadata
                    html += '<div class="modal-content-column">';

                    if(e.extendedProps.text){
                        html += '<div class="modal-text">' + e.extendedProps.text + '</div>';
                    }

                    if(e.extendedProps.tags && e.extendedProps.tags.length){
                        html += '<div class="meta-tags">';
                        e.extendedProps.tags.forEach(function(tag) {
                            html += '<span class="tag-badge"><i class="bi bi-hash"></i>' + tag + '</span>';
                        });
                        html += '</div>';
                    }

                    html += '<div class="meta-grid">';
                    html += '<div class="meta-item"><i class="bi bi-calendar-event"></i><span>Scheduled</span><strong>' + new Date(e.extendedProps.time).toLocaleDateString() + '</strong></div>';
                    html += '<div class="meta-item"><i class="bi bi-clock"></i><span>Time</span><strong>' + new Date(e.extendedProps.time).toLocaleTimeString() + '</strong></div>';
                    html += '<div class="meta-item"><i class="bi bi-share"></i><span>Platform</span><strong>' + e.extendedProps.network + '</strong></div>';
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
                        var backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.style.zIndex = '9990';
                        }
                    }, 100);
                },
                dayMaxEvents: 2,
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