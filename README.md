# Agency Submission Management System (ASMS)

Modern PHP + MySQL management system for Admin, Agency, and Viewer users.

## Highlights
- Root entrypoint is `index.php`.
- Modern UI with improved spacing + transitions.
- Multi-file uploads for agency and admin.
- Admin submission list now uses **View** button to open submission-level dashboard (no crowded global agency overview table).
- Admin can update document status directly in each agency section within a submission dashboard.
- Account management supports add/edit + search for large-scale use.
- Viewer has admin-like read-only monitoring (submission dashboards + file downloads) scoped to assigned province.

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
- Schema drops/recreates tables to avoid duplicate structure/import issues.
- `uploaded_documents` now tracks uploader role/user and supports multi-file workflow.
- `users` includes `province` and `sector` for agency/viewer scoping.
