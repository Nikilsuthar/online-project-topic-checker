<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('student');

$stats = topic_stats();
$stmt = db()->prepare("SELECT COUNT(*) FROM topic_requests WHERE student_id = ? AND status = 'Pending'");
$stmt->execute([$user['id']]);
$myPending = (int) $stmt->fetchColumn();
$myTopic = student_topic((int) $user['id']);
$recent = db()->query('SELECT * FROM topics ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <p>Welcome to the Online Project Topic Availability Checker and Management System.</p>
</div>

<div class="stats">
    <div class="stat"><div class="stat-icon i-blue">📚</div><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">Total Project Topics</div></div>
    <div class="stat"><div class="stat-icon i-green">✅</div><div class="stat-num"><?= $stats['available'] ?></div><div class="stat-label">Available Topics</div></div>
    <div class="stat"><div class="stat-icon i-red">🔒</div><div class="stat-num"><?= $stats['selected'] ?></div><div class="stat-label">Selected Topics</div></div>
    <div class="stat"><div class="stat-icon i-amber">⏳</div><div class="stat-num"><?= $myPending ?></div><div class="stat-label">My Pending Requests</div></div>
</div>

<?php if ($myTopic): ?>
    <div class="alert alert-success">
        🎉 Your project topic is approved: <b><?= e($myTopic['title']) ?></b> (<?= e($myTopic['department']) ?>)
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>Quick Topic Availability Check</h2>
        <a class="btn" href="checker.php">Open Checker</a>
    </div>
    <p class="muted" style="margin:0">Students can search for a project topic and instantly verify whether the topic is available before submitting it.</p>
</div>

<div class="card">
    <h2>Recently Added Topics</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Project Topic</th><th>Department</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $t): ?>
                <tr>
                    <td class="t-title"><?= e($t['title']) ?></td>
                    <td><?= e($t['department']) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="3" class="empty">No topics added yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
