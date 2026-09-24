<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('student');
$sid = (int) $user['id'];
$pdo = db();

/* ---------- Handle actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel') {
        $stmt = $pdo->prepare("DELETE FROM topic_requests WHERE id = ? AND student_id = ? AND status = 'Pending'");
        $stmt->execute([(int) ($_POST['id'] ?? 0), $sid]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Request cancelled.' : 'Request could not be cancelled.');
        redirect('student/requests.php');
    }

    if ($action === 'existing' || $action === 'new') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM topic_requests WHERE student_id = ? AND status = 'Pending'");
        $stmt->execute([$sid]);
        $pendingCount = (int) $stmt->fetchColumn();

        if (student_topic($sid)) {
            flash('error', 'You already have an approved project topic. You cannot request another one.');
            redirect('student/requests.php');
        }
        if ($pendingCount >= MAX_PENDING_REQUESTS) {
            flash('error', 'You already have ' . MAX_PENDING_REQUESTS . ' pending requests. Cancel one or wait for the admin.');
            redirect('student/requests.php');
        }

        if ($action === 'existing') {
            $stmt = $pdo->prepare('SELECT * FROM topics WHERE id = ?');
            $stmt->execute([(int) ($_POST['topic_id'] ?? 0)]);
            $topic = $stmt->fetch();
            if (!$topic) {
                flash('error', 'Topic not found.');
                redirect('student/checker.php');
            }
            if ($topic['status'] !== 'Available') {
                flash('error', 'Sorry, "' . $topic['title'] . '" is no longer available.');
                redirect('student/checker.php');
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM topic_requests WHERE student_id = ? AND topic_id = ? AND status = 'Pending'");
            $stmt->execute([$sid, $topic['id']]);
            if ($stmt->fetchColumn() > 0) {
                flash('error', 'You have already requested this topic.');
                redirect('student/requests.php');
            }
            $pdo->prepare('INSERT INTO topic_requests (student_id, topic_id, title, description, department, type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$sid, $topic['id'], $topic['title'], $topic['description'], $topic['department'], 'existing', 'Pending', now(), now()]);
            flash('success', 'Request sent for "' . $topic['title'] . '". Wait for admin approval.');
            redirect('student/requests.php');
        }

        // New topic proposal
        $title = trim(preg_replace('/\s+/', ' ', $_POST['title'] ?? ''));
        if ($title === strtolower($title)) {
            $title = ucwords($title); // "pg finder website" -> "Pg Finder Website"
        }
        $description = trim($_POST['description'] ?? '');
        $department = trim($_POST['department'] ?? '');

        $check = check_topic($title);
        $error = null;
        if ($check['state'] === 'invalid') {
            $error = $check['message'];
        } elseif (in_array($check['state'], ['taken', 'exists', 'pending'], true)) {
            $error = $check['message'];
        } elseif ($msg = validate_description($description)) {
            $error = $msg;
        } elseif (!valid_department($department)) {
            $error = 'Please select a valid department.';
        }
        if ($error) {
            flash('error', $error);
            redirect('student/requests.php');
        }

        $pdo->prepare('INSERT INTO topic_requests (student_id, topic_id, title, description, department, type, status, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$sid, $title, $description, $department, 'new', 'Pending', now(), now()]);
        flash('success', 'Your new topic "' . $title . '" was submitted for approval.');
        redirect('student/requests.php');
    }

    redirect('student/requests.php');
}

/* ---------- Page ---------- */
$stmt = $pdo->prepare('SELECT * FROM topic_requests WHERE student_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$sid]);
$requests = $stmt->fetchAll();
$myTopic = student_topic($sid);

$pageTitle = 'Topic Requests';
$active = 'requests';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Topic Requests</h1>
    <p>Track the status of topics you requested or proposed.</p>
</div>

<?php if ($myTopic): ?>
    <div class="alert alert-success">🎉 Approved topic: <b><?= e($myTopic['title']) ?></b></div>
<?php endif; ?>

<div class="card">
    <h2>My Requests</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Project Topic</th><th>Type</th><th>Requested On</th><th>Status</th><th>Admin Remark</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><div class="t-title"><?= e($r['title']) ?></div><div class="t-desc"><?= e($r['department']) ?></div></td>
                    <td><?= $r['type'] === 'new' ? '<span class="badge badge-new">New Topic</span>' : '<span class="badge badge-existing">Existing</span>' ?></td>
                    <td class="muted"><?= e(nice_date($r['created_at'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="muted"><?= e($r['remark'] ?: '-') ?></td>
                    <td>
                        <?php if ($r['status'] === 'Pending'): ?>
                            <form method="post" class="inline" data-confirm="Cancel this request?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-light" type="submit">Cancel</button>
                            </form>
                        <?php else: ?><span class="muted">-</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="6" class="empty">You have not requested any topic yet. Use the <a href="checker.php">Topic Checker</a> to find one.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$myTopic): ?>
<div class="card">
    <h2>Propose a New Topic</h2>
    <p class="sub">The topic is checked for duplicates automatically before it is submitted.</p>
    <form method="post" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="new">
        <div class="grid-2">
            <div class="field">
                <label>Project Topic</label>
                <input class="input" name="title" maxlength="150" placeholder="e.g. PG Finder Website" data-rule="required|min:5|max:150|topic|letters">
            </div>
            <div class="field">
                <label>Department</label>
                <select class="input" name="department" data-rule="required">
                    <?php foreach (DEPARTMENTS as $d): ?>
                        <option <?= $user['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label>Short Description</label>
            <textarea class="input" name="description" placeholder="What will this project do?" data-rule="required|min:10|max:1000"></textarea>
        </div>
        <button class="btn" type="submit">Submit for Approval</button>
    </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
