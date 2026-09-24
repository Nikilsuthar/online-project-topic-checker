<?php
/*
 * Page layout (top bar + sidebar). Expects $user, $pageTitle and $active to be set.
 */
$role = $user['role'];
$menu = $role === 'admin'
    ? [
        'dashboard' => ['admin/dashboard.php', '📊', 'Dashboard'],
        'topics' => ['admin/topics.php', '📚', 'Manage Topics'],
        'requests' => ['admin/requests.php', '📝', 'Topic Requests'],
        'students' => ['admin/students.php', '🎓', 'Students'],
        'reports' => ['admin/reports.php', '📈', 'Reports'],
    ]
    : [
        'dashboard' => ['student/dashboard.php', '📊', 'Dashboard'],
        'checker' => ['student/checker.php', '🔍', 'Topic Checker'],
        'topics' => ['student/topics.php', '📚', 'Project Topics'],
        'requests' => ['student/requests.php', '📝', 'Topic Requests'],
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <button class="menu-toggle" type="button" aria-label="Toggle menu" data-menu-toggle>☰</button>
    <a class="brand" href="<?= e(url($menu['dashboard'][0])) ?>">
        <span class="brand-logo">P</span>
        <span>
            <span class="brand-title"><?= e(APP_NAME) ?></span>
            <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
        </span>
    </a>
    <div class="user-box">
        <div class="user-info">
            <strong><?= e($user['name']) ?></strong>
            <small><?= $role === 'admin' ? 'Admin' : 'Student' ?></small>
        </div>
        <a class="btn btn-logout" href="<?= e(url('logout.php')) ?>">Logout</a>
    </div>
</header>

<div class="layout">
    <aside class="sidebar" data-sidebar>
        <div class="menu-label">Main Menu</div>
        <nav>
            <?php foreach ($menu as $key => [$href, $icon, $label]): ?>
                <a class="nav-link<?= $key === $active ? ' active' : '' ?>" href="<?= e(url($href)) ?>">
                    <span class="nav-icon"><?= $icon ?></span><?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="content">
        <?php foreach (take_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>" data-autohide><?= e($f['message']) ?></div>
        <?php endforeach; ?>
