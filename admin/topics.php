<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('admin');
$pdo = db();

$errors = [];
$form = ['id' => 0, 'title' => '', 'description' => '', 'department' => DEPARTMENTS[0], 'status' => 'Available', 'assigned_to' => ''];

/* ---------- Handle actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM topics WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Topic deleted.');
        redirect('admin/topics.php');
    }

    if ($action === 'release') {
        $pdo->prepare("UPDATE topics SET status = 'Available', assigned_to = NULL, updated_at = ? WHERE id = ?")
            ->execute([now(), (int) ($_POST['id'] ?? 0)]);
        flash('success', 'Topic released and marked as Available.');
        redirect('admin/topics.php');
    }

    if ($action === 'save') {
        $form = [
            'id' => (int) ($_POST['id'] ?? 0),
            'title' => trim(preg_replace('/\s+/', ' ', $_POST['title'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'status' => $_POST['status'] ?? 'Available',
            'assigned_to' => (string) ($_POST['assigned_to'] ?? ''),
        ];

        $check = check_topic($form['title'], $form['id']);
        if ($check['state'] === 'invalid') {
            $errors['title'] = $check['message'];
        } elseif (in_array($check['state'], ['taken', 'exists'], true)) {
            $errors['title'] = 'A topic with the same title already exists: "' . $check['topic']['title'] . '".';
        }
        if ($msg = validate_description($form['description'], false)) {
            $errors['description'] = $msg;
        }
        if (!valid_department($form['department'])) {
            $errors['department'] = 'Select a department.';
        }
        if (!in_array($form['status'], ['Available', 'Selected'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        $assigned = null;
        if ($form['status'] === 'Selected') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
            $stmt->execute([(int) $form['assigned_to']]);
            $student = $stmt->fetch();
            if (!$student) {
                $errors['assigned_to'] = 'Select the student this topic is assigned to.';
            } else {
                $existing = student_topic((int) $student['id']);
                if ($existing && (int) $existing['id'] !== $form['id']) {
                    $errors['assigned_to'] = $student['name'] . ' already has the topic "' . $existing['title'] . '".';
                }
                $assigned = (int) $student['id'];
            }
        }

        if (!$errors) {
            if ($form['id']) {
                $pdo->prepare('UPDATE topics SET title = ?, description = ?, department = ?, status = ?, assigned_to = ?, updated_at = ? WHERE id = ?')
                    ->execute([$form['title'], $form['description'], $form['department'], $form['status'], $assigned, now(), $form['id']]);
                flash('success', 'Topic updated successfully.');
            } else {
                $pdo->prepare('INSERT INTO topics (title, description, department, status, assigned_to, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$form['title'], $form['description'], $form['department'], $form['status'], $assigned, now(), now()]);
                flash('success', 'Topic added successfully.');
            }
            if ($assigned) {
                // the student got a topic: close their other pending requests
                $pdo->prepare("UPDATE topic_requests SET status = 'Rejected', remark = 'Topic assigned by admin', updated_at = ? WHERE student_id = ? AND status = 'Pending'")
                    ->execute([now(), $assigned]);
            }
            redirect('admin/topics.php');
        }
    }
}

/* ---------- Edit mode ---------- */
if (!$errors && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM topics WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    if ($t = $stmt->fetch()) {
        $form = ['id' => (int) $t['id'], 'title' => $t['title'], 'description' => (string) $t['description'],
            'department' => $t['department'], 'status' => $t['status'], 'assigned_to' => (string) $t['assigned_to']];
    }
}

$topics = $pdo->query('SELECT t.*, u.name AS student_name, u.roll_no FROM topics t LEFT JOIN users u ON u.id = t.assigned_to ORDER BY t.title')->fetchAll();
$students = $pdo->query("SELECT id, name, roll_no FROM users WHERE role = 'student' ORDER BY name")->fetchAll();

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
}

$pageTitle = 'Manage Topics';
$active = 'topics';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Manage Project Topics</h1>
    <p>Add, edit, delete, assign or release project topics.</p>
