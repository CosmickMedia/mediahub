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
            $profile_ids = null;
            if (isset($_POST['hootsuite_profile_ids'])) {
                $profile_ids = implode(',', to_string_array($_POST['hootsuite_profile_ids']));
                if ($profile_ids === '') $profile_ids = null;
            }
            $campaign_id = $_POST['hootsuite_campaign_id'] ?? null;
            if ($campaign_id === '') $campaign_id = null;
            $update = $pdo->prepare('UPDATE stores SET name=?, pin=?, admin_email=?, drive_folder=?, hootsuite_token=?, hootsuite_campaign_tag=?, hootsuite_campaign_id=?, hootsuite_profile_ids=?, hootsuite_custom_property_key=?, hootsuite_custom_property_value=?, first_name=?, last_name=?, phone=?, address=?, city=?, state=?, zip_code=?, country=?, marketing_report_url=?, dripley_override_tags=? WHERE id=?');
            $update->execute([
                $_POST['name'],
                $_POST['pin'],
                $_POST['email'],
                $_POST['folder'],
                $_POST['hootsuite_token'],
                $_POST['hootsuite_campaign_tag'] ?? null,
                $campaign_id,
                $profile_ids,
                $_POST['hootsuite_custom_property_key'] ?? null,
                $_POST['hootsuite_custom_property_value'] ?? null,
                $_POST['first_name'] ?? null,
                $_POST['last_name'] ?? null,
                format_mobile_number($_POST['phone'] ?? ''),
                $_POST['address'] ?? null,
                $_POST['city'] ?? null,
                $_POST['state'] ?? null,
                $_POST['zip_code'] ?? null,
                $_POST['country'] ?? null,
                $_POST['marketing_report_url'] ?? null,
                !empty($_POST['dripley_override_tags']) ? trim($_POST['dripley_override_tags']) : null,
                $id
            ]);
            $success[] = 'Store updated successfully';

            // If email is set and contact has not been synced yet, sync with Groundhogg
            if (!empty($_POST['email']) && empty($store['groundhogg_synced'])) {
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
                    'tags'         => groundhogg_get_default_tags((int)$id),
                    'store_id'     => (int)$id
                ];

                [$ghSuccess, $ghMessage] = groundhogg_send_contact($contact);
                if ($ghSuccess) {
                    $syncStmt = $pdo->prepare('UPDATE stores SET groundhogg_synced = 1 WHERE id = ?');
                    $syncStmt->execute([$id]);
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
                // Send to Groundhogg only once when user is initially added
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
                    'tags'         => groundhogg_get_default_tags((int)$id),
                    'store_id'     => (int)$id
                ];

                [$ghSuccess, $ghMessage] = groundhogg_send_contact($contact);
                if ($ghSuccess) {
                    $syncUser = $pdo->prepare('UPDATE store_users SET groundhogg_synced = 1 WHERE id = ?');
                    $syncUser->execute([$insertId]);
                    $success[] = 'User added and ' . $ghMessage;
                } else {
                    $errors[] = 'User added but Groundhogg sync failed: ' . $ghMessage;
                }

                // Refresh user list to include latest sync status
                $userStmt->execute([$id]);
                $store_users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
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
            <div class="form-card animate__animated animate__fadeIn delay-10">
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

            <div class="form-card animate__animated animate__fadeIn delay-20">
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
                        <!--
                        <div class="col-md-6">
                            <label for="hootsuite_campaign_id" class="form-label-modern">Hootsuite Campaign ID</label>
                            <div class="input-group">
                                <input type="number" name="hootsuite_campaign_id" id="hootsuite_campaign_id" class="form-control form-control-modern" list="campaigns_list" value="<?php echo htmlspecialchars($store['hootsuite_campaign_id']); ?>">
                                <button class="btn btn-outline-secondary" type="button" id="load_campaigns">Load</button>
                            </div>
                            <datalist id="campaigns_list"></datalist>
                        </div>
                        -->
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="hootsuite_profile_ids" class="form-label-modern">
                                        <span id="profiles_label">Hootsuite Profiles</span>
                                        <small class="text-muted ms-2">
                                            (<span id="selected_count">0</span> selected of <span id="total_count">0</span> available)
                                        </small>
                                    </label>
                                    <input type="text" id="hootsuite_profile_search" class="form-control form-control-modern mb-2" placeholder="Search profiles">
                                    <select name="hootsuite_profile_ids[]" id="hootsuite_profile_ids" multiple
                                            class="form-select form-select-modern"
                                            style="height: 400px;"
                                            data-selected="<?php echo htmlspecialchars($store['hootsuite_profile_ids']); ?>"></select>
                                    <div class="form-text">Select one or more profiles</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-modern">Selected Profiles</label>
                                    <div id="selected_profiles_box" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; min-height: 400px; max-height: 400px; overflow-y: auto; background: #f8f9fa;">
                                        <div class="text-muted text-center" id="selected_empty_state">
                                            <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.3;"></i>
                                            <p class="mb-0 mt-2">No profiles selected</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--
                        <div class="col-md-6">
                            <label for="hootsuite_custom_property_key" class="form-label-modern">Hootsuite Custom Property Key</label>
                            <input type="text" name="hootsuite_custom_property_key" id="hootsuite_custom_property_key"
                                   class="form-control form-control-modern"
                                   value="<?php echo htmlspecialchars($store['hootsuite_custom_property_key']); ?>">
                            <div class="form-text">Custom property name to match</div>
                        </div>
                        <div class="col-md-6">
                            <label for="hootsuite_custom_property_value" class="form-label-modern">Hootsuite Custom Property Value</label>
                            <input type="text" name="hootsuite_custom_property_value" id="hootsuite_custom_property_value"
                                   class="form-control form-control-modern"
                                   value="<?php echo htmlspecialchars($store['hootsuite_custom_property_value']); ?>">
                            <div class="form-text">Required value for the custom property</div>
                        </div>
                        -->
                        <div class="col-md-12">
                            <label for="marketing_report_url" class="form-label-modern">Marketing Report URL</label>
                            <input type="url" name="marketing_report_url" id="marketing_report_url"
                                   class="form-control form-control-modern"
                                   value="<?php echo htmlspecialchars($store['marketing_report_url']); ?>">
                        </div>
                        <div class="col-md-12">
                            <label for="dripley_override_tags" class="form-label-modern">
                                <i class="bi bi-tags"></i> Dripley Override Tags
                            </label>
                            <input type="text" name="dripley_override_tags" id="dripley_override_tags"
                                   class="form-control form-control-modern"
                                   placeholder="<?php
                                   require_once __DIR__.'/../lib/settings.php';
                                   $default_tags = get_setting('groundhogg_contact_tags');
                                   echo htmlspecialchars($default_tags ?: 'media-hub, store-onboarding');
                                   ?>"
                                   value="<?php echo htmlspecialchars($store['dripley_override_tags'] ?? ''); ?>">
                            <div class="form-text">
                                Override default contact tags for this store. Leave blank to use system defaults. Separate tags with commas.
                            </div>
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
        <div class="form-card animate__animated animate__fadeIn delay-30">
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
                                       class="btn btn-action btn-action-secondary" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form method="post" class="d-inline m-0"
                                          onsubmit="return confirm('Remove this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button class="btn btn-action btn-action-danger" name="delete_user" title="Remove">
                                            <i class="bi bi-trash"></i>
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
                                   placeholder="First Name" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="user_last_name" class="form-control form-control-modern"
                                   placeholder="Last Name" required>
                        </div>
                        <div class="col-md-3">
                            <input type="email" name="user_email" class="form-control form-control-modern"
                                   placeholder="Email" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="user_mobile_phone" class="form-control form-control-modern"
                                   placeholder="Mobile Phone" required>
                        </div>
                        <div class="col-md-4">
                            <select name="user_opt_in_status" class="form-select form-select-modern" required>
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

    <script>
        function loadCampaigns() {
            fetch('../hootsuite/hootsuite_campaigns.php')
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('campaigns_list');
                    list.innerHTML = '';
                    data.forEach(c => {
                        if (c.id && c.name !== undefined) {
                            const opt = document.createElement('option');
                            opt.value = c.id;
                            opt.textContent = c.name;
                            list.appendChild(opt);
                        }
                    });
                });
        }

        document.getElementById('load_campaigns')?.addEventListener('click', loadCampaigns);
        // Load campaigns on page load for convenience
        loadCampaigns();

        function updateSelectedProfilesDisplay() {
            const select = document.getElementById('hootsuite_profile_ids');
            const selectedBox = document.getElementById('selected_profiles_box');
            const emptyState = document.getElementById('selected_empty_state');
            const selectedCountEl = document.getElementById('selected_count');

            const selectedOptions = Array.from(select.options).filter(opt => opt.selected);
            selectedCountEl.textContent = selectedOptions.length;

            if (selectedOptions.length === 0) {
                emptyState.style.display = 'block';
                selectedBox.innerHTML = `
                    <div class="text-muted text-center" id="selected_empty_state">
                        <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p class="mb-0 mt-2">No profiles selected</p>
                    </div>
                `;
            } else {
                emptyState.style.display = 'none';
                selectedBox.innerHTML = selectedOptions.map(opt => `
                    <div class="selected-profile-tag" data-value="${opt.value}" style="
                        display: inline-flex;
                        align-items: center;
                        background: white;
                        border: 1px solid #dee2e6;
                        border-radius: 6px;
                        padding: 6px 10px;
                        margin: 4px;
                        font-size: 0.875rem;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                    ">
                        <span style="margin-right: 8px;">${opt.textContent}</span>
                        <button type="button" class="remove-profile-btn" data-value="${opt.value}" style="
                            background: none;
                            border: none;
                            color: #dc3545;
                            cursor: pointer;
                            padding: 0;
                            font-size: 1.1rem;
                            line-height: 1;
                            display: flex;
                            align-items: center;
                        " title="Remove">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                `).join('');

                // Add click handlers for remove buttons
                document.querySelectorAll('.remove-profile-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const value = e.currentTarget.dataset.value;
                        const option = Array.from(select.options).find(opt => opt.value === value);
                        if (option) {
                            option.selected = false;
                            updateSelectedProfilesDisplay();
                        }
                    });
                });
            }
        }

        fetch('../hootsuite/hootsuite_profiles.php')
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('hootsuite_profile_ids');
                const search = document.getElementById('hootsuite_profile_search');
                const totalCountEl = document.getElementById('total_count');
                const selected = (select.dataset.selected || '').split(',').map(s => s.trim()).filter(Boolean);

                data.forEach(p => {
                    if (p.id && p.name !== undefined) {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name;
                        if (selected.includes(String(p.id))) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    }
                });

                // Update total count
                totalCountEl.textContent = data.length;

                // Update selected profiles display
                updateSelectedProfilesDisplay();

                // Listen for changes to update the selected box
                select.addEventListener('change', updateSelectedProfilesDisplay);

                search.addEventListener('input', () => {
                    const term = search.value.toLowerCase();
                    Array.from(select.options).forEach(opt => {
                        opt.style.display = opt.textContent.toLowerCase().includes(term) ? '' : 'none';
                    });
                });
            });
    </script>

    <?php include __DIR__.'/footer.php'; ?>
