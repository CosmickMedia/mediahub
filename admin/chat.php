<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();
$admin_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

$store_id = intval($_GET['store_id'] ?? 0);

// handle ajax fetch
if (isset($_GET['load'])) {
    if ($store_id > 0) {
        $stmt = $pdo->prepare('SELECT id, sender, message, created_at, like_by_store, like_by_admin, love_by_store, love_by_admin FROM store_messages WHERE store_id = ? ORDER BY created_at');
        $stmt->execute([$store_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE store_messages SET read_by_admin=1 WHERE store_id=? AND sender='store' AND read_by_admin=0")
            ->execute([$store_id]);
    } else {
        $stmt = $pdo->query('SELECT m.id, m.sender, m.message, m.created_at, m.read_by_admin, m.read_by_store, m.like_by_store, m.like_by_admin, m.love_by_store, m.love_by_admin, s.name AS store_name, u.first_name, u.last_name FROM store_messages m LEFT JOIN stores s ON m.store_id = s.id LEFT JOIN store_users u ON u.store_id = s.id ORDER BY m.created_at');
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pdo->exec("UPDATE store_messages SET read_by_admin=1 WHERE sender='store'");
    }
    foreach ($messages as &$m) {
        $m['created_at'] = format_ts($m['created_at']);
    }
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $store_id > 0) {
    $message = sanitize_message($_POST['message']);
    if ($message !== '') {
        $parent = intval($_POST['parent_id'] ?? 0) ?: null;
        $ins = $pdo->prepare("INSERT INTO store_messages (store_id, sender, message, parent_id, created_at, read_by_admin, read_by_store) VALUES (?, 'admin', ?, ?, NOW(), 1, 0)");
        $ins->execute([$store_id, $message, $parent]);
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
    $stmt = $pdo->prepare('SELECT sender, message, created_at, read_by_admin, read_by_store, like_by_store, like_by_admin, love_by_store, love_by_admin FROM store_messages WHERE store_id = ? ORDER BY created_at');
    $stmt->execute([$store_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $store_stmt = $pdo->prepare("SELECT s.name, u.first_name, u.last_name
        FROM stores s
        LEFT JOIN store_users u ON u.store_id = s.id
        WHERE s.id = ?
        ORDER BY u.id
        LIMIT 1");
    $store_stmt->execute([$store_id]);
    $store = $store_stmt->fetch(PDO::FETCH_ASSOC);
    $store_name = $store['name'] ?? '';
    $store_contact = trim(($store['first_name'] ?? '') . ' ' . ($store['last_name'] ?? ''));
    $pdo->prepare("UPDATE store_messages SET read_by_admin=1 WHERE store_id=? AND sender='store' AND read_by_admin=0")
        ->execute([$store_id]);
} else {
    $stmt = $pdo->query('SELECT m.id, m.sender, m.message, m.created_at, m.read_by_admin, m.read_by_store, m.like_by_store, m.like_by_admin, m.love_by_store, m.love_by_admin, s.name AS store_name, u.first_name, u.last_name FROM store_messages m LEFT JOIN stores s ON m.store_id = s.id LEFT JOIN store_users u ON u.store_id = s.id ORDER BY m.created_at');
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pdo->exec("UPDATE store_messages SET read_by_admin=1 WHERE sender='store'");
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
<style>
    #messages .mine{ text-align:right; }
    #messages .bubble{display:inline-block;padding:6px 12px;border-radius:12px;margin-bottom:4px;max-width:70%;}
    #messages .mine .bubble{background:#d1e7dd;}
    #messages .theirs .bubble{background:#e2e3e5;}
</style>
<div id="messages" class="mb-4" style="max-height:400px; overflow-y:auto;">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2 <?php echo $msg['sender']==='admin'?'mine':'theirs'; ?>">
            <div class="bubble">
                <strong><?php echo $msg['sender']==='admin'?htmlspecialchars($admin_name):htmlspecialchars($store_contact ?: ($msg['store_name'] ?? 'Store')); ?>:</strong>
                <span><?php echo nl2br($msg['message']); ?></span>
                <small class="text-muted ms-2">
                    <?php echo format_ts($msg['created_at']); ?>
                    <?php if($msg['sender']==='admin' && ($msg['read_by_store']??0)): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php elseif($msg['sender']==='store' && ($msg['read_by_admin']??0)): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php endif; ?>
                </small>
                <span class="ms-1 reactions">
                    <i class="bi bi-hand-thumbs-up<?php if($msg['like_by_admin']||$msg['like_by_store']) echo ' text-danger'; ?>" data-id="<?php echo $msg['id']; ?>" data-type="like"></i>
                    <i class="bi bi-heart<?php if($msg['love_by_admin']||$msg['love_by_store']) echo ' text-danger'; ?>" data-id="<?php echo $msg['id']; ?>" data-type="love"></i>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($store_id > 0): ?>
<form method="post" id="chatForm" class="input-group align-items-end">
    <textarea name="message" class="form-control" rows="2" placeholder="Type message" required></textarea>
    <button type="button" id="emojiBtn" class="btn btn-light border"><i class="bi bi-emoji-smile"></i></button>
    <button class="btn btn-primary" type="submit">Send</button>
    <input type="hidden" name="ajax" value="1">
    <input type="hidden" name="parent_id" id="parent_id" value="">
</form>
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1"></script>
<emoji-picker style="display:none; position:absolute; bottom:60px; right:20px;" id="emojiPicker"></emoji-picker>
<?php endif; ?>
<script>
const ADMIN_NAME = <?php echo json_encode($admin_name); ?>;
const STORE_CONTACT = <?php echo json_encode($store_contact ?? ''); ?>;
function refreshMessages(){
    fetch('chat.php?store_id=<?php echo $store_id; ?>&load=1')
        .then(r=>r.json())
        .then(data=>{
            const container=document.getElementById('messages');
            container.innerHTML='';
            data.forEach(m=>{
                const wrap=document.createElement('div');
                wrap.className='mb-2 '+(m.sender==='admin'?'mine':'theirs');
                const div=document.createElement('div');
                div.className='bubble';
                const sender=m.sender==='admin'?ADMIN_NAME:(STORE_CONTACT||m.store_name||'Store');
                let readIcon='';
                if(m.sender==='admin' && m.read_by_store==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                if(m.sender==='store' && m.read_by_admin==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                div.innerHTML='<strong>'+sender+':</strong> '+m.message.replace(/\n/g,'<br>')+
                    ' <small class="text-muted ms-2">'+m.created_at+readIcon+'</small>'+
                    ' <span class="ms-1 reactions">'+
                    '<i class="bi bi-hand-thumbs-up'+((m.like_by_admin||m.like_by_store)?' text-danger':'')+'" data-id="'+m.id+'" data-type="like"></i> '+
                    '<i class="bi bi-heart'+((m.love_by_admin||m.love_by_store)?' text-danger':'')+'" data-id="'+m.id+'" data-type="love"></i></span>';
                wrap.appendChild(div);
                container.appendChild(wrap);
            });
            container.scrollTop = container.scrollHeight;
            initReactions();
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
    document.querySelector('#chatForm textarea').addEventListener('keydown',function(e){
        if(e.key==='Enter' && !e.shiftKey){
            e.preventDefault();
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }
    });
    const picker=document.getElementById('emojiPicker');
    document.getElementById('emojiBtn').addEventListener('click',()=>{
        picker.style.display=picker.style.display==='none'?'block':'none';
    });
    picker.addEventListener('emoji-click',e=>{
        const ta=document.querySelector('#chatForm textarea');
        ta.value+=e.detail.unicode;
        picker.style.display='none';
        ta.focus();
    });
}
refreshMessages();
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
initReactions();
</script>
<?php include __DIR__.'/footer.php'; ?>
