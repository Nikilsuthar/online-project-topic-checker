<?php
/*
 * Common helpers: sessions, security, validation and topic availability checking.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ---------- Output / navigation ---------- */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Relative prefix from the current script back to the project root ('' or '../')
function base_prefix(): string
{
    static $prefix = null;
    if ($prefix === null) {
        $app = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $dir = str_replace('\\', '/', realpath(dirname($_SERVER['SCRIPT_FILENAME'])));
        $rel = trim(substr($dir, strlen($app)), '/');
        $prefix = $rel === '' ? '' : str_repeat('../', count(explode('/', $rel)));
    }
    return $prefix;
}

function url(string $path): string
{
    return base_prefix() . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function nice_date(?string $value): string
{
    return $value ? date('d M Y, h:i A', strtotime($value)) : '-';
}

/* ---------- CSRF protection ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Please go back, refresh the page and try again.');
    }
}

/* ---------- Authentication ---------- */

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function require_login(string $role): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please login to continue.');
        redirect('index.php');
    }
    if ($user['role'] !== $role) {
        redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php');
    }
    return $user;
}

/* ---------- Validation ---------- */

function validate_topic_title(string $title): ?string
{
    $len = mb_strlen($title);
    if ($title === '') {
        return 'Please enter a project topic.';
    }
    if ($len < 5) {
        return 'Project topic is too short (minimum 5 characters).';
    }
    if ($len > 150) {
        return 'Project topic is too long (maximum 150 characters).';
    }
    if (!preg_match("/^[A-Za-z0-9 .,&()\\/'+:-]+$/", $title)) {
        return 'Project topic may contain only letters, numbers, spaces and . , & ( ) / \' + : -';
    }
    if (!preg_match('/[A-Za-z]{2,}/', $title)) {
        return 'Project topic must contain meaningful words.';
    }
    if (!core_words($title)) {
        return 'Topic is too generic. Add what the system is about, e.g. "PG Finder Website".';
    }
    return null;
}

function validate_description(string $text, bool $required = true): ?string
{
    $len = mb_strlen($text);
    if ($required && $len === 0) {
        return 'Please enter a short description of the project.';
    }
    if ($len > 0 && $len < 10) {
        return 'Description is too short (minimum 10 characters).';
    }
    if ($len > 1000) {
        return 'Description is too long (maximum 1000 characters).';
    }
    return null;
}

function valid_department(string $dept): bool
{
    return in_array($dept, DEPARTMENTS, true);
}

/* ---------- Topic matching ---------- */

// Generic words that do not decide whether two topics are the same
const TOPIC_STOPWORDS = [
    'a', 'an', 'the', 'of', 'for', 'and', 'in', 'on', 'to', 'with', 'using', 'by', 'based', 'via',
    'system', 'systems', 'online', 'website', 'web', 'site', 'app', 'application', 'management',
    'portal', 'project', 'platform', 'software', 'tool', 'digital', 'smart',
];

function normalize_title(string $title): string
{
    $title = strtolower($title);
    $title = preg_replace('/[^a-z0-9]+/', ' ', $title);
    return trim(preg_replace('/\s+/', ' ', $title));
}

function core_words(string $title): array
{
    $words = [];
    foreach (explode(' ', normalize_title($title)) as $w) {
        if ($w === '' || in_array($w, TOPIC_STOPWORDS, true)) {
            continue;
        }
        // simple plural handling: "books" -> "book"
        if (strlen($w) > 3 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') {
            $w = substr($w, 0, -1);
        }
        $words[$w] = true;
    }
    return array_keys($words);
}

function topic_similarity(string $a, string $b): float
{
    $wa = core_words($a);
    $wb = core_words($b);
    if (!$wa || !$wb) {
        return 0.0;
    }
    $common = 0;
    foreach ($wa as $x) {
        foreach ($wb as $y) {
            if (words_match($x, $y)) {
                $common++;
                break;
            }
        }
    }
    $all = count($wa) + count($wb) - $common;
    return $all > 0 ? min(1.0, $common / $all) : 0.0;
}

