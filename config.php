<?php
/*
 * Online Project Topic Availability Checker System
 * Configuration file
 *
 * For XAMPP keep the default MySQL settings below (user "root", empty password).
 * The database and tables are created automatically on first run.
 */

define('APP_NAME', 'Project Topic Checker');
define('APP_TAGLINE', 'Online Project Topic Availability Checker');

// Database driver: 'mysql' (XAMPP / phpMyAdmin) or 'sqlite' (no setup needed)
define('DB_DRIVER', getenv('PTC_DB_DRIVER') ?: 'mysql');

// MySQL settings (XAMPP defaults). On an online host these can be set as environment variables.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'project_topic_checker');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_SSL', getenv('DB_SSL') === '1'); // set DB_SSL=1 for cloud MySQL that requires SSL

// SQLite file (used only when DB_DRIVER = 'sqlite')
define('SQLITE_PATH', __DIR__ . '/data/project_topic_checker.sqlite');

// Departments shown in forms
define('DEPARTMENTS', [
    'Computer Science',
    'Information Technology',
    'BCA',
    'MCA',
    'Electronics',
    'Mechanical',
    'Civil',
]);

// A student may have at most this many pending requests at once
define('MAX_PENDING_REQUESTS', 3);

date_default_timezone_set('Asia/Kolkata');
