<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_login();
$pdo = get_pdo();

$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = format_mobile_number($_POST['mobile_phone'] ?? '');
        $optin = $_POST['opt_in_status'] ?? 'confirmed';
        if ($username === '' || $password === '' || $email === '') {
            $errors[] = 'Username, password and email are required';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, mobile_phone, opt_in_status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$username, $hash, $first ?: null, $last ?: null, $email, $mobile ?: null, $optin]);
                $success[] = 'User added';

                $location = groundhogg_get_location();
                $contact = [
                    'email'       => $email,
                    'first_name'  => $first,
                    'last_name'   => $last,
                    'mobile_phone'=> $mobile,
                    'address'     => $location['address'],
                    'city'        => $location['city'],
                    'state'       => $location['state'],
                    'zip'         => $location['zip'],
                    'country'     => $location['country'],
                    'user_role'   => 'Admin User',
                    'company_name'=> 'Cosmick Media',
                    'lead_source' => 'cosmick-employee',
                    'opt_in_status'=> $optin,
                    'tags'        => groundhogg_get_default_tags()
                ];
                [$ghSuccess, $ghMessage] = groundhogg_send_contact($contact);
                if ($ghSuccess) {
                    $success[] = $ghMessage;
                } else {
                    $errors[] = 'Groundhogg sync failed: ' . $ghMessage;
                }
            } catch (PDOException $e) {
                $errors[] = 'User already exists';
            }
        }
    } elseif (isset($_POST['delete']) && isset($_POST['id'])) {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id=?');
        $stmt->execute([$_POST['id']]);
        $email = $stmt->fetchColumn();

        $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
        $stmt->execute([$_POST['id']]);
        $success[] = 'User deleted';

        if ($email) {
            [$delSuccess, $delMsg] = groundhogg_delete_contact($email);
            if (!$delSuccess) {
                $errors[] = 'Groundhogg delete failed: ' . $delMsg;
            }
        }
    }
}

$users = $pdo->query('SELECT id, username, first_name, last_name, email, created_at FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_users = count($users);
$recent_users = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE email IS NOT NULL AND email != ''")->fetchColumn();

$active = 'users';
include __DIR__.'/header.php';
?>


    <div class="animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <h1 class="page-title">Admin Users</h1>
            <p class="page-subtitle">Manage administrator accounts and permissions</p>
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
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $total_users; ?>">0</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card success animate__animated animate__fadeInUp delay-10">
                <div class="stat-icon">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $recent_users; ?>">0</div>
                <div class="stat-label">Recent (30 days)</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card warning animate__animated animate__fadeInUp delay-20">
                <div class="stat-icon">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $active_users; ?>">0</div>
                <div class="stat-label">Active Users</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-card animate__animated animate__fadeIn delay-30">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-list-ul"></i>
                    All Admin Users
                </h5>
            </div>
            <div class="card-body-modern">
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="bi bi-people"></i>
                        <h4>No users yet</h4>
                        <p>Add your first admin user below to get started</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php
                                                $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                                if ($name) {
                                                    echo strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? 'U', 0, 1));
                                                } else {
                                                    echo strtoupper(substr($u['username'], 0, 2));
                                                }
                                                ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name">
                                                    <?php
                                                    $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                                    echo htmlspecialchars($fullName ?: 'No name set');
                                                    ?>
                                                </div>
                                                <div class="user-username">@<?php echo htmlspecialchars($u['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($u['email']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($u['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($u['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($u['created_at'])); ?></small>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-action btn-action-primary" title="Edit User">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" class="delete-user-form" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <button class="btn btn-action btn-action-danger" name="delete" title="Delete User">
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

        <!-- Add New User -->
        <div class="add-user-card animate__animated animate__fadeIn delay-40">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-person-plus"></i>
                    Add New Admin User
                </h5>
            </div>
            <form method="post" class="card-body-modern add-user-form">
                <div class="form-section">
                    <h6 class="section-title">Account Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label-modern">
                                <i class="bi bi-person"></i> Username *
                            </label>
                            <input type="text" name="username" id="username" class="form-control form-control-modern" required>
                            <div class="form-text-modern">Unique identifier for login</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label-modern">
                                <i class="bi bi-lock"></i> Password *
                            </label>
                            <input type="password" name="password" id="password" class="form-control form-control-modern" required>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="form-text-modern">Minimum 8 characters recommended</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6 class="section-title">Personal Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label-modern">
                                <i class="bi bi-person"></i> First Name
                            </label>
                            <input type="text" name="first_name" id="first_name" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label-modern">
                                <i class="bi bi-person"></i> Last Name
                            </label>
                            <input type="text" name="last_name" id="last_name" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label-modern">
                                <i class="bi bi-envelope"></i> Email Address *
                            </label>
                            <input type="email" name="email" id="email" class="form-control form-control-modern" required>
                            <div class="form-text-modern">Used for notifications and account recovery</div>
                        </div>
                        <div class="col-md-6">
                            <label for="mobile_phone" class="form-label-modern">
                                <i class="bi bi-telephone"></i> Mobile Phone
                            </label>
                            <input type="text" name="mobile_phone" id="mobile_phone" class="form-control form-control-modern">
                            <div class="form-text-modern">Include country code for international numbers</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6 class="section-title">Communication Preferences</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="opt_in_status" class="form-label-modern">
                                <i class="bi bi-envelope-check"></i> Opt-in Status
                            </label>
                            <select name="opt_in_status" id="opt_in_status" class="form-select form-select-modern">
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
                            <div class="form-text-modern">Controls email communication preferences</div>
                        </div>
                    </div>
                </div>

                <div class="form-section text-end">
                    <button class="btn btn-add-user" name="add" type="submit">
                        <i class="bi bi-person-plus"></i> Add Admin User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Counter animations
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('[data-count]');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const duration = 1000;
                const step = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 16);
            });
        });

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrengthBar');

        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = getPasswordStrength(password);

                strengthBar.className = 'password-strength-bar';
                if (strength.score > 0) {
                    strengthBar.classList.add(`strength-${strength.level}`);
                }
            });
        }

        function getPasswordStrength(password) {
            let score = 0;
            let level = 'weak';

            if (password.length >= 8) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^a-zA-Z0-9]/)) score++;

            if (score >= 4) level = 'strong';
            else if (score >= 3) level = 'good';
            else if (score >= 2) level = 'fair';

            return { score, level };
        }
    </script>

<?php include __DIR__.'/footer.php'; ?>