// Same word, or a short form of it ("exam" / "examination", "hotel" / "hotels")
function words_match(string $a, string $b): bool
{
    if ($a === $b) {
        return true;
    }
    $short = strlen($a) < strlen($b) ? $a : $b;
    $long = $short === $a ? $b : $a;
    return strlen($short) >= 4 && strpos($long, $short) === 0;
}

/*
 * Checks a proposed title against all topics and pending new-topic requests.
 * Returns ['state' => available|similar|exists|taken|pending|invalid, 'message' => ..., 'topic' => ?, 'matches' => [...]]
 * $ignoreRequestId lets an admin re-check a request without matching the request itself.
 */
function check_topic(string $title, int $ignoreTopicId = 0, int $ignoreRequestId = 0): array
{
    $title = trim(preg_replace('/\s+/', ' ', $title));
    $error = validate_topic_title($title);
    if ($error) {
        return ['state' => 'invalid', 'message' => $error, 'topic' => null, 'matches' => []];
    }

    $exact = null;
    $matches = [];
    foreach (db()->query('SELECT * FROM topics')->fetchAll() as $topic) {
        if ((int) $topic['id'] === $ignoreTopicId) {
            continue;
        }
        $score = topic_similarity($title, $topic['title']);
        if ($score >= 1.0 || normalize_title($title) === normalize_title($topic['title'])) {
            $exact = $topic;
        } elseif ($score >= 0.5) {
            $topic['score'] = $score;
            $matches[] = $topic;
        }
    }
    usort($matches, fn($x, $y) => $y['score'] <=> $x['score']);

    if ($exact) {
        if ($exact['status'] === 'Selected') {
            return ['state' => 'taken', 'topic' => $exact, 'matches' => $matches,
                'message' => 'This topic is already selected by another student and is not available.'];
        }
        return ['state' => 'exists', 'topic' => $exact, 'matches' => $matches,
            'message' => 'This topic already exists in the list and is available. You can request it.'];
    }

    $stmt = db()->prepare("SELECT * FROM topic_requests WHERE status = 'Pending' AND type = 'new' AND id <> ?");
    $stmt->execute([$ignoreRequestId]);
    foreach ($stmt->fetchAll() as $req) {
        if (topic_similarity($title, $req['title']) >= 1.0) {
            return ['state' => 'pending', 'topic' => null, 'matches' => $matches,
                'message' => 'Another student has already requested this topic and it is waiting for approval.'];
        }
    }

    if ($matches) {
        return ['state' => 'similar', 'topic' => null, 'matches' => $matches,
            'message' => 'No exact match, but similar topics exist. You can still submit it; the admin will review it.'];
    }

    return ['state' => 'available', 'topic' => null, 'matches' => [],
        'message' => 'No existing project topic matches "' . $title . '". You can submit this topic for approval.'];
}

/* ---------- Small queries used on several pages ---------- */

function topic_stats(): array
{
    $pdo = db();
    return [
        'total' => (int) $pdo->query('SELECT COUNT(*) FROM topics')->fetchColumn(),
        'available' => (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE status = 'Available'")->fetchColumn(),
        'selected' => (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE status = 'Selected'")->fetchColumn(),
        'pending' => (int) $pdo->query("SELECT COUNT(*) FROM topic_requests WHERE status = 'Pending'")->fetchColumn(),
        'students' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    ];
}

function student_topic(int $studentId): ?array
{
    $stmt = db()->prepare('SELECT * FROM topics WHERE assigned_to = ?');
    $stmt->execute([$studentId]);
    return $stmt->fetch() ?: null;
}

function status_badge(string $status): string
{
    $class = strtolower($status);
    return '<span class="badge badge-' . e($class) . '">' . e($status) . '</span>';
}
