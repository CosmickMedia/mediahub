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

// Get statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_messages,
        COUNT(CASE WHEN sender = 'admin' THEN 1 END) as admin_messages,
        COUNT(CASE WHEN sender = 'store' THEN 1 END) as your_messages,
        COUNT(CASE WHEN like_by_store = 1 OR like_by_admin = 1 THEN 1 END) as liked_messages,
        COUNT(CASE WHEN love_by_store = 1 OR love_by_admin = 1 THEN 1 END) as loved_messages,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_messages
    FROM store_messages 
    WHERE store_id = ?
");
$stats_stmt->execute([$store_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get store name
$store_stmt = $pdo->prepare('SELECT name FROM stores WHERE id = ?');
$store_stmt->execute([$store_id]);
$store_name = $store_stmt->fetchColumn();

include __DIR__.'/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">


    <div class="messages-container animate__animated animate__fadeIn">
        <!-- Header Section -->
        <div class="messages-header">
            <div>
                <h2 class="messages-title">Chat</h2>
                <p class="messages-subtitle"><?php echo htmlspecialchars($store_name); ?></p>
            </div>
            <a href="index.php" class="btn btn-modern-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Statistics Dashboard -->
        <div class="chat-stats">
            <div class="stat-card total-messages animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['total_messages']; ?>" data-stat="total">0</div>
                <div class="stat-label">Total Messages</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card admin-messages animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['admin_messages']; ?>" data-stat="admin">0</div>
                <div class="stat-label">From Admin</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card your-messages animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['your_messages']; ?>" data-stat="store">0</div>
                <div class="stat-label">Your Messages</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card liked animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-hand-thumbs-up-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['liked_messages']; ?>" data-stat="liked">0</div>
                <div class="stat-label">Liked</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card loved animate__animated animate__fadeInUp delay-40">
                <div class="stat-icon">
                    <i class="bi bi-heart-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['loved_messages']; ?>" data-stat="loved">0</div>
                <div class="stat-label">Loved</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card recent animate__animated animate__fadeInUp delay-50">
                <div class="stat-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stats['recent_messages']; ?>" data-stat="recent">0</div>
                <div class="stat-label">This Week</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Chat Container -->
        <div class="chat-wrapper animate__animated animate__fadeIn delay-60">
            <div class="chat-header">
                <div class="chat-avatar">
                    <i class="bi bi-building"></i>
                </div>
                <div class="chat-info">
                    <h3>Admin Team</h3>
                    <p>Always here to help</p>
                </div>
            </div>

            <div id="messages">
                <?php if (empty($messages)): ?>
                    <div class="empty-messages">
                        <i class="bi bi-chat-square-dots"></i>
                        <h4>No messages yet</h4>
                        <p>Start a conversation below</p>
                    </div>
                <?php else: ?>
                    <?php
                    $currentDate = null;
                    foreach ($messages as $msg):
                        $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                        if ($msgDate !== $currentDate):
                            $currentDate = $msgDate;
                            $displayDate = date('Y-m-d', strtotime($msg['created_at'])) === date('Y-m-d') ? 'Today' :
                                (date('Y-m-d', strtotime($msg['created_at'])) === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' :
                                    date('F j, Y', strtotime($msg['created_at'])));
                            ?>
                            <div class="date-separator">
                                <span><?php echo $displayDate; ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="message-group <?php echo $msg['sender'] === 'admin' ? 'theirs' : 'mine'; ?>">
                            <div class="message-avatar">
                                <?php echo $msg['sender'] === 'admin' ? 'A' : strtoupper(substr($your_name, 0, 1)); ?>
                            </div>
                            <div class="message-content">
                                <div class="message-bubble">
                            <span class="message-sender">
                                <?php echo $msg['sender'] === 'admin' ? htmlspecialchars($admin_name) : htmlspecialchars($your_name); ?>
                            </span>

                                    <?php if(!empty($msg['parent_message'])): ?>
                                        <div class="reply-preview">
                                            <?php echo htmlspecialchars(substr($msg['parent_message'], 0, 50)); ?>...
                                        </div>
                                    <?php endif; ?>

                                    <?php if(!empty($msg['filename'])): ?>
                                        <a href="https://drive.google.com/file/d/<?php echo $msg['drive_id']; ?>/view"
                                           target="_blank"
                                           class="message-file">
                                            <i class="bi bi-paperclip"></i>
                                            <?php echo htmlspecialchars($msg['filename']); ?>
                                        </a>
                                    <?php endif; ?>

                                    <p class="message-text"><?php echo nl2br($msg['message']); ?></p>

                                    <div class="message-footer">
                                <span class="message-time">
                                    <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                </span>

                                        <?php if($msg['sender']==='admin' && $msg['read_by_store'] ||
                                            $msg['sender']==='store' && $msg['read_by_admin']): ?>
                                            <span class="message-status">
                                        <i class="bi bi-check2-all"></i>
                                    </span>
                                        <?php endif; ?>

                                        <?php if($msg['sender']==='admin'): ?>
                                            <div class="message-reactions">
                                                <button class="reaction-button like <?php echo ($msg['like_by_admin']||$msg['like_by_store']) ? 'active' : ''; ?>"
                                                        data-id="<?php echo $msg['id']; ?>"
                                                        data-type="like">
                                                    <i class="bi bi-hand-thumbs-up<?php echo ($msg['like_by_admin']||$msg['like_by_store']) ? '-fill' : ''; ?>"></i>
                                                </button>
                                                <button class="reaction-button love <?php echo ($msg['love_by_admin']||$msg['love_by_store']) ? 'active' : ''; ?>"
                                                        data-id="<?php echo $msg['id']; ?>"
                                                        data-type="love">
                                                    <i class="bi bi-heart<?php echo ($msg['love_by_admin']||$msg['love_by_store']) ? '-fill' : ''; ?>"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>

                                        <span class="reply-link" data-id="<?php echo $msg['id']; ?>">Reply</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="typing-indicator" id="typingIndicator">
                    <span>Admin is typing</span>
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>

            <div class="chat-input-wrapper">
                <div id="replyTo">
                    <span class="close-reply" onclick="cancelReply()">×</span>
                    <span id="replyText"></span>
                </div>

                <form method="post" action="send_message.php" id="msgForm" class="chat-form" enctype="multipart/form-data">
                    <div class="input-group-modern">
                    <textarea name="message"
                              class="chat-textarea"
                              placeholder="Type your message..."
                              rows="1"></textarea>
                    </div>

                    <div class="input-actions">
                        <button type="button" id="fileBtn" class="action-btn" title="Attach file">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <input type="file" id="fileInput" name="file" class="d-none">

                        <button type="button" id="emojiBtn" class="action-btn" title="Add emoji">
                            <i class="bi bi-emoji-smile"></i>
                        </button>

                        <button type="submit" class="action-btn send-btn" title="Send message">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>

                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="parent_id" id="parent_id" value="">

                    <div id="emojiPicker">
                        <div class="emoji-grid"></div>
                    </div>
                </form>

                <div id="mentionBox" class="mention-box"></div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>
    <script src="../assets/js/emoji-picker.js"></script>
    <script src="../assets/js/chat-common.js"></script>
    <script>
        // Initialize emoji picker
        const textarea = document.querySelector('#msgForm textarea');
        initEmojiPicker(textarea, document.getElementById('emojiBtn'), document.getElementById('emojiPicker'));

        // Animate counters
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const animation = new countUp.CountUp(counter, target, {
                    duration: 2,
                    useEasing: true,
                    useGrouping: true
                });
                if (!animation.error) {
                    animation.start();
                }
            });
        });

        // Auto-resize textarea
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });

        // Mention functionality
        const mentionBox = document.getElementById('mentionBox');
        const MENTION_NAMES = <?php echo json_encode($mentionNames); ?>;

        textarea.addEventListener('keyup', e => {
            const pre = textarea.value.substring(0, textarea.selectionStart);
            const m = pre.match(/@(\w*)$/);
            if (m) {
                const term = m[1].toLowerCase();
                const matches = MENTION_NAMES.filter(n => n.toLowerCase().startsWith(term));
                if (matches.length) {
                    mentionBox.innerHTML = matches.map(n => `<div>${n}</div>`).join('');
                    const rect = textarea.getBoundingClientRect();
                    mentionBox.style.left = '0';
                    mentionBox.style.bottom = (rect.height + 10) + 'px';
                    mentionBox.style.display = 'block';
                } else {
                    mentionBox.style.display = 'none';
                }
            } else {
                mentionBox.style.display = 'none';
            }
        });

        mentionBox.addEventListener('click', e => {
            if (e.target.tagName === 'DIV') {
                const pre = textarea.value.substring(0, textarea.selectionStart).replace(/@\w*$/, '@' + e.target.textContent + ' ');
                textarea.value = pre + textarea.value.substring(textarea.selectionStart);
                textarea.focus();
                mentionBox.style.display = 'none';
            }
        });

        // Constants
        const ADMIN_NAME = <?php echo json_encode($admin_name); ?>;
        const YOUR_NAME = <?php echo json_encode($your_name); ?>;

        // Refresh messages
        function refreshMessages() {
            fetch('chat.php?load=1')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('messages');
                    if (data.length === 0) {
                        container.innerHTML = `
                    <div class="empty-messages">
                        <i class="bi bi-chat-square-dots"></i>
                        <h4>No messages yet</h4>
                        <p>Start a conversation below</p>
                    </div>
                `;
                        return;
                    }

                    container.innerHTML = '';
                    let currentDate = null;

                    data.forEach(m => {
                        const msgDate = new Date(m.created_at).toDateString();
                        const today = new Date().toDateString();
                        const yesterday = new Date(Date.now() - 86400000).toDateString();

                        if (msgDate !== currentDate) {
                            currentDate = msgDate;
                            let displayDate = msgDate === today ? 'Today' :
                                (msgDate === yesterday ? 'Yesterday' :
                                    new Date(m.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));

                            container.innerHTML += `
                        <div class="date-separator">
                            <span>${displayDate}</span>
                        </div>
                    `;
                        }

                        const group = document.createElement('div');
                        group.className = 'message-group ' + (m.sender === 'admin' ? 'theirs' : 'mine');

                        let html = `
                    <div class="message-avatar">
                        ${m.sender === 'admin' ? 'A' : YOUR_NAME.charAt(0).toUpperCase()}
                    </div>
                    <div class="message-content">
                        <div class="message-bubble">
                            <span class="message-sender">
                                ${m.sender === 'admin' ? ADMIN_NAME : YOUR_NAME}
                            </span>
                `;

                        if (m.parent_message) {
                            html += `<div class="reply-preview">${m.parent_message.substring(0, 50)}...</div>`;
                        }

                        if (m.filename) {
                            html += `
                        <a href="https://drive.google.com/file/d/${m.drive_id}/view"
                           target="_blank"
                           class="message-file">
                            <i class="bi bi-paperclip"></i>
                            ${m.filename}
                        </a>
                    `;
                        }

                        html += `<p class="message-text">${m.message.replace(/\n/g, '<br>')}</p>`;

                        const time = new Date(m.created_at).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });

                        html += `
                    <div class="message-footer">
                        <span class="message-time">${time}</span>
                `;

                        if ((m.sender === 'admin' && m.read_by_store == 1) ||
                            (m.sender === 'store' && m.read_by_admin == 1)) {
                            html += '<span class="message-status"><i class="bi bi-check2-all"></i></span>';
                        }

                        if (m.sender === 'admin') {
                            html += `
                        <div class="message-reactions">
                            <button class="reaction-button like ${(m.like_by_admin || m.like_by_store) ? 'active' : ''}"
                                    data-id="${m.id}"
                                    data-type="like">
                                <i class="bi bi-hand-thumbs-up${(m.like_by_admin || m.like_by_store) ? '-fill' : ''}"></i>
                            </button>
                            <button class="reaction-button love ${(m.love_by_admin || m.love_by_store) ? 'active' : ''}"
                                    data-id="${m.id}"
                                    data-type="love">
                                <i class="bi bi-heart${(m.love_by_admin || m.love_by_store) ? '-fill' : ''}"></i>
                            </button>
                        </div>
                    `;
                        } else if (m.like_by_admin || m.love_by_admin) {
                            html += `
                        <div class="message-reactions readonly">
                            <span class="reaction-button like ${(m.like_by_admin) ? 'active' : ''}">
                                <i class="bi bi-hand-thumbs-up${m.like_by_admin ? '-fill' : ''}"></i>
                            </span>
                            <span class="reaction-button love ${(m.love_by_admin) ? 'active' : ''}">
                                <i class="bi bi-heart${m.love_by_admin ? '-fill' : ''}"></i>
                            </span>
                        </div>
                    `;
                        }

                        html += `
                            <span class="reply-link" data-id="${m.id}">Reply</span>
                        </div>
                    </div>
                </div>
                `;

                        group.innerHTML = html;
                        container.appendChild(group);
                    });

                    // Add typing indicator
                    container.innerHTML += `
                <div class="typing-indicator" id="typingIndicator">
                    <span>Admin is typing</span>
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            `;

                    container.scrollTop = container.scrollHeight;
                    updateStatsFromMessages(data, {
                        total: '[data-stat="total"]',
                        admin: '[data-stat="admin"]',
                        store: '[data-stat="store"]',
                        liked: '[data-stat="liked"]',
                        loved: '[data-stat="loved"]',
                        recent: '[data-stat="recent"]'
                    });
                    initReactions();
                    initReplyLinks();

                    if (typeof checkNotifications === 'function') {
                        checkNotifications();
                    }
                });
        }

        // Initial load and refresh interval
        refreshMessages();
        setInterval(refreshMessages, 5000);

        // Form submission
        document.getElementById('msgForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('store_id', <?php echo json_encode($store_id); ?>);

            if (document.getElementById('fileInput').files.length) {
                fd.append('ajax', '1');
                fetch('../chat_upload.php', { method: 'POST', body: fd })
                    .then(async r => {
                        try { return await r.json(); }
                        catch(e) { return { error: 'Upload failed' }; }
                    })
                    .then(res => {
                        if (res.success) {
                            this.reset();
                            cancelReply();
                            textarea.style.height = 'auto';
                            refreshMessages();
                            if (typeof checkNotifications === 'function') {
                                checkNotifications();
                            }
                        } else {
                            alert(res.error || 'Upload failed');
                        }
                    });
            } else {
                fetch('send_message.php', { method: 'POST', body: fd })
                    .then(async r => {
                        try { return await r.json(); }
                        catch(e) { return { error: 'Send failed' }; }
                    })
                    .then(res => {
                        if (res.success) {
                            this.reset();
                            cancelReply();
                            textarea.style.height = 'auto';
                            refreshMessages();
                            if (typeof checkNotifications === 'function') {
                                checkNotifications();
                            }
                        } else {
                            alert(res.error || 'Send failed');
                        }
                    });
            }
        });

        // Enter to send (shift+enter for new line)
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('msgForm').dispatchEvent(new Event('submit'));
            }
        });

        // File button
        document.getElementById('fileBtn').addEventListener('click', () => {
            document.getElementById('fileInput').click();
        });

        document.getElementById('fileInput').addEventListener('change', () => {
            if (document.getElementById('fileInput').files.length) {
                document.getElementById('msgForm').dispatchEvent(new Event('submit'));
            }
        });

        // Reactions
        function initReactions() {
            bindReactionButtons(document, () => {
                refreshMessages();
                if (typeof checkNotifications === 'function') {
                    checkNotifications();
                }
            });
        }

        // Reply functionality
        function initReplyLinks() {
            document.querySelectorAll('.reply-link').forEach(link => {
                link.addEventListener('click', () => {
                    const bubble = link.closest('.message-bubble');
                    const text = bubble.querySelector('.message-text').textContent;

                    document.getElementById('parent_id').value = link.dataset.id;
                    document.getElementById('replyText').textContent = 'Replying to: ' + text.substring(0, 50) + '...';
                    document.getElementById('replyTo').style.display = 'block';
                    textarea.focus();
                });
            });
        }

        function cancelReply() {
            document.getElementById('parent_id').value = '';
            document.getElementById('replyTo').style.display = 'none';
        }

        // Initialize
        initReactions();
        initReplyLinks();

        // Check notifications
        if (typeof checkNotifications === 'function') {
            checkNotifications();
        }
    </script>

<?php include __DIR__.'/footer.php'; ?>