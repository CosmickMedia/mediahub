<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/auth.php';

ensure_session();

// Check if logged in
if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$pdo = get_pdo();

// Get store info
$stmt = $pdo->prepare('SELECT marketing_report_url, name FROM stores WHERE id = ?');
$stmt->execute([$store_id]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);

$url = $store['marketing_report_url'];
$store_name = $store['name'];

include __DIR__.'/header.php';
?>

    <div class="marketing-container">
        <!-- Header Section -->
        <div class="marketing-header">
            <div>
                <h2 class="marketing-title">Marketing Analytics</h2>
                <p class="marketing-subtitle"><?php echo htmlspecialchars($store_name); ?></p>
            </div>
            <a href="index.php" class="btn btn-modern-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($url): ?>
            <!-- Report Container -->
            <div class="report-container" id="reportContainer">
                <div class="report-header">
                    <div class="report-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="report-info">
                        <h3>Marketing Report Dashboard</h3>
                        <p>Real-time analytics and performance metrics</p>
                    </div>
                    <div class="report-actions">
                        <button class="action-button" onclick="refreshReport()" title="Refresh Report">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button class="action-button fullscreen-btn" onclick="toggleFullscreen()" title="Toggle Fullscreen">
                            <i class="bi bi-fullscreen"></i>
                        </button>
                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="action-button" title="Open in New Tab">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                </div>

                <div class="iframe-wrapper">
                    <!-- Loading Overlay -->
                    <div class="loading-overlay" id="loadingOverlay">
                        <div class="loading-spinner"></div>
                    </div>

                    <!-- Error State (hidden by default) -->
                    <div class="error-state d-none" id="errorState">
                        <i class="bi bi-exclamation-triangle"></i>
                        <h3>Failed to Load Report</h3>
                        <p>There was an issue loading your marketing report. Please try again.</p>
                        <button class="retry-btn" onclick="refreshReport()">
                            <i class="bi bi-arrow-clockwise"></i>
                            Try Again
                        </button>
                    </div>

                    <!-- Report Iframe -->
                    <iframe
                            id="reportFrame"
                            src="<?php echo htmlspecialchars($url); ?>"
                            allowfullscreen
                            onload="handleIframeLoad()"
                            onerror="handleIframeError()">
                    </iframe>
                </div>
            </div>

        <?php else: ?>
            <!-- No Report State -->
            <div class="report-container">
                <div class="no-report-state">
                    <i class="bi bi-graph-up"></i>
                    <h3>Marketing Report Not Set Up</h3>
                    <p>Your marketing analytics dashboard hasn't been configured yet. Contact your admin team to set up your personalized marketing report with real-time insights and performance metrics.</p>
                    <a href="chat.php" class="contact-admin-btn">
                        <i class="bi bi-chat-dots"></i>
                        Contact Admin
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let isFullscreen = false;

        // Handle iframe loading
        function handleIframeLoad() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const errorState = document.getElementById('errorState');

            loadingOverlay.classList.add('hidden');
            errorState.style.display = 'none';
        }

        // Handle iframe error
        function handleIframeError() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const errorState = document.getElementById('errorState');

            loadingOverlay.classList.add('hidden');
            errorState.style.display = 'block';
        }

        // Refresh report
        function refreshReport() {
            const iframe = document.getElementById('reportFrame');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const errorState = document.getElementById('errorState');

            // Show loading
            loadingOverlay.classList.remove('hidden');
            errorState.style.display = 'none';

            // Force reload
            iframe.src = iframe.src;
        }

        // Toggle fullscreen
        function toggleFullscreen() {
            const container = document.getElementById('reportContainer');
            const button = document.querySelector('.fullscreen-btn');
            const icon = button.querySelector('i');

            isFullscreen = !isFullscreen;

            if (isFullscreen) {
                container.classList.add('fullscreen');
                button.classList.add('active');
                icon.className = 'bi bi-fullscreen-exit';
                button.title = 'Exit Fullscreen';
            } else {
                container.classList.remove('fullscreen');
                button.classList.remove('active');
                icon.className = 'bi bi-fullscreen';
                button.title = 'Toggle Fullscreen';
            }
        }

        // Escape key to exit fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isFullscreen) {
                toggleFullscreen();
            }
        });

        // Resize iframe on window resize
        function resizeFrame() {
            const iframe = document.getElementById('reportFrame');
            if (iframe && !isFullscreen) {
                // The iframe will auto-resize with CSS, but we can trigger any custom logic here
                console.log('Window resized, iframe auto-adjusting');
            }
        }

        window.addEventListener('load', function() {
            resizeFrame();

            // If iframe doesn't load within 10 seconds, show error
            setTimeout(() => {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (!loadingOverlay.classList.contains('hidden')) {
                    handleIframeError();
                }
            }, 10000);
        });

        window.addEventListener('resize', resizeFrame);

        // Auto-refresh every 5 minutes (optional)
        // setInterval(refreshReport, 300000);
    </script>

<?php include __DIR__.'/footer.php'; ?>