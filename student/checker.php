<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('student');

$q = trim(preg_replace('/\s+/', ' ', $_GET['q'] ?? ''));
$result = $q !== '' ? check_topic($q) : null;
if (isset($_GET['q']) && $q === '') {
    $result = ['state' => 'invalid', 'message' => 'Please enter a project topic to check.', 'matches' => [], 'topic' => null];
}
if ($result && $result['state'] === 'taken' && (int) $result['topic']['assigned_to'] === (int) $user['id']) {
    $result['message'] = 'This topic is already approved for you.';
}

$topics = db()->query('SELECT * FROM topics ORDER BY title')->fetchAll();
$myTopic = student_topic((int) $user['id']);
$stmt = db()->prepare("SELECT topic_id FROM topic_requests WHERE student_id = ? AND status = 'Pending' AND topic_id IS NOT NULL");
$stmt->execute([$user['id']]);
$requestedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

$titles = [
    'available' => '✓ Topic Appears to be Available',
    'similar' => '⚠ Similar Topics Found',
    'exists' => 'ℹ Topic Already in the List',
    'taken' => '✗ Topic Not Available',
    'pending' => '✗ Topic Already Requested',
    'invalid' => '✗ Please Check Your Input',
];

$pageTitle = 'Topic Checker';
$active = 'checker';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Project Topic Availability Checker</h1>
    <p>Search and check whether a project topic is available.</p>
</div>

<div class="card narrow">
    <h2>Check Project Topic Availability</h2>
    <p class="sub">Enter your proposed project topic below.</p>
    <form method="get" class="form-row" data-validate novalidate>
        <div class="grow">
            <input class="input" name="q" value="<?= e($q) ?>" placeholder="Example: Online Library Management System" maxlength="150" data-rule="required|min:5|topic|letters">
        </div>
        <button class="btn" type="submit">Check Availability</button>
    </form>
</div>

<?php if ($result): ?>
    <div class="result result-<?= e($result['state']) ?>">
        <h3><?= e($titles[$result['state']]) ?></h3>
        <p><?= e($result['message']) ?></p>

        <?php if ($result['topic']): ?>
            <p>Existing topic: <b><?= e($result['topic']['title']) ?></b> — <?= e($result['topic']['department']) ?> — <?= status_badge($result['topic']['status']) ?></p>
        <?php endif; ?>

        <?php if ($result['matches']): ?>
            <p style="margin-top:10px"><b>Similar existing topics:</b></p>
            <ul>
                <?php foreach (array_slice($result['matches'], 0, 5) as $m): ?>
                    <li><?= e($m['title']) ?> — <?= status_badge($m['status']) ?> <span class="muted">(<?= round($m['score'] * 100) ?>% match)</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($myTopic && in_array($result['state'], ['available', 'similar', 'exists'], true)): ?>
            <p style="margin-top:10px"><i>You already have an approved topic (<?= e($myTopic['title']) ?>), so you cannot request another one.</i></p>

        <?php elseif ($result['state'] === 'exists' && $result['topic']): ?>
            <div class="actions">
                <?php if (in_array((int) $result['topic']['id'], $requestedIds, true)): ?>
                    <span class="badge badge-pending">You have already requested this topic</span>
                <?php else: ?>
                    <form method="post" action="requests.php" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="existing">
                        <input type="hidden" name="topic_id" value="<?= (int) $result['topic']['id'] ?>">
                        <button class="btn btn-green" type="submit">Request This Topic</button>
                    </form>
                <?php endif; ?>
            </div>

        <?php elseif (in_array($result['state'], ['available', 'similar'], true)): ?>
            <form method="post" action="requests.php" class="actions" data-validate novalidate style="color:var(--text)">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="new">
                <input type="hidden" name="title" value="<?= e($q) ?>">
                <div class="grid-2">
                    <div class="field">
                        <label>Department</label>
                        <select class="input" name="department" data-rule="required">
                            <?php foreach (DEPARTMENTS as $d): ?>
                                <option <?= $user['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Topic</label>
                        <input class="input" value="<?= e($q) ?>" disabled>
                    </div>
                </div>
                <div class="field">
                    <label>Short Description</label>
                    <textarea class="input" name="description" placeholder="What will this project do? (10-1000 characters)" data-rule="required|min:10|max:1000"></textarea>
                </div>
                <button class="btn btn-green" type="submit">Submit Topic for Approval</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Search Existing Topics</h2>
    <div class="toolbar" style="margin-top:14px">
        <div class="search-wrap"><input class="input" placeholder="Search project topic..." data-filter="#topicTable"></div>
        <select class="input" data-status-filter="#topicTable">
            <option value="">All Status</option>
            <option value="Available">Available</option>
            <option value="Selected">Selected</option>
        </select>
    </div>
    <div class="table-wrap">
        <table id="topicTable">
            <thead><tr><th>Project Topic</th><th>Department</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($topics as $t): ?>
                <tr data-row data-status="<?= e($t['status']) ?>">
                    <td><div class="t-title"><?= e($t['title']) ?></div><div class="t-desc"><?= e($t['description']) ?></div></td>
                    <td><?= e($t['department']) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                    <td>
                        <?php if ($t['status'] !== 'Available'): ?>
                            <span class="muted">-</span>
                        <?php elseif (in_array((int) $t['id'], $requestedIds, true)): ?>
                            <span class="badge badge-pending">Requested</span>
                        <?php elseif ($myTopic): ?>
                            <span class="muted">-</span>
                        <?php else: ?>
                            <form method="post" action="requests.php" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="existing">
                                <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                <button class="btn btn-sm" type="submit">Request</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr data-empty-filter style="display:none"><td colspan="4" class="empty">No topics match your search.</td></tr>
            <?php if (!$topics): ?><tr><td colspan="4" class="empty">No topics added yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
