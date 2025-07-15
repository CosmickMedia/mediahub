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
        $stmt = $pdo->prepare('SELECT m.id, m.sender, m.message, m.created_at, m.parent_id, m.like_by_store, m.like_by_admin, m.love_by_store, m.love_by_admin, p.message AS parent_message, u.filename, u.drive_id FROM store_messages m LEFT JOIN store_messages p ON m.parent_id=p.id LEFT JOIN uploads u ON m.upload_id=u.id WHERE m.store_id = ? ORDER BY m.created_at');
        $stmt->execute([$store_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE store_messages SET read_by_admin=1 WHERE store_id=? AND sender='store' AND read_by_admin=0")
            ->execute([$store_id]);
    } else {
        $messages = [];
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
    $stmt = $pdo->prepare('SELECT m.id, m.sender, m.message, m.created_at, m.parent_id, m.read_by_admin, m.read_by_store, m.like_by_store, m.like_by_admin, m.love_by_store, m.love_by_admin, p.message AS parent_message, u.filename, u.drive_id FROM store_messages m LEFT JOIN store_messages p ON m.parent_id=p.id LEFT JOIN uploads u ON m.upload_id=u.id WHERE m.store_id = ? ORDER BY m.created_at');
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
    $messages = [];
    $store_name = 'Select Store';
}

$mentionNames = $pdo->query("SELECT CONCAT(first_name,' ',last_name) AS name FROM users")->fetchAll(PDO::FETCH_COLUMN);
$mentionNames = array_merge($mentionNames, $pdo->query("SELECT CONCAT(first_name,' ',last_name) AS name FROM store_users")->fetchAll(PDO::FETCH_COLUMN));
$mentionNames = array_values(array_filter(array_unique($mentionNames)));

$active = 'chat';
include __DIR__.'/header.php';
?>
<div class="row">
  <div class="col-lg-9 mb-3">
    <h4>Chat - <?php echo htmlspecialchars($store_name); ?></h4>
    <div id="unreadAlert" class="alert alert-warning alert-dismissible fade show" role="alert" style="display:none;">
        You have <span id="totalUnread">0</span> new message(s) from <span id="unreadStores">0</span> stores.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <form method="get" class="mb-3" id="storeSelectForm">
        <label class="form-label">Select Store</label>
        <select name="store_id" class="form-select" id="storeSelect" onchange="document.getElementById('storeSelectForm').submit();">
            <option value="0" disabled<?php if($store_id===0) echo ' selected'; ?>>Please select store</option>
            <?php foreach ($stores as $s): ?>
                <option value="<?php echo $s['id']; ?>"<?php if($store_id===$s['id']) echo ' selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div id="messages" class="mb-4 border rounded p-2">
    <?php if (empty($messages)): ?>
        <p class="text-muted">Select a store to view messages.</p>
    <?php endif; ?>
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2 <?php echo $msg['sender']==='admin'?'mine':'theirs'; ?>">
            <div class="bubble-container"><div class="bubble<?php if($store_id===0 && $msg['store_id']===null) echo ' broadcast'; ?>">
                <?php if(!empty($msg['parent_message'])): ?>
                    <div class="reply-preview"><?php echo htmlspecialchars(substr($msg['parent_message'],0,50)); ?></div>
                <?php endif; ?>
                <strong><?php
                    if($store_id===0 && $msg['store_id']===null && $msg['sender']==='admin'){
                        echo 'BROADCAST ANNOUNCEMENT';
                    } else {
                        echo $msg['sender']==='admin'?htmlspecialchars($admin_name):htmlspecialchars($store_contact ?: ($msg['store_name'] ?? 'Store'));
                    }
                ?>:</strong>
                <?php if(!empty($msg['filename'])): ?>
                    <a href="https://drive.google.com/file/d/<?php echo $msg['drive_id']; ?>/view" target="_blank"><?php echo htmlspecialchars($msg['filename']); ?></a>
                <?php endif; ?>
                <span><?php echo nl2br($msg['message']); ?></span><br>
                <small class="text-muted">
                    <?php echo format_ts($msg['created_at']); ?>
                    <?php if($msg['sender']==='admin' && ($msg['read_by_store']??0)): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php elseif($msg['sender']==='store' && ($msg['read_by_admin']??0)): ?>
                        <i class="bi bi-check2-all text-primary"></i>
                    <?php endif; ?>
                </small>
                <?php if($msg['sender']!=='admin'): ?>
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
                <?php endif; ?>
                <span class="reply-link" data-id="<?php echo $msg['id']; ?>">Reply</span>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($store_id > 0): ?>
<form method="post" id="chatForm" class="input-group align-items-end chat-form" enctype="multipart/form-data">
    <div id="replyTo" class="reply-preview" style="display:none;"></div>
    <textarea name="message" class="form-control" rows="2" placeholder="Type message"></textarea>
    <button type="button" id="fileBtn" class="btn btn-light border"><i class="bi bi-paperclip"></i></button>
    <input type="file" id="fileInput" name="file" class="d-none">
    <button type="button" id="emojiBtn" class="btn btn-light border"><i class="bi bi-emoji-smile"></i></button>
    <button class="btn btn-send" type="submit">Send</button>
    <input type="hidden" name="ajax" value="1">
    <input type="hidden" name="parent_id" id="parent_id" value="">
    <div id="emojiPicker"></div>
</form>
<div id="mentionBox" class="mention-box"></div>
<script src="../assets/js/emoji-picker.js"></script>
<script>
const textarea=document.querySelector('#chatForm textarea');
initEmojiPicker(textarea, document.getElementById('emojiBtn'), document.getElementById('emojiPicker'));
const mentionBox=document.getElementById('mentionBox');
textarea.addEventListener('keyup',e=>{
    const pre=textarea.value.substring(0,textarea.selectionStart);
    const m=pre.match(/@(\w*)$/);
    if(m){
        const term=m[1].toLowerCase();
        const matches=MENTION_NAMES.filter(n=>n.toLowerCase().startsWith(term));
        if(matches.length){
            mentionBox.innerHTML=matches.map(n=>`<div>${n}</div>`).join('');
            const rect=textarea.getBoundingClientRect();
            mentionBox.style.left=rect.left+'px';
            mentionBox.style.top=(rect.bottom)+'px';
            mentionBox.style.display='block';
        }else{mentionBox.style.display='none';}
    }else{mentionBox.style.display='none';}
});
mentionBox.addEventListener('click',e=>{
    if(e.target.tagName==='DIV'){
        const pre=textarea.value.substring(0,textarea.selectionStart).replace(/@\w*$/, '@'+e.target.textContent);
        textarea.value=pre+textarea.value.substring(textarea.selectionStart);
        textarea.focus();
        mentionBox.style.display='none';
    }
});
</script>
<?php endif; ?>
  </div>
  <div class="col-lg-3">
    <div class="card h-100">
      <div class="card-header"><h5 class="mb-0">Stores</h5></div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush" id="storeList">
          <?php foreach($stores as $s): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <a href="#" class="store-link flex-grow-1" data-id="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></a>
            <span class="badge bg-secondary ms-2" style="display:none;" id="store-count-<?php echo $s['id']; ?>">0</span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<script>
const ADMIN_NAME = <?php echo json_encode($admin_name); ?>;
const STORE_CONTACT = <?php echo json_encode($store_contact ?? ''); ?>;
const STORE_ID = <?php echo json_encode($store_id); ?>;
const MENTION_NAMES = <?php echo json_encode($mentionNames); ?>;
function updateStoreCounts(data,total){
    data.forEach(s=>{
        const badge=document.getElementById('store-count-'+s.id);
        if(badge){
            badge.textContent=s.unread;
            badge.style.display=s.unread>0?'inline-block':'none';
        }
    });
    const alertEl=document.getElementById('unreadAlert');
    if(alertEl){
        if(total>0){
            document.getElementById('totalUnread').textContent=total;
            document.getElementById('unreadStores').textContent=data.filter(s=>s.unread>0).length;
            alertEl.style.display='block';
        }else{
            alertEl.style.display='none';
        }
    }
}
function refreshMessages(){
    fetch('chat.php?store_id=<?php echo $store_id; ?>&load=1')
        .then(r=>r.json())
        .then(data=>{
            const container=document.getElementById('messages');
            container.innerHTML='';
            data.forEach(m=>{
                const wrap=document.createElement('div');
                wrap.className='mb-2 '+(m.sender==='admin'?'mine':'theirs');
                const holder=document.createElement('div');
                holder.className='bubble-container';
                const div=document.createElement('div');
                div.className='bubble'+((STORE_ID===0 && m.store_id===null && m.sender==='admin')?' broadcast':'');
                const sender=(STORE_ID===0 && m.store_id===null && m.sender==='admin')?'BROADCAST ANNOUNCEMENT':(m.sender==='admin'?ADMIN_NAME:(STORE_CONTACT||m.store_name||'Store'));
                let readIcon='';
                if(m.sender==='admin' && m.read_by_store==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                if(m.sender==='store' && m.read_by_admin==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                let html='';
                if(m.parent_message){
                    html+='<div class="reply-preview">'+m.parent_message.substring(0,50)+'</div>';
                }
                html+='<strong>'+sender+':</strong> ';
                if(m.filename){
                    html+='<a href="https://drive.google.com/file/d/'+m.drive_id+'/view" target="_blank">'+m.filename+'</a> ';
                }
                html+=m.message.replace(/\n/g,'<br>');
                html+='<br><small class="text-muted">'+m.created_at+readIcon+'</small>';
                if(m.sender!=='admin'){
                    html+=' <span class="ms-1 reactions">'+
                        (m.like_by_admin||m.like_by_store?
                            '<i class="bi bi-hand-thumbs-up-fill text-like" data-id="'+m.id+'" data-type="like"></i>' :
                            '<i class="bi bi-hand-thumbs-up" data-id="'+m.id+'" data-type="like"></i>')+' '+
                        (m.love_by_admin||m.love_by_store?
                            '<i class="bi bi-heart-fill text-love" data-id="'+m.id+'" data-type="love"></i>' :
                            '<i class="bi bi-heart" data-id="'+m.id+'" data-type="love"></i>')+
                        '</span>';
                }
                html+='<span class="reply-link" data-id="'+m.id+'">Reply</span>';
                div.innerHTML=html;
                holder.appendChild(div);
                wrap.appendChild(holder);
                container.appendChild(wrap);
            });
            container.scrollTop = container.scrollHeight;
            initReactions();
            initReplyLinks();
        });
}
setInterval(refreshMessages,5000);
if(document.getElementById('chatForm')){
    const formEl=document.getElementById('chatForm');
    formEl.addEventListener('submit',function(e){
        e.preventDefault();
        const fd=new FormData(this);
        if(document.getElementById('fileInput').files.length){
            fd.append('store_id',STORE_ID);
            fetch('../chat_upload.php',{method:'POST',body:fd})
                .then(async r=>{try{return await r.json();}catch(e){return {error:'Upload failed'};}})
                .then(res=>{if(res.success){formEl.reset(); document.getElementById('replyTo').style.display='none'; refreshMessages(); if(typeof checkNotifications==='function'){checkNotifications();}}else{alert(res.error||'Upload failed');}});
        } else {
            fetch('chat.php?store_id=<?php echo $store_id; ?>',{method:'POST',body:fd})
                .then(async r=>{try{return await r.json();}catch(e){return {error:'Send failed'};}})
                .then(res=>{if(res.success){formEl.reset(); document.getElementById('replyTo').style.display='none'; refreshMessages(); if(typeof checkNotifications==='function'){checkNotifications();}}else{alert(res.error||'Send failed');}});
        }
    });
    document.querySelector('#chatForm textarea').addEventListener('keydown',function(e){
        if(e.key==='Enter' && !e.shiftKey){
            e.preventDefault();
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }
    });
    document.getElementById('fileBtn').addEventListener('click',()=>document.getElementById('fileInput').click());
    document.getElementById('fileInput').addEventListener('change',()=>{
        if(document.getElementById('fileInput').files.length){
            formEl.dispatchEvent(new Event('submit'));
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
if(typeof checkNotifications==='function'){checkNotifications();}
function initReactions(){
    document.querySelectorAll('.reactions i').forEach(icon=>{
        icon.addEventListener('click',()=>{
            const form=new FormData();
            form.append('id',icon.dataset.id);
            form.append('type',icon.dataset.type);
            fetch('../react.php',{method:'POST',body:form})
                .then(r=>r.json())
                .then(()=>{refreshMessages(); if(typeof checkNotifications==='function'){checkNotifications();}});
        });
    });
}
initReactions();
function initReplyLinks(){
    document.querySelectorAll('.reply-link').forEach(l=>{
        l.addEventListener('click',()=>{
            document.getElementById('parent_id').value=l.dataset.id;
            document.getElementById('replyTo').textContent='Replying to: '+l.parentElement.querySelector('span').textContent;
            document.getElementById('replyTo').style.display='block';
        });
    });
}
initReplyLinks();
document.querySelectorAll('.store-link').forEach(l=>{
    l.addEventListener('click',e=>{
        e.preventDefault();
        document.getElementById('storeSelect').value=l.dataset.id;
        document.getElementById('storeSelectForm').submit();
    });
});
</script>
<?php include __DIR__.'/footer.php'; ?>
