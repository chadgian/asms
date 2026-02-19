# Agency Submission Management System (ASMS)

A lightweight PHP + MySQL web app for managing agency submissions with three account types:

- **Admin**: creates and manages submissions, assigns agencies, reviews uploads, updates status/remarks.
- **Agency**: sees pending/submitted lists, uploads documents and remarks, receives updates.
- **Viewer**: read-only access to submissions and agency statuses.

## Tech Stack

- PHP 8+
- MySQL 8+
- HTML/CSS/JavaScript
- Local file storage for uploaded documents (`uploads/`)

## Quick Start

1. Create database and import schema:
   - Open MySQL and run `sql/schema.sql`.
2. Configure database settings in `config/config.php`.
3. Serve app from project root:

```bash
php -S 0.0.0.0:8000 -t public
```

4. Open: `http://localhost:8000`

## Default Seed Users

From `sql/schema.sql`:

- Admin: `admin@asms.local` / `password123`
- Agency A: `agencya@asms.local` / `password123`
- Agency B: `agencyb@asms.local` / `password123`
- Viewer: `viewer@asms.local` / `password123`

## Key Rules Implemented

- Agencies cannot upload when status is **approved**.
- If a submission is marked **for compliance** and agency uploads new docs, status automatically becomes **for review**.
- Dashboard for agencies is grouped:
  - top: not yet submitted
  - bottom: already submitted
- Admin can target all agencies or selected agencies.

