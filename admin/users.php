<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/helpers.php';
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
        if ($username === '' || $password === '' || $email === '') {
            $errors[] = 'Username, password and email are required';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$username, $hash, $first ?: null, $last ?: null, $email]);
                $success[] = 'User added';
            } catch (PDOException $e) {
                $errors[] = 'User already exists';
            }
        }
    } elseif (isset($_POST['delete']) && isset($_POST['id'])) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
        $stmt->execute([$_POST['id']]);
        $success[] = 'User deleted';
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
                <div class="col-md-6">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" name="first_name" id="first_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="last_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" name="add" type="submit">Add User</button>
                </div>
            </form>
        </div>
    </div>

<?php include __DIR__.'/footer.php'; ?>
