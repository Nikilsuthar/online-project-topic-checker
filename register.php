<?php
/* Student registration */
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$old = ['name' => '', 'roll_no' => '', 'username' => '', 'email' => '', 'department' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!preg_match("/^[A-Za-z .']{3,100}$/", $old['name'])) {
        $errors['name'] = 'Enter your full name (letters only, 3-100 characters).';
    }
    if (!preg_match('/^[A-Za-z0-9\/-]{2,30}$/', $old['roll_no'])) {
        $errors['roll_no'] = 'Enter a valid roll number.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $old['username'])) {
        $errors['username'] = 'Username must be 3-30 letters, numbers or underscore.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!valid_department($old['department'])) {
        $errors['department'] = 'Please select your department.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }

    if (!isset($errors['username'])) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$old['username']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['username'] = 'This username is already taken.';
        }
    }
    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (name, username, email, roll_no, department, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$old['name'], $old['username'], $old['email'], strtoupper($old['roll_no']), $old['department'],
            password_hash($password, PASSWORD_DEFAULT), 'student', now()]);
        flash('success', 'Registration successful. Please login.');
        redirect('index.php');
    }
}

function err(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="card auth-card wide">
        <div class="auth-brand">
            <span class="brand-logo">P</span>
            <span>
                <span class="brand-title"><?= e(APP_NAME) ?></span>
                <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
            </span>
        </div>
        <h2>Student Registration</h2>
        <p class="sub">Create your account to check and request project topics.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error">Please correct the highlighted fields.</div>
        <?php endif; ?>

        <form method="post" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="grid-2">
                <div class="field">
                    <label for="name">Full Name</label>
                    <input class="input<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= e($old['name']) ?>" data-rule="required|min:3|letters">
                    <?= err($errors, 'name') ?>
                </div>
                <div class="field">
                    <label for="roll_no">Roll Number</label>
                    <input class="input<?= isset($errors['roll_no']) ? ' is-invalid' : '' ?>" id="roll_no" name="roll_no" value="<?= e($old['roll_no']) ?>" data-rule="required|min:2|max:30">
                    <?= err($errors, 'roll_no') ?>
                </div>
                <div class="field">
                    <label for="username">Username</label>
                    <input class="input<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="username" name="username" value="<?= e($old['username']) ?>" data-rule="required|username">
                    <?= err($errors, 'username') ?>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e($old['email']) ?>" data-rule="required|email">
                    <?= err($errors, 'email') ?>
                </div>
                <div class="field">
                    <label for="department">Department</label>
                    <select class="input<?= isset($errors['department']) ? ' is-invalid' : '' ?>" id="department" name="department" data-rule="required">
                        <option value="">-- Select --</option>
                        <?php foreach (DEPARTMENTS as $d): ?>
                            <option <?= $old['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= err($errors, 'department') ?>
                </div>
                <div></div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" type="password" id="password" name="password" data-rule="required|min:6">
                        <button type="button" class="pw-toggle">Show</button>
                    </div>
                    <?= err($errors, 'password') ?>
                    <div class="hint">At least 6 characters with a letter and a number.</div>
                </div>
                <div class="field">
                    <label for="confirm">Confirm Password</label>
                    <input class="input<?= isset($errors['confirm']) ? ' is-invalid' : '' ?>" type="password" id="confirm" name="confirm" data-rule="required|match:password">
                    <?= err($errors, 'confirm') ?>
                </div>
            </div>
            <button class="btn btn-block" type="submit">Register</button>
        </form>
        <div class="auth-foot">Already registered? <a href="index.php">Login</a></div>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
