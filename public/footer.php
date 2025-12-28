</div>

<?php
// What's New Modal - check if we should show it
require_once __DIR__ . '/../lib/version.php';
$currentVersion = getCurrentVersion();
// Check cookie first, then session, then default to 0.0.0
$lastSeenVersion = $_COOKIE['whats_new_seen'] ?? $_SESSION['last_seen_version'] ?? '0.0.0';
$showWhatsNew = isset($_SESSION['store_user_id']) && shouldShowWhatsNew($lastSeenVersion);
// Get ALL versions since last seen (not just the current one)
$changelogs = $showWhatsNew ? getChangelogSinceVersion($lastSeenVersion) : [];
?>

<?php if(isset($_SESSION['store_id'])): ?>
<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav d-md-none">
    <a href="index.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
        <i class="bi bi-house-door"></i>
        <span>Home</span>
    </a>
    <a href="history.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
        <i class="bi bi-clock-history"></i>
        <span>History</span>
    </a>
    <a href="chat.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">
        <i class="bi bi-chat-dots"></i>
        <span>Chat</span>
        <?php if(isset($unread_count) && $unread_count > 0): ?>
            <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                <?php echo $unread_count; ?>
            </span>
        <?php endif; ?>
    </a>
    <a href="articles.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'articles.php' ? 'active' : ''; ?>">
        <i class="bi bi-pencil-square"></i>
        <span>Articles</span>
    </a>
    <a href="calendar.php" class="nav-item-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
        <i class="bi bi-calendar3"></i>
        <span>More</span>
    </a>
</nav>
<?php endif; ?>

<footer class="text-center py-2">
    <small class="footer-version" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#changelogModal">
        Version: <?php echo $version; ?>
    </small>
</footer>

<!-- Full Changelog Modal -->
<div class="modal fade" id="changelogModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.25rem 1.5rem;">
                <h5 class="modal-title" style="font-weight: 600;">
                    <i class="bi bi-journal-text me-2"></i>Changelog
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
                <?php echo generateFullChangelogHTML(); ?>
            </div>
        </div>
    </div>
</div>

<!-- What's New Modal -->
<div class="modal fade" id="whatsNewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden; max-height: 80vh;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.5rem;">
                <h5 class="modal-title" style="font-weight: 600;">
                    <i class="bi bi-stars me-2"></i>What's New
                    <?php if (count($changelogs) > 1): ?>
                        <span class="badge bg-white text-primary ms-2" style="font-size: 0.7rem;"><?php echo count($changelogs); ?> updates</span>
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; overflow-y: auto;">
                <?php if (!empty($changelogs)): ?>
                    <?php echo generateMultiVersionWhatsNewHTML($changelogs); ?>
                <?php else: ?>
                    <p>Thanks for updating! Check the changelog for details.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0" style="padding: 1rem 1.5rem 1.5rem;">
                <button type="button" class="btn w-100" data-bs-dismiss="modal" id="whatsNewDismiss" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; padding: 12px 24px; font-weight: 600;">
                    <i class="bi bi-check-circle me-1"></i>Got it!
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.whats-new-section h6 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
}
.whats-new-list {
    list-style: none;
    padding-left: 1.5rem;
}
.whats-new-list li {
    position: relative;
    padding: 0.25rem 0;
    font-size: 0.9rem;
    color: #555;
}
.whats-new-list li::before {
    content: "•";
    position: absolute;
    left: -1rem;
    color: #667eea;
}
/* Changelog styles */
.changelog-version {
    border-bottom: 1px solid #eee;
    padding-bottom: 1rem;
}
.changelog-version:last-child {
    border-bottom: none;
}
.changelog-section h6 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
}
.changelog-list {
    list-style: none;
    padding-left: 1.5rem;
    margin-bottom: 0;
}
.changelog-list li {
    position: relative;
    padding: 0.25rem 0;
    font-size: 0.9rem;
    color: #555;
}
.changelog-list li::before {
    content: "•";
    position: absolute;
    left: -1rem;
    color: #667eea;
}
.footer-version:hover {
    color: #667eea !important;
    text-decoration: underline;
}
</style>

<!-- Bootstrap JS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

<!-- Mobile Enhancements Script -->
<script>
// iOS viewport height fix
function setViewportHeight() {
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
}
setViewportHeight();
window.addEventListener('resize', setViewportHeight);
window.addEventListener('orientationchange', setViewportHeight);

// Disable pull-to-refresh on iOS
document.addEventListener('touchmove', function(e) {
    if (e.touches.length > 1) {
        e.preventDefault();
    }
}, { passive: false });

// Smooth scrolling for internal links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// Touch feedback for buttons
document.querySelectorAll('.btn, .nav-item-mobile, .action-card').forEach(element => {
    element.addEventListener('touchstart', function() {
        this.style.opacity = '0.7';
    });
    element.addEventListener('touchend', function() {
        this.style.opacity = '1';
    });
});

// Detect iOS and add class for specific styling
if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
    document.body.classList.add('ios-device');
}

// Handle safe areas for iOS
if (CSS.supports('padding-top: env(safe-area-inset-top)')) {
    document.body.classList.add('supports-safe-areas');
}

// Show What's New modal if needed
<?php if ($showWhatsNew && !empty($changelogs)): ?>
setTimeout(function() {
    // Skip if already dismissed this session (prevents reappearing on page reloads)
    if (sessionStorage.getItem('whats_new_dismissed') === '<?php echo $currentVersion; ?>') {
        return;
    }

    const whatsNewModal = new bootstrap.Modal(document.getElementById('whatsNewModal'));
    whatsNewModal.show();

    // Only set cookie when "Got it!" button is clicked
    document.getElementById('whatsNewDismiss').addEventListener('click', function() {
        sessionStorage.setItem('whats_new_dismissed', '<?php echo $currentVersion; ?>');
        document.cookie = 'whats_new_seen=<?php echo $currentVersion; ?>; path=/; max-age=31536000; SameSite=Lax';
        fetch('../lib/mark_version_seen.php', { method: 'POST' }).catch(function() {});
    });
}, 1000);
<?php endif; ?>
</script>

<!-- Notification polling is handled in header.php -->
<?php if(isset($extra_js)) echo $extra_js; ?>
</body>
</html>