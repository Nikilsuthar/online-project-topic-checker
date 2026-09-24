<?php
/*
 * Database connection (PDO) + automatic table creation and sample data.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $dir = dirname(SQLITE_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $pdo = new PDO('sqlite:' . SQLITE_PATH);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $options = [];
            if (DB_SSL) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
            try {
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
            } catch (PDOException $ignored) {
                // hosted MySQL users often cannot create databases; the database must already exist
            }
            $pdo->exec('USE `' . DB_NAME . '`');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f5c2c7;background:#f8d7da;border-radius:10px;color:#842029">'
            . '<h2>Database connection failed</h2>'
            . '<p>Please start <b>Apache</b> and <b>MySQL</b> from the XAMPP Control Panel, then refresh this page.</p>'
            . '<p style="font-size:13px;color:#555">Details: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
        exit;
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    install_schema($pdo);
    seed_data($pdo);

    return $pdo;
}

function install_schema(PDO $pdo): void
{
    $sqlite = DB_DRIVER === 'sqlite';
    $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $id,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(120) NOT NULL,
        roll_no VARCHAR(30) NULL,
        department VARCHAR(100) NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(10) NOT NULL DEFAULT 'student',
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS topics (
        id $id,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        department VARCHAR(100) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Available',
        assigned_to INT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS topic_requests (
        id $id,
        student_id INT NOT NULL,
        topic_id INT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        department VARCHAR(100) NOT NULL,
        type VARCHAR(10) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        remark VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
    )$engine");
}

function seed_data(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');

    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $users = [
            ['Administrator', 'admin', 'admin@college.edu', null, 'Computer Science', 'admin123', 'admin'],
            ['Student User', 'student', 'student@college.edu', 'CS001', 'Computer Science', 'student123', 'student'],
            ['Rahul Sharma', 'rahul', 'rahul@college.edu', 'CS002', 'Computer Science', 'rahul123', 'student'],
            ['Priya Patel', 'priya', 'priya@college.edu', 'CS003', 'Computer Science', 'priya123', 'student'],
        ];
        $stmt = $pdo->prepare('INSERT INTO users (name, username, email, roll_no, department, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($users as $u) {
            $stmt->execute([$u[0], $u[1], $u[2], $u[3], $u[4], password_hash($u[5], PASSWORD_DEFAULT), $u[6], $now]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM topics')->fetchColumn() === 0) {
        $find = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $find->execute(['rahul']);
        $rahul = $find->fetchColumn() ?: null;
        $find->execute(['priya']);
        $priya = $find->fetchColumn() ?: null;

        $topics = [
            ['Online Library Management System', 'A web-based system for managing library books and students', 'Selected', $rahul],
            ['Online Hospital Management System', 'System for managing hospital records and appointments', 'Available', null],
            ['Student Attendance Management System', 'Application for managing student attendance', 'Available', null],
            ['Online Food Ordering System', 'Website for ordering food online from restaurants', 'Selected', $priya],
            ['Hostel Management System', 'Manage hostel rooms, fees and student allotment', 'Available', null],
            ['College Event Management System', 'Plan, publish and register for college events', 'Available', null],
            ['Online Examination System', 'Conduct online tests with automatic result generation', 'Available', null],
            ['Student Result Management System', 'Store marks and publish student results online', 'Available', null],
        ];
        $stmt = $pdo->prepare('INSERT INTO topics (title, description, department, status, assigned_to, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $t = time() - 3600 * count($topics);
        foreach ($topics as $row) {
            $t += 3600; // later rows are "recently added"
            $stamp = date('Y-m-d H:i:s', $t);
            $stmt->execute([$row[0], $row[1], 'Computer Science', $row[2], $row[3], $stamp, $stamp]);
        }
    }
}
