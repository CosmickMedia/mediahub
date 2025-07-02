<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';

$errors = [];
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login($_POST['username'], $_POST['password'])) {
        header('Location: index.php');
        exit;
    } else {
        $errors[] = 'Login failed';
    }
}
include __DIR__.'/login_header.php';
?>
<h4>Admin Login</h4>
<?php foreach ($errors as $e) echo "<div class=\"alert alert-danger\">$e</div>"; ?>
<form method="post">
    <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" name="username" id="username" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" name="password" id="password" class="form-control" required>
    </div>
    <button class="btn btn-primary" type="submit">Login</button>
</form>
<p class="mt-3"><a class="btn btn-danger" href="google_login.php">Login with Google</a></p>
</div>
<?php include __DIR__.'/footer.php'; ?>

