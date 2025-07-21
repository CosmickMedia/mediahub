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

        /* Users Table Card */
        .users-card {
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

        /* User Avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.125rem;
        }

        .user-username {
            font-size: 0.875rem;
            color: #6c757d;
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
            text-decoration: none;
        }

        .btn-action-primary {
            background: #667eea;
            color: white;
        }

        .btn-action-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            color: white;
        }

        .btn-action-danger {
            background: #dc3545;
            color: white;
        }

        .btn-action-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Add User Form */
        .add-user-card {
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

        .form-text-modern {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .btn-add-user {
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

        .btn-add-user:hover {
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

        /* Password Strength Indicator */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.5rem;
            background: #e9ecef;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: var(--transition);
            border-radius: 2px;
        }

        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #28a745; width: 75%; }
        .strength-strong { background: #20c997; width: 100%; }

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

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }
        }
    </style>

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

            <div class="stat-card success animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="stat-icon">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $recent_users; ?>">0</div>
                <div class="stat-label">Recent (30 days)</div>
                <div class="stat-bg"></div>
            </div>

            <div class="stat-card warning animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="stat-icon">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="stat-number" data-count="<?php echo $active_users; ?>">0</div>
                <div class="stat-label">Active Users</div>
                <div class="stat-bg"></div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-card animate__animated animate__fadeIn" style="animation-delay: 0.3s">
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
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-action btn-action-primary" title="Edit User">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <button class="btn btn-action btn-action-danger" name="delete" title="Delete User">
                                                <i class="bi bi-trash"></i> Delete
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
        <div class="add-user-card animate__animated animate__fadeIn" style="animation-delay: 0.4s">
            <div class="card-header-modern">
                <h5 class="card-title-modern">
                    <i class="bi bi-person-plus"></i>
                    Add New Admin User
                </h5>
            </div>
            <form method="post">
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