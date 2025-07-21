<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
$stmt->execute([$id]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$store) {
    header('Location: stores.php');
    exit;
}

$errors = [];
$success = [];

// Fetch store users
$userStmt = $pdo->prepare('SELECT * FROM store_users WHERE store_id = ? ORDER BY email');
$userStmt->execute([$id]);
$store_users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_store'])) {
        $stmt = $pdo->prepare('SELECT id FROM stores WHERE pin = ? AND id <> ?');
        $stmt->execute([$_POST['pin'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'PIN already exists';
        } else {
            $update = $pdo->prepare('UPDATE stores SET name=?, pin=?, admin_email=?, drive_folder=?, hootsuite_token=?, hootsuite_campaign_tag=?, first_name=?, last_name=?, phone=?, address=?, city=?, state=?, zip_code=?, country=?, marketing_report_url=? WHERE id=?');
            $update->execute([
                $_POST['name'],
                $_POST['pin'],
                $_POST['email'],
                $_POST['folder'],
                $_POST['hootsuite_token'],
                $_POST['hootsuite_campaign_tag'] ?? null,
                $_POST['first_name'] ?? null,
                $_POST['last_name'] ?? null,
                format_mobile_number($_POST['phone'] ?? ''),
                $_POST['address'] ?? null,
                $_POST['city'] ?? null,
                $_POST['state'] ?? null,
                $_POST['zip_code'] ?? null,
                $_POST['country'] ?? null,
                $_POST['marketing_report_url'] ?? null,
                $id
            ]);
            $success[] = 'Store updated successfully';

            // If email is set, sync with Groundhogg
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
                    'store_id'     => (int)$id
                ];

                [$ghSuccess, $ghMessage] = groundhogg_send_contact($contact);
                if ($ghSuccess) {
                    $success[] = $ghMessage;
                } else {
                    $errors[] = 'Store updated but Groundhogg sync failed: ' . $ghMessage;
                }
            }

            $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
            $stmt->execute([$id]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } elseif (isset($_POST['add_user'])) {
        $email = trim($_POST['user_email']);
        $first = trim($_POST['user_first_name'] ?? '');
        $last = trim($_POST['user_last_name'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mobile = format_mobile_number($_POST['user_mobile_phone'] ?? '');
            $optin = $_POST['user_opt_in_status'] ?? 'confirmed';
            try {
                $stmt = $pdo->prepare('INSERT INTO store_users (store_id, email, first_name, last_name, mobile_phone, opt_in_status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$id, $email, $first ?: null, $last ?: null, $mobile ?: null, $optin]);
                $insertId = $pdo->lastInsertId();
                $success[] = 'User added';
                $store_users[] = ['id' => $insertId, 'email' => $email, 'first_name' => $first, 'last_name' => $last, 'mobile_phone' => $mobile, 'opt_in_status' => $optin];

                // Send to Groundhogg
                $contact = [
                    'email'        => $email,
                    'first_name'   => $first,
                    'last_name'    => $last,
                    'mobile_phone' => $mobile,
                    'address'      => $store['address'] ?? '',
                    'city'         => $store['city'] ?? '',
                    'state'        => $store['state'] ?? '',
                    'zip'          => $store['zip_code'] ?? '',
                    'country'      => $store['country'] ?? '',
                    'company_name' => $store['name'] ?? '',
                    'user_role'    => 'Store Admin',
                    'lead_source'  => 'mediahub',
                    'opt_in_status'=> 'confirmed',
                    'tags'         => groundhogg_get_default_tags(),
                    'store_id'     => (int)$id
                ];

                [$ghSuccess, $ghMessage] = groundhogg_send_contact($contact);
                if ($ghSuccess) {
                    $success[] = 'User added and ' . $ghMessage;
                } else {
                    $errors[] = 'User added but Groundhogg sync failed: ' . $ghMessage;
                }
            } catch (PDOException $e) {
                $errors[] = 'User already exists for this store';
            }
        } else {
            $errors[] = 'Invalid email';
        }
    } elseif (isset($_POST['delete_user'])) {
        $stmt = $pdo->prepare('SELECT email FROM store_users WHERE id=? AND store_id=?');
        $stmt->execute([$_POST['user_id'], $id]);
        $email = $stmt->fetchColumn();

        $stmt = $pdo->prepare('DELETE FROM store_users WHERE id=? AND store_id=?');
        $stmt->execute([$_POST['user_id'], $id]);
        $success[] = 'User removed';

        if ($email) {
            [$delSuccess, $delMsg] = groundhogg_delete_contact($email);
            if (!$delSuccess) {
                $errors[] = 'Groundhogg delete failed: ' . $delMsg;
            }
        }

        // Refresh user list
        $userStmt->execute([$id]);
        $store_users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get store statistics
$stmt = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE store_id = ?');
$stmt->execute([$id]);
$upload_count = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM store_messages WHERE store_id = ?');
$stmt->execute([$id]);
$message_count = $stmt->fetchColumn();

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

        .page-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.25rem 0 0 0;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .mini-stat {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .mini-stat:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .mini-stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .mini-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .mini-stat-label {
            font-size: 0.875rem;
            color: #6c757d;
        }

        /* Form Cards */
        .form-card {
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

        /* Form Sections */
        .form-section {
            padding: 1.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section:first-child {
            padding-top: 0;
        }

        .form-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Form Controls */
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

        /* PIN Code Display */
        .pin-display {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-family: monospace;
            font-size: 1.1rem;
            display: inline-block;
            margin-top: 0.5rem;
        }

        /* User List */
        .user-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            background: #f8f9fa;
            transition: var(--transition);
        }

        .user-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .user-email {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .user-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Action Buttons */
        .btn-save {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            transition: var(--transition);
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

        .btn-add-user {
            background: var(--success-gradient);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-add-user:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .user-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .user-actions {
                width: 100%;
            }

            .btn-action {
                flex: 1;
            }
        }
    </style>

    <div class="animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">Edit Store</h1>
                    <p class="page-subtitle"><?php echo htmlspecialchars($store['name']); ?></p>
                </div>
                <a href="stores.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Back to Stores
                </a>
            </div>
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

        <!-- Store Stats -->
        <div class="stats-row animate__animated animate__fadeInUp">
            <div class="mini-stat">
                <div class="mini-stat-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <div class="mini-stat-number"><?php echo $upload_count; ?></div>
                <div class="mini-stat-label">Uploads</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-icon">
                    <i class="bi bi-chat-dots"></i>
                </div>
                <div class="mini-stat-number"><?php echo $message_count; ?></div>
                <div class="mini-stat-label">Messages</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="mini-stat-number"><?php echo count($store_users); ?></div>
                <div class="mini-stat-label">Users</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-icon">
                    <i class="bi bi-key"></i>
                </div>
                <div class="mini-stat-number"><?php echo htmlspecialchars($store['pin']); ?></div>
                <div class="mini-stat-label">PIN Code</div>
            </div>
        </div>

        <!-- Store Details Form -->
        <form method="post">
            <div class="form-card animate__animated animate__fadeIn" style="animation-delay: 0.1s">
                <div class="card-header-modern">
                    <h5 class="card-title-modern">
                        <i class="bi bi-info-circle"></i>
                        Store Details
                    </h5>
                </div>
                <div class="card-body-modern">
                    <div class="form-section">
                        <h6 class="section-title">Basic Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label-modern">Store Name *</label>
                                <input type="text" name="name" id="name" class="form-control form-control-modern"
                                       required value="<?php echo htmlspecialchars($store['name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="pin" class="form-label-modern">PIN *</label>
                                <input type="text" name="pin" id="pin" class="form-control form-control-modern"
                                       required value="<?php echo htmlspecialchars($store['pin']); ?>">
                                <div class="form-text">Store access code - must be unique</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h6 class="section-title">Contact Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label-modern">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['first_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label-modern">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['last_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label-modern">Admin Email</label>
                                <input type="email" name="email" id="email" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['admin_email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label-modern">Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['phone']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h6 class="section-title">Location Details</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="address" class="form-label-modern">Address</label>
                                <input type="text" name="address" id="address" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['address']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="city" class="form-label-modern">City</label>
                                <input type="text" name="city" id="city" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['city']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="state" class="form-label-modern">State</label>
                                <input type="text" name="state" id="state" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['state']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="zip_code" class="form-label-modern">Zip Code</label>
                                <input type="text" name="zip_code" id="zip_code" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['zip_code']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="country" class="form-label-modern">Country</label>
                                <input type="text" name="country" id="country" class="form-control form-control-modern"
                                       value="<?php echo htmlspecialchars($store['country']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-card animate__animated animate__fadeIn" style="animation-delay: 0.2s">
                <div class="card-header-modern">
                    <h5 class="card-title-modern">
                        <i class="bi bi-gear"></i>
                        API Settings
                    </h5>
                </div>
                <div class="card-body-modern">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="folder" class="form-label-modern">Drive Folder ID</label>
                            <input type="text" name="folder" id="folder" class="form-control form-control-modern"
                                   value="<?php echo htmlspecialchars($store['drive_folder']); ?>">
                            <div class="form-text">Google Drive folder for store uploads</div>
                        </div>
                        <input type="hidden" name="hootsuite_token" id="hootsuite_token"
                               value="<?php echo htmlspecialchars($store['hootsuite_token']); ?>">
                        <div class="col-md-6">
                            <label for="hootsuite_campaign_tag" class="form-label-modern">Hootsuite Tag</label>
                            <input type="text" name="hootsuite_campaign_tag" id="hootsuite_campaign_tag"
                                   class="form-control form-control-modern"
                                   placeholder="lowercase, no spaces"
                                   value="<?php echo htmlspecialchars($store['hootsuite_campaign_tag']); ?>">
                            <div class="form-text">Must match the tag in Hootsuite</div>
                        </div>
                        <div class="col-md-12">
                            <label for="marketing_report_url" class="form-label-modern">Marketing Report URL</label>
                            <input type="url" name="marketing_report_url" id="marketing_report_url"
                                   class="form-control form-control-modern"
                                   value="<?php echo htmlspecialchars($store['marketing_report_url']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-4">
                <button class="btn btn-save" name="save_store" type="submit">
                    <i class="bi bi-check-circle me-2"></i>Save Changes
                </button>
            </div>
        </form>

        <!-- Store Users -->
        <div class="form-card animate__animated animate__fadeIn" style="animation-delay: 0.3s">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-people"></i>
                    Store Users
                </h5>
            </div>
            <div class="card-body-modern">
                <ul class="user-list mb-3">
                    <?php if (empty($store_users)): ?>
                        <li class="empty-state">
                            <i class="bi bi-person-plus"></i>
                            <p>No additional users yet</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($store_users as $u): ?>
                            <li class="user-item">
                                <div class="user-info">
                                    <div class="user-name">
                                        <?php
                                        $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                        echo htmlspecialchars($fullName ?: 'No name set');
                                        ?>
                                    </div>
                                    <div class="user-email"><?php echo htmlspecialchars($u['email']); ?></div>
                                    <?php if (!empty($u['mobile_phone'])): ?>
                                        <div class="user-email">
                                            <i class="bi bi-telephone me-1"></i>
                                            <?php echo htmlspecialchars($u['mobile_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="user-actions">
                                    <a href="edit_store_user.php?store_id=<?php echo $id; ?>&id=<?php echo $u['id']; ?>"
                                       class="btn btn-action btn-action-secondary">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <form method="post" class="d-inline m-0"
                                          onsubmit="return confirm('Remove this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button class="btn btn-action btn-action-danger" name="delete_user">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <div class="form-section">
                    <h6 class="section-title">Add New User</h6>
                    <form method="post" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="user_first_name" class="form-control form-control-modern"
                                   placeholder="First Name">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="user_last_name" class="form-control form-control-modern"
                                   placeholder="Last Name">
                        </div>
                        <div class="col-md-3">
                            <input type="email" name="user_email" class="form-control form-control-modern"
                                   placeholder="Email" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="user_mobile_phone" class="form-control form-control-modern"
                                   placeholder="Mobile Phone">
                        </div>
                        <div class="col-md-4">
                            <select name="user_opt_in_status" class="form-select form-select-modern">
                                <option value="confirmed" selected>Confirmed</option>
                                <option value="unconfirmed">Unconfirmed</option>
                                <option value="unsubscribed">Unsubscribed</option>
                                <option value="subscribed_weekly">Subscribed Weekly</option>
                                <option value="subscribed_monthly">Subscribed Monthly</option>
                                <option value="bounced">Bounced</option>
                                <option value="spam">Spam</option>
                                <option value="complained">Complained</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-add-user" name="add_user" type="submit">
                                <i class="bi bi-person-plus me-2"></i>Add User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>