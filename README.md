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

From `sql/schema.sql` (all passwords are `password123`):

- Admin: `admin`
- Agency A: `agency_a`
- Agency B: `agency_b`
- Viewer: `viewer`

## Important Note (If Login Fails)

If you imported an older schema using `email` accounts, refresh the `users` table by re-running `sql/schema.sql` on a clean database or updating your schema/data to match the new `username`-based login.

## Key Rules Implemented

- Agencies cannot upload when status is **approved**.
- If a submission is marked **for compliance** and agency uploads new docs, status automatically becomes **for review**.
- Dashboard for agencies is grouped:
  - top: not yet submitted
  - bottom: already submitted
- Admin can target all agencies or selected agencies.

## Deploying on Vercel (PHP + MySQL)

> Vercel can run PHP through a serverless runtime setup. For production, ensure your MySQL database is publicly reachable or via a secure hosted provider.

1. Install Vercel CLI and login:

```bash
npm i -g vercel
vercel login
```

2. Add a `vercel.json` in project root:

```json
{
  "version": 2,
  "builds": [
    { "src": "public/index.php", "use": "vercel-php@0.7.3" }
  ],
  "routes": [
    { "src": "/(.*)", "dest": "/public/index.php" }
  ]
}
```

3. Set production environment variables in Vercel Dashboard (or CLI), then map them in `config/config.php` (recommended enhancement):
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

4. Deploy:

```bash
vercel
```

5. For production deployment:

```bash
vercel --prod
```

6. After deploy, import `sql/schema.sql` to your production MySQL database and verify `uploads/` strategy (local ephemeral storage is not ideal for serverless; consider external object storage for long-term file persistence).
