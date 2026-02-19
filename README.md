# Agency Submission Management System (ASMS)

Modern PHP + MySQL management system for Admin, Agency, and Viewer users.

## Latest Updates
- Redesigned submission pages so submission title/details are visually distinct from agency lists.
- Admin submission form improvements:
  - multiple template file upload
  - date-only deadline
  - auto-open agency modal when scope = selected
  - line-by-line alphabetical agency list with aligned checkboxes
  - selected-agency preview + "Edit Selected Agency" button
- Accounts editing now uses modal (no full-page reload when opening edit).
- Submission dashboard now lists agencies first, with per-agency view.
- Agency document batches are grouped and shown in reverse chronological order with timestamp headings.
- Document status update now uses modal with larger remarks area.
- Viewer header simplified to Dashboard + Logout.
- Added Statistics page (Admin and Viewer) showing per-province completion % and counts.
- Realtime as-you-type search in major lists/tables.
- Buttons and action labels standardized for clarity (Download for downloadable items).

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


## Seed Data
- Added Region VI (Western Visayas) sample government agencies with province + sector tags.
- Agency usernames use agency identifiers and default password `password123`.
- Added one viewer account per province (`viewer_aklan`, `viewer_antique`, `viewer_capiz`, `viewer_guimaras`, `viewer_iloilo`, `viewer_negocc`).
