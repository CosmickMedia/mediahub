<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/helpers.php';

session_start();

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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .marketing-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
            height: calc(100vh - 70px);
            display: flex;
            flex-direction: column;
        }

        .marketing-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .marketing-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .marketing-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            margin: 0.25rem 0 0 0;
        }

        .btn-modern-primary {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            background: var(--primary-gradient);
            color: white;
            font-weight: 500;
            text-decoration: none;
            border: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        /* Report Container */
        .report-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .report-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .report-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .report-info h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .report-info p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .report-actions {
            margin-left: auto;
            display: flex;
            gap: 0.5rem;
        }

        .action-button {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Iframe Wrapper */
        .iframe-wrapper {
            flex: 1;
            position: relative;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #reportFrame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }

        /* Loading State */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: opacity 0.3s ease;
        }

        .loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e9ecef;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .loading-subtext {
            color: #adb5bd;
            font-size: 0.9rem;
        }

        /* No Report State */
        .no-report-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .no-report-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1.5rem;
        }

        .no-report-state h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-report-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .contact-admin-btn {
            background: var(--warning-gradient);
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .contact-admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(250, 112, 154, 0.3);
            color: white;
        }

        /* Modern Alert */
        .alert-modern {
            background: #fff3cd;
            border: none;
            border-left: 4px solid #ffc107;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 10px rgba(255, 193, 7, 0.1);
        }

        .alert-icon {
            font-size: 1.5rem;
            color: #856404;
            flex-shrink: 0;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            color: #856404;
            margin-bottom: 0.25rem;
        }

        .alert-message {
            color: #856404;
            margin: 0;
            font-size: 0.95rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .marketing-container {
                padding: 1rem;
                height: calc(100vh - 70px);
            }

            .marketing-header {
                text-align: center;
            }

            .marketing-title {
                font-size: 1.5rem;
            }

            .report-actions {
                margin-left: 0;
                margin-top: 1rem;
            }
        }

        @media (max-width: 768px) {
            .marketing-container {
                padding: 1rem;
            }

            .report-header {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }

            .report-info {
                margin-bottom: 1rem;
            }

            .action-button {
                width: 44px;
                height: 44px;
                font-size: 1.1rem;
            }

            .no-report-state {
                padding: 2rem 1rem;
            }

            .no-report-state i {
                font-size: 3rem;
            }

            .no-report-state h3 {
                font-size: 1.25rem;
            }
        }

        /* Error State */
        .error-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .error-state i {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }

        .error-state h3 {
            color: #dc3545;
            margin-bottom: 1rem;
        }

        .retry-btn {
            background: var(--danger-gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .retry-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 87, 108, 0.3);
        }

        /* Fullscreen Mode */
        .fullscreen-btn.active {
            background: rgba(255, 255, 255, 0.3);
        }

        .report-container.fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            border-radius: 0;
        }

        .report-container.fullscreen .report-header {
            border-radius: 0;
        }
    </style>

    <div class="marketing-container animate__animated animate__fadeIn">
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
            <div class="report-container animate__animated animate__fadeIn" style="animation-delay: 0.3s" id="reportContainer">
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
                        <div class="loading-text">Loading Marketing Report</div>
                        <div class="loading-subtext">Please wait while we fetch your analytics...</div>
                    </div>

                    <!-- Error State (hidden by default) -->
                    <div class="error-state" id="errorState" style="display: none;">
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
            <div class="report-container animate__animated animate__fadeIn" style="animation-delay: 0.3s">
                <div class="no-report-state">
                    <i class="bi bi-graph-up"></i>
                    <h3>Marketing Report Not Set Up</h3>
                    <p>Your marketing analytics dashboard hasn't been configured yet. Contact your admin team to set up your personalized marketing report with real-time insights and performance metrics.</p>
                    <a href="messages.php" class="contact-admin-btn">
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

            setTimeout(() => {
                loadingOverlay.classList.add('hidden');
                errorState.style.display = 'none';
            }, 1000); // Give it a moment to actually load content
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