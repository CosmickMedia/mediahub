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
    $stmt = $pdo->prepare("SELECT m.id, m.sender, m.message, m.created_at, m.parent_id, m.read_by_store, m.read_by_admin, m.like_by_store, m.like_by_admin, m.love_by_store, m.love_by_admin, p.message AS parent_message, u.filename, u.drive_id FROM store_messages m LEFT JOIN store_messages p ON m.parent_id=p.id LEFT JOIN uploads u ON m.upload_id=u.id WHERE m.store_id = ? ORDER BY m.created_at");
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

$stmt = $pdo->prepare("SELECT m.id, m.sender, m.message, m.created_at, m.parent_id, m.read_by_store, m.read_by_admin, m.like_by_store, m.like_by_admin, m.love_by_store, m.love_by_admin, p.message AS parent_message, u.filename, u.drive_id FROM store_messages m LEFT JOIN store_messages p ON m.parent_id=p.id LEFT JOIN uploads u ON m.upload_id=u.id WHERE m.store_id = ? ORDER BY m.created_at");
$stmt->execute([$store_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pdo->prepare("UPDATE store_messages SET read_by_store=1 WHERE store_id=? AND sender='admin' AND read_by_store=0")
    ->execute([$store_id]);

$adminRow = $pdo->query('SELECT first_name, last_name FROM users ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$admin_name = trim(($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? ''));
$your_name = trim(($_SESSION['store_first_name'] ?? '') . ' ' . ($_SESSION['store_last_name'] ?? ''));

$mentionNames = $pdo->query("SELECT CONCAT(first_name,' ',last_name) AS name FROM users")->fetchAll(PDO::FETCH_COLUMN);
$stmtMent = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM store_users WHERE store_id=?");
$stmtMent->execute([$store_id]);
$mentionNames = array_merge($mentionNames, $stmtMent->fetchAll(PDO::FETCH_COLUMN));
$mentionNames = array_values(array_filter(array_unique($mentionNames)));

include __DIR__.'/header.php';
?>
<div id="messages" class="mb-4 border rounded p-2">
    <?php foreach ($messages as $msg): ?>
        <div class="mb-2 <?php echo $msg['sender'] === 'admin' ? 'theirs' : 'mine'; ?>">
            <div class="bubble-container"><div class="bubble">
                <?php if(!empty($msg['parent_message'])): ?>
                    <div class="reply-preview"><?php echo htmlspecialchars(substr($msg['parent_message'],0,50)); ?></div>
                <?php endif; ?>
                <strong><?php echo $msg['sender'] === 'admin' ? htmlspecialchars($admin_name) : htmlspecialchars($your_name); ?>:</strong>
                <?php if(!empty($msg['filename'])): ?>
                    <a href="https://drive.google.com/file/d/<?php echo $msg['drive_id']; ?>/view" target="_blank"><?php echo htmlspecialchars($msg['filename']); ?></a>
                <?php endif; ?>
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
                <span class="reply-link" data-id="<?php echo $msg['id']; ?>">Reply</span>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>
<form method="post" action="send_message.php" id="msgForm" class="input-group align-items-end chat-form" enctype="multipart/form-data">
    <div id="replyTo" class="reply-preview" style="display:none;"></div>
    <textarea name="message" class="form-control" rows="2" placeholder="Type message"></textarea>
    <button type="button" id="fileBtn" class="btn btn-light border"><i class="bi bi-paperclip"></i></button>
    <input type="file" id="fileInput" name="file" class="d-none">
    <button type="button" id="emojiBtn" class="btn btn-light border"><i class="bi bi-emoji-smile"></i></button>
    <button type="submit" class="btn btn-send">Send</button>
    <input type="hidden" name="ajax" value="1">
    <input type="hidden" name="parent_id" id="parent_id" value="">
</form>
<div id="emojiPicker"></div>
<div id="mentionBox" class="mention-box"></div>
<script src="../assets/js/emoji-picker.js"></script>
<script>
const textarea = document.querySelector('#msgForm textarea');
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
<script>
const ADMIN_NAME = <?php echo json_encode($admin_name); ?>;
const YOUR_NAME = <?php echo json_encode($your_name); ?>;
const MENTION_NAMES = <?php echo json_encode($mentionNames); ?>;
function refreshMessages() {
    fetch('messages.php?load=1')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('messages');
            container.innerHTML = '';
            data.forEach(m => {
                const wrap=document.createElement('div');
                wrap.className='mb-2 '+(m.sender==='admin'?'theirs':'mine');
                const holder=document.createElement('div');
                holder.className='bubble-container';
                const div=document.createElement('div');
                div.className='bubble';
                let readIcon='';
                if(m.sender==='admin' && m.read_by_store==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                if(m.sender==='store' && m.read_by_admin==1) readIcon=' <i class="bi bi-check2-all text-primary"></i>';
                let html='';
                if(m.parent_message){ html+='<div class="reply-preview">'+m.parent_message.substring(0,50)+'</div>'; }
                html+=`<strong>${m.sender==='admin'?ADMIN_NAME:YOUR_NAME}:</strong> `;
                if(m.filename){ html+='<a href="https://drive.google.com/file/d/'+m.drive_id+'/view" target="_blank">'+m.filename+'</a> '; }
                html+=m.message.replace(/\n/g,'<br>');
                html+=` <small class="text-muted ms-2">${m.created_at}${readIcon}</small>`;
                html+=' <span class="ms-1 reactions">'+
                    (m.like_by_admin||m.like_by_store?
                        `<i class="bi bi-hand-thumbs-up-fill text-like" data-id="${m.id}" data-type="like"></i>`:
                        `<i class="bi bi-hand-thumbs-up" data-id="${m.id}" data-type="like"></i>`)+' '+
                    (m.love_by_admin||m.love_by_store?
                        `<i class="bi bi-heart-fill text-love" data-id="${m.id}" data-type="love"></i>`:
                        `<i class="bi bi-heart" data-id="${m.id}" data-type="love"></i>`)+
                    '</span>';
                html+=`<span class="reply-link" data-id="${m.id}">Reply</span>`;
                div.innerHTML=html;
                holder.appendChild(div);
                wrap.appendChild(holder);
                container.appendChild(wrap);
            });
            container.scrollTop = container.scrollHeight;
            initReactions();
            initReplyLinks();
            if(typeof checkNotifications==='function'){checkNotifications();}
        });
}
setInterval(refreshMessages, 5000);
document.getElementById('msgForm').addEventListener('submit', function(e){
    e.preventDefault();
    const fd=new FormData(this);
    if(document.getElementById('fileInput').files.length){
        fd.append('ajax','1');
        fetch('../chat_upload.php',{method:'POST',body:fd})
            .then(async r=>{try{return await r.json();}catch(e){return {error:'Upload failed'};}})
            .then(res=>{ if(res.success){ this.reset(); document.getElementById('replyTo').style.display='none'; refreshMessages(); if(typeof checkNotifications==='function'){checkNotifications();} } else { alert(res.error||'Upload failed'); } });
        }else{
            fetch('send_message.php', {method:'POST', body:fd})
                .then(async r=>{try{return await r.json();}catch(e){return {error:'Send failed'};}})
                .then(res=>{ if(res.success){ this.reset(); document.getElementById('replyTo').style.display='none'; refreshMessages(); if(typeof checkNotifications==='function'){checkNotifications();} } else { alert(res.error||'Send failed'); } });
        }
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
document.getElementById('fileBtn').addEventListener('click',()=>document.getElementById('fileInput').click());
document.getElementById('fileInput').addEventListener('change',()=>{
    if(document.getElementById('fileInput').files.length){
        document.getElementById('msgForm').dispatchEvent(new Event('submit'));
    }
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
                .then(()=>{refreshMessages(); if(typeof checkNotifications==='function'){checkNotifications();}});
        });
    });
}
refreshMessages();
if(typeof checkNotifications==='function'){checkNotifications();}
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
</script>
<?php include __DIR__.'/footer.php'; ?>
