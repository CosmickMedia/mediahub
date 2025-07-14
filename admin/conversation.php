<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$store_id = intval($_GET['store_id'] ?? 0);
if (!$store_id) {
    echo 'Store ID required';
    exit;
}

$stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    echo 'Store not found';
    exit;
}

if (isset($_GET['load'])) {
    $s = $pdo->prepare('SELECT sender, message, created_at FROM store_messages WHERE store_id = ? ORDER BY created_at');
    $s->execute([$store_id]);
    $msgs = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($msgs as &$m) {
        $m['created_at'] = format_ts($m['created_at']);
    }
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $ins = $pdo->prepare("INSERT INTO store_messages (store_id, sender, message, created_at) VALUES (?, 'admin', ?, NOW())");
        $ins->execute([$store_id, $message]);
    }
}

$s = $pdo->prepare('SELECT sender, message, created_at FROM store_messages WHERE store_id = ? ORDER BY created_at');
$s->execute([$store_id]);
$messages = $s->fetchAll(PDO::FETCH_ASSOC);

$active = 'messages';
include __DIR__.'/header.php';
?>
<h4>Conversation with <?php echo htmlspecialchars($store['name']); ?></h4>
<div id="messages" class="mb-4">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2">
            <strong><?php echo $msg['sender'] === 'admin' ? 'Admin' : 'Store'; ?>:</strong>
            <span><?php echo nl2br(htmlspecialchars($msg['message'])); ?></span>
            <small class="text-muted ms-2"><?php echo format_ts($msg['created_at']); ?></small>
        </div>
    <?php endforeach; ?>
</div>
<form method="post">
    <textarea name="message" class="form-control mb-2" rows="3" required></textarea>
    <button class="btn btn-primary" type="submit">Send</button>
</form>
<script>
function refreshMessages() {
    fetch('conversation.php?store_id=<?php echo $store_id; ?>&load=1')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('messages');
            container.innerHTML = '';
            data.forEach(m => {
                const div = document.createElement('div');
                div.className = 'mb-2';
                div.innerHTML = `<strong>${m.sender === 'admin' ? 'Admin' : 'Store'}:</strong> ` +
                    m.message.replace(/\n/g,'<br>') +
                    ` <small class="text-muted ms-2">${m.created_at}</small>`;
                container.appendChild(div);
            });
        });
}
setInterval(refreshMessages, 30000);
</script>
<?php include __DIR__.'/footer.php'; ?>
