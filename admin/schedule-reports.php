<?php
// Social Health Report - provides at-a-glance insights across stores and networks
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();

$active = 'calendars'; // keeps Calendars highlighted in nav
$pdo = get_pdo();

// --------------------
// Filter Options
// --------------------
$stores = $pdo->query('SELECT id, name, hootsuite_profile_ids FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$networks = $pdo->query('SELECT DISTINCT network FROM hootsuite_profiles ORDER BY network')->fetchAll(PDO::FETCH_COLUMN);

// --------------------
// Parse Filters
// --------------------
$start = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end   = $_GET['end_date']   ?? date('Y-m-d');
$storeFilter    = isset($_GET['store'])   ? array_filter(array_map('intval', (array)$_GET['store'])) : [];
$networkFilter  = isset($_GET['network']) ? array_filter((array)$_GET['network']) : [];

// Build dynamic WHERE clause
$params = [];
$where  = [];
$where[] = '(p.scheduled_send_time BETWEEN ? AND ?)';
$params[] = $start.' 00:00:00';
$params[] = $end.' 23:59:59';

if ($storeFilter) {
    $where[] = 'p.store_id IN ('.implode(',', array_fill(0, count($storeFilter), '?')).')';
    $params = array_merge($params, $storeFilter);
}
if ($networkFilter) {
    $where[] = 'pr.network IN ('.implode(',', array_fill(0, count($networkFilter), '?')).')';
    $params = array_merge($params, $networkFilter);
}
$whereSql = $where ? implode(' AND ', $where) : '1=1';

// --------------------
// Fetch Posts within range applying filters
// --------------------
$stmt = $pdo->prepare("SELECT p.store_id, s.name AS store_name, pr.network, p.state, p.scheduled_send_time
                        FROM hootsuite_posts p
                        JOIN stores s ON p.store_id = s.id
                        LEFT JOIN hootsuite_profiles pr ON p.social_profile_id = pr.id
                        WHERE $whereSql");
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------
// Prepare lookups for coverage heatmap
// --------------------
$profileMap = [];
foreach ($pdo->query('SELECT id, network FROM hootsuite_profiles') as $row) {
    $profileMap[$row['id']] = strtolower($row['network']);
}

$storeNetworks = [];
foreach ($stores as $s) {
    $ids = array_filter(array_map('trim', explode(',', $s['hootsuite_profile_ids'] ?? '')));
    $storeNetworks[$s['id']] = [];
    foreach ($ids as $pid) {
        if (isset($profileMap[$pid])) {
            $storeNetworks[$s['id']][] = $profileMap[$pid];
        }
    }
}

// --------------------
// Metrics Calculations
// --------------------
$totalActiveStores = count($stores);
$storesPosting = [];
$postsByStatus = [];
$postsByNetwork = [];
$coverage = [];
$postsByDayNetwork = [];
$postsByHour = array_fill(0, 24, 0);
$postsByDay = [];

foreach ($posts as $p) {
    $state = strtoupper($p['state'] ?? '');
    $network = strtolower($p['network'] ?? 'unknown');
    $storeId = (int)$p['store_id'];
    $storeName = $p['store_name'];
    $day = substr($p['scheduled_send_time'], 0, 10);
    $hour = (int)date('H', strtotime($p['scheduled_send_time']));

    // Posts by status
    $postsByStatus[$state] = ($postsByStatus[$state] ?? 0) + 1;

    // Posts by network
    $postsByNetwork[$network] = ($postsByNetwork[$network] ?? 0) + 1;

    // Posts by hour
    $postsByHour[$hour]++;

    // Posts by day
    $postsByDay[$day] = ($postsByDay[$day] ?? 0) + 1;

    // Coverage
    if (!isset($coverage[$storeId][$network])) {
        $coverage[$storeId][$network] = ['published'=>0,'scheduled'=>0,'failed'=>0];
    }
    if ($state === 'PUBLISHED') {
        $coverage[$storeId][$network]['published']++;
        $storesPosting[$storeId] = true;
    } elseif ($state === 'FAILED') {
        $coverage[$storeId][$network]['failed']++;
    } else {
        $coverage[$storeId][$network]['scheduled']++;
    }

    // Posts by day & network
    if (!isset($postsByDayNetwork[$day][$network])) {
        $postsByDayNetwork[$day][$network] = 0;
    }
    $postsByDayNetwork[$day][$network]++;
}

$totalPosts = count($posts);
$storesPostingCount = count($storesPosting);
$storesNoPosts = $totalActiveStores - $storesPostingCount;

// Calculate success rate
$successRate = $totalPosts > 0 ? round((($postsByStatus['PUBLISHED'] ?? 0) / $totalPosts) * 100, 1) : 0;

// Alerts: stores with no posts in last 7/14 days and no scheduled posts next 7 days
$lastPublished = [];
$res = $pdo->query("SELECT store_id, MAX(scheduled_send_time) AS last_pub FROM hootsuite_posts WHERE state='PUBLISHED' GROUP BY store_id");
foreach ($res as $row) {
    $lastPublished[$row['store_id']] = $row['last_pub'];
}

$threshold7 = (new DateTime())->modify('-7 days');
$threshold14 = (new DateTime())->modify('-14 days');
$noPost7 = [];
$noPost14 = [];
foreach ($stores as $s) {
    $last = isset($lastPublished[$s['id']]) ? new DateTime($lastPublished[$s['id']]) : null;
    if (!$last || $last < $threshold7) {
        $noPost7[] = $s['name'];
    }
    if (!$last || $last < $threshold14) {
        $noPost14[] = $s['name'];
    }
}

$scheduledNext7 = [];
$stmt = $pdo->prepare("SELECT store_id, COUNT(*) AS c FROM hootsuite_posts WHERE state='SCHEDULED' AND scheduled_send_time BETWEEN ? AND ? GROUP BY store_id");
$stmt->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+7 days'))]);
foreach ($stmt as $row) {
    $scheduledNext7[$row['store_id']] = $row['c'];
}
$noScheduledNext7 = [];
foreach ($stores as $s) {
    if (empty($scheduledNext7[$s['id']])) {
        $noScheduledNext7[] = $s['name'];
    }
}

// Network colors
$networkColors = [
    'facebook' => '#1877f2',
    'instagram' => '#E4405F',
    'x' => '#000000',
    'twitter' => '#1DA1F2',
    'linkedin' => '#0A66C2',
    'youtube' => '#FF0000',
    'tiktok' => '#000000',
    'pinterest' => '#E60023',
    'snapchat' => '#FFFC00',
    'unknown' => '#6c757d'
];
include __DIR__.'/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.bootstrap5.min.css">

<style>
    /* Dashboard Variables */
    :root {
        --dashboard-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        --hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #3b82f6;
    }

    /* Page Container */
    .dashboard-container {
        padding: 2rem;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: calc(100vh - 75px);
    }

    /* Page Header */
    .dashboard-header {
        background: var(--dashboard-bg);
        color: white;
        padding: 2.5rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
    }

    .dashboard-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 20s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    .dashboard-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .dashboard-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 0.5rem 0 0 0;
        position: relative;
        z-index: 1;
    }

    .date-range-badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        padding: 0.5rem 1rem;
        border-radius: 30px;
        margin-top: 1rem;
        font-size: 0.9rem;
        backdrop-filter: blur(10px);
    }

    /* Filter Card */
    .filter-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .filter-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .filter-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .filter-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
    }

    /* Form Controls */
    .form-control-modern, .form-select-modern {
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: var(--transition);
        font-size: 0.95rem;
        background: white;
    }

    .form-control-modern:focus, .form-select-modern:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .date-input {
        max-width: 140px;
    }

    .form-label-modern {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Buttons */
    .btn-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-gradient-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        color: white;
    }

    .btn-outline-modern {
        background: white;
        color: #6c757d;
        border: 2px solid #e0e0e0;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-outline-modern:hover {
        border-color: #667eea;
        color: #667eea;
        transform: translateY(-2px);
        background: #f8f9ff;
    }

    /* KPI Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .kpi-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
        cursor: pointer;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .kpi-card:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: var(--hover-shadow);
    }

    .kpi-card-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        position: relative;
        z-index: 1;
    }

    .kpi-card.primary .kpi-card-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .kpi-card.success .kpi-card-icon {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .kpi-card.warning .kpi-card-icon {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    .kpi-card.danger .kpi-card-icon {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .kpi-card.info .kpi-card-icon {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .kpi-card.secondary .kpi-card-icon {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }

    .kpi-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .kpi-label {
        font-size: 0.875rem;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .kpi-trend {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
    }

    .kpi-trend.up {
        background: #d1fae5;
        color: #065f46;
    }

    .kpi-trend.down {
        background: #fee2e2;
        color: #991b1b;
    }

    .kpi-background {
        position: absolute;
        right: -20px;
        bottom: -20px;
        width: 100px;
        height: 100px;
        opacity: 0.1;
        border-radius: 50%;
    }

    .kpi-card.primary .kpi-background { background: #667eea; }
    .kpi-card.success .kpi-background { background: #10b981; }
    .kpi-card.warning .kpi-background { background: #f59e0b; }
    .kpi-card.danger .kpi-background { background: #ef4444; }
    .kpi-card.info .kpi-background { background: #3b82f6; }
    .kpi-card.secondary .kpi-background { background: #8b5cf6; }

    /* Network Badges */
    .network-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 2rem;
    }

    .network-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        background: white;
        border-radius: 30px;
        font-weight: 600;
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: var(--transition);
        border: 2px solid transparent;
    }

    .network-badge:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .network-badge-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.875rem;
    }

    .network-badge-count {
        color: #2c3e50;
    }

    /* Charts Section */
    .charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .chart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .chart-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-title i {
        color: #667eea;
    }

    /* Coverage Heatmap */
    .heatmap-container {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
        overflow: auto;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .heatmap-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .heatmap-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .heatmap-title i {
        color: #667eea;
    }

    .heatmap-legend {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .heatmap-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 2px;
    }

    .heatmap-table th {
        background: #f8f9fa;
        padding: 0.75rem;
        text-align: center;
        font-weight: 600;
        font-size: 0.875rem;
        color: #495057;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .heatmap-table th:first-child {
        text-align: left;
        background: linear-gradient(90deg, #f8f9fa 0%, #f8f9fa 95%, transparent 100%);
        position: sticky;
        left: 0;
        z-index: 11;
    }

    .heatmap-table td {
        padding: 0;
        text-align: center;
        position: relative;
    }

    .heatmap-table td:first-child {
        padding: 0.75rem;
        font-weight: 600;
        font-size: 0.875rem;
        color: #495057;
        background: white;
        position: sticky;
        left: 0;
        z-index: 9;
        border-right: 2px solid #f0f0f0;
    }

    .heatmap-cell {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        transition: var(--transition);
        cursor: pointer;
        position: relative;
    }

    .heatmap-cell:hover {
        transform: scale(1.1);
        z-index: 5;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .heatmap-cell.excellent {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .heatmap-cell.good {
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        color: white;
    }

    .heatmap-cell.scheduled {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: white;
    }

    .heatmap-cell.poor {
        background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
        color: white;
    }

    .heatmap-cell.no-profile {
        background: #e5e7eb;
        color: #9ca3af;
    }

    .heatmap-tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1a1a1a;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-size: 0.75rem;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s;
        z-index: 100;
        margin-bottom: 0.5rem;
    }

    .heatmap-cell:hover .heatmap-tooltip {
        opacity: 1;
    }

    /* Alerts Section */
    .alerts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .alert-card {
        background: white;
        border-radius: 20px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .alert-header {
        padding: 1.25rem;
        font-size: 1.1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: white;
    }

    .alert-header.critical {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .alert-header.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .alert-header.info {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .alert-header i {
        font-size: 1.25rem;
    }

    .alert-body {
        padding: 1.25rem;
        max-height: 300px;
        overflow-y: auto;
    }

    .alert-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 12px;
        margin-bottom: 0.75rem;
        transition: var(--transition);
    }

    .alert-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }

    .alert-item:last-child {
        margin-bottom: 0;
    }

    .alert-item-icon {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        flex-shrink: 0;
    }

    .alert-item.critical .alert-item-icon {
        background: #fee2e2;
        color: #dc2626;
    }

    .alert-item.warning .alert-item-icon {
        background: #fef3c7;
        color: #d97706;
    }

    .alert-item.info .alert-item-icon {
        background: #dbeafe;
        color: #2563eb;
    }

    .alert-item-text {
        flex: 1;
        font-weight: 500;
        color: #2c3e50;
    }

    .alert-empty {
        text-align: center;
        padding: 2rem;
        color: #9ca3af;
    }

    .alert-empty i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    /* Summary Table */
    .summary-table-container {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        overflow: auto;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .summary-table {
        width: 100%;
        border-collapse: collapse;
    }

    .summary-table thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .summary-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .summary-table tbody tr {
        border-bottom: 1px solid #f0f0f0;
        transition: var(--transition);
    }

    .summary-table tbody tr:hover {
        background: #f8f9fa;
        transform: translateX(5px);
    }

    .summary-table tbody tr:last-child {
        border-bottom: none;
    }

    .summary-table td {
        padding: 1rem;
        font-size: 0.95rem;
    }

    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 0.5rem;
    }

    .status-indicator.published { background: #10b981; }
    .status-indicator.scheduled { background: #f59e0b; }
    .status-indicator.failed { background: #ef4444; }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }

        .alerts-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 1rem;
        }

        .dashboard-header {
            padding: 1.5rem;
        }

        .dashboard-title {
            font-size: 1.75rem;
        }

        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .kpi-value {
            font-size: 1.75rem;
        }

        .network-badges {
            gap: 0.5rem;
        }

        .network-badge {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .heatmap-cell {
            width: 45px;
            height: 45px;
            font-size: 0.75rem;
        }

        .filter-card {
            padding: 1rem;
        }
    }

    /* Loading Animation */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s;
    }

    .loading-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid #f3f4f6;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Scrollbar Styles */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #5a67d8, #6b46c1);
    }
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<div class="dashboard-container">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">
            <i class="bi bi-graph-up"></i> Social Health Report
        </h1>
        <p class="dashboard-subtitle">
            Comprehensive analytics and insights for your social media performance
        </p>
        <div class="date-range-badge">
            <i class="bi bi-calendar-range"></i>
            <?php echo date('M d', strtotime($start)); ?> - <?php echo date('M d, Y', strtotime($end)); ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <div class="filter-header">
            <div class="filter-icon">
                <i class="bi bi-funnel"></i>
            </div>
            <h3 class="filter-title">Filters</h3>
        </div>
        <form method="get" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label-modern">Start Date</label>
                    <input type="date" name="start_date" value="<?=htmlspecialchars($start)?>" class="form-control-modern date-input">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">End Date</label>
                    <input type="date" name="end_date" value="<?=htmlspecialchars($end)?>" class="form-control-modern date-input">
                </div>
                <div class="col-md-4">
                    <label class="form-label-modern">Stores</label>
                    <select name="store[]" class="form-select-modern" multiple style="height: 43px; min-width:250px;">
                        <?php foreach ($stores as $s): ?>
                            <option value="<?=$s['id']?>" <?php if(in_array($s['id'],$storeFilter)) echo 'selected';?>><?=htmlspecialchars($s['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Networks</label>
                    <select name="network[]" class="form-select-modern" multiple style="height: 43px;">
                        <?php foreach ($networks as $n): ?>
                            <option value="<?=$n?>" <?php if(in_array($n,$networkFilter)) echo 'selected';?>><?=htmlspecialchars(ucfirst($n))?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-gradient-primary">
                            <i class="bi bi-search"></i> Apply
                        </button>
                        <a href="schedule-reports.php" class="btn-outline-modern">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card primary">
            <div class="kpi-card-icon">
                <i class="bi bi-shop"></i>
            </div>
            <div class="kpi-value" data-count="<?=$totalActiveStores?>">0</div>
            <div class="kpi-label">Total Active Stores</div>
            <div class="kpi-background"></div>
        </div>

        <div class="kpi-card success">
            <div class="kpi-card-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="kpi-value" data-count="<?=$storesPostingCount?>">0</div>
            <div class="kpi-label">Stores Posting</div>
            <?php if ($totalActiveStores > 0): ?>
                <div class="kpi-trend <?= $storesPostingCount > ($totalActiveStores/2) ? 'up' : 'down' ?>">
                    <?= round(($storesPostingCount/$totalActiveStores)*100) ?>%
                </div>
            <?php endif; ?>
            <div class="kpi-background"></div>
        </div>

        <div class="kpi-card warning">
            <div class="kpi-card-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="kpi-value" data-count="<?=$storesNoPosts?>">0</div>
            <div class="kpi-label">Stores Not Posting</div>
            <div class="kpi-background"></div>
        </div>

        <div class="kpi-card info">
            <div class="kpi-card-icon">
                <i class="bi bi-grid-3x3-gap"></i>
            </div>
            <div class="kpi-value" data-count="<?=$totalPosts?>">0</div>
            <div class="kpi-label">Total Posts</div>
            <div class="kpi-background"></div>
        </div>

        <div class="kpi-card secondary">
            <div class="kpi-card-icon">
                <i class="bi bi-send-check"></i>
            </div>
            <div class="kpi-value" data-count="<?=($postsByStatus['PUBLISHED'] ?? 0)?>">0</div>
            <div class="kpi-label">Published</div>
            <div class="kpi-trend up"><?=$successRate?>%</div>
            <div class="kpi-background"></div>
        </div>

        <div class="kpi-card danger">
            <div class="kpi-card-icon">
                <i class="bi bi-clock"></i>
            </div>
            <div class="kpi-value" data-count="<?=($postsByStatus['SCHEDULED'] ?? 0)?>">0</div>
            <div class="kpi-label">Scheduled</div>
            <div class="kpi-background"></div>
        </div>
    </div>

    <!-- Network Badges -->
    <div class="network-badges">
        <?php foreach ($postsByNetwork as $n => $c):
            $color = $networkColors[$n] ?? '#6c757d';
            ?>
            <div class="network-badge">
                <div class="network-badge-icon" style="background: <?=$color?>">
                    <?php
                    $icon = match($n) {
                        'facebook' => 'bi-facebook',
                        'instagram' => 'bi-instagram',
                        'x', 'twitter' => 'bi-twitter-x',
                        'linkedin' => 'bi-linkedin',
                        'youtube' => 'bi-youtube',
                        'tiktok' => 'bi-tiktok',
                        'pinterest' => 'bi-pinterest',
                        default => 'bi-share'
                    };
                    ?>
                    <i class="bi <?=$icon?>"></i>
                </div>
                <span class="network-badge-count"><?=htmlspecialchars(ucfirst($n))?>: <?=$c?></span>
            </div>
        <?php endforeach; ?>
    </div>



    <!-- Coverage Heatmap -->
    <div class="heatmap-container">
        <div class="heatmap-header">
            <div class="heatmap-title">
                <i class="bi bi-grid-3x3"></i>
                Coverage Heatmap
            </div>
            <div class="heatmap-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);"></div>
                    <span>Excellent</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);"></div>
                    <span>Good</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);"></div>
                    <span>Scheduled</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);"></div>
                    <span>Needs Attention</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #e5e7eb;"></div>
                    <span>No Profile</span>
                </div>
            </div>
        </div>
        <table class="heatmap-table">
            <thead>
            <tr>
                <th>Store</th>
                <?php foreach ($networks as $n): ?>
                    <th><?=htmlspecialchars(ucfirst($n))?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stores as $s): ?>
                <tr>
                    <td><?=htmlspecialchars($s['name'])?></td>
                    <?php foreach ($networks as $n):
                        $cell = $coverage[$s['id']][$n] ?? ['published'=>0,'scheduled'=>0,'failed'=>0];
                        $hasProfile = in_array($n, $storeNetworks[$s['id']] ?? []);
                        $total = $cell['published'] + $cell['scheduled'] + $cell['failed'];

                        if (!$hasProfile) {
                            $class = 'no-profile';
                            $display = '-';
                        } elseif ($cell['published'] >= 5) {
                            $class = 'excellent';
                            $display = $cell['published'];
                        } elseif ($cell['published'] > 0) {
                            $class = 'good';
                            $display = $cell['published'];
                        } elseif ($cell['scheduled'] > 0) {
                            $class = 'scheduled';
                            $display = $cell['scheduled'];
                        } else {
                            $class = 'poor';
                            $display = '0';
                        }
                        ?>
                        <td>
                            <div class="heatmap-cell <?=$class?>">
                                <?=$display?>
                                <div class="heatmap-tooltip">
                                    Published: <?=$cell['published']?><br>
                                    Scheduled: <?=$cell['scheduled']?><br>
                                    Failed: <?=$cell['failed']?>
                                </div>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Alerts Section -->
    <div class="alerts-grid">
        <!-- Critical Alerts -->
        <div class="alert-card">
            <div class="alert-header critical">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Critical - No Posts in 7 Days
            </div>
            <div class="alert-body">
                <?php if (empty($noPost7)): ?>
                    <div class="alert-empty">
                        <i class="bi bi-check-circle"></i>
                        <p>All stores are active!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($noPost7 as $name): ?>
                        <div class="alert-item critical">
                            <div class="alert-item-icon">
                                <i class="bi bi-shop"></i>
                            </div>
                            <div class="alert-item-text"><?=htmlspecialchars($name)?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Warning Alerts -->
        <div class="alert-card">
            <div class="alert-header warning">
                <i class="bi bi-exclamation-circle-fill"></i>
                Warning - No Posts in 14 Days
            </div>
            <div class="alert-body">
                <?php if (empty($noPost14)): ?>
                    <div class="alert-empty">
                        <i class="bi bi-check-circle"></i>
                        <p>All stores posted recently!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($noPost14 as $name): ?>
                        <div class="alert-item warning">
                            <div class="alert-item-icon">
                                <i class="bi bi-shop"></i>
                            </div>
                            <div class="alert-item-text"><?=htmlspecialchars($name)?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Alerts -->
        <div class="alert-card">
            <div class="alert-header info">
                <i class="bi bi-info-circle-fill"></i>
                Info - No Scheduled Posts (Next 7 Days)
            </div>
            <div class="alert-body">
                <?php if (empty($noScheduledNext7)): ?>
                    <div class="alert-empty">
                        <i class="bi bi-check-circle"></i>
                        <p>All stores have scheduled content!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($noScheduledNext7 as $name): ?>
                        <div class="alert-item info">
                            <div class="alert-item-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <div class="alert-item-text"><?=htmlspecialchars($name)?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Summary Table -->
    <div class="summary-table-container">
        <div class="chart-header">
            <div class="chart-title">
                <i class="bi bi-table"></i>
                Detailed Store Summary
            </div>
        </div>
        <table class="summary-table">
            <thead>
            <tr>
                <th>Store</th>
                <th>Published</th>
                <th>Scheduled</th>
                <th>Failed</th>
                <th>Total</th>
                <th>Success Rate</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stores as $s):
                $published = $scheduled = $failed = 0;
                foreach ($coverage[$s['id']] ?? [] as $n => $vals) {
                    $published += $vals['published'];
                    $scheduled += $vals['scheduled'];
                    $failed += $vals['failed'];
                }
                $total = $published + $scheduled + $failed;
                $successRate = $total > 0 ? round(($published / $total) * 100, 1) : 0;

                $statusClass = 'published';
                $statusText = 'Active';
                if ($published == 0 && $scheduled == 0) {
                    $statusClass = 'failed';
                    $statusText = 'Inactive';
                } elseif ($published == 0) {
                    $statusClass = 'scheduled';
                    $statusText = 'Pending';
                }
                ?>
                <tr>
                    <td><strong><?=htmlspecialchars($s['name'])?></strong></td>
                    <td><?=$published?></td>
                    <td><?=$scheduled?></td>
                    <td><?=$failed?></td>
                    <td><strong><?=$total?></strong></td>
                    <td>
                        <div class="progress" style="height: 20px; background: #f3f4f6;">
                            <div class="progress-bar" role="progressbar"
                                 style="width: <?=$successRate?>%; background: linear-gradient(135deg, #10b981 0%, #059669 100%);"
                                 aria-valuenow="<?=$successRate?>" aria-valuemin="0" aria-valuemax="100">
                                <?=$successRate?>%
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="status-indicator <?=$statusClass?>"></span>
                        <?=$statusText?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show loading overlay on form submit
            document.getElementById('filterForm').addEventListener('submit', function() {
                document.getElementById('loadingOverlay').classList.add('active');
            });

            // Animate KPI counters
            const counters = document.querySelectorAll('.kpi-value');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const animation = new countUp.CountUp(counter, target, {
                    duration: 2,
                    useEasing: true,
                    useGrouping: true,
                    separator: ',',
                    decimal: '.',
                });
                if (!animation.error) {
                    animation.start();
                }
            });

      

            // Initialize Tom Select for better multi-select
            if (typeof TomSelect !== 'undefined') {
                new TomSelect('select[name="store[]"]', {
                    plugins: ['remove_button'],
                    persist: false
                });
                new TomSelect('select[name="network[]"]', {
                    plugins: ['remove_button'],
                    persist: false
                });
            }

            // Add hover effects to cards
            document.querySelectorAll('.kpi-card, .alert-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Smooth scroll to sections
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Auto-hide loading overlay
            setTimeout(function() {
                document.getElementById('loadingOverlay').classList.remove('active');
            }, 500);
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>
