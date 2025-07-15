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

                $contact = [
                    'email'       => $email,
                    'first_name'  => $first,
                    'last_name'   => $last,
                    'mobile_phone'=> $mobile,
                    'address'     => '1147 Jacobsburg Rd.',
                    'city'        => 'Wind Gap',
                    'state'       => 'PA',
                    'zip'         => '18091',
                    'country'     => 'US',
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

$active = 'users';
include __DIR__.'/header.php';
?>
    <h4 class="mb-4">Admin Users</h4>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

<?php foreach ($success as $s): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($s); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo format_ts($u['created_at']); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button class="btn btn-sm btn-danger" name="delete" onclick="return confirm('Delete this user?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Add Admin User</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" name="first_name" id="first_name" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="last_name" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label for="mobile_phone" class="form-label">Mobile Phone</label>
                    <input type="text" name="mobile_phone" id="mobile_phone" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="opt_in_status" class="form-label">Opt-in Status</label>
                    <select name="opt_in_status" id="opt_in_status" class="form-select">
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
                    <button class="btn btn-primary" name="add" type="submit">Add User</button>
                </div>
            </form>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>
