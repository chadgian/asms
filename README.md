# Agency Submission Management System (ASMS)

Modern PHP + MySQL management system for Admin, Agency, and Viewer users.

## Highlights
- Root entrypoint is now `index.php` (easy navigation on XAMPP/InfinityFree).
- Modern animated UI with improved spacing/padding and smooth transitions.
- Username login (not email).
- Admin notifications for newly uploaded agency documents.
- Admin can add agency/viewer accounts with province and sector metadata.
- Viewer dashboards are province-scoped and read-only.
- Agency documents table shows document status and download actions.

## XAMPP Setup
1. Copy this project to `C:\xampp\htdocs\asms`.
2. Start Apache + MySQL in XAMPP.
3. Create a database in phpMyAdmin.
4. Import `sql/schema.sql`.
5. Update DB values in `config/config.php` if needed.
6. Open `http://localhost/asms/`.

## InfinityFree Setup
1. Create a MySQL database in InfinityFree panel.
2. Import `sql/schema.sql` through phpMyAdmin.
3. Upload all project files to `htdocs`.
4. Set DB credentials in `config/config.php`.
5. Ensure `uploads/` is writable.

## Default Accounts
Password for all: `password123`
- `admin` (admin)
- `agency_a` (agency)
- `agency_b` (agency)
- `viewer_aklan` (viewer, scoped to Aklan)

## Schema Notes
- `schema.sql` drops/recreates tables to avoid duplicate-column errors.
- Uses `province` and `sector` fields for agencies/viewers.
- Uses `file_template_name` so template download keeps the original filename.
