-- Online Project Topic Availability Checker System
-- MySQL database schema (for phpMyAdmin import - OPTIONAL).
-- The application creates these tables and the demo data automatically on first run,
-- so importing this file is not required.

CREATE DATABASE IF NOT EXISTS project_topic_checker CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE project_topic_checker;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL,
    roll_no VARCHAR(30) NULL,
    department VARCHAR(100) NULL,
    password VARCHAR(255) NOT NULL,          -- bcrypt hash (password_hash)
    role VARCHAR(10) NOT NULL DEFAULT 'student', -- 'student' or 'admin'
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    department VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Available', -- 'Available' or 'Selected'
    assigned_to INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS topic_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    topic_id INT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    department VARCHAR(100) NOT NULL,
    type VARCHAR(10) NOT NULL,                  -- 'existing' topic or 'new' proposal
    status VARCHAR(20) NOT NULL DEFAULT 'Pending', -- 'Pending', 'Approved', 'Rejected'
    remark VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
