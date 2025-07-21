<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$store_id = $_GET['store_id'] ?? 0;
$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare('SELECT * FROM store_users WHERE id=? AND store_id=?');
$stmt->execute([$id, $store_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: edit_store.php?id=' . urlencode($store_id));
    exit;
}

// Get store info for breadcrumb
$stmt = $pdo->prepare('SELECT name FROM stores WHERE id=?');
$stmt->execute([$store_id]);
$store_name = $stmt->fetchColumn();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $email = trim($_POST['email'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    } else {
        $mobile = format_mobile_number($_POST['mobile_phone'] ?? '');
        $optin = $_POST['opt_in_status'] ?? 'confirmed';
        $stmt = $pdo->prepare('UPDATE store_users SET email=?, first_name=?, last_name=?, mobile_phone=?, opt_in_status=? WHERE id=? AND store_id=?');
        $stmt->execute([$email, $first ?: null, $last ?: null, $mobile ?: null, $optin, $id, $store_id]);
        $success = true;
        $stmt = $pdo->prepare('SELECT * FROM store_users WHERE id=? AND store_id=?');
        $stmt->execute([$id, $store_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // fetch store for location data
        $storeStmt = $pdo->prepare('SELECT * FROM stores WHERE id=?');
        $storeStmt->execute([$store_id]);
        $store = $storeStmt->fetch(PDO::FETCH_ASSOC);

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
            'opt_in_status'=> $optin,
            'tags'         => groundhogg_get_default_tags(),
            'store_id'     => (int)$store_id
        ];

        groundhogg_send_contact($contact);
    }
}

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

        /* Breadcrumb */
        .breadcrumb-modern {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            margin: 0;
            font-size: 0.9rem;
        }

        .breadcrumb-modern .breadcrumb-item {
            color: rgba(255, 255, 255, 0.8);
        }

        .breadcrumb-modern .breadcrumb-item.active {
            color: white;
        }

        .breadcrumb-modern .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.6);
            content: "›";
            font-size: 1.2rem;
            font-weight: bold;
        }

        .breadcrumb-modern a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-modern a:hover {
            color: white;
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

        /* User Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-header {
            background: var(--secondary-gradient);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 600;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-email {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
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
            padding: 2rem;
        }

        /* Form Controls */
        .form-control-modern, .form-select-modern {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: var(--transition);
            font-size: 0.95rem;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label-modern i {
            font-size: 1rem;
            color: #6c757d;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            gap: 1.5rem;
        }

        .form-group-modern {
            position: relative;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .status-confirmed {
            background: #d1f2eb;
            color: #2e7d65;
        }

        .status-unconfirmed {
            background: #fff3cd;
            color: #856404;
        }

        .status-unsubscribed {
            background: #f8d7da;
            color: #721c24;
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-cancel {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #e0e0e0;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-cancel:hover {
            background: #e9ecef;
            color: #495057;
            transform: translateY(-2px);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            margin-top: 2rem;
        }

        /* Info Cards */
        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-icon {
            width: 32px;
            height: 32px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-icon i {
            font-size: 1rem;
            color: #667eea;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.75rem;
            color: #6c757d;
            margin-bottom: 0.125rem;
        }

        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .page-header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .breadcrumb-modern {
                margin-bottom: 1rem;
            }

            .card-body-modern {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-save, .btn-cancel {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <div class="animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <div class="page-header-content">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-modern">
                            <li class="breadcrumb-item"><a href="stores.php">Stores</a></li>
                            <li class="breadcrumb-item"><a href="edit_store.php?id=<?php echo $store_id; ?>"><?php echo htmlspecialchars($store_name); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit User</li>
                        </ol>
                    </nav>
                    <h1 class="page-title mt-2">Edit Store User</h1>
                    <p class="page-subtitle">Update user information and permissions</p>
                </div>
                <a href="edit_store.php?id=<?php echo $store_id; ?>" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Back to Store
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

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                User updated successfully
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <!-- User Profile Card -->
                <div class="profile-card animate__animated animate__fadeIn" style="animation-delay: 0.1s">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php
                            $initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1));
                            echo $initials;
                            ?>
                        </div>
                        <div class="profile-name">
                            <?php
                            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                            echo htmlspecialchars($fullName ?: 'No name set');
                            ?>
                        </div>
                        <div class="profile-email">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                    </div>
                    <div class="card-body-modern">
                        <div class="info-card">
                            <h6 class="info-card-title">User Information</h6>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Store</div>
                                    <div class="info-value"><?php echo htmlspecialchars($store_name); ?></div>
                                </div>
                            </div>

                            <?php if (!empty($user['mobile_phone'])): ?>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-telephone"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Mobile Phone</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['mobile_phone']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Opt-in Status</div>
                                    <div class="info-value">
                                        <?php
                                        $statusClass = 'status-unconfirmed';
                                        if ($user['opt_in_status'] === 'confirmed') $statusClass = 'status-confirmed';
                                        elseif ($user['opt_in_status'] === 'unsubscribed') $statusClass = 'status-unsubscribed';
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['opt_in_status'])); ?>
                                    </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Edit Form -->
                <div class="form-card animate__animated animate__fadeIn" style="animation-delay: 0.2s">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern">
                            <i class="bi bi-pencil-square"></i>
                            Edit User Details
                        </h5>
                    </div>
                    <div class="card-body-modern">
                        <form method="post">
                            <div class="form-grid">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group-modern">
                                            <label for="first_name" class="form-label-modern">
                                                <i class="bi bi-person"></i> First Name
                                            </label>
                                            <input type="text" name="first_name" id="first_name"
                                                   class="form-control form-control-modern"
                                                   value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                                   placeholder="Enter first name">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group-modern">
                                            <label for="last_name" class="form-label-modern">
                                                <i class="bi bi-person"></i> Last Name
                                            </label>
                                            <input type="text" name="last_name" id="last_name"
                                                   class="form-control form-control-modern"
                                                   value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                                   placeholder="Enter last name">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group-modern">
                                    <label for="email" class="form-label-modern">
                                        <i class="bi bi-envelope"></i> Email Address *
                                    </label>
                                    <input type="email" name="email" id="email"
                                           class="form-control form-control-modern"
                                           required
                                           value="<?php echo htmlspecialchars($user['email']); ?>"
                                           placeholder="user@example.com">
                                    <div class="form-text">Primary email for store access and notifications</div>
                                </div>

                                <div class="form-group-modern">
                                    <label for="mobile_phone" class="form-label-modern">
                                        <i class="bi bi-telephone"></i> Mobile Phone
                                    </label>
                                    <input type="text" name="mobile_phone" id="mobile_phone"
                                           class="form-control form-control-modern"
                                           value="<?php echo htmlspecialchars($user['mobile_phone']); ?>"
                                           placeholder="+1 (555) 123-4567">
                                    <div class="form-text">Include country code for international numbers</div>
                                </div>

                                <div class="form-group-modern">
                                    <label for="opt_in_status" class="form-label-modern">
                                        <i class="bi bi-shield-check"></i> Opt-in Status
                                    </label>
                                    <select name="opt_in_status" id="opt_in_status" class="form-select form-select-modern">
                                        <?php
                                        $statuses = [
                                            'unconfirmed' => 'Unconfirmed',
                                            'confirmed' => 'Confirmed',
                                            'unsubscribed' => 'Unsubscribed',
                                            'subscribed_weekly' => 'Subscribed Weekly',
                                            'subscribed_monthly' => 'Subscribed Monthly',
                                            'bounced' => 'Bounced',
                                            'spam' => 'Spam',
                                            'complained' => 'Complained',
                                            'blocked' => 'Blocked'
                                        ];
                                        foreach($statuses as $value => $label): ?>
                                            <option value="<?php echo $value; ?>"
                                                <?php echo $user['opt_in_status'] == $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Controls email communication preferences</div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="edit_store.php?id=<?php echo $store_id; ?>" class="btn btn-cancel">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                                <button class="btn btn-save" name="save_user" type="submit">
                                    <i class="bi bi-check-circle"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>