<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('admin');

$stats = topic_stats();
$pending = db()->query("SELECT r.*, u.name AS student_name, u.roll_no FROM topic_requests r JOIN users u ON u.id = r.student_id WHERE r.status = 'Pending' ORDER BY r.created_at ASC, r.id ASC LIMIT 5")->fetchAll();
$recent = db()->query('SELECT t.*, u.name AS student_name FROM topics t LEFT JOIN users u ON u.id = t.assigned_to ORDER BY t.updated_at DESC, t.id DESC LIMIT 5')->fetchAll();

$pageTitle = 'Admin Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Admin Dashboard</h1>
    <p>Manage project topics, approve student requests and generate reports.</p>
</div>

<div class="stats">
    <div class="stat"><div class="stat-icon i-blue">📚</div><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">Total Project Topics</div></div>
    <div class="stat"><div class="stat-icon i-green">✅</div><div class="stat-num"><?= $stats['available'] ?></div><div class="stat-label">Available Topics</div></div>
    <div class="stat"><div class="stat-icon i-red">🔒</div><div class="stat-num"><?= $stats['selected'] ?></div><div class="stat-label">Selected Topics</div></div>
    <div class="stat"><div class="stat-icon i-amber">⏳</div><div class="stat-num"><?= $stats['pending'] ?></div><div class="stat-label">Pending Requests</div></div>
    <div class="stat"><div class="stat-icon i-purple">🎓</div><div class="stat-num"><?= $stats['students'] ?></div><div class="stat-label">Registered Students</div></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Pending Requests</h2>
        <a class="btn btn-sm" href="requests.php">View All Requests</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Project Topic</th><th>Type</th><th>Requested On</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $r): ?>
                <tr>
                    <td><div class="t-title"><?= e($r['student_name']) ?></div><div class="t-desc"><?= e($r['roll_no']) ?></div></td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= $r['type'] === 'new' ? '<span class="badge badge-new">New Topic</span>' : '<span class="badge badge-existing">Existing</span>' ?></td>
                    <td class="muted"><?= e(nice_date($r['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pending): ?><tr><td colspan="4" class="empty">No pending requests. 🎉</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Recently Updated Topics</h2>
        <a class="btn btn-sm" href="topics.php">Manage Topics</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Project Topic</th><th>Department</th><th>Status</th><th>Assigned To</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $t): ?>
                <tr>
                    <td class="t-title"><?= e($t['title']) ?></td>
                    <td><?= e($t['department']) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                    <td><?= e($t['student_name'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
