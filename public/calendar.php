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
    $icon = $network['icon'] ?? '';
    $color = $network['color'] ?? '#adb5bd';
    $network_name = $network['name'] ?? '';
    if ($network_name !== '') {
        $network_name = ucfirst($network_name);
    }
    $class = '';
    if ($network_name) {
        $class = 'social-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($network_name));
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

$extra_head = <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<style>
#calendar{width:100%;margin:0 auto;}
.fc-custom-event{display:flex;align-items:flex-start;}
.fc-custom-event img{width:24px;height:24px;object-fit:cover;margin-right:4px;border-radius:4px;}
.fc-custom-event .event-title{font-size:.875rem;line-height:1.2;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;}
.fc-custom-event .event-network{font-size:.75rem;}
.fc-custom-event .play-icon{font-size:1.2rem;margin-left:4px;}
.fc-daygrid-event{color:#fff;}
.fc-toolbar-title{color:#2c3e50;}
.fc .fc-button-primary{background-color:#2c3e50;border-color:#2c3e50;}
.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active{background-color:#1a252f;border-color:#1a252f;}
/* Calendar day and header styling */
.fc-col-header-cell{background-color:#f5f5f5;}
.fc-daygrid-day.fc-day-today,
.fc-daygrid-day.fc-day-past{background-color:#f0f0f0;}
.fc-daygrid-day-number{color:#000;}
.fc-daygrid-day.fc-day-today .fc-daygrid-day-number{font-size:1.25rem;}
</style>
HTML;

$extra_js = <<<JS
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        events: $events_json,
        eventContent: function(arg) {
            var cont = document.createElement('div');
            cont.className = 'fc-custom-event';

            if (arg.event.extendedProps.image) {
                var img = document.createElement('img');
                img.src = arg.event.extendedProps.image;
                cont.appendChild(img);
            }

            var info = document.createElement('div');
            info.className = 'flex-grow-1';

            var title = document.createElement('div');
            title.className = 'event-title';
            title.textContent = arg.event.extendedProps.text || '';
            info.appendChild(title);

            var net = document.createElement('div');
            net.className = 'event-network';
            if (arg.event.extendedProps.icon) {
                var icon = document.createElement('i');
                icon.className = 'bi ' + arg.event.extendedProps.icon + ' me-1';
                icon.style.color = '#fff';
                net.appendChild(icon);
            }
            net.append('Post On: ' + arg.event.title);
            info.appendChild(net);

            cont.appendChild(info);

            if (arg.event.extendedProps.video) {
                var play = document.createElement('i');
                play.className = 'bi bi-play-circle play-icon';
                cont.appendChild(play);
            }

            return { domNodes: [cont] };
        },
        eventClick: function(info){
            var e = info.event;
            var body = document.getElementById('eventModalBody');
            var title = document.getElementById('eventModalTitle');
            var titleHtml = '';
            if(e.extendedProps.icon){
                titleHtml += '<i class="' + e.extendedProps.icon + '" style="color:' + e.backgroundColor + '"></i> ';
            }
            titleHtml += e.title;
            title.innerHTML = titleHtml;
            var html = '';
            if(e.extendedProps.video){
                html += '<video controls class="w-100 mb-2"><source src="'+e.extendedProps.video+'" type="video/mp4"></video>';
            } else if(e.extendedProps.image){
                html += '<img src="'+e.extendedProps.image+'" class="img-fluid mb-2">';
            }
            html += '<p>' + (e.extendedProps.text || '') + '</p>';
            if(e.extendedProps.time){
                html += '<p><strong>Scheduled Date:</strong> ' + new Date(e.extendedProps.time).toLocaleString() + '</p>';
            }
            if(e.extendedProps.tags && e.extendedProps.tags.length){
                html += '<p><strong>Tags:</strong> ' + e.extendedProps.tags.join(', ') + '</p>';
            }
            if(e.extendedProps.network){
                html += '<p class="small text-muted">Post On: ' + e.extendedProps.network + '</p>';
            }
            body.innerHTML = html;
            new bootstrap.Modal(document.getElementById('eventModal')).show();
        }
    });
    calendar.render();
});
</script>
JS;

include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Calendar - <?php echo htmlspecialchars($store_name); ?></h2>
    <a href="index.php" class="btn btn-primary">Back to Upload</a>
</div>

<?php if (empty($posts)): ?>
    <div class="alert alert-info">No scheduled posts found.</div>
<?php else: ?>
    <div id="calendar"></div>
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventModalBody"></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__.'/footer.php'; ?>
