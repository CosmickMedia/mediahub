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
    $stmt = $pdo->prepare("SELECT id, sender, message, created_at, read_by_store, read_by_admin FROM store_messages WHERE store_id = ? ORDER BY created_at");
    $stmt->execute([$store_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($messages as &$m) {
        $m['created_at'] = format_ts($m['created_at']);
    }
    $pdo->prepare("UPDATE store_messages SET read_by_store=1 WHERE store_id=? AND sender='admin' AND read_by_store=0")
        ->execute([$store_id]);
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

$stmt = $pdo->prepare("SELECT sender, message, created_at, read_by_store, read_by_admin FROM store_messages WHERE store_id = ? ORDER BY created_at");
$stmt->execute([$store_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pdo->prepare("UPDATE store_messages SET read_by_store=1 WHERE store_id=? AND sender='admin' AND read_by_store=0")
    ->execute([$store_id]);

$adminRow = $pdo->query('SELECT first_name, last_name FROM users ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$admin_name = trim(($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? ''));
$your_name = trim(($_SESSION['store_first_name'] ?? '') . ' ' . ($_SESSION['store_last_name'] ?? ''));

include __DIR__.'/header.php';
?>
<style>
    #messages .mine { text-align:right; }
    #messages .bubble{display:inline-block;padding:6px 12px;border-radius:12px;margin-bottom:4px;max-width:70%;}
    #messages .mine .bubble{background:#d1e7dd;}
    #messages .theirs .bubble{background:#e2e3e5;}
</style>
<h2>Chat</h2>
<div id="messages" class="mb-4 border rounded p-3" style="max-height:400px;overflow-y:auto;">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2 <?php echo $msg['sender'] === 'admin' ? 'theirs' : 'mine'; ?>">
            <div class="bubble">
                <strong><?php echo $msg['sender'] === 'admin' ? htmlspecialchars($admin_name) : htmlspecialchars($your_name); ?>:</strong>
                <span><?php echo nl2br(htmlspecialchars($msg['message'])); ?></span>
                <small class="text-muted ms-2">
                    <?php echo format_ts($msg['created_at']); ?>
                    <?php if($msg['sender']==='admin' && $msg['read_by_store']): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php elseif($msg['sender']==='store' && $msg['read_by_admin']): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<form method="post" action="send_message.php" id="msgForm" class="input-group align-items-end">
    <textarea name="message" class="form-control" rows="2" placeholder="Type message" required></textarea>
    <button type="button" id="emojiBtn" class="btn btn-light border"><i class="bi bi-emoji-smile"></i></button>
    <button type="submit" class="btn btn-primary">Send</button>
    <input type="hidden" name="ajax" value="1">
    <input type="hidden" name="parent_id" id="parent_id" value="">
</form>
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1"></script>
<emoji-picker style="display:none; position:absolute; bottom:60px; right:20px;" id="emojiPicker"></emoji-picker>
<script>
const ADMIN_NAME = <?php echo json_encode($admin_name); ?>;
const YOUR_NAME = <?php echo json_encode($your_name); ?>;
function refreshMessages() {
    fetch('messages.php?load=1')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('messages');
            container.innerHTML = '';
            data.forEach(m => {
                const wrap=document.createElement('div');
                wrap.className='mb-2 '+(m.sender==='admin'?'theirs':'mine');
                const div=document.createElement('div');
                div.className='bubble';
                let readIcon='';
                if(m.sender==='admin' && m.read_by_store==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                if(m.sender==='store' && m.read_by_admin==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                div.innerHTML=`<strong>${m.sender==='admin'?ADMIN_NAME:YOUR_NAME}:</strong> `+
                    m.message.replace(/\n/g,'<br>')+
                    ` <small class="text-muted ms-2">${m.created_at}${readIcon}</small>`;
                wrap.appendChild(div);
                container.appendChild(wrap);
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
document.querySelector('#msgForm textarea').addEventListener('keydown', function(e){
    if(e.key === 'Enter' && !e.shiftKey){
        e.preventDefault();
        document.getElementById('msgForm').dispatchEvent(new Event('submit'));
    }
});
const picker=document.getElementById('emojiPicker');
document.getElementById('emojiBtn').addEventListener('click', ()=>{
    picker.style.display = picker.style.display==='none' ? 'block' : 'none';
});
picker.addEventListener('emoji-click', e=>{
    const ta=document.querySelector('#msgForm textarea');
    ta.value += e.detail.unicode;
    picker.style.display='none';
    ta.focus();
});
refreshMessages();
</script>
<?php include __DIR__.'/footer.php'; ?>
