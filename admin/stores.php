<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Check if PIN already exists
        $stmt = $pdo->prepare('SELECT id FROM stores WHERE pin = ?');
        $stmt->execute([$_POST['pin']]);
        if ($stmt->fetch()) {
            $errors[] = 'PIN already exists';
        } else {
            $stmt = $pdo->prepare('INSERT INTO stores (name, pin, admin_email, drive_folder, hootsuite_campaign_tag, first_name, last_name, phone, address, city, state, zip_code, country, marketing_report_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $_POST['name'],
                $_POST['pin'],
                $_POST['email'],
                $_POST['folder'],
                $_POST['hootsuite_campaign_tag'] ?? null,
                $_POST['first_name'] ?? null,
                $_POST['last_name'] ?? null,
                format_mobile_number($_POST['phone'] ?? ''),
                $_POST['address'] ?? null,
                $_POST['city'] ?? null,
                $_POST['state'] ?? null,
                $_POST['zip_code'] ?? null,
                $_POST['country'] ?? null,
                $_POST['marketing_report_url'] ?? null
            ]);
            $storeId = $pdo->lastInsertId();
            $success[] = 'Store added successfully';

            // Send to Groundhogg if email is provided
            if (!empty($_POST['email'])) {
                $contact = [
                    'email'        => $_POST['email'],
                    'first_name'   => $_POST['first_name'] ?? '',
                    'last_name'    => $_POST['last_name'] ?? '',
                    'mobile_phone' => format_mobile_number($_POST['phone'] ?? ''),
                    'address'      => $_POST['address'] ?? '',
                    'city'         => $_POST['city'] ?? '',
                    'state'        => $_POST['state'] ?? '',
                    'zip'          => $_POST['zip_code'] ?? '',
                    'country'      => $_POST['country'] ?? '',
                    'company_name' => $_POST['name'] ?? '',
                    'user_role'    => 'Store Admin',
                    'lead_source'  => 'mediahub',
                    'opt_in_status'=> 'confirmed',
                    'tags'         => groundhogg_get_default_tags(),
                    'store_id'     => (int)$storeId
                ];

                [$ghSuccess, $ghMessage] = groundhogg_send_contact($contact);
                if ($ghSuccess) {
                    $success[] = $ghMessage;
                } else {
                    $errors[] = 'Store created but Groundhogg sync failed: ' . $ghMessage;
                }
            }
        }
    }
    if (isset($_POST['delete'])) {
        // Check if store has uploads
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE store_id = ?');
        $stmt->execute([$_POST['id']]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $errors[] = 'Cannot delete store with existing uploads';
        } else {
            $emailStmt = $pdo->prepare('SELECT admin_email FROM stores WHERE id=?');
            $emailStmt->execute([$_POST['id']]);
            $storeEmail = $emailStmt->fetchColumn();

            $userStmt = $pdo->prepare('SELECT email FROM store_users WHERE store_id=?');
            $userStmt->execute([$_POST['id']]);
            $userEmails = $userStmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare('DELETE FROM stores WHERE id=?');
            $stmt->execute([$_POST['id']]);

            $stmt = $pdo->prepare('DELETE FROM store_users WHERE store_id=?');
            $stmt->execute([$_POST['id']]);

            $success[] = 'Store deleted successfully';

            if ($storeEmail) {
                [$delSuccess, $delMsg] = groundhogg_delete_contact($storeEmail);
                if (!$delSuccess) {
                    $errors[] = 'Groundhogg delete failed for main contact: ' . $delMsg;
                }
            }
            foreach ($userEmails as $email) {
                [$delSuccess, $delMsg] = groundhogg_delete_contact($email);
                if (!$delSuccess) {
                    $errors[] = 'Groundhogg delete failed for ' . $email . ': ' . $delMsg;
                }
            }
        }
    }
}

