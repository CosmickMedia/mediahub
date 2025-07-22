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

$stmt = $pdo->prepare("SELECT s.name, u.first_name, u.last_name
    FROM stores s
    LEFT JOIN store_users u ON u.store_id = s.id
    WHERE s.id = ?
    ORDER BY u.id
    LIMIT 1");
$stmt->execute([$store_id]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    echo 'Store not found';
    exit;
}
$store_contact = trim(($store['first_name'] ?? '') . ' ' . ($store['last_name'] ?? ''));
$admin_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

if (isset($_GET['load'])) {
    $s = $pdo->prepare('SELECT id, sender, message, created_at, read_by_admin, read_by_store FROM store_messages WHERE store_id = ? ORDER BY created_at');
    $s->execute([$store_id]);
    $msgs = $s->fetchAll(PDO::FETCH_ASSOC);
    $pdo->prepare("UPDATE store_messages SET read_by_admin=1 WHERE store_id=? AND sender='store' AND read_by_admin=0")
        ->execute([$store_id]);
    foreach ($msgs as &$m) {
        $m['created_at'] = format_ts($m['created_at']);
    }
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitize_message($_POST['message']);
    if ($message !== '') {
        $parent = intval($_POST['parent_id'] ?? 0) ?: null;
        $ins = $pdo->prepare("INSERT INTO store_messages (store_id, sender, message, parent_id, created_at, read_by_admin, read_by_store) VALUES (?, 'admin', ?, ?, NOW(), 1, 0)");
        $ins->execute([$store_id, $message, $parent]);
    }
}

$s = $pdo->prepare('SELECT sender, message, created_at, read_by_admin, read_by_store FROM store_messages WHERE store_id = ? ORDER BY created_at');
$s->execute([$store_id]);
$messages = $s->fetchAll(PDO::FETCH_ASSOC);
$pdo->prepare("UPDATE store_messages SET read_by_admin=1 WHERE store_id=? AND sender='store' AND read_by_admin=0")->execute([$store_id]);

$active = 'chat';
include __DIR__.'/header.php';
?>
<h4>Conversation with <?php echo htmlspecialchars($store['name']); ?></h4>
<div id="messages" class="mb-4">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2 <?php echo $msg['sender']==='admin'?'mine':'theirs'; ?>">
            <div class="bubble">
                <strong><?php echo $msg['sender']==='admin'?htmlspecialchars($admin_name):htmlspecialchars($store_contact ?: 'Store'); ?>:</strong>
                <span><?php echo nl2br($msg['message']); ?></span>
                <small class="text-muted ms-2">
                    <?php echo format_ts($msg['created_at']); ?>
                    <?php if($msg['sender']==='admin' && ($msg['read_by_store']??0)): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php elseif($msg['sender']==='store' && ($msg['read_by_admin']??0)): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<form method="post" id="convForm" class="input-group align-items-end mt-3">
    <textarea name="message" class="form-control" rows="2" required></textarea>
    <button type="button" id="emojiBtn" class="btn btn-light border"><i class="bi bi-emoji-smile"></i></button>
    <button class="btn btn-send" type="submit">Send</button>
    <input type="hidden" name="parent_id" id="parent_id" value="">
</form>
<div id="emojiPicker"></div>
<script src="../assets/js/emoji-picker.js"></script>
<script>
initEmojiPicker(document.querySelector('#convForm textarea'), document.getElementById('emojiBtn'), document.getElementById('emojiPicker'));
</script>
<script>
const ADMIN_NAME = <?php echo json_encode($admin_name); ?>;
const STORE_CONTACT = <?php echo json_encode($store_contact); ?>;
function refreshMessages() {
    fetch('conversation.php?store_id=<?php echo $store_id; ?>&load=1')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('messages');
            container.innerHTML = '';
            data.forEach(m => {
                const wrap=document.createElement('div');
                wrap.className='mb-2 '+(m.sender==='admin'?'mine':'theirs');
                const div=document.createElement('div');
                div.className='bubble';
                const sender=m.sender==='admin'?ADMIN_NAME:(STORE_CONTACT||'Store');
                let readIcon='';
                if(m.sender==='admin' && m.read_by_store==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                if(m.sender==='store' && m.read_by_admin==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                div.innerHTML=`<strong>${sender}:</strong> ` +
                    m.message.replace(/\n/g,'<br>') +
                    ` <small class="text-muted ms-2">${m.created_at}${readIcon}</small>`;
                wrap.appendChild(div);
                container.appendChild(wrap);
            });
        });
}
setInterval(refreshMessages, 30000);
document.getElementById('convForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('conversation.php?store_id=<?php echo $store_id; ?>', {method:'POST', body:new FormData(this)})
        .then(()=>{ this.reset(); refreshMessages(); });
});
document.querySelector('#convForm textarea').addEventListener('keydown', function(e){
    if(e.key==='Enter' && !e.shiftKey){
        e.preventDefault();
        document.getElementById('convForm').dispatchEvent(new Event('submit'));
    }
});
const picker=document.getElementById('emojiPicker');
document.getElementById('emojiBtn').addEventListener('click',()=>{
    picker.style.display = picker.style.display==='none' ? 'block':'none';
});
picker.addEventListener('emoji-click',e=>{
    const ta=document.querySelector('#convForm textarea');
    ta.value+=e.detail.unicode;
    picker.style.display='none';
    ta.focus();
});
refreshMessages();
</script>
<?php include __DIR__.'/footer.php'; ?>
