<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('admin');
$pdo = db();

/* ---------- Approve / Reject ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $remark = trim($_POST['remark'] ?? '');
    if (mb_strlen($remark) > 255) {
        $remark = mb_substr($remark, 0, 255);
    }

    $stmt = $pdo->prepare("SELECT * FROM topic_requests WHERE id = ? AND status = 'Pending'");
    $stmt->execute([(int) ($_POST['id'] ?? 0)]);
    $req = $stmt->fetch();

    if (!$req) {
        flash('error', 'Request not found or already processed.');
        redirect('admin/requests.php');
    }

    if ($action === 'reject') {
        if ($remark === '') {
            flash('error', 'Please enter a reason when rejecting a request.');
            redirect('admin/requests.php');
        }
        $pdo->prepare("UPDATE topic_requests SET status = 'Rejected', remark = ?, updated_at = ? WHERE id = ?")
            ->execute([$remark, now(), $req['id']]);
        flash('success', 'Request rejected.');
        redirect('admin/requests.php');
    }

    if ($action === 'approve') {
        $sid = (int) $req['student_id'];
        if (student_topic($sid)) {
            flash('error', 'This student already has an approved topic.');
            redirect('admin/requests.php');
        }

        $pdo->beginTransaction();
        try {
            if ($req['type'] === 'existing') {
                $stmt = $pdo->prepare("UPDATE topics SET status = 'Selected', assigned_to = ?, updated_at = ? WHERE id = ? AND status = 'Available'");
                $stmt->execute([$sid, now(), (int) $req['topic_id']]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('This topic is no longer available.');
                }
                $topicId = (int) $req['topic_id'];
            } else {
                $check = check_topic($req['title'], 0, (int) $req['id']);
                if (in_array($check['state'], ['taken', 'exists', 'invalid'], true)) {
                    throw new RuntimeException('Cannot approve: ' . $check['message']);
                }
                $pdo->prepare("INSERT INTO topics (title, description, department, status, assigned_to, created_at, updated_at) VALUES (?, ?, ?, 'Selected', ?, ?, ?)")
                    ->execute([$req['title'], $req['description'], $req['department'], $sid, now(), now()]);
                $topicId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare("UPDATE topic_requests SET status = 'Approved', topic_id = ?, remark = ?, updated_at = ? WHERE id = ?")
                ->execute([$topicId, $remark !== '' ? $remark : 'Approved', now(), $req['id']]);

            // Other students who asked for the same topic
            $pdo->prepare("UPDATE topic_requests SET status = 'Rejected', remark = 'Topic assigned to another student', updated_at = ? WHERE topic_id = ? AND status = 'Pending'")
                ->execute([now(), $topicId]);
            $others = $pdo->prepare("SELECT id, title FROM topic_requests WHERE type = 'new' AND status = 'Pending'");
            $others->execute();
            foreach ($others->fetchAll() as $o) {
                if (topic_similarity($o['title'], $req['title']) >= 1.0) {
                    $pdo->prepare("UPDATE topic_requests SET status = 'Rejected', remark = 'Topic assigned to another student', updated_at = ? WHERE id = ?")
                        ->execute([now(), $o['id']]);
                }
            }
            // The same student's other pending requests
            $pdo->prepare("UPDATE topic_requests SET status = 'Rejected', remark = 'Another topic was approved for you', updated_at = ? WHERE student_id = ? AND status = 'Pending'")
                ->execute([now(), $sid]);

            $pdo->commit();
            flash('success', 'Request approved. "' . $req['title'] . '" is now assigned to the student.');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            flash('error', $ex->getMessage());
        }
        redirect('admin/requests.php');
    }

    redirect('admin/requests.php');
}

/* ---------- List ---------- */
$filter = $_GET['status'] ?? 'Pending';
if (!in_array($filter, ['Pending', 'Approved', 'Rejected', 'All'], true)) {
    $filter = 'Pending';
}
$sql = 'SELECT r.*, u.name AS student_name, u.roll_no FROM topic_requests r JOIN users u ON u.id = r.student_id';
if ($filter !== 'All') {
    $stmt = $pdo->prepare($sql . ' WHERE r.status = ? ORDER BY r.created_at DESC, r.id DESC');
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query($sql . ' ORDER BY r.created_at DESC, r.id DESC');
}
$requests = $stmt->fetchAll();

$pageTitle = 'Topic Requests';
$active = 'requests';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Topic Requests</h1>
    <p>Approve or reject topics requested by students. Duplicate checks run again on approval.</p>
</div>

<div class="card">
    <div class="toolbar">
        <?php foreach (['Pending', 'Approved', 'Rejected', 'All'] as $s): ?>
            <a class="btn btn-sm <?= $filter === $s ? '' : 'btn-light' ?>" href="requests.php?status=<?= $s ?>"><?= $s ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Project Topic</th><th>Type</th><th>Requested On</th><th>Status</th><th>Action / Remark</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <?php $dupe = $r['status'] === 'Pending' && $r['type'] === 'new' ? check_topic($r['title'], 0, (int) $r['id']) : null; ?>
                <tr>
                    <td><div class="t-title"><?= e($r['student_name']) ?></div><div class="t-desc"><?= e($r['roll_no']) ?></div></td>
                    <td>
                        <div class="t-title"><?= e($r['title']) ?></div>
                        <div class="t-desc"><?= e($r['department']) ?><?= $r['description'] ? ' — ' . e($r['description']) : '' ?></div>
                        <?php if ($dupe && $dupe['matches']): ?>
                            <div class="t-desc" style="color:var(--amber)">⚠ Similar to: <?= e(implode(', ', array_column(array_slice($dupe['matches'], 0, 3), 'title'))) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $r['type'] === 'new' ? '<span class="badge badge-new">New Topic</span>' : '<span class="badge badge-existing">Existing</span>' ?></td>
                    <td class="muted"><?= e(nice_date($r['created_at'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'Pending'): ?>
                            <form method="post" class="action-cell">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input class="input" style="padding:7px 10px;font-size:13px;min-width:150px;flex:1" name="remark" maxlength="255" placeholder="Remark (required to reject)">
                                <button class="btn btn-sm btn-green" name="action" value="approve" type="submit">Approve</button>
                                <button class="btn btn-sm btn-red" name="action" value="reject" type="submit">Reject</button>
                            </form>
                        <?php else: ?>
                            <span class="muted"><?= e($r['remark'] ?: '-') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?><tr><td colspan="6" class="empty">No <?= $filter === 'All' ? '' : strtolower($filter) ?> requests.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
