<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_once __DIR__.'/../lib/helpers.php';
require_login();
$pdo = get_pdo();

$errors = [];
$success = [];
$addFormOpen = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Check if PIN already exists
        $stmt = $pdo->prepare('SELECT id FROM stores WHERE pin = ?');
        $stmt->execute([$_POST['pin']]);
        if ($stmt->fetch()) {
            $errors[] = 'PIN already exists';
        } else {
            $profile_ids = null;
            if (isset($_POST['hootsuite_profile_ids'])) {
                $profile_ids = implode(',', to_string_array($_POST['hootsuite_profile_ids']));
                if ($profile_ids === '') $profile_ids = null;
            }
            $campaign_id = $_POST['hootsuite_campaign_id'] ?? null;
            if ($campaign_id === '') {
                $campaign_id = null;
            }

            $stmt = $pdo->prepare('INSERT INTO stores (name, pin, admin_email, drive_folder, hootsuite_campaign_tag, hootsuite_campaign_id, hootsuite_profile_ids, hootsuite_custom_property_key, hootsuite_custom_property_value, first_name, last_name, phone, address, city, state, zip_code, country, marketing_report_url, dripley_override_tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $_POST['name'],
                $_POST['pin'],
                $_POST['email'],
                $_POST['folder'],
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
                !empty($_POST['dripley_override_tags']) ? trim($_POST['dripley_override_tags']) : null
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
                    'tags'         => groundhogg_get_default_tags((int)$storeId),
                    'store_id'     => (int)$storeId
                ];

                [$ghSuccess, $ghMessage] = groundhogg_send_contact($contact);
                if ($ghSuccess) {
                    $updateSync = $pdo->prepare('UPDATE stores SET groundhogg_synced = 1 WHERE id = ?');
                    $updateSync->execute([$storeId]);
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
$stores = $pdo->query('SELECT s.*,
                              COALESCE(u.upload_count, 0) AS upload_count,
                              COALESCE(m.chat_count, 0) AS chat_count
                       FROM stores s
                       LEFT JOIN (
                           SELECT store_id, COUNT(*) AS upload_count
                           FROM uploads
                           GROUP BY store_id
                       ) u ON s.id = u.store_id
                       LEFT JOIN (
                           SELECT store_id, COUNT(*) AS chat_count
                           FROM store_messages
                           GROUP BY store_id
                       ) m ON s.id = m.store_id
                       ORDER BY s.name ASC')->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_stores = count($stores);
$total_uploads = array_sum(array_column($stores, 'upload_count'));
$total_chats = array_sum(array_column($stores, 'chat_count'));
$stores_with_uploads = count(array_filter($stores, function($s) { return $s['upload_count'] > 0; }));

// Detect stores without profiles for warning banner
$stores_without_profiles = array_filter($stores, function($s) {
    return empty($s['hootsuite_profile_ids']) || trim($s['hootsuite_profile_ids']) === '';
});

$active = 'stores';
include __DIR__.'/header.php';
?>


    <div class="animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">Store Management</h1>
                    <p class="page-subtitle">Manage all stores and their settings</p>
                </div>
                <div class="page-actions">
                    <button type="button"
                            class="btn btn-toggle-add-store"
                            id="toggleAddStore"
                            aria-expanded="<?php echo $addFormOpen ? 'true' : 'false'; ?>"
                            aria-controls="addStoreCard">
                        <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>
                        <span class="toggle-text">Add Store</span>
                    </button>
                </div>
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

        <!-- Warning for stores without profiles -->
        <?php if (!empty($stores_without_profiles)): ?>
            <div class="alert alert-warning alert-dismissible fade show animate__animated animate__fadeIn" role="alert" style="border-left: 4px solid #ff9800;">
                <div class="d-flex align-items-start">
                    <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.5rem; flex-shrink: 0;"></i>
                    <div style="flex-grow: 1;">
                        <h5 class="alert-heading mb-2">
                            <strong>Profile Configuration Required</strong>
                        </h5>
                        <p class="mb-2">
                            The following <?php echo count($stores_without_profiles); ?> store<?php echo count($stores_without_profiles) > 1 ? 's have' : ' has'; ?> no social media profiles attached.
                            Stores need at least one Hootsuite profile to enable post scheduling on their calendar.
                        </p>
                        <div class="mb-2" style="max-height: 200px; overflow-y: auto;">
                            <ul class="mb-0" style="column-count: <?php echo count($stores_without_profiles) > 6 ? '2' : '1'; ?>; column-gap: 2rem;">
                                <?php foreach ($stores_without_profiles as $store): ?>
                                    <li class="mb-1">
                                        <strong><?php echo htmlspecialchars($store['name']); ?></strong>
                                        <a href="edit_store.php?id=<?php echo $store['id']; ?>" class="alert-link ms-2">
                                            <i class="bi bi-arrow-right-circle"></i> Configure Profiles
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <hr style="margin: 0.75rem 0;">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Click "Configure Profiles" next to each store to add Hootsuite profiles, or use the edit button in the table below.
                        </small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="flex-shrink: 0;"></button>
                </div>
            </div>
        <?php endif; ?>

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

        <!-- Add New Store -->
        <div id="addStoreCard" class="add-store-card animate__animated animate__fadeIn delay-40<?php echo $addFormOpen ? '' : ' collapsed'; ?>">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-plus-circle"></i>
                    Add New Store
                </h5>
            </div>
            <div class="card-body-modern">
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
                            <div class="col-md-6">
                                <label for="hootsuite_campaign_id" class="form-label-modern">Hootsuite Campaign ID</label>
                                <div class="input-group">
                                    <input type="number" name="hootsuite_campaign_id" id="hootsuite_campaign_id" class="form-control form-control-modern" list="campaigns_list">
                                    <button class="btn btn-outline-secondary" type="button" id="load_campaigns">Load</button>
                                </div>
                                <datalist id="campaigns_list"></datalist>
                            </div>
                            <div class="col-md-6">
                                <label for="hootsuite_profile_ids" class="form-label-modern">Hootsuite Profiles</label>
                                <select name="hootsuite_profile_ids[]" id="hootsuite_profile_ids" multiple
                                        class="form-select form-select-modern"></select>
                                <div class="form-text">Select one or more profiles</div>
                            </div>
                            <div class="col-md-6">
                                <label for="hootsuite_custom_property_key" class="form-label-modern">Hootsuite Custom Property Key</label>
                                <input type="text" name="hootsuite_custom_property_key" id="hootsuite_custom_property_key" class="form-control form-control-modern">
                            </div>
                            <div class="col-md-6">
                                <label for="hootsuite_custom_property_value" class="form-label-modern">Hootsuite Custom Property Value</label>
                                <input type="text" name="hootsuite_custom_property_value" id="hootsuite_custom_property_value" class="form-control form-control-modern">
                            </div>
                            <div class="col-md-12">
                                <label for="marketing_report_url" class="form-label-modern">Marketing Report URL</label>
                                <input type="url" name="marketing_report_url" id="marketing_report_url"
                                       class="form-control form-control-modern">
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
                                       ?>">
                                <div class="form-text">
                                    Override default contact tags for this store. Leave blank to use system defaults. Separate tags with commas.
                                </div>
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

        <!-- Stores Table -->
        <div class="stores-card animate__animated animate__fadeIn delay-50">
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
                        <p>Use the Add Store button above to get started</p>
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
                                <th>Campaign ID</th>
                                <th>Profiles</th>
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
                                        <?php echo htmlspecialchars($s['hootsuite_campaign_id'] ?? ''); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['hootsuite_profile_ids']) && trim($s['hootsuite_profile_ids']) !== ''):
                                            $profile_count = count(array_filter(explode(',', $s['hootsuite_profile_ids'])));
                                        ?>
                                            <span class="badge bg-success" title="<?php echo $profile_count; ?> profile<?php echo $profile_count > 1 ? 's' : ''; ?> configured">
                                                <i class="bi bi-check-circle"></i> <?php echo $profile_count; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark" title="No profiles configured">
                                                <i class="bi bi-exclamation-triangle"></i> None
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $s['chat_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $s['upload_count']; ?></span>
                                    </td>
                                    <td class="actions-cell">
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

    </div>

    <script>
        const toggleButton = document.getElementById('toggleAddStore');
        const addStoreCard = document.getElementById('addStoreCard');

        if (toggleButton && addStoreCard) {
            const icon = toggleButton.querySelector('i');
            const label = toggleButton.querySelector('.toggle-text');

            const setOpen = (shouldOpen) => {
                addStoreCard.classList.toggle('collapsed', !shouldOpen);
                toggleButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                if (icon) {
                    icon.className = 'bi ' + (shouldOpen ? 'bi-dash-lg' : 'bi-plus-lg') + ' me-2';
                }
                if (label) {
                    label.textContent = shouldOpen ? 'Hide Add Store' : 'Add Store';
                }
            };

            setOpen(!addStoreCard.classList.contains('collapsed'));

            toggleButton.addEventListener('click', () => {
                setOpen(addStoreCard.classList.contains('collapsed'));
            });
        }

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

        fetch('../hootsuite/hootsuite_profiles.php')
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('hootsuite_profile_ids');
                data.forEach(p => {
                    if (p.id && p.name !== undefined) {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name;
                        select.appendChild(opt);
                    }
                });
            });
    </script>

<?php include __DIR__.'/footer.php'; ?>
