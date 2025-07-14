<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/helpers.php';
session_start();

if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = get_pdo();
$store_id = $_SESSION['store_id'];

if (isset($_GET['load'])) {
    $stmt = $pdo->prepare("SELECT sender, message, created_at FROM store_messages WHERE store_id = ? ORDER BY created_at");
    $stmt->execute([$store_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($messages as &$m) {
        $m['created_at'] = format_ts($m['created_at']);
    }
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

$stmt = $pdo->prepare("SELECT sender, message, created_at FROM store_messages WHERE store_id = ? ORDER BY created_at");
$stmt->execute([$store_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__.'/header.php';
?>
<h2>Chat</h2>
<div id="messages" class="mb-4 border rounded p-3" style="max-height:400px;overflow-y:auto;">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2">
            <strong><?php echo $msg['sender'] === 'admin' ? 'Admin' : 'You'; ?>:</strong>
            <span><?php echo nl2br(htmlspecialchars($msg['message'])); ?></span>
            <small class="text-muted ms-2"><?php echo format_ts($msg['created_at']); ?></small>
        </div>
    <?php endforeach; ?>
</div>
<form method="post" action="send_message.php" id="msgForm" class="input-group">
    <textarea name="message" class="form-control" rows="2" placeholder="Type message" required></textarea>
    <button type="submit" class="btn btn-primary">Send</button>
    <input type="hidden" name="ajax" value="1">
</form>
<script>
function refreshMessages() {
    fetch('messages.php?load=1')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('messages');
            container.innerHTML = '';
            data.forEach(m => {
                const div = document.createElement('div');
                div.className = 'mb-2';
                div.innerHTML = `<strong>${m.sender === 'admin' ? 'Admin' : 'You'}:</strong> ` +
                    m.message.replace(/\n/g,'<br>') +
                    ` <small class="text-muted ms-2">${m.created_at}</small>`;
                container.appendChild(div);
            });
            container.scrollTop = container.scrollHeight;
        });
}
setInterval(refreshMessages, 5000);
document.getElementById('msgForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('send_message.php', {method:'POST', body:new FormData(this)})
        .then(r=>r.json())
        .then(()=>{ this.reset(); refreshMessages(); });
});
refreshMessages();
</script>
<?php include __DIR__.'/footer.php'; ?>
