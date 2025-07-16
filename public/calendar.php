<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/calendar.php';
require_once __DIR__.'/../lib/helpers.php';

session_start();

if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch();
$store_name = $store['name'];

$posts = calendar_get_posts($store_id);

$events = [];
foreach ($posts as $p) {
    $time = $p['scheduled_send_time'] ?? $p['scheduled_time'] ?? null;
    $img = '';
    if (!empty($p['media_urls'])) {
        $urls = json_decode($p['media_urls'], true);
        if (is_array($urls) && !empty($urls)) {
            $img = $urls[0];
        }
    }
    $events[] = [
        'title' => $p['text'] ?? '',
        'start' => $time,
        'extendedProps' => [
            'image' => $img,
            'icon' => 'bi-share'
        ]
    ];
}

$events_json = json_encode($events);

$extra_head = <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<style>
#calendar{max-width:900px;margin:0 auto;}
.fc-custom-event{display:flex;align-items:center;}
.fc-custom-event img{width:20px;height:20px;object-fit:cover;margin-right:2px;}
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
            cont.appendChild(icon);
            var span = document.createElement('span');
            span.textContent = arg.event.title;
            cont.appendChild(span);
            return { domNodes: [cont] };
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
<?php endif; ?>

<?php include __DIR__.'/footer.php'; ?>
