<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_login('admin');
$pdo = db();

$selected = $pdo->query("SELECT t.*, u.name AS student_name, u.roll_no FROM topics t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.status = 'Selected' ORDER BY t.department, t.title")->fetchAll();
$available = $pdo->query("SELECT * FROM topics WHERE status = 'Available' ORDER BY department, title")->fetchAll();

/* ---------- CSV export ---------- */
if (isset($_GET['export'])) {
    $type = $_GET['export'] === 'available' ? 'available' : 'selected';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_topics_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($type === 'selected') {
        fputcsv($out, ['#', 'Project Topic', 'Department', 'Student', 'Roll No']);
        foreach ($selected as $i => $t) {
            fputcsv($out, [$i + 1, $t['title'], $t['department'], $t['student_name'], $t['roll_no']]);
        }
    } else {
        fputcsv($out, ['#', 'Project Topic', 'Department', 'Description']);
        foreach ($available as $i => $t) {
            fputcsv($out, [$i + 1, $t['title'], $t['department'], $t['description']]);
        }
    }
    fclose($out);
    exit;
}

$stats = topic_stats();
$byDept = $pdo->query("SELECT department,
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Selected' THEN 1 ELSE 0 END) AS selected
    FROM topics GROUP BY department ORDER BY department")->fetchAll();
$reqStats = $pdo->query('SELECT status, COUNT(*) AS c FROM topic_requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Reports';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Reports</h1>
    <p>Summary of selected and available project topics. Generated on <?= e(date('d M Y, h:i A')) ?>.</p>
</div>

<div class="toolbar no-print">
    <button class="btn" type="button" onclick="window.print()">🖨 Print Report</button>
    <a class="btn btn-light" href="reports.php?export=selected">⬇ Selected Topics (CSV)</a>
    <a class="btn btn-light" href="reports.php?export=available">⬇ Available Topics (CSV)</a>
</div>

<div class="report-grid">
    <div class="card">
        <h2>Topic Summary</h2>
        <p>Total topics: <b><?= $stats['total'] ?></b></p>
        <p>Available: <b><?= $stats['available'] ?></b> &nbsp; Selected: <b><?= $stats['selected'] ?></b></p>
        <div class="bar"><span style="width:<?= $stats['total'] ? round($stats['selected'] / $stats['total'] * 100) : 0 ?>%"></span></div>
        <p class="hint"><?= $stats['total'] ? round($stats['selected'] / $stats['total'] * 100) : 0 ?>% of topics are selected</p>
    </div>
    <div class="card">
        <h2>Requests Summary</h2>
        <p>Pending: <b><?= (int) ($reqStats['Pending'] ?? 0) ?></b></p>
        <p>Approved: <b><?= (int) ($reqStats['Approved'] ?? 0) ?></b></p>
        <p>Rejected: <b><?= (int) ($reqStats['Rejected'] ?? 0) ?></b></p>
    </div>
    <div class="card">
        <h2>Department-wise</h2>
        <?php foreach ($byDept as $d): ?>
            <p style="margin:8px 0 2px"><?= e($d['department']) ?>: <b><?= (int) $d['selected'] ?></b> / <?= (int) $d['total'] ?> selected</p>
            <div class="bar"><span style="width:<?= round($d['selected'] / max(1, $d['total']) * 100) ?>%"></span></div>
        <?php endforeach; ?>
        <?php if (!$byDept): ?><p class="muted">No topics yet.</p><?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Selected Topics (<?= count($selected) ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Project Topic</th><th>Department</th><th>Student</th><th>Roll No</th></tr></thead>
            <tbody>
            <?php foreach ($selected as $i => $t): ?>
                <tr><td><?= $i + 1 ?></td><td class="t-title"><?= e($t['title']) ?></td><td><?= e($t['department']) ?></td><td><?= e($t['student_name'] ?: '-') ?></td><td><?= e($t['roll_no'] ?: '-') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$selected): ?><tr><td colspan="5" class="empty">No selected topics.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Available Topics (<?= count($available) ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Project Topic</th><th>Department</th><th>Description</th></tr></thead>
            <tbody>
            <?php foreach ($available as $i => $t): ?>
                <tr><td><?= $i + 1 ?></td><td class="t-title"><?= e($t['title']) ?></td><td><?= e($t['department']) ?></td><td class="muted"><?= e($t['description']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$available): ?><tr><td colspan="4" class="empty">No available topics.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
