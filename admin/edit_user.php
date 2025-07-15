<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_login();
$pdo = get_pdo();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = trim($_POST['username'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = format_mobile_number($_POST['mobile_phone'] ?? '');
    $optin = $_POST['opt_in_status'] ?? 'confirmed';
    if ($username === '' || $email === '') {
        $errors[] = 'Username and email are required';
    } else {
        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET username=?, password=?, first_name=?, last_name=?, email=?, mobile_phone=?, opt_in_status=? WHERE id=?');
            $stmt->execute([$username, $hash, $first ?: null, $last ?: null, $email, $mobile ?: null, $optin, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username=?, first_name=?, last_name=?, email=?, mobile_phone=?, opt_in_status=? WHERE id=?');
            $stmt->execute([$username, $first ?: null, $last ?: null, $email, $mobile ?: null, $optin, $id]);
        }
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
        if (!$ghSuccess) {
            $errors[] = 'Groundhogg sync failed: ' . $ghMessage;
        }
        $success = true;
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$active = 'users';
include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Edit User</h4>
    <a href="users.php" class="btn btn-sm btn-outline-secondary">Back</a>
</div>
<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        User updated successfully
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required value="<?php echo htmlspecialchars($user['username']); ?>">
            </div>
            <div class="col-md-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep same">
            </div>
            <div class="col-md-4">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>">
            </div>
            <div class="col-md-4">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>">
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>
            <div class="col-md-4">
                <label for="mobile_phone" class="form-label">Mobile Phone</label>
                <input type="text" name="mobile_phone" id="mobile_phone" class="form-control" value="<?php echo htmlspecialchars($user['mobile_phone']); ?>">
            </div>
            <div class="col-md-4">
                <label for="opt_in_status" class="form-label">Opt-in Status</label>
                <select name="opt_in_status" id="opt_in_status" class="form-select">
                    <?php $statuses=['unconfirmed','confirmed','unsubscribed','subscribed_weekly','subscribed_monthly','bounced','spam','complained','blocked'];
                    foreach($statuses as $status): ?>
                        <option value="<?php echo $status; ?>"<?php echo $user['opt_in_status']==$status?' selected':''; ?>><?php echo ucfirst(str_replace('_',' ', $status)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" name="save_user" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__.'/footer.php'; ?>
