# Agency Submission Management System (ASMS)

A PHP + MySQL system for managing document submissions with three roles:
- **Admin**
- **Agency**
- **Viewer (read-only)**

## Features
- Username/password login
- Agency dashboard grouped into:
  - Not Yet Submitted
  - Already Submitted
- Agency upload with remarks
- Admin submission creation/editing with optional file template
- Admin agency review with statuses:
  - Not Yet Submitted
  - For Review
  - For Compliance
  - Approved
- Notification updates to agencies
- Problem reporting page
- Local document storage in `/uploads`

## Required Stack
- PHP 8.x (XAMPP PHP is fine)
- MySQL / MariaDB (XAMPP phpMyAdmin is fine)

## Local Setup (XAMPP)
1. Copy project folder to:
   - `C:\xampp\htdocs\asms`
2. Start **Apache** and **MySQL** in XAMPP Control Panel.
3. Open phpMyAdmin and create a database (example: `asms`).
4. Select the database, then import `sql/schema.sql`.
5. Update DB settings in `config/config.php` if needed.
6. Open app:
   - `http://localhost/asms/public`

## Default Accounts
Password for all accounts: `password123`
- Admin: `admin`
- Agency A: `agency_a`
- Agency B: `agency_b`
- Viewer: `viewer`

## If You Encounter Import Errors
- Use a **new empty database** before importing.
- `schema.sql` already drops/recreates tables to avoid duplicate-column issues.
- The previous error `#1060 Duplicate column name 'admin'` is resolved by using a clean users table definition and full table recreation in this schema.

## InfinityFree Deployment Guide
InfinityFree does not support Node/Vercel-style deployment. Deploy as standard PHP hosting.

1. In InfinityFree control panel, create your MySQL database.
2. Get DB credentials (DB host, DB name, DB user, DB password).
3. Import `sql/schema.sql` in InfinityFree phpMyAdmin.
4. Upload project files via File Manager or FTP to your domain directory (`htdocs`).
5. Update `config/config.php` values:
   - `DB_HOST`
   - `DB_PORT` (usually `3306`)
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
6. Ensure `uploads/` is writable.
7. Open your site URL and login.

## Notes for InfinityFree
- Shared hosting may restrict some PHP settings and filesystem operations.
- Local-file uploads can work, but for scale you may move files to cloud storage later.
