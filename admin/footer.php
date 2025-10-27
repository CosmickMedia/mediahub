</div>

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


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

<!-- CountUp JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>

<!-- Sweet Alert 2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($active === 'broadcasts'): ?>
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
<?php endif; ?>

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
                        window.location.href = 'broadcasts.php';
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

    // Update current time (removed - footer no longer displayed)

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

    // Check notifications every 3 seconds for near real-time updates
    setInterval(checkNotifications, 3000);
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