</div>

<div class="card" id="topic-form">
    <h2><?= $form['id'] ? 'Edit Topic' : 'Add New Topic' ?></h2>
    <p class="sub">Duplicate titles are blocked automatically.</p>
    <form method="post" action="topics.php#topic-form" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="grid-2">
            <div class="field">
                <label>Project Topic</label>
                <input class="input<?= isset($errors['title']) ? ' is-invalid' : '' ?>" name="title" maxlength="150" value="<?= e($form['title']) ?>" data-rule="required|min:5|max:150|topic|letters">
                <?= field_error($errors, 'title') ?>
            </div>
            <div class="field">
                <label>Department</label>
                <select class="input" name="department">
                    <?php foreach (DEPARTMENTS as $d): ?>
                        <option <?= $form['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'department') ?>
            </div>
            <div class="field">
                <label>Status</label>
                <select class="input" name="status" id="statusSelect">
                    <option <?= $form['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                    <option <?= $form['status'] === 'Selected' ? 'selected' : '' ?>>Selected</option>
                </select>
                <?= field_error($errors, 'status') ?>
            </div>
            <div class="field" id="assignField">
                <label>Assigned To (when Selected)</label>
                <select class="input<?= isset($errors['assigned_to']) ? ' is-invalid' : '' ?>" name="assigned_to">
                    <option value="">-- Select student --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $form['assigned_to'] === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name'] . ' (' . $s['roll_no'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'assigned_to') ?>
            </div>
        </div>
        <div class="field">
            <label>Description</label>
            <textarea class="input<?= isset($errors['description']) ? ' is-invalid' : '' ?>" name="description" data-rule="max:1000"><?= e($form['description']) ?></textarea>
            <?= field_error($errors, 'description') ?>
        </div>
        <button class="btn" type="submit"><?= $form['id'] ? 'Update Topic' : 'Add Topic' ?></button>
        <?php if ($form['id']): ?><a class="btn btn-light" href="topics.php">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>All Topics (<?= count($topics) ?>)</h2>
    <div class="toolbar" style="margin-top:14px">
        <div class="search-wrap"><input class="input" placeholder="Search topics..." data-filter="#adminTopics"></div>
        <select class="input" data-status-filter="#adminTopics">
            <option value="">All Status</option>
            <option value="Available">Available</option>
            <option value="Selected">Selected</option>
        </select>
    </div>
    <div class="table-wrap">
        <table id="adminTopics">
            <thead><tr><th>Project Topic</th><th>Department</th><th>Status</th><th>Assigned To</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($topics as $t): ?>
                <tr data-row data-status="<?= e($t['status']) ?>">
                    <td><div class="t-title"><?= e($t['title']) ?></div><div class="t-desc"><?= e($t['description']) ?></div></td>
                    <td><?= e($t['department']) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                    <td><?= $t['student_name'] ? e($t['student_name']) . '<div class="t-desc">' . e($t['roll_no']) . '</div>' : '<span class="muted">-</span>' ?></td>
                    <td>
                        <div class="action-cell">
                            <a class="btn btn-sm btn-light" href="topics.php?edit=<?= (int) $t['id'] ?>#topic-form">Edit</a>
                            <?php if ($t['status'] === 'Selected'): ?>
                                <form method="post" class="inline" data-confirm="Release this topic and make it Available again?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="release">
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <button class="btn btn-sm btn-green" type="submit">Release</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="inline" data-confirm="Delete this topic permanently?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <button class="btn btn-sm btn-red" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr data-empty-filter style="display:none"><td colspan="5" class="empty">No topics match your search.</td></tr>
            <?php if (!$topics): ?><tr><td colspan="5" class="empty">No topics yet. Add one above.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    // Show the "Assigned To" field only when status is Selected
    (function () {
        var s = document.getElementById('statusSelect'), f = document.getElementById('assignField');
        function sync() { f.style.visibility = s.value === 'Selected' ? 'visible' : 'hidden'; }
        s.addEventListener('change', sync); sync();
    })();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
