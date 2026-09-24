<?php
/* Login page for students and admin */
require_once __DIR__ . '/includes/functions.php';

if ($u = current_user()) {
    redirect($u['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            flash('success', 'Welcome, ' . $user['name'] . '!');
            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php');
        }
        $error = 'Invalid username or password.';
    }
}
$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="card auth-card">
        <div class="auth-brand">
            <span class="brand-logo">P</span>
            <span>
                <span class="brand-title"><?= e(APP_NAME) ?></span>
                <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
            </span>
        </div>
        <h2>Login</h2>
        <p class="sub">Students and admin (teacher) login with username and password.</p>

        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input class="input" id="username" name="username" value="<?= e($username) ?>" data-rule="required" autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input class="input" type="password" id="password" name="password" data-rule="required">
                    <button type="button" class="pw-toggle">Show</button>
                </div>
            </div>
            <button class="btn btn-block" type="submit">Login</button>
        </form>

        <div class="auth-foot">New student? <a href="register.php">Create an account</a></div>

        <div class="demo-box">
            <b>Demo accounts</b><br>
            Admin: <code>admin</code> / <code>admin123</code><br>
            Student: <code>student</code> / <code>student123</code>
        </div>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
