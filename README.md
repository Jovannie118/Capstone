# Paracale Scholarship Management System (HTML / CSS / JS + PHP + MySQL)

A standalone version of the system that runs on XAMPP, WAMP, MAMP or any
Apache + PHP + MySQL server. No build step, no npm — plain HTML, CSS and
JavaScript on the front end, PHP endpoints on the back end.

## Setup (XAMPP)

1. Copy this folder into `C:\xampp\htdocs\` and rename it if you like, e.g.
   `htdocs/paracale` or keep `htdocs/project`.
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open <http://localhost/phpmyadmin>, click **Import**, choose
   `database.sql` and run it. This creates the `paracale_sms` database with
   sample applications and one admin account.
4. If your MySQL user or password is different, edit `api/config.php`
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
5. Open <http://localhost/project/index.html>.

   > For installs upgraded from an earlier version that stored files on disk,
   > also run `migration.sql` (adds the `audit_log` table, settings rows and
   > document verification columns). New uploads are always stored inside the
   > database (`LONGBLOB`) — nothing is written to `uploads/`.

## Admin sign in

| Email | Password |
| --- | --- |
| `admin@paracale.edu` | `paracale2026` |

## Pages

**Public**

- `index.html` — home page with how-it-works, the current application window
  status and quick links
- `apply.html` — student application: full name, email, address, school and
  the four required documents (disabled while the application window is closed)
- `status.html` — students look up their reference code or email to see if
  they were approved
- `login.html` — administrator sign in

**Admin (session protected)**

- `dashboard.html` — cycle statistics, budget utilisation, top ranked
- `applications.html` — register with server-side search (name, application
  ID, email, mobile), status and document filters, and pagination
- `verification.html` — document verification queue with search, filters and
  pagination
- `approvals.html` — committee approvals against the live budget
- `reports.html` — analytics and CSV export
- `notifications.html` — audit trail of every submission, verification,
  decision and settings change
- `settings.html` — open/close the application window and set the cycle dates

## Application window

The relevant application period is managed on the admin **Settings** page
(`settings.html`) rather than in code:

- `application_window` — `OPEN` or `CLOSED`
- `application_open_date` / `application_close_date` — displayed on the home
  page and inside `apply.html`

While the window is `CLOSED`, the home page shows a notice, the Apply button
is replaced/disabled, and `api/apply.php` rejects submissions with the
configurable message. Window changes are recorded in the audit trail.

## Required documents

1. Registration Form
2. School ID with 3 Signatures
3. Barangay Indigency
4. Certificate of Grade (COG)

PDF, JPG and PNG up to 8 MB each, stored as BLOBs inside the database.

## Audit trail

Every important action is written to the `audit_log` table and shown under
**Notifications** (`notifications.html`):

- Admin sign-in
- Application submissions
- Per-document verify / reject (with an optional rejection reason)
- Application decisions (approve / waitlist / reject, with optional remarks)
- Application-window settings changes

## API endpoints

| Endpoint | Purpose |
| --- | --- |
| `api/auth.php?action=login\|logout\|session` | admin session |
| `api/apply.php` | student submission; files stored in the database; 403 when the window is closed |
| `api/view_document.php` | stream a document from the database (admin) |
| `api/status.php?q=` | public status lookup |
| `api/applications.php` | list / single application (admin). List supports `q`, `status`, `doc_status`, `page` and `per_page` (SQL-side search, filter and pagination) |
| `api/verify.php` | verify or reject a document (admin) |
| `api/decision.php` | set application status and award (admin) |
| `api/reports.php` | analytics and CSV export (admin) |
| `api/notifications.php` | audit trail feed (admin) |
| `api/window.php` | public application-window status |
| `api/settings.php` | read / update window settings (admin) |

## Scoring

Weighted composite score, shown on the dashboard, applications and reports:

- Academics 45%
- Financial need 30%
- Extracurriculars 15%
- Essay 10%
- minus 6 points if any document was rejected