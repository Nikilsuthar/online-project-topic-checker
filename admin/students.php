<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    // free the student's topic before removing the account
    $pdo->prepare("UPDATE topics SET status = 'Available', assigned_to = NULL, updated_at = ? WHERE assigned_to = ?")->execute([now(), $id]);
    $pdo->prepare('DELETE FROM topic_requests WHERE student_id = ?')->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$id]);
    flash('success', 'Student removed and their topic (if any) is Available again.');
    redirect('admin/students.php');
}

$students = $pdo->query("SELECT u.*, t.title AS topic_title,
        (SELECT COUNT(*) FROM topic_requests r WHERE r.student_id = u.id) AS request_count
    FROM users u LEFT JOIN topics t ON t.assigned_to = u.id
    WHERE u.role = 'student' ORDER BY u.name")->fetchAll();

$pageTitle = 'Students';
$active = 'students';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Registered Students</h1>
    <p>All student accounts and their allocated project topics.</p>
</div>

<div class="card">
    <div class="toolbar">
        <div class="search-wrap"><input class="input" placeholder="Search students..." data-filter="#studentTable"></div>
    </div>
    <div class="table-wrap">
        <table id="studentTable">
            <thead><tr><th>Name</th><th>Roll No</th><th>Username / Email</th><th>Department</th><th>Allocated Topic</th><th>Requests</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr data-row>
                    <td class="t-title"><?= e($s['name']) ?></td>
                    <td><?= e($s['roll_no']) ?></td>
                    <td><?= e($s['username']) ?><div class="t-desc"><?= e($s['email']) ?></div></td>
                    <td><?= e($s['department']) ?></td>
                    <td><?= $s['topic_title'] ? e($s['topic_title']) : '<span class="muted">Not allocated</span>' ?></td>
                    <td><?= (int) $s['request_count'] ?></td>
                    <td>
                        <form method="post" class="inline" data-confirm="Delete this student account? Their topic will become Available.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-sm btn-red" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr data-empty-filter style="display:none"><td colspan="7" class="empty">No students match your search.</td></tr>
            <?php if (!$students): ?><tr><td colspan="7" class="empty">No students registered yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