// Get stores sorted by name
$stores = $pdo->query('SELECT s.*, COUNT(u.id) as upload_count,
                       (SELECT COUNT(*) FROM store_messages m WHERE m.store_id = s.id) as chat_count
                       FROM stores s
                       LEFT JOIN uploads u ON s.id = u.store_id
                       GROUP BY s.id
                       ORDER BY s.name ASC')->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_stores = count($stores);
$total_uploads = array_sum(array_column($stores, 'upload_count'));
$total_chats = array_sum(array_column($stores, 'chat_count'));
$stores_with_uploads = count(array_filter($stores, function($s) { return $s['upload_count'] > 0; }));

$active = 'stores';
include __DIR__.'/header.php';
?>

    <style>
        /* Page Header */
        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-card .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .stat-card.primary .stat-icon { color: #667eea; }
        .stat-card.success .stat-icon { color: #4facfe; }
        .stat-card.warning .stat-icon { color: #fa709a; }
        .stat-card.info .stat-icon { color: #4ade80; }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .stat-bg {
            position: absolute;
            right: -20px;
            bottom: -20px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            opacity: 0.1;
        }

        .stat-card.primary .stat-bg { background: var(--primary-gradient); }
        .stat-card.success .stat-bg { background: var(--success-gradient); }
        .stat-card.warning .stat-bg { background: var(--warning-gradient); }
        .stat-card.info .stat-bg { background: linear-gradient(135deg, #4ade80, #22c55e); }

        /* Stores Table Card */
        .stores-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header-modern {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title-modern {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title-modern i {
            font-size: 1.1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-body-modern {
            padding: 1.5rem;
        }

        /* Modern Table */
        .table-modern {
            margin: 0;
        }

        .table-modern thead {
            background: var(--primary-gradient);
            color: white;
        }

        .table-modern th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table-modern td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .table-modern tbody tr {
            transition: var(--transition);
        }

        .table-modern tbody tr:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .table-modern tbody tr:last-child td {
            border-bottom: none;
        }

        /* Store Name Link */
        .store-name-link {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .store-name-link:hover {
            color: #667eea;
        }

        /* PIN Code */
        .pin-code {
            background: #f8f9fa;
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
        }

        /* Drive Link */
        .drive-link {
            color: #667eea;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: var(--transition);
        }

        .drive-link:hover {
            color: #764ba2;
            transform: translateY(-2px);
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 10px;
            border: none;
            font-weight: 500;
            transition: var(--transition);
            margin: 0 0.25rem;
        }

        .btn-action-primary {
            background: #667eea;
            color: white;
        }

        .btn-action-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-action-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-action-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-action-danger {
            background: #dc3545;
            color: white;
        }

        .btn-action-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Add Store Form */
        .add-store-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .form-section {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control-modern, .form-select-modern {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: var(--transition);
        }

        .form-control-modern:focus, .form-select-modern:focus {
            border-color: #667eea;
            box-shadow: none;
            outline: none;
        }

        .form-label-modern {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .btn-add-store {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-add-store:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .table-modern {
                font-size: 0.875rem;
            }

            .table-modern th, .table-modern td {
                padding: 0.75rem 0.5rem;
            }

            .btn-action {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
                margin: 0.125rem;
            }
        }
    </style>

    <div class="animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <h1 class="page-title">Store Management</h1>
            <p class="page-subtitle">Manage all stores and their settings</p>
        </div>

        <!-- Alerts -->
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($e); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?php foreach ($success as $s): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($s); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="bi bi-shop"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_stores; ?>">0</div>
                <div class="stat-label">Total Stores</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card success animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_uploads; ?>">0</div>
                <div class="stat-label">Total Uploads</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card warning animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-chat-dots"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_chats; ?>">0</div>
                <div class="stat-label">Total Chats</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card info animate__animated animate__fadeInUp delay-30">
                <div class="stat-icon">
                    <i class="bi bi-activity"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $stores_with_uploads; ?>">0</div>
                <div class="stat-label">Active Stores</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Stores Table -->
        <div class="stores-card animate__animated animate__fadeIn delay-40">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-list-ul"></i>
                    All Stores
                </h5>
            </div>
            <div class="card-body-modern">
                <?php if (empty($stores)): ?>
                    <div class="empty-state">
                        <i class="bi bi-shop"></i>
                        <h4>No stores yet</h4>
                        <p>Add your first store below to get started</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                            <tr>
                                <th>Store Name</th>
                                <th>PIN</th>
                                <th>Admin Email</th>
                                <th>Drive Folder</th>
                                <th>Chats</th>
                                <th>Uploads</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($stores as $s): ?>
                                <tr>
                                    <td>
                                        <a href="edit_store.php?id=<?php echo $s['id']; ?>" class="store-name-link">
                                            <?php echo htmlspecialchars($s['name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="pin-code"><?php echo htmlspecialchars($s['pin']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($s['admin_email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($s['admin_email']); ?>">
                                                <?php echo htmlspecialchars($s['admin_email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['drive_folder']): ?>
                                            <a href="https://drive.google.com/drive/folders/<?php echo $s['drive_folder']; ?>"
                                               target="_blank" class="drive-link">
                                                <i class="bi bi-folder2-open"></i>
                                                View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Auto-create</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $s['chat_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $s['upload_count']; ?></span>
                                    </td>
                                    <td>
                                        <a href="uploads.php?store_id=<?php echo $s['id']; ?>"
                                           class="btn btn-action btn-action-primary"
                                           title="View Uploads">
                                            <i class="bi bi-cloud-upload"></i>
                                        </a>
                                        <a href="edit_store.php?id=<?php echo $s['id']; ?>"
                                           class="btn btn-action btn-action-secondary"
                                           title="Edit Store">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this store? This cannot be undone.');">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button name="delete" class="btn btn-action btn-action-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Store -->
        <div class="add-store-card animate__animated animate__fadeIn delay-50">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-plus-circle"></i>
                    Add New Store
                </h5>
            </div>
            <form method="post">
                <div class="form-section">
                    <h6 class="section-title">Basic Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label-modern">Store Name *</label>
                            <input type="text" name="name" id="name" class="form-control form-control-modern" required>
                        </div>
                        <div class="col-md-6">
                            <label for="pin" class="form-label-modern">PIN (Access Code) *</label>
                            <input type="text" name="pin" id="pin" class="form-control form-control-modern" required
                                   pattern="[A-Za-z0-9]{4,}" title="At least 4 alphanumeric characters">
                            <div class="form-text">Unique code for store access</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6 class="section-title">Contact Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label-modern">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label-modern">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label-modern">Admin Email</label>
                            <input type="email" name="email" id="email" class="form-control form-control-modern">
                            <div class="form-text">For notifications specific to this store</div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label-modern">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control form-control-modern">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6 class="section-title">Location Details</h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="address" class="form-label-modern">Address</label>
                            <input type="text" name="address" id="address" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6">
                            <label for="city" class="form-label-modern">City</label>
                            <input type="text" name="city" id="city" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3">
                            <label for="state" class="form-label-modern">State</label>
                            <input type="text" name="state" id="state" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-3">
                            <label for="zip_code" class="form-label-modern">Zip Code</label>
                            <input type="text" name="zip_code" id="zip_code" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6">
                            <label for="country" class="form-label-modern">Country</label>
                            <input type="text" name="country" id="country" class="form-control form-control-modern">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6 class="section-title">Integration Settings</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="folder" class="form-label-modern">Drive Folder ID</label>
                            <input type="text" name="folder" id="folder" class="form-control form-control-modern">
                            <div class="form-text">Leave blank to auto-create on first upload</div>
                        </div>
                        <div class="col-md-6">
                            <label for="hootsuite_campaign_tag" class="form-label-modern">Hootsuite Tag</label>
                            <input type="text" name="hootsuite_campaign_tag" id="hootsuite_campaign_tag"
                                   class="form-control form-control-modern">
                        </div>
                        <div class="col-md-12">
                            <label for="marketing_report_url" class="form-label-modern">Marketing Report URL</label>
                            <input type="url" name="marketing_report_url" id="marketing_report_url"
                                   class="form-control form-control-modern">
                        </div>
                    </div>
                </div>

                <div class="form-section text-end">
                    <button class="btn btn-add-store" name="add" type="submit">
                        <i class="bi bi-plus-circle me-2"></i>Add Store
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>