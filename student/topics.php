<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('student');

$topics = db()->query('SELECT * FROM topics ORDER BY department, title')->fetchAll();

$pageTitle = 'Project Topics';
$active = 'topics';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Project Topics</h1>
    <p>All project topics maintained by the department, with their current availability.</p>
</div>

<div class="card">
    <div class="toolbar">
        <div class="search-wrap"><input class="input" placeholder="Search by title, description or department..." data-filter="#allTopics"></div>
        <select class="input" data-status-filter="#allTopics">
            <option value="">All Status</option>
            <option value="Available">Available</option>
            <option value="Selected">Selected</option>
        </select>
    </div>
    <div class="table-wrap">
        <table id="allTopics">
            <thead><tr><th>#</th><th>Project Topic</th><th>Department</th><th>Added On</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($topics as $i => $t): ?>
                <tr data-row data-status="<?= e($t['status']) ?>">
                    <td class="muted"><?= $i + 1 ?></td>
                    <td><div class="t-title"><?= e($t['title']) ?></div><div class="t-desc"><?= e($t['description']) ?></div></td>
                    <td><?= e($t['department']) ?></td>
                    <td class="muted"><?= e(date('d M Y', strtotime($t['created_at']))) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr data-empty-filter style="display:none"><td colspan="5" class="empty">No topics match your search.</td></tr>
            <?php if (!$topics): ?><tr><td colspan="5" class="empty">No topics added yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="hint">To request a topic, use the <a href="checker.php">Topic Checker</a>.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
