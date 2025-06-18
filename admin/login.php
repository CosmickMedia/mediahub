<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';

session_start();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login($_POST['username'], $_POST['password'])) {
        header('Location: index.php');
        exit;
    } else {
        $errors[] = 'Login failed';
    }
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
</head>
<body class="container">
<h4>Admin Login</h4>
<?php foreach ($errors as $e) echo "<p class=red-text>$e</p>"; ?>
<form method="post">
    <div class="input-field"><input type="text" name="username" id="username" required><label for="username">Username</label></div>
    <div class="input-field"><input type="password" name="password" id="password" required><label for="password">Password</label></div>
    <button class="btn" type="submit">Login</button>
</form>
</body>
</html>
