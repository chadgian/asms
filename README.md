# Agency Submission Management System (ASMS)

Modern PHP + MySQL management system for Admin, Agency, and Viewer users.

## Latest Updates
- Submission form now supports **multiple file templates** upload.
- Deadline input is now **date-only**.
- Scope agency selection now uses a **searchable modal** with improved checkbox alignment.
- Search in tables/lists is now **realtime as you type** (no page reload).
- Admin submission list now shows per-submission **View** and **Edit** actions with updated button color scheme:
  - View = dark
  - Edit = light
- Admin submission dashboard now first shows agencies only, with **View** per agency to inspect grouped uploaded batches.
- Admin can update **document status per file**; statuses are tracked individually.
- Agency uploads are grouped by upload batch token and each document has its own status.
- Notifications include the submission name and are searchable.

## XAMPP Setup
1. Copy project to `C:\xampp\htdocs\asms`.
2. Start Apache + MySQL.
3. Create database in phpMyAdmin.
4. Import `sql/schema.sql`.
5. Update `config/config.php` DB settings if needed.
6. Open `http://localhost/asms/`.

## InfinityFree Setup
1. Create MySQL DB in InfinityFree panel.
2. Import `sql/schema.sql`.
3. Upload files to `htdocs`.
4. Set DB values in `config/config.php`.
5. Ensure `uploads/` directory is writable.

## Default Accounts
Password for all: `password123`
- `admin`
- `agency_a`
- `agency_b`
- `viewer_aklan` (viewer scoped to Aklan)

## Schema Notes
- Schema recreates all tables for clean imports.
- `submission_templates` stores multiple templates per submission.
- `uploaded_documents` stores per-document status, batch token, uploader metadata, and admin remarks.
