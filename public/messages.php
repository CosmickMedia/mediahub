<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/auth.php';
ensure_session();

if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = get_pdo();
$store_id = $_SESSION['store_id'];

if (isset($_GET['load'])) {
    $stmt = $pdo->prepare("SELECT id, sender, message, created_at, read_by_store, read_by_admin, like_by_store, like_by_admin, love_by_store, love_by_admin FROM store_messages WHERE store_id = ? ORDER BY created_at");
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

$stmt = $pdo->prepare("SELECT id, sender, message, created_at, read_by_store, read_by_admin, like_by_store, like_by_admin, love_by_store, love_by_admin FROM store_messages WHERE store_id = ? ORDER BY created_at");
$stmt->execute([$store_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pdo->prepare("UPDATE store_messages SET read_by_store=1 WHERE store_id=? AND sender='admin' AND read_by_store=0")
    ->execute([$store_id]);

$adminRow = $pdo->query('SELECT first_name, last_name FROM users ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$admin_name = trim(($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? ''));
$your_name = trim(($_SESSION['store_first_name'] ?? '') . ' ' . ($_SESSION['store_last_name'] ?? ''));

include __DIR__.'/header.php';
?>
<div id="messages" class="mb-4 border rounded p-2">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2 <?php echo $msg['sender'] === 'admin' ? 'theirs' : 'mine'; ?>">
            <div class="bubble">
                <strong><?php echo $msg['sender'] === 'admin' ? htmlspecialchars($admin_name) : htmlspecialchars($your_name); ?>:</strong>
                <span><?php echo nl2br($msg['message']); ?></span>
                <small class="text-muted ms-2">
                    <?php echo format_ts($msg['created_at']); ?>
                    <?php if($msg['sender']==='admin' && $msg['read_by_store']): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php elseif($msg['sender']==='store' && $msg['read_by_admin']): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php endif; ?>
                </small>
                <span class="ms-1 reactions">
                    <?php if($msg['like_by_admin']||$msg['like_by_store']): ?>
                        <i class="bi bi-hand-thumbs-up-fill text-like" data-id="<?php echo $msg['id']; ?>" data-type="like"></i>
                    <?php else: ?>
                        <i class="bi bi-hand-thumbs-up" data-id="<?php echo $msg['id']; ?>" data-type="like"></i>
                    <?php endif; ?>
                    <?php if($msg['love_by_admin']||$msg['love_by_store']): ?>
                        <i class="bi bi-heart-fill text-love" data-id="<?php echo $msg['id']; ?>" data-type="love"></i>
                    <?php else: ?>
                        <i class="bi bi-heart" data-id="<?php echo $msg['id']; ?>" data-type="love"></i>
                    <?php endif; ?>
                </span>
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
<emoji-picker id="emojiPicker"></emoji-picker>
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
                    ` <small class="text-muted ms-2">${m.created_at}${readIcon}</small>`+
                    ' <span class="ms-1 reactions">'+
                    (m.like_by_admin||m.like_by_store?
                        `<i class="bi bi-hand-thumbs-up-fill text-like" data-id="${m.id}" data-type="like"></i>`:
                        `<i class="bi bi-hand-thumbs-up" data-id="${m.id}" data-type="like"></i>`)+' '+
                    (m.love_by_admin||m.love_by_store?
                        `<i class="bi bi-heart-fill text-love" data-id="${m.id}" data-type="love"></i>`:
                        `<i class="bi bi-heart" data-id="${m.id}" data-type="love"></i>`)+
                    '</span>';
                wrap.appendChild(div);
                container.appendChild(wrap);
            });
            container.scrollTop = container.scrollHeight;
            initReactions();
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
function initReactions(){
    document.querySelectorAll('.reactions i').forEach(icon=>{
        icon.addEventListener('click',()=>{
            const form=new FormData();
            form.append('id',icon.dataset.id);
            form.append('type',icon.dataset.type);
            fetch('../react.php',{method:'POST',body:form})
                .then(r=>r.json())
                .then(()=>{refreshMessages();});
        });
    });
}
refreshMessages();
initReactions();
</script>
<?php include __DIR__.'/footer.php'; ?>
