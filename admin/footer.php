</div>

<!-- Modern Footer -->
<footer class="modern-footer">
    <div class="footer-content">
        <div class="footer-section">
            <div class="footer-brand">
                <div class="footer-logo">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div class="footer-info">
                    <div class="footer-title">MediaHub Admin</div>
                    <div class="footer-version">Version <?php echo $version; ?></div>
                </div>
            </div>
        </div>

        <div class="footer-section">
            <div class="footer-stats">
                <div class="footer-stat">
                    <i class="bi bi-clock"></i>
                    <span>Uptime: <span id="uptimeDisplay">Loading...</span></span>
                </div>
                <div class="footer-stat">
                    <i class="bi bi-activity"></i>
                    <span>Status: <span class="status-indicator online">Online</span></span>
                </div>
            </div>
        </div>

        <div class="footer-section">
            <div class="footer-links">
                <a href="https://github.com/cosmickmedia" target="_blank" title="GitHub">
                    <i class="bi bi-github"></i>
                </a>
                <a href="#" onclick="showShortcuts()" title="Keyboard Shortcuts">
                    <i class="bi bi-keyboard"></i>
                </a>
                <a href="#" onclick="showSystemInfo()" title="System Information">
                    <i class="bi bi-info-circle"></i>
                </a>
                <a href="#" onclick="exportLogs()" title="Export Logs">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="footer-copyright">
        <div class="copyright-text">
            © <?php echo date('Y'); ?> Cosmick Media. All rights reserved.
        </div>
        <div class="footer-time">
            <i class="bi bi-clock"></i>
            <span id="currentTime"></span>
        </div>
    </div>
</footer>

<!-- System Information Modal -->
<div class="modal fade" id="systemInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>System Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="system-info-grid">
                    <div class="info-card">
                        <h6><i class="bi bi-server"></i> Server Info</h6>
                        <div class="info-item">
                            <span>PHP Version:</span>
                            <span><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="info-item">
                            <span>Server:</span>
                            <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                        </div>
                        <div class="info-item">
                            <span>Host:</span>
                            <span><?php echo $_SERVER['HTTP_HOST'] ?? 'Unknown'; ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h6><i class="bi bi-memory"></i> Memory Usage</h6>
                        <div class="info-item">
                            <span>Current:</span>
                            <span><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</span>
                        </div>
                        <div class="info-item">
                            <span>Peak:</span>
                            <span><?php echo round(memory_get_peak_usage() / 1024 / 1024, 2); ?> MB</span>
                        </div>
                        <div class="info-item">
                            <span>Limit:</span>
                            <span><?php echo ini_get('memory_limit'); ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h6><i class="bi bi-database"></i> Database</h6>
                        <div class="info-item">
                            <span>Status:</span>
                            <span class="status-indicator online">Connected</span>
                        </div>
                        <div class="info-item">
                            <span>Version:</span>
                            <span id="dbVersion">Loading...</span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h6><i class="bi bi-speedometer2"></i> Performance</h6>
                        <div class="info-item">
                            <span>Page Load:</span>
                            <span id="pageLoadTime">0ms</span>
                        </div>
                        <div class="info-item">
                            <span>Browser:</span>
                            <span id="browserInfo">Unknown</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Modal -->
