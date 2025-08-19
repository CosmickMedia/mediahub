</div>

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
</script>

<!-- Notification polling is handled in header.php -->
<?php if(isset($extra_js)) echo $extra_js; ?>
</body>
</html>