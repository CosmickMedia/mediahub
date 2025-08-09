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
$admins   = $pdo->query('SELECT id, username FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);

// Map admin id to name for later use
$adminMap = [];
foreach ($admins as $a) {
    $adminMap[$a['id']] = $a['username'];
}
$adminMap[0] = 'Unassigned';

// --------------------
// Parse Filters
// --------------------
$start = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end   = $_GET['end_date']   ?? date('Y-m-d');
$storeFilter    = isset($_GET['store'])   ? array_filter(array_map('intval', (array)$_GET['store'])) : [];
$networkFilter  = isset($_GET['network']) ? array_filter((array)$_GET['network']) : [];
$adminFilter    = isset($_GET['admin'])   ? array_filter(array_map('intval', (array)$_GET['admin'])) : [];

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
if ($adminFilter) {
    $where[] = '(p.created_by_user_id IN ('.implode(',', array_fill(0, count($adminFilter), '?')).'))';
    $params = array_merge($params, $adminFilter);
}
$whereSql = $where ? implode(' AND ', $where) : '1=1';

// --------------------
// Fetch Posts within range applying filters
// --------------------
$stmt = $pdo->prepare("SELECT p.store_id, s.name AS store_name, pr.network, p.state, p.created_by_user_id, p.scheduled_send_time
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
$postsByStorePublished = [];
$postsByAdminPublished = [];
$failuresByStore = [];
$failuresByAdmin = [];
$postsByDayNetwork = [];

foreach ($posts as $p) {
    $state = strtoupper($p['state'] ?? '');
    $network = strtolower($p['network'] ?? 'unknown');
    $storeId = (int)$p['store_id'];
    $storeName = $p['store_name'];
    $adminId = (int)($p['created_by_user_id'] ?? 0);
    $day = substr($p['scheduled_send_time'], 0, 10);

    // Posts by status
    $postsByStatus[$state] = ($postsByStatus[$state] ?? 0) + 1;

    // Posts by network
    $postsByNetwork[$network] = ($postsByNetwork[$network] ?? 0) + 1;

    // Coverage
    if (!isset($coverage[$storeId][$network])) {
        $coverage[$storeId][$network] = ['published'=>0,'scheduled'=>0,'failed'=>0];
    }
    if ($state === 'PUBLISHED') {
        $coverage[$storeId][$network]['published']++;
        $storesPosting[$storeId] = true;
        $postsByStorePublished[$storeName] = ($postsByStorePublished[$storeName] ?? 0) + 1;
        $postsByAdminPublished[$adminId] = ($postsByAdminPublished[$adminId] ?? 0) + 1;
    } elseif ($state === 'FAILED') {
        $coverage[$storeId][$network]['failed']++;
        $failuresByStore[$storeName] = ($failuresByStore[$storeName] ?? 0) + 1;
        $failuresByAdmin[$adminId] = ($failuresByAdmin[$adminId] ?? 0) + 1;
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

// Leaderboard sorting
arsort($postsByStorePublished);
arsort($postsByAdminPublished);
arsort($failuresByStore);
arsort($failuresByAdmin);

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

include __DIR__.'/header.php';
?>

<style>
    /* Simple heatmap color classes */
    .heatmap-table td { width:40px;height:40px; }
    .heat-green { background:#d4edda; }
    .heat-yellow { background:#fff3cd; }
    .heat-red { background:#f8d7da; }
    .heat-gray { background:#e9ecef; }
</style>

<div class="container-fluid p-4">
    <h1 class="mb-4">Social Health Report</h1>

    <!-- Filters Bar -->
    <form method="get" class="row g-3 align-items-end mb-4">
        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" value="<?=htmlspecialchars($start)?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" value="<?=htmlspecialchars($end)?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Store</label>
            <select name="store[]" class="form-select" multiple>
                <?php foreach ($stores as $s): ?>
                    <option value="<?=$s['id']?>" <?php if(in_array($s['id'],$storeFilter)) echo 'selected';?>><?=htmlspecialchars($s['name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Network</label>
            <select name="network[]" class="form-select" multiple>
                <?php foreach ($networks as $n): ?>
                    <option value="<?=$n?>" <?php if(in_array($n,$networkFilter)) echo 'selected';?>><?=htmlspecialchars(ucfirst($n))?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Admin</label>
            <select name="admin[]" class="form-select" multiple>
                <?php foreach ($admins as $a): ?>
                    <option value="<?=$a['id']?>" <?php if(in_array($a['id'],$adminFilter)) echo 'selected';?>><?=htmlspecialchars($a['username'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-12 text-end">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="schedule-reports.php" class="btn btn-secondary">Reset</a>
            <button type="submit" name="export" value="csv" class="btn btn-outline-success">Export CSV</button>
        </div>
    </form>

    <!-- KPI Cards Row -->
    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-6 g-4 mb-4">
        <div class="col">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <h6 class="card-title">Total Active Stores</h6>
                    <p class="fs-4 mb-0"><?=$totalActiveStores?></p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <h6 class="card-title">Stores Posting</h6>
                    <p class="fs-4 mb-0"><?=$storesPostingCount?></p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <h6 class="card-title">Stores With No Posts</h6>
                    <p class="fs-4 mb-0"><?=$storesNoPosts?></p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <h6 class="card-title">Total Posts</h6>
                    <p class="fs-4 mb-0"><?=$totalPosts?></p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <h6 class="card-title">Published</h6>
                    <p class="fs-4 mb-0"><?=($postsByStatus['PUBLISHED'] ?? 0)?></p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <h6 class="card-title">Scheduled</h6>
                    <p class="fs-4 mb-0"><?=($postsByStatus['SCHEDULED'] ?? 0)?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Posts by Network Badges -->
    <div class="mb-4">
        <?php foreach ($postsByNetwork as $n => $c): ?>
            <span class="badge text-bg-primary me-1"><?=htmlspecialchars(ucfirst($n))?>: <?=$c?></span>
        <?php endforeach; ?>
    </div>

    <!-- Coverage Heatmap -->
    <h3>Coverage Heatmap</h3>
    <div class="table-responsive mb-5">
        <table class="table table-bordered heatmap-table">
            <thead>
            <tr>
                <th>Store</th>
                <?php foreach ($networks as $n): ?>
                    <th class="text-center"><?=htmlspecialchars(ucfirst($n))?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stores as $s): ?>
                <tr>
                    <th><?=htmlspecialchars($s['name'])?></th>
                    <?php foreach ($networks as $n):
                        $cell = $coverage[$s['id']][$n] ?? ['published'=>0,'scheduled'=>0,'failed'=>0];
                        $hasProfile = in_array($n, $storeNetworks[$s['id']] ?? []);
                        if (!$hasProfile) {
                            $class = 'heat-gray';
                        } elseif ($cell['published'] > 0) {
                            $class = 'heat-green';
                        } elseif ($cell['scheduled'] > 0) {
                            $class = 'heat-yellow';
                        } else {
                            $class = 'heat-red';
                        }
                        $tooltip = "Published: {$cell['published']}\nScheduled: {$cell['scheduled']}\nFailed: {$cell['failed']}";
                        ?>
                        <td class="<?=$class?>" title="<?=$tooltip?>"></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row g-4">
        <!-- Leaderboards -->
        <div class="col-lg-4">
            <h3>Leaderboards</h3>
            <div class="mb-4">
                <h5>Top Posting Stores</h5>
                <table class="table table-sm">
                    <?php foreach (array_slice($postsByStorePublished,0,10,true) as $store=>$count): ?>
                        <tr><td><?=htmlspecialchars($store)?></td><td class="text-end"><?=$count?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="mb-4">
                <h5>Top Admins</h5>
                <table class="table table-sm">
                    <?php foreach (array_slice($postsByAdminPublished,0,10,true) as $id=>$count): ?>
                        <tr><td><?=htmlspecialchars($adminMap[$id] ?? 'Unknown')?></td><td class="text-end"><?=$count?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="mb-4">
                <h5>Failures by Store</h5>
                <table class="table table-sm">
                    <?php foreach (array_slice($failuresByStore,0,10,true) as $store=>$count): ?>
                        <tr><td><?=htmlspecialchars($store)?></td><td class="text-end"><?=$count?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Alerts -->
        <div class="col-lg-4">
            <h3>Alerts</h3>
            <div class="mb-4">
                <h5>Stores with no posts in last 7 days</h5>
                <ul class="list-group">
                    <?php foreach ($noPost7 as $name): ?>
                        <li class="list-group-item list-group-item-danger"><?=htmlspecialchars($name)?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="mb-4">
                <h5>Stores with no posts in last 14 days</h5>
                <ul class="list-group">
                    <?php foreach ($noPost14 as $name): ?>
                        <li class="list-group-item list-group-item-warning"><?=htmlspecialchars($name)?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="mb-4">
                <h5>No scheduled posts next 7 days</h5>
                <ul class="list-group">
                    <?php foreach ($noScheduledNext7 as $name): ?>
                        <li class="list-group-item list-group-item-danger"><?=htmlspecialchars($name)?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Detail Tables -->
        <div class="col-lg-4">
            <h3>Posts by Admin</h3>
            <table class="table table-sm table-striped">
                <thead><tr><th>Admin</th><th class="text-end">Published</th></tr></thead>
                <tbody>
                <?php foreach ($postsByAdminPublished as $id=>$count): ?>
                    <tr><td><?=htmlspecialchars($adminMap[$id] ?? 'Unknown')?></td><td class="text-end"><?=$count?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h3 class="mt-4">Per-Store Summary</h3>
            <table class="table table-sm table-striped">
                <thead><tr><th>Store</th><th class="text-end">Published</th><th class="text-end">Scheduled</th><th class="text-end">Failed</th></tr></thead>
                <tbody>
                <?php foreach ($stores as $s):
                    $published = $scheduled = $failed = 0;
                    foreach ($coverage[$s['id']] ?? [] as $n=>$vals) {
                        $published += $vals['published'];
                        $scheduled += $vals['scheduled'];
                        $failed += $vals['failed'];
                    }
                    ?>
                    <tr><td><?=htmlspecialchars($s['name'])?></td><td class="text-end"><?=$published?></td><td class="text-end"><?=$scheduled?></td><td class="text-end"><?=$failed?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__.'/footer.php'; ?>