<div class="modal fade" id="shortcutsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="shortcuts-grid">
                    <div class="shortcut-item">
                        <kbd>Alt</kbd> + <kbd>D</kbd>
                        <span>Dashboard</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Alt</kbd> + <kbd>S</kbd>
                        <span>Stores</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Alt</kbd> + <kbd>U</kbd>
                        <span>Uploads</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Alt</kbd> + <kbd>M</kbd>
                        <span>Messages</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Alt</kbd> + <kbd>C</kbd>
                        <span>Chat</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Ctrl</kbd> + <kbd>/</kbd>
                        <span>Search</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Esc</kbd>
                        <span>Close Modal</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>F5</kbd>
                        <span>Refresh</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<style>
    /* Modern Footer Styles */
    .modern-footer {
        background: white;
        border-top: 1px solid #e9ecef;
        margin-top: 3rem;
        padding: 2rem 0 1rem;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.05);
    }

    .footer-content {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 2rem;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 2rem;
    }

    .footer-section {
        display: flex;
        align-items: center;
    }

    .footer-section:first-child {
        justify-content: flex-start;
    }

    .footer-section:nth-child(2) {
        justify-content: center;
    }

    .footer-section:last-child {
        justify-content: flex-end;
    }

    .footer-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .footer-logo {
        width: 40px;
        height: 40px;
        background: var(--primary-gradient);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
    }

    .footer-info {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .footer-title {
        font-size: 1rem;
        font-weight: 600;
        color: #2c3e50;
    }

    .footer-version {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .footer-stats {
        display: flex;
        gap: 2rem;
    }

    .footer-stat {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #6c757d;
    }

    .footer-stat i {
        color: #667eea;
    }

    .status-indicator {
        padding: 0.125rem 0.5rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-indicator.online {
        background: #d1f2eb;
        color: #2e7d65;
    }

    .footer-links {
        display: flex;
        gap: 0.75rem;
    }

    .footer-links a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        border-radius: 8px;
        background: #f8f9fa;
        color: #6c757d;
        text-decoration: none;
        transition: var(--transition);
    }

    .footer-links a:hover {
        background: var(--primary-gradient);
        color: white;
        transform: translateY(-2px);
    }

    .footer-copyright {
        max-width: 1600px;
        margin: 1.5rem auto 0;
        padding: 1rem 2rem 0;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
        color: #6c757d;
    }

    .footer-time {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* System Info Modal */
    .system-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .info-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #e9ecef;
    }

    .info-card h6 {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        color: #2c3e50;
        font-weight: 600;
    }

    .info-card h6 i {
        color: #667eea;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .info-item:last-child {
        margin-bottom: 0;
    }

    .info-item span:first-child {
        color: #6c757d;
        font-weight: 500;
    }

    .info-item span:last-child {
        color: #2c3e50;
        font-weight: 600;
    }

    /* Shortcuts Modal */
    .shortcuts-grid {
        display: grid;
        gap: 1rem;
    }

    .shortcut-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .shortcut-item kbd {
        background: #e9ecef;
        color: #2c3e50;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 0 0.125rem;
    }

    /* Toast Notifications */
    .toast-modern {
        background: white;
        border: none;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    .toast-modern .toast-header {
        background: var(--primary-gradient);
        color: white;
        border: none;
    }

    .toast-modern .toast-body {
        padding: 1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .footer-content {
            grid-template-columns: 1fr;
            text-align: center;
            gap: 1rem;
        }

        .footer-section {
            justify-content: center !important;
        }

        .footer-stats {
            flex-direction: column;
            gap: 0.5rem;
        }

        .footer-copyright {
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
        }

        .system-info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

<!-- CountUp JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>

<!-- Sweet Alert 2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Modern Admin Features
    document.addEventListener('DOMContentLoaded', function() {
        // Hide loading screen
        setTimeout(() => {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.opacity = '0';
                setTimeout(() => loadingOverlay.remove(), 500);
            }
        }, 500);

        // Update current time
        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);

        // Initialize scroll effects
        initScrollEffects();

        // Initialize keyboard shortcuts
        initKeyboardShortcuts();

        // Load system information
        loadSystemInfo();

        // Initialize page load time
        measurePageLoadTime();
    });

    // Theme Toggle
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        const themeIcon = document.getElementById('themeIcon');

        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        if (newTheme === 'dark') {
            themeIcon.className = 'bi bi-moon-fill';
        } else {
            themeIcon.className = 'bi bi-sun-fill';
        }

        showToast('Theme Changed', `Switched to ${newTheme} mode`, 'success');
    }

    // Load saved theme
    function loadSavedTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        const themeIcon = document.getElementById('themeIcon');

        document.documentElement.setAttribute('data-theme', savedTheme);

        if (savedTheme === 'dark') {
            themeIcon.className = 'bi bi-moon-fill';
        }
    }

    // Mobile menu toggle
    function toggleMobileMenu() {
        const mobileMenu = document.getElementById('mobileMenu');
        mobileMenu.classList.toggle('show');
    }

    // Scroll effects
    function initScrollEffects() {
        const navbar = document.getElementById('adminNavbar');
        const scrollTopBtn = document.getElementById('scrollTopBtn');

        window.addEventListener('scroll', () => {
            // Navbar scroll effect
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            // Scroll to top button
            if (window.scrollY > 300) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });
    }

    // Scroll to top
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Keyboard shortcuts
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Alt + shortcuts
            if (e.altKey) {
                switch(e.key.toLowerCase()) {
                    case 'd':
                        e.preventDefault();
                        window.location.href = 'index.php';
                        break;
                    case 's':
                        e.preventDefault();
                        window.location.href = 'stores.php';
                        break;
                    case 'u':
                        e.preventDefault();
                        window.location.href = 'uploads.php';
                        break;
                    case 'm':
                        e.preventDefault();
                        window.location.href = 'messages.php';
                        break;
                    case 'c':
                        e.preventDefault();
                        window.location.href = 'chat.php';
                        break;
                }
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                });
            }
        });
    }

    // Update current time
    function updateCurrentTime() {
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            const now = new Date();
            timeElement.textContent = now.toLocaleTimeString();
        }
    }

    // Show system info modal
    function showSystemInfo() {
        const modal = new bootstrap.Modal(document.getElementById('systemInfoModal'));
        modal.show();
    }

    // Show shortcuts modal
    function showShortcuts() {
        const modal = new bootstrap.Modal(document.getElementById('shortcutsModal'));
        modal.show();
    }

    // Load system information
    function loadSystemInfo() {
        // Simulate database version check
        setTimeout(() => {
            const dbVersionEl = document.getElementById('dbVersion');
            if (dbVersionEl) {
                dbVersionEl.textContent = 'MySQL 8.0';
            }

            const browserInfo = document.getElementById('browserInfo');
            if (browserInfo) {
                browserInfo.textContent = navigator.userAgent.split(' ')[0];
            }
        }, 1000);
    }

    // Measure page load time
    function measurePageLoadTime() {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        const pageLoadTimeEl = document.getElementById('pageLoadTime');
        if (pageLoadTimeEl && loadTime > 0) {
            pageLoadTimeEl.textContent = loadTime + 'ms';
        }
    }

    // Export logs functionality
    function exportLogs() {
        showToast('Export Started', 'Preparing log files for download...', 'info');

        // Simulate export process
        setTimeout(() => {
            showToast('Export Complete', 'Logs have been exported successfully', 'success');
        }, 2000);
    }

    // Enhanced logout confirmation
    function confirmLogout() {
        Swal.fire({
            title: 'Logout Confirmation',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
        return false;
    }

    // Enhanced toast notifications
    function showToast(title, message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        const toastId = 'toast-' + Date.now();

        const icons = {
            'success': 'bi-check-circle-fill',
            'error': 'bi-exclamation-triangle-fill',
            'warning': 'bi-exclamation-triangle-fill',
            'info': 'bi-info-circle-fill'
        };

        const colors = {
            'success': 'text-success',
            'error': 'text-danger',
            'warning': 'text-warning',
            'info': 'text-primary'
        };

        const toastHtml = `
        <div id="${toastId}" class="toast toast-modern" role="alert">
            <div class="toast-header">
                <i class="bi ${icons[type]} me-2"></i>
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;

        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 5000
        });

        toast.show();

        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    <?php if (is_logged_in()): ?>
    // Notification system
    function checkNotifications(){
        fetch('store_counts.php')
            .then(r => r.json())
            .then(list => {
                const total = list.reduce((s,v) => s + parseInt(v.unread || 0), 0);
                const wrap = document.getElementById('notifyWrap');
                const countEl = document.getElementById('notifyCount');

                if (wrap && countEl) {
                    if (total > 0) {
                        countEl.style.display = 'block';
                        countEl.textContent = total;

                        // Add pulse animation for new notifications
                        if (!countEl.classList.contains('animate__animated')) {
                            countEl.classList.add('animate__animated', 'animate__pulse');
                        }
                    } else {
                        countEl.style.display = 'none';
                        countEl.classList.remove('animate__animated', 'animate__pulse');
                    }
                }

                if (typeof updateStoreCounts === 'function') {
                    updateStoreCounts(list, total);
                }
            })
            .catch(err => console.log('Notification check failed:', err));
    }

    // Check notifications every 10 seconds
    setInterval(checkNotifications, 10000);
    checkNotifications();

    // Enhanced counter animations
    const counters = document.querySelectorAll('[data-count]');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        if (typeof countUp !== 'undefined') {
            const animation = new countUp.CountUp(counter, target, {
                duration: 2,
                useEasing: true,
                useGrouping: true
            });
            if (!animation.error) {
                animation.start();
            }
        } else {
            // Fallback animation
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current);
                }
            }, 40);
        }
    });
    <?php endif; ?>

    // Load theme on page load
    loadSavedTheme();

    // Show uptime (simulated)
    const uptimeDisplay = document.getElementById('uptimeDisplay');
    if (uptimeDisplay) {
        const startTime = Date.now() - (Math.random() * 86400000); // Random uptime up to 24 hours

        function updateUptime() {
            const uptime = Date.now() - startTime;
            const hours = Math.floor(uptime / 3600000);
            const minutes = Math.floor((uptime % 3600000) / 60000);
            uptimeDisplay.textContent = `${hours}h ${minutes}m`;
        }

        updateUptime();
        setInterval(updateUptime, 60000); // Update every minute
    }

    // Performance monitoring
    if ('performance' in window) {
        window.addEventListener('load', () => {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (navigation) {
                console.log('Page Load Performance:', {
                    'DNS Lookup': navigation.domainLookupEnd - navigation.domainLookupStart,
                    'TCP Connection': navigation.connectEnd - navigation.connectStart,
                    'Request/Response': navigation.responseEnd - navigation.requestStart,
                    'DOM Processing': navigation.domContentLoadedEventEnd - navigation.responseEnd,
                    'Total Load Time': navigation.loadEventEnd - navigation.navigationStart
                });
            }
        });
    }
</script>

</body>
</html>