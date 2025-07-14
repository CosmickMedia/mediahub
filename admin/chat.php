<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$store_id = intval($_GET['store_id'] ?? 0);

// handle ajax fetch
if (isset($_GET['load'])) {
    if ($store_id > 0) {
        $stmt = $pdo->prepare('SELECT sender, message, created_at FROM store_messages WHERE store_id = ? ORDER BY created_at');
        $stmt->execute([$store_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query('SELECT m.sender, m.message, m.created_at, s.name AS store_name FROM store_messages m LEFT JOIN stores s ON m.store_id = s.id ORDER BY m.created_at');
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($messages as &$m) {
        $m['created_at'] = format_ts($m['created_at']);
    }
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $store_id > 0) {
    $message = trim($_POST['message']);
    if ($message !== '') {
        $ins = $pdo->prepare("INSERT INTO store_messages (store_id, sender, message, created_at) VALUES (?, 'admin', ?, NOW())");
        $ins->execute([$store_id, $message]);
    }
    if (!empty($_POST['ajax'])) {
        echo json_encode(['success' => true]);
        exit;
    }
    header('Location: chat.php?store_id='.$store_id);
    exit;
}

// get stores list for dropdown
$stores = $pdo->query('SELECT id, name FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

if ($store_id > 0) {
    $stmt = $pdo->prepare('SELECT sender, message, created_at FROM store_messages WHERE store_id = ? ORDER BY created_at');
    $stmt->execute([$store_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $store_stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ?');
    $store_stmt->execute([$store_id]);
    $store = $store_stmt->fetch(PDO::FETCH_ASSOC);
    $store_name = $store['name'] ?? '';
} else {
    $stmt = $pdo->query('SELECT m.sender, m.message, m.created_at, s.name AS store_name FROM store_messages m LEFT JOIN stores s ON m.store_id = s.id ORDER BY m.created_at');
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $store_name = 'All Stores';
}

$active = 'chat';
include __DIR__.'/header.php';
?>
<h4>Chat - <?php echo htmlspecialchars($store_name); ?></h4>
<form method="get" class="mb-3" id="storeSelectForm">
    <label class="form-label">Select Store</label>
    <select name="store_id" class="form-select" onchange="document.getElementById('storeSelectForm').submit();">
        <option value="0"<?php if($store_id===0) echo ' selected'; ?>>All Stores</option>
        <?php foreach ($stores as $s): ?>
            <option value="<?php echo $s['id']; ?>"<?php if($store_id===$s['id']) echo ' selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option>
        <?php endforeach; ?>
    </select>
</form>
<div id="messages" class="mb-4" style="max-height:400px; overflow-y:auto;">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2">
            <strong>
                <?php echo $msg['sender'] === 'admin' ? 'Admin' : ($msg['store_name'] ?? 'Store'); ?>:
            </strong>
            <span><?php echo nl2br(htmlspecialchars($msg['message'])); ?></span>
            <small class="text-muted ms-2"><?php echo format_ts($msg['created_at']); ?></small>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($store_id > 0): ?>
<form method="post" id="chatForm" class="input-group">
    <textarea name="message" class="form-control" rows="2" placeholder="Type message" required></textarea>
    <button class="btn btn-primary" type="submit">Send</button>
    <input type="hidden" name="ajax" value="1">
</form>
<?php endif; ?>
<script>
function refreshMessages(){
    fetch('chat.php?store_id=<?php echo $store_id; ?>&load=1')
        .then(r=>r.json())
        .then(data=>{
            const container=document.getElementById('messages');
            container.innerHTML='';
            data.forEach(m=>{
                const div=document.createElement('div');
                div.className='mb-2';
                const sender=m.sender==='admin'?'Admin':(m.store_name||'Store');
                div.innerHTML='<strong>'+sender+':</strong> '+m.message.replace(/\n/g,'<br>')+' <small class="text-muted ms-2">'+m.created_at+'</small>';
                container.appendChild(div);
            });
            container.scrollTop = container.scrollHeight;
        });
}
setInterval(refreshMessages,5000);
if(document.getElementById('chatForm')){
    document.getElementById('chatForm').addEventListener('submit',function(e){
        e.preventDefault();
        fetch('chat.php?store_id=<?php echo $store_id; ?>', {method:'POST', body:new FormData(this)})
            .then(r=>r.json())
            .then(()=>{this.reset(); refreshMessages();});
    });
}
refreshMessages();
</script>
<?php include __DIR__.'/footer.php'; ?>
