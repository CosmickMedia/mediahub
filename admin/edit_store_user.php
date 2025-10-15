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
            'tags'         => groundhogg_get_default_tags((int)$store_id),
            'store_id'     => (int)$store_id
        ];

        groundhogg_send_contact($contact);
    }
}

$active = 'stores';
include __DIR__.'/header.php';
?>


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
                <div class="profile-card animate__animated animate__fadeIn delay-10">
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
                <div class="form-card animate__animated animate__fadeIn delay-20">
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