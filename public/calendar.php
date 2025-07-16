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
    $network_map[strtolower($n['name'])] = ['icon'=>$n['icon'], 'color'=>$n['color']];
}

function profile_icon(string $type): string {
    return match($type) {
        'FACEBOOK_PAGE' => 'bi-facebook text-primary',
        'INSTAGRAM_BUSINESS' => 'bi-instagram text-danger',
        'TWITTER_PROFILE' => 'bi-twitter text-info',
        'LINKEDIN_COMPANY' => 'bi-linkedin text-primary',
        'YOUTUBE_CHANNEL' => 'bi-youtube text-danger',
        default => 'bi-share'
    };
}

$events = [];
foreach ($posts as $p) {
    $time = $p['scheduled_send_time'] ?? $p['scheduled_time'] ?? null;
    $img = '';
    if (!empty($p['media_thumb_urls'])) {
        $urls = json_decode($p['media_thumb_urls'], true);
        if (is_array($urls) && !empty($urls)) {
            $img = $urls[0];
        }
    }
    if (!$img && !empty($p['media_urls'])) {
        $urls = json_decode($p['media_urls'], true);
        if (is_array($urls) && !empty($urls)) {
            $img = $urls[0];
        }
    }
    $type = $profile_map[$p['social_profile_id']] ?? '';
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
    if (!$network && $type) {
        $type_map = [
            'FACEBOOK_PAGE' => 'facebook',
            'INSTAGRAM_BUSINESS' => 'instagram',
            'TWITTER_PROFILE' => 'x',
            'LINKEDIN_COMPANY' => 'linkedin',
            'YOUTUBE_CHANNEL' => 'youtube'
        ];
        $key = $type_map[$type] ?? null;
        if ($key && isset($network_map[$key])) {
            $network = $network_map[$key];
        }
    }
    $icon = $network['icon'] ?? profile_icon($type);
    $color = $network['color'] ?? '#2c3e50';
    $events[] = [
        'title' => $p['text'] ?? '',
        'start' => $time,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'image' => $img,
            'icon'  => $icon,
            'text'  => $p['text'] ?? '',
            'time'  => $time
        ]
    ];
}

$events_json = json_encode($events);

$extra_head = <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<style>
#calendar{width:100%;margin:0 auto;}
.fc-custom-event{display:flex;align-items:center;}
.fc-custom-event img{width:20px;height:20px;object-fit:cover;margin-right:4px;border-radius:4px;}
.fc-daygrid-event{color:#fff;}
.fc-toolbar-title{color:#2c3e50;}
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
            var icon = document.createElement('i');
            icon.className = 'bi ' + (arg.event.extendedProps.icon || 'bi-share') + ' me-1';
            icon.style.color = arg.event.backgroundColor;
            cont.appendChild(icon);
            var span = document.createElement('span');
            span.textContent = arg.event.title;
            cont.appendChild(span);
            return { domNodes: [cont] };
        },
        eventClick: function(info){
            var e = info.event;
            var body = document.getElementById('eventModalBody');
            var title = document.getElementById('eventModalTitle');
            title.innerHTML = '<i class="' + (e.extendedProps.icon || 'bi-share') + '" style="color:' + e.backgroundColor + '"></i> ' + e.title;
            var html = '';
            if(e.extendedProps.image){
                html += '<img src="'+e.extendedProps.image+'" class="img-fluid mb-2">';
            }
            html += '<p>' + (e.extendedProps.text || '') + '</p>';
            if(e.extendedProps.time){
                html += '<p><small>' + new Date(e.extendedProps.time).toLocaleString() + '</small></p>';
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
