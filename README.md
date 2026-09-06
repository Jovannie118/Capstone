# Paracale Scholarship Management System (HTML / CSS / JS + PHP + MySQL)

A standalone version of the system that runs on XAMPP, WAMP, MAMP or any
Apache + PHP + MySQL server. No build step, no npm — plain HTML, CSS and
JavaScript on the front end, PHP endpoints on the back end.

## Setup (XAMPP)

1. Copy the whole `php-app` folder into `C:\xampp\htdocs\` and rename it if
   you like, e.g. `htdocs/paracale`.
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open <http://localhost/phpmyadmin>, click **Import**, choose
   `database.sql` and run it. This creates the `paracale_sms` database with
   sample applications and one admin account.
4. If your MySQL user or password is different, edit `api/config.php`
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
5. Make sure the `uploads/` folder exists and is writable — student documents
   are stored there.
6. Open <http://localhost/paracale/index.html>.

## Admin sign in

| Email | Password |
| --- | --- |
| `admin@paracale.edu` | `paracale2026` |

## Pages

**Public**

- `index.html` — home page with how-it-works and quick links
- `apply.html` — student application: full name, email, address, school and
  the four required documents
- `status.html` — students look up their reference code or email to see if
  they were approved
- `login.html` — administrator sign in

**Admin (session protected)**

- `dashboard.html` — cycle statistics, budget utilisation, top ranked
- `applications.html` — searchable register, detail view, decisions
- `verification.html` — document verification queue
- `approvals.html` — committee approvals against the live budget
- `reports.html` — analytics and CSV export
- `notifications.html` — audit trail of every submission and decision

## Required documents

1. Registration Form
2. School ID with 3 Signatures
3. Barangay Indigency
4. Certificate of Grade (COG)

PDF, JPG and PNG up to 8 MB each.

## API endpoints

| Endpoint | Purpose |
| --- | --- |
| `api/auth.php?action=login\|logout\|session` | admin session |
| `api/apply.php` | student submission with uploads |
| `api/status.php?q=` | public status lookup |
| `api/applications.php` | list / single application (admin) |
| `api/verify.php` | verify or reject a document (admin) |
| `api/decision.php` | set application status and award (admin) |
| `api/reports.php` | analytics and CSV export (admin) |
| `api/notifications.php` | notification feed (admin) |

## Scoring

Weighted composite score, shown on the dashboard, ranking and reports:

- Academics 45%
- Financial need 30%
- Extracurriculars 15%
- Essay 10%
- minus 6 points if any document was rejected
