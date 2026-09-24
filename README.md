# Online Project Topic Availability Checker System

A web-based system where students check whether a project topic is available before submitting it,
and the admin (teacher) approves requests, manages topics and generates reports.

**Technology:** HTML5, CSS3, JavaScript, PHP, MySQL, XAMPP, Visual Studio Code

---

## How to run (XAMPP) - recommended for the college

1. Install **XAMPP** (https://www.apachefriends.org) if it is not installed.
2. Copy this whole folder `Online_Project_Topic_Checker` into `C:\xampp\htdocs\`
3. Open the **XAMPP Control Panel**, click **Start** for **Apache** and **MySQL**.
4. Open the browser: **http://localhost/Online_Project_Topic_Checker/**

The database `project_topic_checker` and all tables are **created automatically** on first open,
with sample topics and demo accounts. (Optional: `database.sql` can be imported in phpMyAdmin.)

## Quick run without XAMPP (backup)

Double-click **`START.bat`**. It uses the bundled PHP in `_tools\php` with an SQLite file
database and opens http://localhost:8080 . Keep the black window open while using the site.

## Demo accounts

| Role    | Username  | Password     |
|---------|-----------|--------------|
| Admin   | `admin`   | `admin123`   |
| Student | `student` | `student123` |

New students can register from the login page.

## Live links

- **Demo (GitHub Pages, works instantly):** https://nikilsuthar.github.io/online-project-topic-checker/
  Browser-only copy of the same system; data is saved in the viewer's browser.
- **Full PHP version (Render.com):** deploy with the included `Dockerfile` + `render.yaml`  
  (Render > New > Blueprint > select this repo). Uses SQLite by default; set `PTC_DB_DRIVER=mysql` and
  `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (and `DB_SSL=1` if required) to use a cloud MySQL.

---

## Modules / Features

**Student**
- Register and login (username + password, passwords stored as secure hashes)
- Dashboard: total / available / selected topics, own pending requests, recently added topics
- **Topic Checker**: instant availability check
  - *Available* - no match, the topic can be submitted for approval
  - *Similar topics found* - close matches are listed with a % match (e.g. "Online Exam Result System" vs "Online Examination System")
  - *Already in the list* - the topic exists and is available, so it can be requested
  - *Not available* - the topic is already selected by another student
  - *Already requested* - another student's request for the same topic is waiting for approval
- Search and filter existing topics, request an available topic
- Topic Requests: track status and admin remarks, cancel pending requests, propose a new topic

**Admin (Teacher)**
- Dashboard with statistics and pending requests
- Manage Topics: add, edit, delete, assign to a student, release a selected topic
- Topic Requests: approve or reject (a reason is required to reject)
- Students: list of students with their allocated topic
- Reports: topic summary, request summary, department-wise report, selected/available lists,
  print, and CSV download (opens in Excel)

## Validation and rules

- Topic title: 5-150 characters, letters/numbers/basic punctuation only, must be specific
  (e.g. "Online Management System" is rejected as too generic)
- Duplicate check ignores common words (online, system, management, website, app...) and
  plurals, so "Library Management" = "Online Library Management System"
- A student can have only **one** approved topic and at most **3** pending requests
- When a topic is approved, other students' pending requests for the same topic and the
  student's own other pending requests are closed automatically
- The duplicate check runs again when the admin approves (so two students cannot get the same topic)
- Registration: valid email, unique username/email, password with a letter + number, confirm password
- Security: prepared statements (no SQL injection), output escaping (no XSS), CSRF tokens on
  every form, role-based page access, session regeneration on login

## Folder structure

```
Online_Project_Topic_Checker/
├── index.php            Login
├── register.php         Student registration
├── logout.php
├── config.php           Database settings
├── database.sql         MySQL schema (optional import)
├── includes/            db.php (connection + auto setup), functions.php, header.php, footer.php
├── student/             dashboard, checker, topics, requests
├── admin/               dashboard, topics, requests, students, reports
├── assets/css/style.css
├── assets/js/app.js     Client-side validation, table search/filter
├── demo/index.html      Browser-only demo (used for the online link)
├── START.bat            Run without XAMPP
├── Dockerfile, render.yaml   Live hosting on Render.com
└── _tools/php/          Portable PHP used by START.bat
```

## Database tables

- **users** (id, name, username, email, roll_no, department, password, role, created_at)
- **topics** (id, title, description, department, status [Available/Selected], assigned_to, created_at, updated_at)
- **topic_requests** (id, student_id, topic_id, title, description, department, type [existing/new],
  status [Pending/Approved/Rejected], remark, created_at, updated_at)

