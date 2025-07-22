// Common chat helper functions

function bindReactionButtons(container, refresh) {
    container.querySelectorAll('.reaction-button').forEach(btn => {
        btn.addEventListener('click', () => {
            const fd = new FormData();
            fd.append('id', btn.dataset.id);
            fd.append('type', btn.dataset.type);
            fetch('../react.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(() => {
                    if (typeof refresh === 'function') refresh();
                });
        }, { once: true });
    });
}

function updateStatsFromMessages(messages, mapping) {
    const stats = {
        total: messages.length,
        admin: 0,
        store: 0,
        liked: 0,
        loved: 0,
        recent: 0
    };
    const weekAgo = Date.now() - 7*24*60*60*1000;
    messages.forEach(m => {
        if (m.sender === 'admin') stats.admin++; else stats.store++;
        if (m.like_by_admin || m.like_by_store) stats.liked++;
        if (m.love_by_admin || m.love_by_store) stats.loved++;
        if (new Date(m.created_at).getTime() >= weekAgo) stats.recent++;
    });
    if (!mapping) mapping = {
        total: '[data-stat="total"]',
        admin: '[data-stat="admin"]',
        store: '[data-stat="store"]',
        liked: '[data-stat="liked"]',
        loved: '[data-stat="loved"]',
        recent: '[data-stat="recent"]'
    };
    Object.keys(stats).forEach(k => {
        const el = document.querySelector(mapping[k]);
        if (el) el.textContent = stats[k];
    });
}

window.bindReactionButtons = bindReactionButtons;
window.updateStatsFromMessages = updateStatsFromMessages;

