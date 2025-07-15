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
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Edit Store User</h4>
    <a href="edit_store.php?id=<?php echo $store_id; ?>" class="btn btn-sm btn-outline-secondary">Back</a>
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
